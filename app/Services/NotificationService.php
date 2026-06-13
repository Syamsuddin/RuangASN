<?php
namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(
        User $recipient,
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
        NotificationChannel $channel = NotificationChannel::IN_APP,
    ): AppNotification {
        $notif = AppNotification::create([
            'id'                => (string) Str::ulid(),
            'organization_id'   => $recipient->organization_id,
            'recipient_id'      => $recipient->id,
            'notification_type' => $type->value,
            'title'             => $title,
            'body'              => $body,
            'data'              => $data,
            'channel'           => $channel->value,
            'status'            => 'sent',
            'delivered_at'      => now(),
        ]);

        // Broadcast real-time via Reverb
        broadcast(new \App\Events\NotificationSent($notif))->toOthers();

        return $notif;
    }

    public function notifyTaskAssigned(User $assignee, \App\Models\Task $task): void
    {
        $this->send(
            $assignee,
            NotificationType::TASK_ASSIGNED,
            'Tugas Baru Ditugaskan',
            "Anda mendapat tugas baru: {$task->title}",
            ['task_id' => $task->id],
        );
    }
}
