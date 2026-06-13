<?php

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $titles = [
            'Surat Perintah Tugas Inspeksi Lapangan',
            'Peraturan Bupati tentang Tata Naskah Dinas',
            'SOP Pengelolaan Arsip Dinamis',
            'Laporan Kinerja Instansi Pemerintah Triwulan III',
            'Notulensi Rapat Koordinasi Pembangunan Daerah',
            'Keputusan Kepala OPD tentang Tim Pelaksana',
            'Memo Internal Penyesuaian Jadwal Kerja',
            'Template Surat Dinas Resmi',
            'Referensi Standar Pelayanan Minimal',
            'Kontrak Kerjasama Pengadaan Barang',
            'Dokumen Perencanaan Proyek Infrastruktur',
            'Dokumen Penilaian Kinerja ASN Tahunan',
        ];

        $org = Organization::first();

        return [
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $org?->id ?? (string) Str::ulid(),
            'pemda_id'            => $org?->id ?? (string) Str::ulid(),
            'title'               => $this->faker->randomElement($titles),
            'description'         => $this->faker->optional()->sentence(15),
            'document_type'       => $this->faker->randomElement(array_column(DocumentType::cases(), 'value')),
            'status'              => DocumentStatus::DRAFT->value,
            'document_number'     => $this->faker->optional()->numerify('###/OPD/####'),
            'document_date'       => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'data_classification' => $this->faker->randomElement([
                DataClassification::PUBLIC->value,
                DataClassification::INTERNAL->value,
            ]),
            'version_number'      => 1,
            'is_latest'           => true,
            'version'             => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => DocumentStatus::DRAFT->value]);
    }

    public function published(): static
    {
        return $this->state(['status' => DocumentStatus::PUBLISHED->value]);
    }

    public function withOwner(User $owner): static
    {
        return $this->state([
            'owner_id'        => $owner->id,
            'created_by'      => $owner->id,
            'organization_id' => $owner->organization_id,
            'pemda_id'        => $owner->pemda_id,
        ]);
    }
}
