<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMilestoneFactory extends Factory
{
    protected $model = ProjectMilestone::class;

    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'name'        => $this->faker->randomElement([
                'Penyusunan Dokumen Perencanaan',
                'Pengadaan Infrastruktur',
                'Pengembangan Modul Inti',
                'Uji Coba Terbatas (Pilot)',
                'Pelatihan Pengguna',
                'Go-Live dan Serah Terima',
                'Evaluasi dan Pelaporan Akhir',
            ]),
            'description' => $this->faker->sentence(),
            'status'      => MilestoneStatus::PENDING->value,
            'due_date'    => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'sort_order'  => $this->faker->numberBetween(0, 10),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => MilestoneStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
    }
}
