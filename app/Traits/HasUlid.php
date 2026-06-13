<?php
namespace App\Traits;

use Illuminate\Support\Str;

trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
            if (auth()->check()) {
                $fillable = $model->getFillable();
                if (in_array('created_by', $fillable)) {
                    $model->created_by = $model->created_by ?? auth()->id();
                }
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $fillable = $model->getFillable();
                if (in_array('updated_by', $fillable)) {
                    $model->updated_by = auth()->id();
                }
                if (in_array('version', $fillable)) {
                    $model->version = ($model->getOriginal('version') ?? 0) + 1;
                }
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
