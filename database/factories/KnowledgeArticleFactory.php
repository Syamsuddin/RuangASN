<?php

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    protected $model = KnowledgeArticle::class;

    public function definition(): array
    {
        $titles = [
            'SOP Pengajuan Cuti Tahunan ASN',
            'FAQ Sistem Informasi Kepegawaian',
            'Panduan Pengisian SKP Elektronik',
            'Prosedur Kenaikan Pangkat PNS',
            'Glosarium Istilah Administrasi Pemerintahan',
            'Template Surat Dinas Resmi',
            'Panduan Penggunaan Portal Layanan ASN',
            'SOP Pengelolaan Arsip Elektronik',
            'Praktik Terbaik Pelayanan Publik Digital',
            'Catatan Regulasi PP 94 Tahun 2021',
        ];

        $content = '<p>' . implode('</p><p>', $this->faker->paragraphs(3)) . '</p>';

        return [
            'organization_id'     => Organization::factory(),
            'pemda_id'            => Organization::factory(),
            'category_id'         => null,
            'title'               => $this->faker->randomElement($titles),
            'content'             => $content,
            'excerpt'             => $this->faker->paragraph(),
            'knowledge_type'      => $this->faker->randomElement(KnowledgeType::cases())->value,
            'status'              => KnowledgeStatus::DRAFT->value,
            'version_number'      => 1,
            'parent_article_id'   => null,
            'is_latest'           => true,
            'tags'                => $this->faker->randomElements(['asn', 'sop', 'panduan', 'regulasi', 'digital'], 2),
            'view_count'          => 0,
            'helpful_count'       => 0,
            'data_classification' => DataClassification::INTERNAL->value,
            'author_id'           => User::factory(),
            'published_by'        => null,
            'published_at'        => null,
            'created_by'          => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status'       => KnowledgeStatus::PUBLISHED->value,
            'published_at' => now(),
        ]);
    }
}
