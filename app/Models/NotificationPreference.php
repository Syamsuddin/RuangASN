<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property bool $in_app
 * @property bool $email
 * @property bool $push
 * @property bool $task_assigned
 * @property bool $task_due
 * @property bool $meeting_invited
 * @property bool $document_approval
 * @property bool $report_status
 * @property string|null $digest_frequency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NotificationPreference extends Model
{
    use HasUlid;

    protected $table = 'notification_preferences';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id',
        'in_app', 'email', 'push',
        'task_assigned', 'task_due', 'meeting_invited',
        'document_approval', 'report_status',
        'digest_frequency',
    ];

    protected $casts = [
        'in_app'            => 'boolean',
        'email'             => 'boolean',
        'push'              => 'boolean',
        'task_assigned'     => 'boolean',
        'task_due'          => 'boolean',
        'meeting_invited'   => 'boolean',
        'document_approval' => 'boolean',
        'report_status'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
