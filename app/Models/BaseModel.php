<?php
namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @phpstan-consistent-constructor */
abstract class BaseModel extends Model
{
    use BelongsToOrganization;
    use HasUlid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
