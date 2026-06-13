<?php

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $endDate   = $this->faker->dateTimeBetween($startDate, '+1 month');
        $user      = User::first();

        return [
            'title'               => $this->faker->randomElement([
                'Laporan Kegiatan Bulanan Dinas Pendidikan',
                'Laporan Kinerja Triwulan Pertama',
                'Laporan Kegiatan Tahunan Bidang Keuangan',
                'Laporan Kegiatan Pemberdayaan Masyarakat',
                'Laporan Pelaksanaan Program Kerja',
                'Laporan Evaluasi Kinerja ASN',
                'Laporan Rapat Koordinasi Teknis',
                'Laporan Monitoring dan Evaluasi',
            ]) . ' ' . $this->faker->year(),
            'content'             => $this->faker->paragraphs(3, true),
            'ai_draft'            => null,
            'report_type'         => $this->faker->randomElement(array_column(ReportType::cases(), 'value')),
            'period_type'         => $this->faker->randomElement(array_column(ReportPeriodType::cases(), 'value')),
            'status'              => ReportStatus::DRAFT->value,
            'period_start_date'   => $startDate->format('Y-m-d'),
            'period_end_date'     => $endDate->format('Y-m-d'),
            'data_sources'        => [],
            'data_classification' => DataClassification::INTERNAL->value,
            'version'             => 1,
        ];
    }
}
