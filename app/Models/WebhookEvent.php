<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit + idempotency record of an inbound webhook delivery. organization_id is
 * nullable (the org may only be resolvable after signature verification), so
 * this model deliberately does NOT use the BelongsToOrganization global scope —
 * callers filter by org explicitly. headers/body_excerpt are REDACTED.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $provider
 * @property string|null $event_id
 * @property string|null $body_hash
 * @property bool $signature_valid
 * @property bool $processed
 * @property array<string, mixed>|null $headers
 * @property string|null $body_excerpt
 * @property Carbon|null $created_at
 * @property-read Organization|null $organization
 *
 * @phpstan-consistent-constructor
 */
class WebhookEvent extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public const UPDATED_AT = null;

    protected $fillable = [
        'id', 'organization_id', 'provider', 'event_id', 'body_hash',
        'signature_valid', 'processed', 'headers', 'body_excerpt', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'processed'       => 'boolean',
            'headers'         => 'array',
            'created_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookEvent $event) {
            if (empty($event->created_at)) {
                $event->created_at = now();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
