<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $canViewAll = $request->user()->can('audit.view.all');
        $canViewOwn = $request->user()->can('audit.view.own');

        abort_unless($canViewAll || $canViewOwn, 403);

        $query = AuditLog::with('user')
            ->where('organization_id', $request->user()->organization_id)
            ->when(!$canViewAll, fn($q) => $q->where('user_id', $request->user()->id))
            ->when($request->action, fn($q, $a) => $q->where('action', $a))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->when($request->auditable_type, fn($q, $t) => $q->where('auditable_type', 'like', "%{$t}%"))
            ->when($request->date_from, fn($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->orderByDesc('created_at');

        $logs = $query->paginate(30)->withQueryString();

        $actions = AuditLog::where('organization_id', $request->user()->organization_id)
            ->distinct()->pluck('action')->sort()->values();

        return Inertia::render('Admin/Audit/Index', [
            'logs'    => $logs,
            'actions' => $actions,
            'filters' => $request->only('action', 'user_id', 'auditable_type', 'date_from', 'date_to'),
            'canViewAll' => $canViewAll,
        ]);
    }
}
