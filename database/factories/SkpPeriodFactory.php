<?php

namespace Database\Factories;

use App\Models\SkpPeriod;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SkpPeriodFactory extends Factory
{
    protected $model = SkpPeriod::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2026);

        return [
            'organization_id' => Organization::factory(),
            'year'            => $year,
            'semester'        => null,
            'name'            => "Periode SKP Tahun {$year}",
            'start_date'      => "{$year}-01-01",
            'end_date'        => "{$year}-12-31",
            'is_active'       => true,
        ];
    }
}
