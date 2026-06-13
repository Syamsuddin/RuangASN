<?php

namespace App\Http\Controllers;

use App\Enums\DataClassification;
use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Services\KnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function __construct(private KnowledgeService $knowledgeService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', KnowledgeArticle::class);

        $user = $request->user();

        $query = KnowledgeArticle::with(['author:id,name', 'category:id,name,slug'])
            ->where('is_latest', true)
            ->where(fn ($q) => $q
                ->where('author_id', $user->id)
                ->orWhere('status', KnowledgeStatus::PUBLISHED->value)
            )
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->type, fn ($q, $t) => $q->where('knowledge_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('updated_at');

        $articles = $query->get()->map(fn ($a) => $this->formatCard($a));

        $popular = KnowledgeArticle::with('author:id,name')
            ->where('is_latest', true)
            ->where('status', KnowledgeStatus::PUBLISHED->value)
            ->orderByDesc('view_count')
            ->limit(5)
            ->get()
            ->map(fn ($a) => $this->formatCard($a));

        $recent = KnowledgeArticle::with('author:id,name')
            ->where('is_latest', true)
            ->where('status', KnowledgeStatus::PUBLISHED->value)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn ($a) => $this->formatCard($a));

        return Inertia::render('Knowledge/Index', [
            'articles'   => $articles,
            'categories' => $this->buildCategoryTree($user->organization_id),
            'filters'    => $request->only(['category', 'type', 'status', 'search']),
            'popular'    => $popular,
            'recent'     => $recent,
        ]);
    }

    public function show(KnowledgeArticle $article): Response
    {
        $this->authorize('view', $article);

        $this->knowledgeService->recordView($article);

        $article->load(['author:id,name', 'publisher:id,name', 'category:id,name,slug', 'versions']);

        $user = auth()->user();

        $related = KnowledgeArticle::with('author:id,name')
            ->where('is_latest', true)
            ->where('status', KnowledgeStatus::PUBLISHED->value)
            ->where('id', '!=', $article->id)
            ->when($article->category_id, fn ($q) => $q->where('category_id', $article->category_id))
            ->orderByDesc('view_count')
            ->limit(5)
            ->get()
            ->map(fn ($a) => $this->formatCard($a));

        return Inertia::render('Knowledge/Show', [
            'article' => $article,
            'can'     => [
                'update'        => $user->can('update', $article),
                'publish'       => $user->can('publish', $article),
                'archive'       => $user->can('archive', $article),
                'createVersion' => $user->can('createVersion', $article),
            ],
            'related' => $related,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', KnowledgeArticle::class);

        return Inertia::render('Knowledge/Editor', [
            'article'    => null,
            'categories' => KnowledgeCategory::orderBy('name')->get(['id', 'name', 'parent_id']),
        ]);
    }

    public function edit(KnowledgeArticle $article): Response
    {
        $this->authorize('update', $article);

        return Inertia::render('Knowledge/Editor', [
            'article'    => $article,
            'categories' => KnowledgeCategory::orderBy('name')->get(['id', 'name', 'parent_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', KnowledgeArticle::class);

        $typeValues = array_column(KnowledgeType::cases(), 'value');

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:500'],
            'content'             => ['nullable', 'string'],
            'excerpt'             => ['nullable', 'string'],
            'knowledge_type'      => ['required', Rule::in($typeValues)],
            'category_id'         => ['nullable', 'string', 'exists:knowledge_categories,id'],
            'data_classification' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'tags'                => ['nullable', 'array'],
            'tags.*'              => ['string', 'max:50'],
        ]);

        $article = $this->knowledgeService->create($data, $request->user());

        return redirect()->route('knowledge.show', $article)
            ->with('success', 'Artikel berhasil dibuat.');
    }

    public function update(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $typeValues = array_column(KnowledgeType::cases(), 'value');

        $data = $request->validate([
            'title'               => ['sometimes', 'string', 'max:500'],
            'content'             => ['nullable', 'string'],
            'excerpt'             => ['nullable', 'string'],
            'knowledge_type'      => ['sometimes', Rule::in($typeValues)],
            'category_id'         => ['nullable', 'string', 'exists:knowledge_categories,id'],
            'data_classification' => ['sometimes', 'integer', Rule::in([1, 2, 3, 4])],
            'tags'                => ['nullable', 'array'],
            'tags.*'              => ['string', 'max:50'],
        ]);

        $this->knowledgeService->update($article, $data);

        return back()->with('success', 'Artikel berhasil diperbarui.');
    }

    public function transition(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        $statusValues = array_column(KnowledgeStatus::cases(), 'value');

        $data = $request->validate([
            'status' => ['required', Rule::in($statusValues)],
        ]);

        $new = KnowledgeStatus::from($data['status']);

        if ($new === KnowledgeStatus::PUBLISHED) {
            $this->authorize('publish', $article);
        } elseif ($new === KnowledgeStatus::ARCHIVED) {
            $this->authorize('archive', $article);
        } else {
            $this->authorize('update', $article);
        }

        $this->knowledgeService->transition($article, $new, $request->user());

        return back()->with('success', 'Status artikel berhasil diubah.');
    }

    public function publish(KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        $this->knowledgeService->transition($article, KnowledgeStatus::PUBLISHED, auth()->user());

        return back()->with('success', 'Artikel berhasil dipublish.');
    }

    public function archive(KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('archive', $article);

        $this->knowledgeService->transition($article, KnowledgeStatus::ARCHIVED, auth()->user());

        return back()->with('success', 'Artikel berhasil diarsipkan.');
    }

    public function createVersion(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('createVersion', $article);

        $typeValues = array_column(KnowledgeType::cases(), 'value');

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:500'],
            'content'        => ['nullable', 'string'],
            'excerpt'        => ['nullable', 'string'],
            'knowledge_type' => ['sometimes', Rule::in($typeValues)],
            'category_id'    => ['nullable', 'string', 'exists:knowledge_categories,id'],
            'tags'           => ['nullable', 'array'],
        ]);

        $newArticle = $this->knowledgeService->createNewVersion($article, $data, $request->user());

        return redirect()->route('knowledge.show', $newArticle)
            ->with('success', 'Versi baru artikel berhasil dibuat.');
    }

    public function markHelpful(KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('view', $article);

        $this->knowledgeService->markHelpful($article);

        return back()->with('success', 'Terima kasih atas masukan Anda.');
    }

    public function destroy(KnowledgeArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $article->update(['deleted_by' => auth()->id()]);
        $article->delete();

        return redirect()->route('knowledge.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', KnowledgeArticle::class);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'string', 'exists:knowledge_categories,id'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $this->knowledgeService->createCategory($data, $request->user());

        return back()->with('success', 'Kategori berhasil dibuat.');
    }

    public function updateCategory(Request $request, KnowledgeCategory $category): RedirectResponse
    {
        $this->authorize('create', KnowledgeArticle::class);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $this->knowledgeService->updateCategory($category, $data);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    private function formatCard(KnowledgeArticle $a): array
    {
        $typeLabels = [
            'wiki'            => 'Wiki',
            'faq'             => 'FAQ',
            'sop'             => 'SOP',
            'best_practice'   => 'Praktik Terbaik',
            'lesson_learned'  => 'Pelajaran',
            'glossary'        => 'Glosarium',
            'regulation_note' => 'Catatan Regulasi',
            'template'        => 'Template',
            'directory'       => 'Direktori',
        ];

        /** @var \App\Models\User|null $author */
        $author = $a->author;
        /** @var \App\Models\KnowledgeCategory|null $category */
        $category = $a->category;

        return [
            'id'             => $a->id,
            'title'          => $a->title,
            'excerpt'        => $a->excerpt,
            'knowledge_type' => $a->knowledge_type->value,
            'type_label'     => $typeLabels[$a->knowledge_type->value],
            'status'         => $a->status->value,
            'version_number' => $a->version_number,
            'is_latest'      => $a->is_latest,
            'view_count'     => $a->view_count,
            'helpful_count'  => $a->helpful_count,
            'tags'           => $a->tags ?? [],
            'author'         => $author ? ['id' => $author->id, 'name' => $author->name] : null,
            'category'       => $category ? ['id' => $category->id, 'name' => $category->name] : null,
            'updated_at'     => $a->updated_at?->toISOString(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildCategoryTree(string $organizationId): array
    {
        $categories = KnowledgeCategory::where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return $categories->map(fn ($c) => [
            'id'        => $c->id,
            'name'      => $c->name,
            'slug'      => $c->slug,
            'parent_id' => $c->parent_id,
        ])->toArray();
    }
}
