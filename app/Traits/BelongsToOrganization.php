<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->hasRole('super_admin')) {
                return;
            }

            $table = (new static)->getTable();
            if (Schema::hasColumn($table, 'organization_id')) {
                $builder->where("{$table}.organization_id", $user->organization_id);
            }
        });
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
