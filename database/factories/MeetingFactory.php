<?php

namespace Database\Factories;

use App\Enums\MeetingMode;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        $titles = [
            'Rapat Koordinasi Bidang Perencanaan',
            'Evaluasi Kinerja Triwulan',
            'Pembahasan Anggaran APBD',
            'Rapat Tindak Lanjut Hasil Audit',
            'Sosialisasi Peraturan Baru',
            'Briefing Tim Teknis',
            'Review Program Kerja Tahunan',
            'Koordinasi Lintas OPD',
        ];

        $scheduledAt = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $org = Organization::first();

        return [
            'id'               => (string) Str::ulid(),
            'organization_id'  => $org?->id ?? (string) Str::ulid(),
            'pemda_id'         => $org?->id ?? (string) Str::ulid(),
            'title'            => $this->faker->randomElement($titles),
            'description'      => $this->faker->optional()->sentence(10),
            'meeting_type'     => $this->faker->randomElement(array_column(MeetingType::cases(), 'value')),
            'meeting_mode'     => $this->faker->randomElement(array_column(MeetingMode::cases(), 'value')),
            'status'           => $this->faker->randomElement([MeetingStatus::DRAFT->value, MeetingStatus::SCHEDULED->value]),
            'scheduled_at'     => $scheduledAt,
            'duration_minutes' => $this->faker->randomElement([30, 60, 90, 120]),
            'location'         => $this->faker->optional()->streetAddress(),
            'data_classification' => 2,
            'version'          => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => MeetingStatus::DRAFT->value]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => MeetingStatus::SCHEDULED->value]);
    }

    public function withHost(User $host): static
    {
        return $this->state([
            'host_id'         => $host->id,
            'created_by'      => $host->id,
            'organization_id' => $host->organization_id,
            'pemda_id'        => $host->pemda_id,
        ]);
    }
}
