<?php
namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('user.' . $this->notification->recipient_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                => $this->notification->id,
            'notification_type' => $this->notification->notification_type,
            'title'             => $this->notification->title,
            'body'              => $this->notification->body,
            'data'              => $this->notification->data,
            'created_at'        => $this->notification->created_at?->toISOString(),
        ];
    }
}
