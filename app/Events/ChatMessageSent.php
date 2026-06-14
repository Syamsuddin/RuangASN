<?php
namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('chat.channel.' . $this->message->channel_id);
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    public function broadcastWith(): array
    {
        /** @var \App\Models\User|null $sender */
        $sender = $this->message->sender;

        return [
            'id'           => $this->message->id,
            'channel_id'   => $this->message->channel_id,
            'sender'       => [
                'id'   => $sender?->id,
                'name' => $sender?->name,
            ],
            'content'      => $this->message->content,
            'content_type' => $this->message->content_type,
            'created_at'   => $this->message->created_at?->toISOString(),
            'parent_id'    => $this->message->parent_id,
        ];
    }
}
