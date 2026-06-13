<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiMessage */
class AiMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'conversation_id'  => $this->conversation_id,
            'role'             => $this->role,
            'content'          => $this->content,
            'tokens_used'      => $this->tokens_used,
            'model_name'       => $this->model_name,
            'finish_reason'    => $this->finish_reason,
            'citations'        => $this->citations ?? [],
            'proposed_actions' => $this->proposed_actions ?? [],
            'action_confirmed' => $this->action_confirmed,
            'has_pending_actions' => $this->hasPendingActions(),
            'confirmed_at'     => $this->confirmed_at?->toISOString(),
            'confirmed_by'     => $this->confirmed_by,
            'data_classification' => $this->data_classification,
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
