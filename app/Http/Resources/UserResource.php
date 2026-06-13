<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nip'             => $this->nip,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'user_type'       => $this->user_type,
            'status'          => $this->status,
            'presence_status' => $this->presence_status,
            'workspace_mode'  => $this->workspace_mode,
            'avatar_path'     => $this->avatar_path,
            'mfa_enabled'     => $this->mfa_enabled,
            'timezone'        => $this->timezone,
            'locale'          => $this->locale,
            'organization_id' => $this->organization_id,
            'pemda_id'        => $this->pemda_id,
            'organization'    => $this->whenLoaded('organization', fn () => [
                'id'         => $this->organization->id,
                'name'       => $this->organization->name,
                'short_name' => $this->organization->short_name,
                'type'       => $this->organization->type,
            ]),
            'roles'           => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'permissions'     => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
