<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiConversation */
class AiConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'agent_type'     => $this->agent_type,
            'title'          => $this->title,
            'context_type'   => $this->context_type,
            'context_id'     => $this->context_id,
            'total_tokens'   => $this->total_tokens,
            'model_provider' => $this->model_provider,
            'model_name'     => $this->model_name,
            'archived_at'    => $this->archived_at?->toISOString(),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
            'messages'       => AiMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
