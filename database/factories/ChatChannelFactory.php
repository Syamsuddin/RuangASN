<?php

namespace Database\Factories;

use App\Enums\ChatChannelType;
use App\Models\ChatChannel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatChannel>
 */
class ChatChannelFactory extends Factory
{
    protected $model = ChatChannel::class;

    public function definition(): array
    {
        $names = [
            'Koordinasi Bidang Perencanaan',
            'Tim Pengadaan Barang & Jasa',
            'Diskusi Program Kerja 2026',
            'Sekretariat OPD',
            'Tim Teknis SPBE',
            'Gugus Tugas Reformasi Birokrasi',
            'Channel Pengumuman Resmi',
            'Koordinasi Lintas OPD',
        ];

        $org = Organization::first();

        return [
            'id'              => (string) Str::ulid(),
            'organization_id' => $org?->id ?? (string) Str::ulid(),
            'channel_type'    => ChatChannelType::GROUP->value,
            'name'            => $this->faker->randomElement($names),
            'description'     => $this->faker->optional()->sentence(8),
            'is_archived'     => false,
        ];
    }

    public function dm(): static
    {
        return $this->state(['channel_type' => ChatChannelType::DM->value, 'name' => null]);
    }

    public function announcement(): static
    {
        return $this->state([
            'channel_type' => ChatChannelType::ANNOUNCEMENT->value,
            'name'         => 'Pengumuman ' . $this->faker->randomElement(['Pemda', 'OPD', 'Kepegawaian']),
        ]);
    }

    public function forCreator(User $creator): static
    {
        return $this->state([
            'organization_id' => $creator->organization_id,
            'created_by'      => $creator->id,
        ]);
    }
}
