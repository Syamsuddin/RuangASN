<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, ?UploadedFile $file, User $owner): Document
    {
        return DB::transaction(function () use ($data, $file, $owner) {
            $fileData = $this->storeFile($file, $owner->organization_id);

            $document = Document::create([
                ...$data,
                ...$fileData,
                'organization_id' => $owner->organization_id,
                'pemda_id'        => $owner->pemda_id,
                'owner_id'        => $owner->id,
                'created_by'      => $owner->id,
                'status'          => DocumentStatus::DRAFT->value,
                'version_number'  => 1,
                'is_latest'       => true,
            ]);

            $document->refresh();
            $this->outbox->publish('document.created', $document->toArray(), 'Document', $document->id);
            return $document;
        });
    }

    public function update(Document $doc, array $data, ?UploadedFile $file = null): Document
    {
        return DB::transaction(function () use ($doc, $data, $file) {
            if ($file) {
                $data = array_merge($data, $this->storeFile($file, $doc->organization_id));
            }
            $doc->update($data);
            $doc->refresh();
            $this->outbox->publish('document.updated', $doc->toArray(), 'Document', $doc->id);
            return $doc;
        });
    }

    public function submit(Document $doc, array $approverIds, User $actor): Document
    {
        return DB::transaction(function () use ($doc, $approverIds, $actor) {
            if (! $doc->canTransitionTo(DocumentStatus::IN_REVIEW, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat submit dari status {$doc->status->value}.",
                ]);
            }

            $doc->update(['status' => DocumentStatus::IN_REVIEW->value]);

            foreach (array_values($approverIds) as $idx => $approverId) {
                DocumentApproval::create([
                    'document_id' => $doc->id,
                    'approver_id' => $approverId,
                    'step_number' => $idx + 1,
                    'status'      => 'pending',
                ]);
            }

            $this->outbox->publish('document.submitted', [
                'document_id'     => $doc->id,
                'organization_id' => $doc->organization_id,
                'submitted_by'    => $actor->id,
            ], 'Document', $doc->id);

            return $doc;
        });
    }

    public function approve(DocumentApproval $approval, User $approver, ?string $notes = null): Document
    {
        return DB::transaction(function () use ($approval, $approver, $notes) {
            $approval->update([
                'status'     => 'approved',
                'notes'      => $notes,
                'decided_at' => now(),
            ]);

            /** @var Document $doc */
            $doc = $approval->document()->firstOrFail();
            $allApproved = $doc->approvals()->where('status', '!=', 'approved')->doesntExist();

            if ($allApproved && $doc->status === DocumentStatus::IN_REVIEW) {
                $doc->update(['status' => DocumentStatus::APPROVED->value]);
            }

            $this->outbox->publish('document.approved', [
                'document_id'     => $doc->id,
                'organization_id' => $doc->organization_id,
                'approved_by'     => $approver->id,
            ], 'Document', $doc->id);

            $doc->refresh();
            $this->audit->log(AuditAction::APPROVED, 'Document', $doc->id,
                ['status' => DocumentStatus::IN_REVIEW->value],
                ['status' => $doc->status->value]
            );

            return $doc;
        });
    }

    public function reject(DocumentApproval $approval, User $approver, string $reason): Document
    {
        return DB::transaction(function () use ($approval, $approver, $reason) {
            $approval->update([
                'status'     => 'rejected',
                'notes'      => $reason,
                'decided_at' => now(),
            ]);

            /** @var Document $doc */
            $doc = $approval->document()->firstOrFail();
            $doc->update(['status' => DocumentStatus::REJECTED->value]);

            $this->outbox->publish('document.rejected', [
                'document_id'     => $doc->id,
                'organization_id' => $doc->organization_id,
                'rejected_by'     => $approver->id,
                'reason'          => $reason,
            ], 'Document', $doc->id);

            $this->audit->log(AuditAction::STATUS_CHANGED, 'Document', $doc->id,
                ['status' => DocumentStatus::IN_REVIEW->value],
                ['status' => DocumentStatus::REJECTED->value]
            );

            return $doc;
        });
    }

    public function publish(Document $doc, User $actor): Document
    {
        return DB::transaction(function () use ($doc, $actor) {
            if (! $doc->canTransitionTo(DocumentStatus::PUBLISHED, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Dokumen harus berstatus approved sebelum dipublish.",
                ]);
            }

            $doc->update(['status' => DocumentStatus::PUBLISHED->value]);

            $this->outbox->publish('document.published', [
                'document_id'     => $doc->id,
                'organization_id' => $doc->organization_id,
                'published_by'    => $actor->id,
            ], 'Document', $doc->id);

            return $doc;
        });
    }

    public function createNewVersion(Document $doc, array $data, ?UploadedFile $file, User $actor): Document
    {
        return DB::transaction(function () use ($doc, $data, $file, $actor) {
            if ($doc->status !== DocumentStatus::PUBLISHED) {
                throw ValidationException::withMessages([
                    'status' => 'Versi baru hanya dapat dibuat dari dokumen berstatus published.',
                ]);
            }

            $fileData = $this->storeFile($file, $doc->organization_id);

            $newDoc = Document::create([
                ...$data,
                ...$fileData,
                'organization_id'    => $doc->organization_id,
                'pemda_id'           => $doc->pemda_id,
                'owner_id'           => $actor->id,
                'created_by'         => $actor->id,
                'parent_document_id' => $doc->id,
                'version_number'     => $doc->version_number + 1,
                'is_latest'          => true,
                'status'             => DocumentStatus::DRAFT->value,
            ]);

            $doc->update([
                'is_latest' => false,
                'status'    => DocumentStatus::SUPERSEDED->value,
            ]);

            $this->outbox->publish('document.versioned', [
                'document_id'         => $newDoc->id,
                'parent_document_id'  => $doc->id,
                'organization_id'     => $doc->organization_id,
                'version_number'      => $newDoc->version_number,
            ], 'Document', $newDoc->id);

            return $newDoc;
        });
    }

    public function archive(Document $doc, User $actor): Document
    {
        return DB::transaction(function () use ($doc, $actor) {
            if (! $doc->canTransitionTo(DocumentStatus::ARCHIVED, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat mengarsipkan dari status {$doc->status->value}.",
                ]);
            }

            $doc->update(['status' => DocumentStatus::ARCHIVED->value]);

            $this->outbox->publish('document.archived', [
                'document_id'     => $doc->id,
                'organization_id' => $doc->organization_id,
                'archived_by'     => $actor->id,
            ], 'Document', $doc->id);

            return $doc;
        });
    }

    private function storeFile(?UploadedFile $file, string $orgId): array
    {
        if (! $file) {
            return [];
        }

        $ulid = Str::ulid();
        $ext  = $file->getClientOriginalExtension();
        $path = Storage::disk(config('filesystems.evidence_disk', 'local'))
            ->putFileAs("documents/{$orgId}", $file, "{$ulid}.{$ext}");

        return [
            'file_path'  => $path,
            'file_name'  => $file->getClientOriginalName(),
            'file_size'  => $file->getSize(),
            'mime_type'  => $file->getMimeType(),
        ];
    }
}
