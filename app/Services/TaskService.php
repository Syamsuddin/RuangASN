<?php
namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\EvidenceType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $creator): Task
    {
        return DB::transaction(function () use ($data, $creator) {
            $status = ! empty($data['assignee_id'])
                ? TaskStatus::ASSIGNED->value
                : TaskStatus::OPEN->value;

            $task = Task::create([
                ...$data,
                'organization_id' => $creator->organization_id,
                'pemda_id'        => $creator->pemda_id,
                'creator_id'      => $creator->id,
                'created_by'      => $creator->id,
                'status'          => $status,
            ]);

            $this->outbox->publish('task.created', $task->fresh()->toArray(), 'Task', $task->id);
            return $task;
        });
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->update($data);
            $this->outbox->publish('task.updated', $task->fresh()->toArray(), 'Task', $task->id);
            return $task->fresh();
        });
    }

    public function transitionStatus(Task $task, TaskStatus $newStatus, User $actor, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $newStatus, $actor, $reason) {
            if (! $task->canTransitionTo($newStatus, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat berpindah dari {$task->status->value} ke {$newStatus->value}.",
                ]);
            }

            // Enforce evidence rule for completion
            if ($newStatus === TaskStatus::COMPLETED && ! $task->hasEvidence()) {
                throw ValidationException::withMessages([
                    'evidence' => 'Task harus memiliki minimal 1 bukti (evidence) sebelum diselesaikan.',
                ]);
            }

            $oldStatus = $task->status;

            TaskStatusHistory::create([
                'task_id'     => $task->id,
                'from_status' => $oldStatus->value,
                'to_status'   => $newStatus->value,
                'changed_by'  => $actor->id,
                'reason'      => $reason,
                'changed_at'  => now(),
            ]);

            $task->update([
                'status'       => $newStatus->value,
                'completed_at' => $newStatus === TaskStatus::COMPLETED ? now() : null,
                'started_at'   => ($newStatus === TaskStatus::IN_PROGRESS && ! $task->started_at) ? now() : $task->started_at,
            ]);

            $this->outbox->publish('task.status_changed', [
                'task_id'         => $task->id,
                'from_status'     => $oldStatus->value,
                'to_status'       => $newStatus->value,
                'changed_by'      => $actor->id,
                'organization_id' => $task->organization_id,
            ], 'Task', $task->id);

            $this->audit->log(AuditAction::STATUS_CHANGED, 'Task', $task->id,
                ['status' => $oldStatus->value],
                ['status' => $newStatus->value]
            );

            return $task->fresh();
        });
    }

    public function addEvidence(Task $task, array $data, ?UploadedFile $file, User $uploader): TaskEvidence
    {
        $path = null;
        if ($file) {
            $path = Storage::disk(config('filesystems.evidence_disk'))->putFileAs(
                "evidences/{$task->organization_id}/{$task->id}",
                $file,
                Str::ulid() . '.' . $file->getClientOriginalExtension()
            );
        }

        return TaskEvidence::create([
            'task_id'             => $task->id,
            'uploader_id'         => $uploader->id,
            'evidence_type'       => $data['evidence_type'],
            'title'               => $data['title'],
            'description'         => $data['description'] ?? null,
            'file_path'           => $path,
            'file_name'           => $file?->getClientOriginalName(),
            'file_size'           => $file?->getSize(),
            'mime_type'           => $file?->getMimeType(),
            'url'                 => $data['url'] ?? null,
            'data_classification' => $data['data_classification'] ?? 3,
        ]);
    }

    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $task->update(['deleted_by' => $actor->id]);
            $task->delete();
            $this->outbox->publish('task.deleted', [
                'task_id'         => $task->id,
                'organization_id' => $task->organization_id,
            ], 'Task', $task->id);
        });
    }
}
