<?php

namespace Database\Factories;

use App\Enums\CalendarType;
use App\Models\CalendarEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    private static array $titles = [
        'Rapat Koordinasi Bidang',
        'Sosialisasi Program Kerja',
        'Evaluasi Kinerja Triwulan',
        'Pelatihan Peningkatan Kapasitas ASN',
        'Diskusi Anggaran Tahunan',
        'Upacara Hari Nasional',
        'Workshop Transformasi Digital',
        'Bimbingan Teknis Pengadaan',
        'Rapat Pimpinan OPD',
        'Monitoring Proyek Strategis',
    ];

    public function definition(): array
    {
        $startAt = $this->faker->dateTimeBetween('now', '+30 days');
        $endAt   = (clone $startAt)->modify('+' . $this->faker->numberBetween(30, 120) . ' minutes');
        $color   = $this->faker->randomElement(['#3B82F6', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444']);

        return [
            'id'            => (string) Str::ulid(),
            'calendar_type' => $this->faker->randomElement(array_column(CalendarType::cases(), 'value')),
            'title'         => $this->faker->randomElement(self::$titles),
            'description'   => $this->faker->optional()->sentence(8),
            'location'      => $this->faker->optional()->randomElement([
                'Ruang Rapat Lantai 2', 'Aula Utama', 'Ruang Videoconference', 'Kantor Pusat',
            ]),
            'start_at'      => $startAt,
            'end_at'        => $endAt,
            'all_day'       => false,
            'is_recurring'  => false,
            'color'         => $color,
            'is_public'     => $this->faker->boolean(30),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CalendarEvent $event) {
            if (! $event->organization_id) {
                $org = Organization::factory()->create();
                $event->organization_id = $org->id;
            }
            if (! $event->owner_id || ! $event->created_by) {
                $user = User::factory()->create(['organization_id' => $event->organization_id]);
                $event->owner_id   = $event->owner_id   ?? $user->id;
                $event->created_by = $event->created_by ?? $user->id;
            }
        });
    }

    public function personal(): static
    {
        return $this->state(['calendar_type' => CalendarType::PERSONAL->value]);
    }

    public function allDay(): static
    {
        return $this->state(['all_day' => true]);
    }
}
