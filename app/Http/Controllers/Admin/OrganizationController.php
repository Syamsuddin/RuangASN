<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('admin.organizations.view') || $request->user()->can('organization.view.tree'),
            403
        );

        $pemda = Organization::where('id', $request->user()->pemda_id)->first();

        $flat = Organization::where('pemda_id', $request->user()->pemda_id)
            ->withCount('users')
            ->orderBy('depth')->orderBy('name')
            ->get();

        $tree = $this->buildTree($flat->toArray(), null);

        return Inertia::render('Admin/Organizations/Index', [
            'tree'  => $tree,
            'flat'  => $flat->map(fn($o) => [
                'id'        => $o->id,
                'name'      => $o->name,
                'type'      => $o->type->value,
                'depth'     => $o->depth,
                'parent_id' => $o->parent_id,
            ]),
            'types' => array_column(OrganizationType::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.organizations.create'), 403);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'type'       => ['required', 'string', 'in:' . implode(',', array_column(OrganizationType::cases(), 'value'))],
            'code'       => ['nullable', 'string', 'max:30', 'unique:organizations,code'],
            'parent_id'  => ['nullable', 'exists:organizations,id'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $pemda_id = $request->user()->pemda_id;
        $depth = 0;

        if (!empty($data['parent_id'])) {
            $parent = Organization::findOrFail($data['parent_id']);
            $depth = $parent->depth + 1;
        }

        DB::transaction(function () use ($data, $pemda_id, $depth) {
            $org = Organization::create(array_merge($data, [
                'pemda_id'   => $pemda_id,
                'depth'      => $depth,
                'is_active'  => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]));
            $this->audit->log(AuditAction::CREATED, Organization::class, $org->id, [], $org->only('name', 'type', 'depth'));
        });

        return redirect()->route('admin.organizations.index')->with('success', 'Unit organisasi berhasil dibuat.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->can('admin.organizations.edit'), 403);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code'       => ['nullable', 'string', 'max:30', "unique:organizations,code,{$organization->id}"],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $old = $organization->only('name', 'short_name', 'is_active');
        $organization->update($data);
        $this->audit->log(AuditAction::UPDATED, Organization::class, $organization->id, $old, $data);

        return redirect()->route('admin.organizations.index')->with('success', 'Unit organisasi diperbarui.');
    }

    private function buildTree(array $items, ?string $parentId): array
    {
        $branch = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $item['children'] = $this->buildTree($items, $item['id']);
                $branch[] = $item;
            }
        }
        return $branch;
    }
}
