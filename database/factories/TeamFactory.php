<?php
namespace Database\Factories;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $org = Organization::first();
        return [
            'pemda_id'        => $org?->id,
            'organization_id' => $org?->id,
            'type'            => $this->faker->randomElement(['task_force', 'project', 'committee', 'working_group']),
            'name'            => 'Tim ' . $this->faker->words(3, true),
            'description'     => $this->faker->sentence(),
            'is_cross_opd'    => false,
            'is_active'       => true,
            'start_date'      => now()->toDateString(),
            'sk_number'       => $this->faker->numerify('###/SK/2026'),
        ];
    }
}
