<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $recipient_id
 * @property string $notification_type
 * @property string $title
 * @property string $body
 * @property array|null $data
 * @property string|null $channel
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property string|null $failed_reason
 * @property int|null $retry_count
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AppNotification extends Model
{
    use HasUlid;

    protected $table = 'app_notifications';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'recipient_id', 'notification_type',
        'title', 'body', 'data', 'channel', 'status',
        'read_at', 'delivered_at', 'failed_reason', 'retry_count', 'scheduled_at',
    ];

    protected $casts = [
        'data'         => 'array',
        'read_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function isRead(): bool { return $this->read_at !== null; }
}
