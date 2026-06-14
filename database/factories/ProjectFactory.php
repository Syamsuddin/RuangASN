<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $org   = Organization::first() ?? $this->makeOrg();
        $owner = User::where('organization_id', $org->id)->first() ?? $this->makeUser($org);

        $start = $this->faker->dateTimeBetween('-2 months', '+1 month');
        $end   = $this->faker->dateTimeBetween($start, '+8 months');

        return [
            'organization_id'    => $org->id,
            'pemda_id'           => $org->pemda_id ?? $org->id,
            'team_id'            => null,
            'name'               => $this->faker->randomElement([
                'Digitalisasi Layanan Perizinan Terpadu',
                'Pembangunan Sistem Informasi Kepegawaian',
                'Penataan Arsip Daerah Berbasis Cloud',
                'Optimalisasi Pelayanan Publik Kecamatan',
                'Integrasi Data Kependudukan Antar OPD',
                'Modernisasi Infrastruktur Jaringan Pemda',
                'Program Smart City Tahap Pertama',
                'Reformasi Birokrasi Pelayanan Terpadu',
            ]) . ' ' . $this->faker->year(),
            'description'        => $this->faker->paragraph(),
            'objectives'         => $this->faker->sentence(12),
            'status'             => ProjectStatus::DRAFT->value,
            'planned_start_date' => $start->format('Y-m-d'),
            'planned_end_date'   => $end->format('Y-m-d'),
            'budget'             => $this->faker->numberBetween(50, 5000) * 1_000_000,
            'budget_spent'       => 0,
            'owner_id'           => $owner->id,
            'manager_id'         => null,
            'progress_percent'   => 0,
            'tags'               => $this->faker->randomElements(
                ['prioritas', 'inovasi', 'pelayanan', 'infrastruktur', 'digital'],
                rand(1, 3)
            ),
            'data_classification' => 2,
            'created_by'          => $owner->id,
            'version'             => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProjectStatus::ACTIVE->value]);
    }

    public function planning(): static
    {
        return $this->state(['status' => ProjectStatus::PLANNING->value]);
    }

    private function makeOrg(): Organization
    {
        return Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Org Project',
            'code'      => 'TOP' . rand(1, 9999),
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
}
