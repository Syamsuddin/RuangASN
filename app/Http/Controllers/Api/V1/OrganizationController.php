<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('admin.organizations.view', Organization::class);

        $orgs = Organization::with('parent', 'children')
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->pemda_id, fn($q, $id) => $q->where('pemda_id', $id))
            ->orderBy('depth')->orderBy('name')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $orgs]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin.organizations.create', Organization::class);

        $data = $request->validate([
            'type'                 => ['required', 'string'],
            'name'                 => ['required', 'string', 'max:255'],
            'short_name'           => ['nullable', 'string', 'max:50'],
            'code'                 => ['nullable', 'string', 'max:30', 'unique:organizations,code'],
            'parent_id'            => ['nullable', 'exists:organizations,id'],
            'pemda_id'             => ['nullable', 'exists:organizations,id'],
            'address'              => ['nullable', 'string'],
            'phone'                => ['nullable', 'string', 'max:30'],
            'email'                => ['nullable', 'email'],
            'effective_start_date' => ['nullable', 'date'],
        ]);

        $data['created_by'] = $request->user()->id;
        if (isset($data['parent_id'])) {
            $parent = Organization::findOrFail($data['parent_id']);
            $data['depth'] = $parent->depth + 1;
            $data['pemda_id'] = $data['pemda_id'] ?? $parent->pemda_id;
        }

        $org = Organization::create($data);
        return response()->json(['data' => $org->load('parent')], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('admin.organizations.view', Organization::class);
        return response()->json(['data' => $organization->load(['parent', 'children', 'positions'])]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('admin.organizations.edit', Organization::class);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'address'    => ['nullable', 'string'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $organization->update($data);
        return response()->json(['data' => $organization->fresh()]);
    }

    public function members(Organization $organization): JsonResponse
    {
        $this->authorize('organization.view.tree', Organization::class);
        $members = $organization->users()->with('currentPosition.position')->paginate(20);
        return response()->json(['data' => $members]);
    }
}
