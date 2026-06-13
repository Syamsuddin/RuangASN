<?php
namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Support\Str;

class AuditService
{
    public function log(
        AuditAction $action,
        ?string $auditableType = null,
        ?string $auditableId = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $organizationId = null,
    ): AuditLog {
        $request = request();
        $user = auth()->user();

        return AuditLog::create([
            'id'             => (string) Str::ulid(),
            'organization_id'=> $organizationId ?? $user?->organization_id,
            'user_id'        => $user?->id,
            'action'         => $action->value,
            'auditable_type' => $auditableType,
            'auditable_id'   => $auditableId,
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'url'            => $request->fullUrl(),
        ]);
    }
}
