<?php

namespace App\Http\Controllers;

use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documentService) {}

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

        return Inertia::render('Documents/Index', [
            'documents' => $query->map(fn ($d) => $this->formatDocumentCard($d)),
            'filters'   => $request->only(['type', 'status', 'classification', 'search']),
            'types'     => array_column(DocumentType::cases(), 'value'),
            'statuses'  => array_column(DocumentStatus::cases(), 'value'),
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

        $user = auth()->user();

        return Inertia::render('Documents/Show', [
            'document' => $document,
            'can'      => [
                'update'        => $user->can('update', $document),
                'submit'        => $user->can('submit', $document),
                'approve'       => $user->can('approve', $document),
                'publish'       => $user->can('publish', $document),
                'createVersion' => $user->can('create', Document::class) && $document->status === DocumentStatus::PUBLISHED,
                'download'      => $user->can('download', $document),
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

        $this->documentService->update($document, [], null); // bump audit

        return Storage::disk($disk)->download($document->file_path, $document->file_name ?? 'document');
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
