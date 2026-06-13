<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $teams = Team::with(['organization'])
            ->withCount('members')
            ->where('pemda_id', $request->user()->pemda_id)
            ->when($request->search, fn($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Teams/Index', [
            'teams'  => $teams,
            'users'  => User::where('pemda_id', $request->user()->pemda_id)
                ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'nip']),
            'organizations' => Organization::where('pemda_id', $request->user()->pemda_id)
                ->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Request $request, Team $team): Response
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $team->load(['members.user', 'organization']);

        return Inertia::render('Admin/Teams/Show', [
            'team'  => $team,
            'users' => User::where('pemda_id', $request->user()->pemda_id)
                ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'nip']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', 'max:30'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'description'     => ['nullable', 'string'],
            'is_cross_opd'    => ['sometimes', 'boolean'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'sk_number'       => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $team = Team::create(array_merge($data, [
                'pemda_id'   => $request->user()->pemda_id,
                'is_active'  => true,
                'created_by' => $request->user()->id,
            ]));
            $this->audit->log(AuditAction::CREATED, Team::class, $team->id, [], $team->only('name', 'type'));
        });

        return redirect()->route('admin.teams.index')->with('success', 'Tim berhasil dibuat.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'is_active'    => ['sometimes', 'boolean'],
            'end_date'     => ['nullable', 'date'],
            'sk_number'    => ['nullable', 'string', 'max:100'],
        ]);

        $old = $team->only('name', 'is_active');
        $team->update($data);
        $this->audit->log(AuditAction::UPDATED, Team::class, $team->id, $old, $data);

        return redirect()->route('admin.teams.show', $team)->with('success', 'Tim diperbarui.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['sometimes', 'string', 'max:30'],
        ]);

        $existing = TeamMember::where('team_id', $team->id)
            ->where('user_id', $data['user_id'])->first();

        if ($existing) {
            $existing->update(['is_active' => true, 'left_at' => null]);
        } else {
            TeamMember::create([
                'id'        => (string) Str::ulid(),
                'team_id'   => $team->id,
                'user_id'   => $data['user_id'],
                'role'      => $data['role'] ?? 'member',
                'joined_at' => now()->toDateString(),
                'is_active' => true,
            ]);
        }
        $this->audit->log(AuditAction::CREATED, TeamMember::class, $team->id, [], ['user_id' => $data['user_id']]);

        return redirect()->route('admin.teams.show', $team)->with('success', 'Anggota ditambahkan.');
    }

    public function removeMember(Request $request, Team $team, TeamMember $member): RedirectResponse
    {
        abort_unless($request->user()->can('admin.units.manage'), 403);

        $member->update(['is_active' => false, 'left_at' => now()->toDateString()]);
        $this->audit->log(AuditAction::UPDATED, TeamMember::class, $member->id, ['is_active' => true], ['is_active' => false]);

        return redirect()->route('admin.teams.show', $team)->with('success', 'Anggota dikeluarkan dari tim.');
    }
}
