<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\WatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
        private AuditService $auditService,
        private WatermarkService $watermarkService,
    ) {}

    public function approvalQueue(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->hasPermissionTo('document.approve')) {
            abort(403);
        }

        $queue = Document::with(['owner:id,name'])
            ->whereHas('approvals', fn ($q) => $q
                ->where('approver_id', $user->id)
                ->where('status', 'pending')
            )
            ->where('status', DocumentStatus::IN_REVIEW->value)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Document $d) use ($user) {
                /** @var DocumentApproval|null $myStep */
                $myStep = $d->approvals
                    ->where('approver_id', $user->id)
                    ->where('status', 'pending')
                    ->first();

                return [
                    ...$this->formatDocumentCard($d),
                    'my_step'       => $myStep?->step_number,
                    'submitted_at'  => $d->updated_at?->toISOString(),
                ];
            });

        return Inertia::render('Documents/ApprovalQueue', [
            'queue' => $queue,
            'count' => $queue->count(),
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        $user  = $request->user();
        $perms = [
            1 => $user->hasPermissionTo('document.view.public'),
            2 => $user->hasPermissionTo('document.view.internal'),
            3 => $user->hasPermissionTo('document.view.confidential'),
            4 => $user->hasPermissionTo('document.view.restricted'),
        ];
        $accessibleLevels = array_keys(array_filter($perms));

        $query = Document::with(['owner:id,name'])
            ->where('is_latest', true)
            ->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhereIn('data_classification', $accessibleLevels)
            )
            ->when($request->type, fn ($q, $t) => $q->where('document_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->classification, fn ($q, $c) => $q->where('data_classification', $c))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($sq) =>
                $sq->where('title', 'like', "%{$s}%")
                   ->orWhere('document_number', 'like', "%{$s}%")
            ))
            ->orderByDesc('updated_at')
            ->get();

        $canApprove = $user->hasPermissionTo('document.approve');
        $pendingApprovalCount = $canApprove
            ? DocumentApproval::where('approver_id', $user->id)->where('status', 'pending')->count()
            : 0;

        return Inertia::render('Documents/Index', [
            'documents'            => $query->map(fn ($d) => $this->formatDocumentCard($d)),
            'filters'              => $request->only(['type', 'status', 'classification', 'search']),
            'types'                => array_column(DocumentType::cases(), 'value'),
            'statuses'             => array_column(DocumentStatus::cases(), 'value'),
            'canApprove'           => $canApprove,
            'pendingApprovalCount' => $pendingApprovalCount,
        ]);
    }

    public function show(Document $document): Response
    {
        $this->authorize('view', $document);

        $document->load([
            'owner:id,name',
            'creator:id,name',
            'approvals.approver:id,name',
            'versions',
            'meeting:id,title',
            'task:id,title',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $canDownload  = $user->can('download', $document);
        $streamUrl    = null;
        $downloadUrl  = null;

        if ($document->file_path && $canDownload) {
            $streamUrl   = URL::temporarySignedRoute('documents.stream', now()->addMinutes(5), ['document' => $document->id]);
            $downloadUrl = URL::temporarySignedRoute('documents.download.signed', now()->addMinutes(5), ['document' => $document->id]);
        }

        return Inertia::render('Documents/Show', [
            'document'     => $document,
            'stream_url'   => $streamUrl,
            'download_url' => $downloadUrl,
            'can'          => [
                'update'        => $user->can('update', $document),
                'submit'        => $user->can('submit', $document),
                'approve'       => $user->can('approve', $document),
                'publish'       => $user->can('publish', $document),
                'createVersion' => $user->can('create', Document::class) && $document->status === DocumentStatus::PUBLISHED,
                'download'      => $canDownload,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $typeValues = array_column(DocumentType::cases(), 'value');
        $data = $request->validate([
            'title'                => ['required', 'string', 'max:500'],
            'description'          => ['nullable', 'string'],
            'document_type'        => ['required', Rule::in($typeValues)],
            'data_classification'  => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'document_number'      => ['nullable', 'string', 'max:100'],
            'document_date'        => ['nullable', 'date'],
            'effective_date'       => ['nullable', 'date'],
            'expiry_date'          => ['nullable', 'date', 'after_or_equal:effective_date'],
            'file'                 => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png'],
            'tags'                 => ['nullable', 'array'],
        ]);

        $file = $request->file('file');
        unset($data['file']);

        $document = $this->documentService->create($data, $file, $request->user());

        return redirect()->route('documents.show', $document)
            ->with('success', 'Dokumen berhasil dibuat.');
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $typeValues = array_column(DocumentType::cases(), 'value');
        $data = $request->validate([
            'title'               => ['sometimes', 'string', 'max:500'],
            'description'         => ['nullable', 'string'],
            'document_type'       => ['sometimes', Rule::in($typeValues)],
            'data_classification' => ['sometimes', 'integer', Rule::in([1, 2, 3, 4])],
            'document_number'     => ['nullable', 'string', 'max:100'],
            'document_date'       => ['nullable', 'date'],
            'effective_date'      => ['nullable', 'date'],
            'expiry_date'         => ['nullable', 'date'],
            'file'                => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png'],
            'tags'                => ['nullable', 'array'],
        ]);

        $file = $request->file('file');
        unset($data['file']);

        $this->documentService->update($document, $data, $file);

        return back()->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function submit(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('submit', $document);

        $data = $request->validate([
            'approver_ids'   => ['required', 'array'],
            'approver_ids.*' => ['exists:users,id'],
        ]);

        $this->documentService->submit($document, $data['approver_ids'], $request->user());

        return back()->with('success', 'Dokumen berhasil disubmit untuk persetujuan.');
    }

    public function approve(Request $request, DocumentApproval $approval): RedirectResponse
    {
        $this->authorize('approve', $approval->document);

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $this->documentService->approve($approval, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Dokumen berhasil disetujui.');
    }

    public function reject(Request $request, DocumentApproval $approval): RedirectResponse
    {
        $this->authorize('approve', $approval->document);

        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $this->documentService->reject($approval, $request->user(), $data['reason']);

        return back()->with('success', 'Dokumen ditolak.');
    }

    public function publish(Document $document): RedirectResponse
    {
        $this->authorize('publish', $document);

        $this->documentService->publish($document, auth()->user());

        return back()->with('success', 'Dokumen berhasil dipublish.');
    }

    public function createVersion(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $typeValues = array_column(DocumentType::cases(), 'value');
        $data = $request->validate([
            'title'               => ['required', 'string', 'max:500'],
            'description'         => ['nullable', 'string'],
            'document_type'       => ['sometimes', Rule::in($typeValues)],
            'data_classification' => ['sometimes', 'integer', Rule::in([1, 2, 3, 4])],
            'document_number'     => ['nullable', 'string', 'max:100'],
            'file'                => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png'],
        ]);

        $file = $request->file('file');
        unset($data['file']);

        $newDoc = $this->documentService->createNewVersion($document, $data, $file, $request->user());

        return redirect()->route('documents.show', $newDoc)
            ->with('success', 'Versi baru dokumen berhasil dibuat.');
    }

    public function archive(Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $this->documentService->archive($document, auth()->user());

        return back()->with('success', 'Dokumen berhasil diarsipkan.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->update(['deleted_by' => auth()->id()]);
        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    /**
     * Download with policy check, audit log, and image watermark for L3/L4.
     * Accessed via the signed route `documents.download.signed` (TTL 5 min).
     */
    public function download(Document $document)
    {
        $this->authorize('download', $document);

        if (! $document->file_path) {
            abort(404, 'File tidak tersedia.');
        }

        $disk = config('filesystems.evidence_disk', 'local');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        // Always audit the download
        $this->auditService->log(
            AuditAction::DOWNLOADED,
            'Document',
            $document->id,
            [],
            ['classification' => $document->data_classification->value],
        );

        $mime     = $document->mime_type ?? 'application/octet-stream';
        $fileName = $document->file_name ?? 'document';
        $level    = $document->data_classification->value;

        // Image watermark for CONFIDENTIAL (3) and RESTRICTED (4)
        if ($level >= DataClassification::CONFIDENTIAL->value && $this->isImageMime($mime)) {
            return $this->streamWatermarkedImage($disk, $document, $mime, $fileName, false);
        }

        // NOTE: Server-side PDF watermark stamping requires setasign/fpdi (Phase 2 deferral).
        // The in-app viewer (Feature 3) provides visual watermark overlay for PDFs.

        return Storage::disk($disk)->download($document->file_path, $fileName);
    }

    /**
     * Inline stream for the in-app PDF/image viewer (Content-Disposition: inline).
     * Accessed via the signed route `documents.stream` (TTL 5 min, middleware signed).
     */
    public function stream(Document $document)
    {
        $this->authorize('download', $document);

        if (! $document->file_path) {
            abort(404, 'File tidak tersedia.');
        }

        $disk = config('filesystems.evidence_disk', 'local');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        $mime     = $document->mime_type ?? 'application/octet-stream';
        $fileName = $document->file_name ?? 'document';
        $level    = $document->data_classification->value;

        // Watermark images inline too for CONFIDENTIAL/RESTRICTED
        if ($level >= DataClassification::CONFIDENTIAL->value && $this->isImageMime($mime)) {
            return $this->streamWatermarkedImage($disk, $document, $mime, $fileName, true);
        }

        // Stream inline (PDF viewer, etc.)
        $content = Storage::disk($disk)->get($document->file_path);

        return response($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    private function isImageMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'], true)
            || str_starts_with($mime, 'image/');
    }

    private function streamWatermarkedImage(
        string $disk,
        Document $document,
        string $mime,
        string $fileName,
        bool $inline
    ): HttpResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $text = $this->watermarkService->buildWatermarkText(
            $user->name,
            $user->nip ?? '—',
            now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i')
        );

        $raw        = Storage::disk($disk)->get($document->file_path) ?? '';
        $watermarked = $this->watermarkService->stampImage($raw, $text, $mime);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($watermarked, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
        ]);
    }

    private function formatDocumentCard(Document $d): array
    {
        $level = $d->data_classification->value;

        $classificationLabels = [1 => 'Publik', 2 => 'Internal', 3 => 'Rahasia', 4 => 'Sangat Rahasia'];

        /** @var \App\Models\User|null $owner */
        $owner = $d->owner;

        return [
            'id'                   => $d->id,
            'title'                => $d->title,
            'document_type'        => $d->document_type,
            'status'               => $d->status,
            'document_number'      => $d->document_number,
            'data_classification'  => $level,
            'classification_label' => $classificationLabels[$level],
            'version_number'       => $d->version_number,
            'is_latest'            => $d->is_latest,
            'file_name'            => $d->file_name,
            'mime_type'            => $d->mime_type,
            'updated_at'           => $d->updated_at?->toISOString(),
            'owner'                => $owner ? ['id' => $owner->id, 'name' => $owner->name] : null,
        ];
    }
}
