<?php
namespace Database\Factories;

use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $org = Organization::first();
        return [
            'organization_id'      => $org?->id,
            'name'                 => $this->faker->jobTitle(),
            'code'                 => $this->faker->bothify('POS-##??'),
            'position_type'        => $this->faker->randomElement(['structural', 'functional', 'jpt']),
            'echelon'              => $this->faker->randomElement(['II', 'III', 'IV', null]),
            'grade_level'          => $this->faker->numberBetween(7, 12),
            'is_head'              => false,
            'is_active'            => true,
            'effective_start_date' => now()->toDateString(),
        ];
    }
}
