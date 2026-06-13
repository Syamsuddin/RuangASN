<?php
namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\DelegationType;
use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DelegationController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('organization.delegation.view') || $request->user()->can('organization.delegation.manage'), 403);

        $delegations = Delegation::with(['delegator', 'delegate'])
            ->where('organization_id', $request->user()->organization_id)
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Delegations/Index', [
            'delegations' => $delegations,
            'users'       => User::where('pemda_id', $request->user()->pemda_id)
                ->where('status', 'active')->orderBy('name')->get(['id', 'name', 'nip']),
            'types'       => array_column(DelegationType::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('organization.delegation.manage'), 403);

        $data = $request->validate([
            'delegator_id' => ['required', 'exists:users,id'],
            'delegate_id'  => ['required', 'exists:users,id', 'different:delegator_id'],
            'type'         => ['required', 'string', 'in:' . implode(',', array_column(DelegationType::cases(), 'value'))],
            'reason'       => ['required', 'string'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['required', 'date', 'after_or_equal:start_date'],
            'sk_number'    => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $delegation = Delegation::create(array_merge($data, [
                'organization_id' => $request->user()->organization_id,
                'is_active'       => true,
                'created_by'      => $request->user()->id,
            ]));
            $this->audit->log(AuditAction::CREATED, Delegation::class, $delegation->id, [], $delegation->only('type', 'delegator_id', 'delegate_id'));
        });

        return redirect()->route('admin.delegations.index')->with('success', 'Delegasi berhasil dibuat.');
    }

    public function update(Request $request, Delegation $delegation): RedirectResponse
    {
        abort_unless($request->user()->can('organization.delegation.manage'), 403);

        $data = $request->validate([
            'reason'     => ['sometimes', 'string'],
            'end_date'   => ['sometimes', 'date'],
            'sk_number'  => ['nullable', 'string', 'max:100'],
        ]);

        $old = $delegation->only('reason', 'end_date');
        $delegation->update($data);
        $this->audit->log(AuditAction::UPDATED, Delegation::class, $delegation->id, $old, $data);

        return redirect()->route('admin.delegations.index')->with('success', 'Delegasi diperbarui.');
    }

    public function revoke(Request $request, Delegation $delegation): RedirectResponse
    {
        abort_unless($request->user()->can('organization.delegation.manage'), 403);

        $delegation->update(['is_active' => false, 'end_date' => now()->toDateString()]);
        $this->audit->log(AuditAction::STATUS_CHANGED, Delegation::class, $delegation->id, ['is_active' => true], ['is_active' => false]);

        return redirect()->route('admin.delegations.index')->with('success', 'Delegasi dicabut.');
    }
}
