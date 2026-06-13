<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string $user_id
 * @property string|null $role
 * @property \App\Enums\AttendanceStatus $attendance_status
 * @property \Illuminate\Support\Carbon|null $response_at
 * @property \Illuminate\Support\Carbon|null $check_in_at
 * @property \Illuminate\Support\Carbon|null $check_out_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MeetingParticipant extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'meeting_id', 'user_id', 'role', 'attendance_status',
        'response_at', 'check_in_at', 'check_out_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'response_at'       => 'datetime',
            'check_in_at'       => 'datetime',
            'check_out_at'      => 'datetime',
        ];
    }

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
