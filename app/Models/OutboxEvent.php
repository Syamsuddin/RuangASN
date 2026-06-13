<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'event_type', 'aggregate_type', 'aggregate_id',
        'payload', 'organization_id', 'occurred_at',
        'processed_at', 'failed_at', 'fail_reason', 'retry_count',
    ];

    protected $casts = [
        'payload'      => 'array',
        'occurred_at'  => 'datetime',
        'processed_at' => 'datetime',
        'failed_at'    => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
