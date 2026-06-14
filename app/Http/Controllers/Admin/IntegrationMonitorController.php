<?php
namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\IntegrationRun;
use App\Models\WebhookEvent;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Observability surface for external integrations: recent sync runs + inbound
 * webhook deliveries (tenant-scoped), plus operator actions to trigger a sync or
 * test a connection. All reads/writes are RBAC-gated.
 */
class IntegrationMonitorController extends Controller
{
    public function __construct(
        private readonly IntegrationManager $manager,
    ) {}

    public function monitor(Request $request): Response
    {
        abort_unless($request->user()->can('admin.integrations.view'), 403);

        $org = $request->user()->organization;
        abort_unless($org !== null, 403);

        // Tenant isolation: only this org's runs/events (super_admin sees all via
        // the global scope short-circuit). Explicit where keeps it correct even
        // for super_admin scoping to the active org context.
        $runs = IntegrationRun::query()
            ->where('organization_id', $org->id)
            ->with('triggeredBy:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (IntegrationRun $r) => [
                'id'              => $r->id,
                'provider'        => $r->provider,
                'direction'       => $r->direction,
                'operation'       => $r->operation,
                'status'          => $r->status->value,
                'items_processed' => $r->items_processed,
                'items_failed'    => $r->items_failed,
                'summary'         => $r->summary,
                'error_message'   => $r->error_message,
                'started_at'      => $r->started_at?->toIso8601String(),
                'finished_at'     => $r->finished_at?->toIso8601String(),
                'triggered_by'    => $r->triggeredBy?->name,
                'created_at'      => $r->created_at?->toIso8601String(),
            ]);

        $events = WebhookEvent::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (WebhookEvent $e) => [
                'id'              => $e->id,
                'provider'        => $e->provider,
                'event_id'        => $e->event_id,
                'signature_valid' => $e->signature_valid,
                'processed'       => $e->processed,
                'body_excerpt'    => $e->body_excerpt,
                'created_at'      => $e->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Integrations/Monitor', [
            'runs'      => $runs,
            'events'    => $events,
            'providers' => $this->providerCards($request),
            'canRun'    => $request->user()->can('admin.integrations.run'),
            'canManage' => $request->user()->can('admin.integrations.manage'),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.integrations.run'), 403);

        $org = $request->user()->organization;
        abort_unless($org !== null, 403);

        $provider = IntegrationProvider::tryFrom((string) $request->input('provider'));
        if ($provider === null) {
            return back()->withErrors(['provider' => 'Provider integrasi tidak dikenal.']);
        }

        $operation = (string) config(
            "integrations.providers.{$provider->value}.sync_operation",
            'sync',
        );

        $run = $this->manager->run($org, $provider, $operation, $request->user());

        return back()->with(
            'success',
            sprintf('Sinkronisasi %s selesai: %s (%d diproses).', $provider->label(), $run->status->value, $run->items_processed),
        );
    }

    public function testConnection(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.integrations.manage'), 403);

        $org = $request->user()->organization;
        abort_unless($org !== null, 403);

        $provider = IntegrationProvider::tryFrom((string) $request->input('provider'));
        if ($provider === null) {
            return back()->withErrors(['provider' => 'Provider integrasi tidak dikenal.']);
        }

        $result = $this->manager->client($provider)->testConnection($org);

        return $result['ok']
            ? back()->with('success', $provider->label() . ': ' . $result['message'])
            : back()->withErrors(['connection' => $provider->label() . ': ' . $result['message']]);
    }

    /**
     * Per-provider summary cards for the action buttons + configured badges.
     *
     * @return array<int, array<string, mixed>>
     */
    private function providerCards(Request $request): array
    {
        $org   = $request->user()->organization;
        $cards = [];

        foreach (IntegrationProvider::cases() as $provider) {
            $cards[] = [
                'value'      => $provider->value,
                'label'      => $provider->label(),
                'configured' => $org !== null && $this->manager->client($provider)->isConfigured($org),
            ];
        }

        return $cards;
    }
}
