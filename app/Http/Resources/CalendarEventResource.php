<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarEvent */
class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'calendar_type' => $this->calendar_type->value,
            'type_label'    => $this->typeLabel(),
            'title'         => $this->title,
            'description'   => $this->description,
            'location'      => $this->location,
            'start_at'      => $this->start_at->toISOString(),
            'end_at'        => $this->end_at?->toISOString(),
            'all_day'       => $this->all_day,
            'is_recurring'  => $this->is_recurring,
            'rrule'         => $this->rrule,
            'color'         => $this->color,
            'is_public'     => $this->is_public,
            'organization_id' => $this->organization_id,
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),

            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id, 'name' => $this->owner->name,
            ] : null),
        ];
    }

    private function typeLabel(): string
    {
        return match ($this->calendar_type->value) {
            'personal'   => 'Pribadi',
            'team'       => 'Tim',
            'project'    => 'Proyek',
            'meeting'    => 'Meeting',
            'org'        => 'Organisasi',
            'government' => 'Pemerintah',
            'holiday'    => 'Hari Libur',
            default      => $this->calendar_type->value,
        };
    }
}
