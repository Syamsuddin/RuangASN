<?php

namespace Database\Factories;

use App\Enums\PerformanceStatus;
use App\Models\Organization;
use App\Models\SkpPeriod;
use App\Models\SkpPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SkpPlanFactory extends Factory
{
    protected $model = SkpPlan::class;

    public function definition(): array
    {
        $org = Organization::first() ?? $this->makeOrg();
        $user = $this->makeUser($org);
        $period = SkpPeriod::where('organization_id', $org->id)->first()
            ?? $this->makePeriod($org);

        return [
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'period_id'       => $period->id,
            'superior_id'     => null,
            'status'          => PerformanceStatus::PLANNING->value,
            'version'         => 1,
            'created_by'      => $user->id,
        ];
    }

    public function planning(): static
    {
        return $this->state(['status' => PerformanceStatus::PLANNING->value]);
    }

    public function active(): static
    {
        return $this->state(['status' => PerformanceStatus::ACTIVE->value]);
    }

    private function makeOrg(): Organization
    {
        return Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Org Factory',
            'code'      => 'TOF' . rand(1, 9999),
            'is_active' => true,
            'depth'     => 0,
            'pemda_id'  => null,
        ]);
    }

    private function makeUser(Organization $org): User
    {
        return User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => (string) rand(100000000000000000, 999999999999999999),
            'name'            => $this->faker->name(),
            'email'           => $this->faker->unique()->safeEmail(),
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $org->id,
            'pemda_id'        => $org->pemda_id ?? $org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
    }

    private function makePeriod(Organization $org): SkpPeriod
    {
        return SkpPeriod::create([
            'id'              => (string) Str::ulid(),
            'organization_id' => $org->id,
            'year'            => 2026,
            'name'            => 'Test Period 2026',
            'start_date'      => '2026-01-01',
            'end_date'        => '2026-12-31',
            'is_active'       => true,
        ]);
    }
}
