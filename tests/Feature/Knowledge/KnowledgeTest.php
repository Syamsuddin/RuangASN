<?php

namespace Tests\Feature\Knowledge;

use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $author;   // asn role
    private User $editor;   // kepala_opd role

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda',
            'code'      => 'TEST',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->author = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Staf ASN',
            'email'           => 'asn@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->author->assignRole('asn');

        $this->editor = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '198001012010011001',
            'name'            => 'Kepala OPD',
            'email'           => 'kepaleopd@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->editor->assignRole('kepala_opd');
    }

    private function articlePayload(array $override = []): array
    {
        return array_merge([
            'title'               => 'SOP Pengajuan Cuti ASN',
            'content'             => '<p>Panduan lengkap pengajuan cuti tahunan.</p>',
            'knowledge_type'      => 'sop',
            'data_classification' => 2,
        ], $override);
    }

    // 1. Author creates article — status draft, version 1, is_latest
    public function test_author_can_create_article(): void
    {
        $response = $this->actingAs($this->author)
            ->post('/knowledge', $this->articlePayload());

        $response->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'title'          => 'SOP Pengajuan Cuti ASN',
            'author_id'      => $this->author->id,
            'status'         => KnowledgeStatus::DRAFT->value,
            'organization_id'=> $this->org->id,
            'version_number' => 1,
            'is_latest'      => true,
        ]);
    }

    // 2a. RBAC: asn cannot publish (no knowledge.publish) → 403
    public function test_asn_cannot_publish(): void
    {
        $article = KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Test Artikel',
            'knowledge_type'      => 'wiki',
            'status'              => KnowledgeStatus::IN_REVIEW->value,
            'version_number'      => 1,
            'is_latest'           => true,
            'data_classification' => 2,
            'author_id'           => $this->author->id,
            'created_by'          => $this->author->id,
        ]);

        $response = $this->actingAs($this->author)
            ->post("/knowledge/{$article->id}/publish");

        $response->assertStatus(403);
    }

    // 2b. kepala_opd can publish article in in_review
    public function test_kepala_opd_can_publish(): void
    {
        $article = KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Artikel Review',
            'knowledge_type'      => 'wiki',
            'status'              => KnowledgeStatus::IN_REVIEW->value,
            'version_number'      => 1,
            'is_latest'           => true,
            'data_classification' => 2,
            'author_id'           => $this->editor->id,
            'created_by'          => $this->editor->id,
        ]);

        $response = $this->actingAs($this->editor)
            ->post("/knowledge/{$article->id}/publish");

        $response->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'id'     => $article->id,
            'status' => KnowledgeStatus::PUBLISHED->value,
        ]);
    }

    // 3. Tenant isolation: cross-org article view denied (404 via BelongsToOrganization)
    public function test_tenant_isolation_prevents_cross_org_access(): void
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Other Pemda',
            'code'      => 'OTHER',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011099',
            'name'            => 'Other Kepala',
            'email'           => 'other@other.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $otherOrg->id,
            'pemda_id'        => $otherOrg->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('kepala_opd');

        // Create article in org A
        $this->actingAs($this->author)->post('/knowledge', $this->articlePayload());
        $article = KnowledgeArticle::where('author_id', $this->author->id)->first();
        $this->assertNotNull($article);

        // User from org B should get 404
        $response = $this->actingAs($otherUser)->get("/knowledge/{$article->id}");
        $response->assertStatus(404);
    }

    // 4a. State machine: draft→in_review valid
    public function test_valid_transition_draft_to_in_review(): void
    {
        $this->actingAs($this->author)->post('/knowledge', $this->articlePayload());
        $article = KnowledgeArticle::where('author_id', $this->author->id)->first();
        $this->assertNotNull($article);

        $response = $this->actingAs($this->author)
            ->post("/knowledge/{$article->id}/transition", ['status' => 'in_review']);

        $response->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'id'     => $article->id,
            'status' => KnowledgeStatus::IN_REVIEW->value,
        ]);
    }

    // 4b. State machine: draft→published direct is invalid (403 — policy blocks it)
    public function test_invalid_transition_draft_to_published_rejected(): void
    {
        $this->actingAs($this->author)->post('/knowledge', $this->articlePayload());
        $article = KnowledgeArticle::where('author_id', $this->author->id)->first();

        // Attempt to publish directly from draft — policy requires in_review
        $response = $this->actingAs($this->editor)
            ->post("/knowledge/{$article->id}/publish");

        $response->assertStatus(403);

        $this->assertDatabaseHas('knowledge_articles', [
            'id'     => $article->id,
            'status' => KnowledgeStatus::DRAFT->value,
        ]);
    }

    // 5. New version from published → v2 is_latest, old → outdated + is_latest false
    public function test_create_new_version_from_published(): void
    {
        $article = KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Artikel Published',
            'knowledge_type'      => 'wiki',
            'status'              => KnowledgeStatus::PUBLISHED->value,
            'version_number'      => 1,
            'is_latest'           => true,
            'data_classification' => 2,
            'author_id'           => $this->editor->id,
            'created_by'          => $this->editor->id,
            'published_at'        => now(),
        ]);

        $response = $this->actingAs($this->editor)->post("/knowledge/{$article->id}/versions", [
            'title'          => 'Artikel Versi 2',
            'content'        => '<p>Versi terbaru.</p>',
            'knowledge_type' => 'wiki',
        ]);

        $response->assertRedirect();

        // Old article: is_latest=false, status=outdated
        $article->refresh();
        $this->assertEquals(KnowledgeStatus::OUTDATED->value, $article->status->value);
        $this->assertFalse((bool) $article->is_latest);

        // New article: is_latest=true, version=2, status=draft
        $newArticle = KnowledgeArticle::where('parent_article_id', $article->id)->first();
        $this->assertNotNull($newArticle);
        $this->assertEquals(2, $newArticle->version_number);
        $this->assertTrue((bool) $newArticle->is_latest);
        $this->assertEquals(KnowledgeStatus::DRAFT->value, $newArticle->status->value);
    }

    // 6. recordView increments view_count on show; markHelpful increments helpful_count
    public function test_view_and_helpful_counts(): void
    {
        $article = KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Artikel Populer',
            'knowledge_type'      => 'wiki',
            'status'              => KnowledgeStatus::PUBLISHED->value,
            'version_number'      => 1,
            'is_latest'           => true,
            'data_classification' => 2,
            'author_id'           => $this->editor->id,
            'created_by'          => $this->editor->id,
            'published_at'        => now(),
        ]);

        $this->withoutVite()->actingAs($this->author)->get("/knowledge/{$article->id}");

        $article->refresh();
        $this->assertEquals(1, $article->view_count);

        $this->actingAs($this->author)->post("/knowledge/{$article->id}/helpful");

        $article->refresh();
        $this->assertEquals(1, $article->helpful_count);
    }

    // 7. Category create + assign article to category
    public function test_category_create_and_assign(): void
    {
        $catResponse = $this->actingAs($this->editor)
            ->post('/knowledge/categories', [
                'name'        => 'SOP & Prosedur',
                'description' => 'Standar Operasional Prosedur',
            ]);

        $catResponse->assertRedirect();

        $category = KnowledgeCategory::where('name', 'SOP & Prosedur')
            ->where('organization_id', $this->org->id)
            ->first();

        $this->assertNotNull($category);

        $response = $this->actingAs($this->author)->post('/knowledge', array_merge(
            $this->articlePayload(), ['category_id' => $category->id]
        ));

        $response->assertRedirect();

        $this->assertDatabaseHas('knowledge_articles', [
            'category_id' => $category->id,
            'author_id'   => $this->author->id,
        ]);
    }
}
