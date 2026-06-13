<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('admin.users.view'), 403);

        $users = User::with(['roles', 'organization'])
            ->where('pemda_id', $request->user()->pemda_id)
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('nip', 'like', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%");
            }))
            ->when($request->role, fn($q, $r) => $q->role($r))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'         => $users,
            'roles'         => Role::orderBy('name')->pluck('name'),
            'organizations' => Organization::where('pemda_id', $request->user()->pemda_id)
                ->orderBy('name')->get(['id', 'name', 'short_name']),
            'filters'       => $request->only('search', 'role', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.users.create'), 403);

        $data = $request->validate([
            'nip'             => ['required', 'string', 'max:18', 'unique:users,nip'],
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'user_type'       => ['required', 'string', 'in:' . implode(',', array_column(UserType::cases(), 'value'))],
            'organization_id' => ['required', 'exists:organizations,id'],
            'role'            => ['required', 'exists:roles,name'],
            'password'        => ['nullable', 'string', 'min:8'],
        ]);

        $org = Organization::findOrFail($data['organization_id']);

        $user = DB::transaction(function () use ($data, $org, $request) {
            $user = User::create([
                'id'              => (string) Str::ulid(),
                'nip'             => $data['nip'],
                'name'            => $data['name'],
                'email'           => $data['email'],
                'user_type'       => $data['user_type'],
                'status'          => UserStatus::ACTIVE->value,
                'organization_id' => $data['organization_id'],
                'pemda_id'        => $org->pemda_id ?? $org->id,
                'password'        => $data['password'] ?? Str::random(16),
                'timezone'        => 'Asia/Jakarta',
                'locale'          => 'id',
                'created_by'      => $request->user()->id,
            ]);
            $user->assignRole($data['role']);
            $this->audit->log(AuditAction::CREATED, User::class, $user->id, [], $user->only('nip', 'name', 'email'));
            return $user;
        });

        return redirect()->route('admin.users.index')->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('admin.users.edit'), 403);

        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'email'           => ['sometimes', 'email', "unique:users,email,{$user->id}"],
            'user_type'       => ['sometimes', 'string', 'in:' . implode(',', array_column(UserType::cases(), 'value'))],
            'organization_id' => ['sometimes', 'exists:organizations,id'],
            'role'            => ['nullable', 'exists:roles,name'],
        ]);

        $old = $user->only('name', 'email', 'user_type', 'organization_id');

        DB::transaction(function () use ($data, $user, $old) {
            $user->update(array_filter($data, fn($k) => $k !== 'role', ARRAY_FILTER_USE_KEY));
            if (isset($data['role']) && $user->can('admin.users.roles.assign') || auth()->user()->can('admin.users.roles.assign')) {
                $user->syncRoles([$data['role']]);
            }
            $this->audit->log(AuditAction::UPDATED, User::class, $user->id, $old, $user->fresh()->only('name', 'email', 'user_type', 'organization_id'));
        });

        return redirect()->route('admin.users.index')->with('success', 'Data pengguna diperbarui.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('admin.users.deactivate'), 403);

        $old = ['status' => $user->status->value];
        $newStatus = $user->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;
        $user->update(['status' => $newStatus->value]);
        $this->audit->log(AuditAction::STATUS_CHANGED, User::class, $user->id, $old, ['status' => $newStatus->value]);

        $label = $newStatus === UserStatus::ACTIVE ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('admin.users.index')->with('success', "Pengguna {$user->name} berhasil {$label}.");
    }
}
