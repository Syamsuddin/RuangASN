<?php

namespace Database\Factories;

use App\Enums\SkpPerspective;
use App\Models\SkpIndicator;
use App\Models\SkpPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkpIndicatorFactory extends Factory
{
    protected $model = SkpIndicator::class;

    public function definition(): array
    {
        $target = $this->faker->numberBetween(1, 100);

        return [
            'skp_plan_id'          => SkpPlan::factory(),
            'parent_indicator_id'  => null,
            'perspective'          => $this->faker->randomElement(array_column(SkpPerspective::cases(), 'value')),
            'name'                 => $this->faker->randomElement([
                'Meningkatkan kualitas pelayanan publik',
                'Menyelesaikan laporan kinerja tepat waktu',
                'Mengikuti pelatihan pengembangan kompetensi',
                'Menyusun anggaran kegiatan dinas',
                'Koordinasi lintas unit kerja',
            ]),
            'target_value'         => $target,
            'target_unit'          => $this->faker->randomElement(['dokumen', 'kegiatan', 'laporan', 'jam', 'orang']),
            'weight'               => 100,
            'realization_value'    => null,
            'achievement_pct'      => null,
            'superior_expectation' => null,
            'sort_order'           => 0,
        ];
    }
}
