<?php

namespace Database\Factories;

use App\Models\KnowledgeCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeCategory>
 */
class KnowledgeCategoryFactory extends Factory
{
    protected $model = KnowledgeCategory::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'SOP & Prosedur', 'FAQ Kepegawaian', 'Panduan Sistem',
            'Regulasi & Kebijakan', 'Glosarium ASN', 'Template Dokumen',
            'Laporan & Evaluasi', 'Direktori Kontak', 'Praktik Terbaik',
        ]);

        return [
            'organization_id' => Organization::factory(),
            'parent_id'       => null,
            'name'            => $name,
            'slug'            => Str::slug($name) . '-' . Str::random(4),
            'description'     => $this->faker->sentence(),
            'sort_order'      => $this->faker->numberBetween(0, 10),
        ];
    }
}
