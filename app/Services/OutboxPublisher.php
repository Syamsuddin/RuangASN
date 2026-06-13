<?php
namespace App\Services;

use App\Models\OutboxEvent;
use Illuminate\Support\Str;

class OutboxPublisher
{
    public function publish(
        string $eventType,
        array $payload,
        ?string $aggregateType = null,
        ?string $aggregateId = null
    ): OutboxEvent {
        return OutboxEvent::create([
            'id'             => (string) Str::ulid(),
            'event_type'     => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'payload'        => $payload,
            'organization_id'=> $payload['organization_id'] ?? null,
            'occurred_at'    => now(),
        ]);
    }
}
