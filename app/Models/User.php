<?php
namespace App\Models;

use App\Enums\PresenceStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUlid, Notifiable, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'nip', 'nip_lama', 'name', 'email', 'phone',
        'user_type', 'status', 'presence_status', 'workspace_mode',
        'avatar_path', 'bio', 'password',
        'email_verified_at', 'mfa_enabled', 'mfa_secret',
        'last_login_at', 'last_login_ip', 'failed_login_count', 'locked_until',
        'national_id', 'birth_date', 'gender',
        'organization_id', 'pemda_id',
        'notification_settings', 'ai_preferences', 'timezone', 'locale',
        'created_by', 'updated_by', 'version',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'national_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'last_login_at'         => 'datetime',
            'locked_until'          => 'datetime',
            'birth_date'            => 'date',
            'mfa_enabled'           => 'boolean',
            'failed_login_count'    => 'integer',
            'version'               => 'integer',
            'notification_settings' => 'array',
            'ai_preferences'        => 'array',
            'password'              => 'hashed',
            'status'                => UserStatus::class,
            'user_type'             => UserType::class,
            'presence_status'       => PresenceStatus::class,
            'deleted_at'            => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function pemda(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pemda_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    public function currentPosition()
    {
        return $this->hasOne(UserPosition::class)->where('is_current', true);
    }

    public function tasksCreated(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function mfaBackupCodes(): HasMany
    {
        return $this->hasMany(MfaBackupCode::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function subordinateIds(): \Illuminate\Support\Collection
    {
        return UserPosition::where('direct_superior_id', $this->id)
            ->where('is_current', true)
            ->pluck('user_id');
    }
}
