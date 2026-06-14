<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatMessage */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->getAuthIdentifier();
        $sender = $this->sender;

        return [
            'id'           => $this->id,
            'channel_id'   => $this->channel_id,
            'parent_id'    => $this->parent_id,
            'sender'       => $sender ? ['id' => $sender->id, 'name' => $sender->name] : null,
            'content'      => $this->content,
            'content_type' => $this->content_type,
            'attachments'  => $this->attachments,
            'mentions'     => $this->mentions,
            'reactions'    => $this->reactions,
            'is_pinned'    => $this->is_pinned,
            'edited_at'    => $this->edited_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
            'is_mine'      => $this->sender_id === $userId,
            'reply_count'  => $this->whenCounted('replies'),
        ];
    }
}
