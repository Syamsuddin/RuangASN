<?php

namespace Tests\Feature\Document;

use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentApproval;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $owner;
    private User $approver;
    private User $asn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        Storage::fake('local');

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda',
            'code'      => 'TEST',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->owner = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Kepala Bidang',
            'email'           => 'kepala@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->owner->assignRole('kepala_bidang');

        $this->approver = User::create([
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
        $this->approver->assignRole('kepala_opd');

        $this->asn = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199501012023011001',
            'name'            => 'Staf ASN',
            'email'           => 'staf@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->asn->assignRole('asn');
    }

    private function documentPayload(array $override = []): array
    {
        return array_merge([
            'title'               => 'Surat Perintah Tugas Koordinasi',
            'description'         => 'Surat untuk koordinasi lapangan',
            'document_type'       => 'letter',
            'data_classification' => 2,
            'document_number'     => '001/KB/2026',
            'document_date'       => '2026-06-01',
        ], $override);
    }

    // 1. Happy path: owner creates document with status draft
    public function test_owner_can_create_document(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->owner)
            ->post('/documents', array_merge($this->documentPayload(), ['file' => $file]));

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'title'               => 'Surat Perintah Tugas Koordinasi',
            'owner_id'            => $this->owner->id,
            'status'              => DocumentStatus::DRAFT->value,
            'organization_id'     => $this->org->id,
            'data_classification' => DataClassification::INTERNAL->value,
        ]);
    }

    // 2a. RBAC: asn cannot view a CONFIDENTIAL document (403)
    public function test_asn_cannot_view_confidential_document(): void
    {
        $doc = Document::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Dokumen Rahasia',
            'document_type'       => 'letter',
            'status'              => DocumentStatus::PUBLISHED->value,
            'data_classification' => DataClassification::CONFIDENTIAL->value,
            'owner_id'            => $this->owner->id,
            'created_by'          => $this->owner->id,
            'version_number'      => 1,
            'is_latest'           => true,
            'version'             => 1,
        ]);

        $response = $this->actingAs($this->asn)->get("/documents/{$doc->id}");
        $response->assertStatus(403);
    }

    // 2b. asn can view INTERNAL document (no 403 or 404)
    public function test_asn_can_view_internal_document(): void
    {
        $doc = Document::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Dokumen Internal',
            'document_type'       => 'letter',
            'status'              => DocumentStatus::PUBLISHED->value,
            'data_classification' => DataClassification::INTERNAL->value,
            'owner_id'            => $this->owner->id,
            'created_by'          => $this->owner->id,
            'version_number'      => 1,
            'is_latest'           => true,
            'version'             => 1,
        ]);

        $response = $this->withoutVite()->actingAs($this->asn)->get("/documents/{$doc->id}");
        $response->assertStatus(200);
    }

    // 3. Tenant isolation: cross-org document view denied (404 via BelongsToOrganization scope)
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
        $otherUser->assignRole('kepala_bidang');

        // Create doc in org A
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();
        $this->assertNotNull($doc);

        // User from org B should get 404
        $response = $this->actingAs($otherUser)->get("/documents/{$doc->id}");
        $response->assertStatus(404);
    }

    // 4. State machine: submit draft→in_review creates approval rows; invalid draft→published rejected
    public function test_submit_creates_approval_rows_and_transitions_to_in_review(): void
    {
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();
        $this->assertNotNull($doc);

        $response = $this->actingAs($this->owner)
            ->post("/documents/{$doc->id}/submit", [
                'approver_ids' => [$this->approver->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id'     => $doc->id,
            'status' => DocumentStatus::IN_REVIEW->value,
        ]);

        $this->assertDatabaseHas('document_approvals', [
            'document_id' => $doc->id,
            'approver_id' => $this->approver->id,
            'step_number' => 1,
            'status'      => 'pending',
        ]);
    }

    public function test_invalid_transition_draft_to_published_rejected(): void
    {
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();

        // Attempt to publish directly from draft (should fail with 403 — policy requires approved status)
        $response = $this->actingAs($this->owner)
            ->post("/documents/{$doc->id}/publish");

        $response->assertStatus(403);

        // Confirm status is still draft
        $this->assertDatabaseHas('documents', [
            'id'     => $doc->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);
    }

    // 5. Approval flow: approve all → document becomes approved; then publish → published
    public function test_full_approval_flow_leads_to_published(): void
    {
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();

        // Submit
        $this->actingAs($this->owner)->post("/documents/{$doc->id}/submit", [
            'approver_ids' => [$this->approver->id],
        ]);

        $approval = DocumentApproval::where('document_id', $doc->id)->first();
        $this->assertNotNull($approval);

        // Approve
        $response = $this->actingAs($this->approver)
            ->post("/documents/approvals/{$approval->id}/approve", ['notes' => 'Disetujui']);

        $response->assertRedirect();

        $doc->refresh();
        $this->assertEquals(DocumentStatus::APPROVED->value, $doc->status->value);

        // Publish
        $response = $this->actingAs($this->owner)
            ->post("/documents/{$doc->id}/publish");

        $response->assertRedirect();

        $doc->refresh();
        $this->assertEquals(DocumentStatus::PUBLISHED->value, $doc->status->value);
    }

    // 6. Reject requires reason → document becomes rejected
    public function test_reject_requires_reason_and_document_becomes_rejected(): void
    {
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();

        // Submit
        $this->actingAs($this->owner)->post("/documents/{$doc->id}/submit", [
            'approver_ids' => [$this->approver->id],
        ]);

        $approval = DocumentApproval::where('document_id', $doc->id)->first();

        // Reject without reason should fail validation
        $response = $this->actingAs($this->approver)
            ->from("/documents/{$doc->id}")
            ->post("/documents/approvals/{$approval->id}/reject", ['reason' => '']);

        $response->assertSessionHasErrors('reason');

        // Reject with reason
        $response = $this->actingAs($this->approver)
            ->post("/documents/approvals/{$approval->id}/reject", ['reason' => 'Perlu revisi substansi']);

        $response->assertRedirect();

        $doc->refresh();
        $this->assertEquals(DocumentStatus::REJECTED->value, $doc->status->value);

        $approval->refresh();
        $this->assertEquals('rejected', $approval->status);
    }

    // 7. New version: from published creates v2 (is_latest), old becomes superseded + is_latest false
    public function test_create_new_version_from_published(): void
    {
        $this->actingAs($this->owner)->post('/documents', $this->documentPayload());
        $doc = Document::where('owner_id', $this->owner->id)->first();

        // Push through to published
        $this->actingAs($this->owner)->post("/documents/{$doc->id}/submit", [
            'approver_ids' => [$this->approver->id],
        ]);
        $approval = DocumentApproval::where('document_id', $doc->id)->first();
        $this->actingAs($this->approver)->post("/documents/approvals/{$approval->id}/approve");
        $this->actingAs($this->owner)->post("/documents/{$doc->id}/publish");

        $doc->refresh();
        $this->assertEquals(DocumentStatus::PUBLISHED->value, $doc->status->value);

        // Create new version
        $newFile = UploadedFile::fake()->create('v2.pdf', 200, 'application/pdf');
        $response = $this->actingAs($this->owner)->post("/documents/{$doc->id}/versions", [
            'title' => 'Surat Perintah Tugas Koordinasi v2',
            'file'  => $newFile,
        ]);

        $response->assertRedirect();

        // Old doc is superseded and not latest
        $doc->refresh();
        $this->assertEquals(DocumentStatus::SUPERSEDED->value, $doc->status->value);
        $this->assertFalse((bool) $doc->is_latest);

        // New doc is draft and latest, version_number = 2
        $newDoc = Document::where('parent_document_id', $doc->id)->first();
        $this->assertNotNull($newDoc);
        $this->assertEquals(2, $newDoc->version_number);
        $this->assertTrue((bool) $newDoc->is_latest);
        $this->assertEquals(DocumentStatus::DRAFT->value, $newDoc->status->value);
    }
}
