<?php
namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\EvidenceType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvidence extends Model
{
    use HasUlid;

    protected $table = 'task_evidences';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'task_id', 'uploader_id', 'evidence_type', 'title',
        'description', 'file_path', 'file_name', 'file_size', 'mime_type',
        'url', 'data_classification',
    ];

    protected function casts(): array
    {
        return [
            'evidence_type'       => EvidenceType::class,
            'data_classification' => DataClassification::class,
            'file_size'           => 'integer',
            'created_at'          => 'datetime',
        ];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploader_id'); }

    public function getSignedUrl(): string
    {
        if ($this->evidence_type === EvidenceType::URL) {
            return $this->url;
        }

        $storage = \Illuminate\Support\Facades\Storage::disk(config('filesystems.evidence_disk'));

        // S3/MinIO mendukung temporaryUrl; disk lokal tidak — fallback ke url biasa.
        try {
            return $storage->temporaryUrl($this->file_path, now()->addHour());
        } catch (\Throwable) {
            return $storage->url($this->file_path);
        }
    }
}
