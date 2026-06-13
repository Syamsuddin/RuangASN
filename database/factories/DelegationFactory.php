<?php
namespace Database\Factories;

use App\Enums\DelegationType;
use App\Models\Delegation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delegation>
 */
class DelegationFactory extends Factory
{
    protected $model = Delegation::class;

    public function definition(): array
    {
        $org = Organization::first();
        return [
            'organization_id' => $org?->id,
            'delegator_id'    => User::factory(),
            'delegate_id'     => User::factory(),
            'type'            => $this->faker->randomElement(array_column(DelegationType::cases(), 'value')),
            'reason'          => $this->faker->sentence(),
            'start_date'      => now()->toDateString(),
            'end_date'        => now()->addDays(30)->toDateString(),
            'is_active'       => true,
            'sk_number'       => $this->faker->numerify('###/PLT/2026'),
        ];
    }
}
