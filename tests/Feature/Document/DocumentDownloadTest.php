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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
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
            'name'      => 'Test Pemda DL',
            'code'      => 'TDLD',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->owner = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011002',
            'name'            => 'Kepala Bidang DL',
            'email'           => 'kepala_dl@test.id',
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
            'nip'             => '198001012010011002',
            'name'            => 'Kepala OPD DL',
            'email'           => 'kepala_opd_dl@test.id',
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
            'nip'             => '199501012023011002',
            'name'            => 'Staf ASN DL',
            'email'           => 'staf_dl@test.id',
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

    private function makeDocument(DataClassification $classification, ?string $filePath = null, string $mime = 'application/pdf'): Document
    {
        return Document::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Test Document',
            'document_type'       => 'letter',
            'status'              => DocumentStatus::PUBLISHED->value,
            'data_classification' => $classification->value,
            'owner_id'            => $this->owner->id,
            'created_by'          => $this->owner->id,
            'version_number'      => 1,
            'is_latest'           => true,
            'version'             => 1,
            'file_path'           => $filePath,
            'file_name'           => $filePath ? basename($filePath) : null,
            'mime_type'           => $filePath ? $mime : null,
        ]);
    }

    // 1. Approval queue shows only documents with a pending approval assigned to the auth user
    public function test_approval_queue_shows_only_own_pending_approvals(): void
    {
        // Create doc submitted to $this->approver
        $doc = $this->makeDocument(DataClassification::INTERNAL);
        $doc->update(['status' => DocumentStatus::IN_REVIEW->value]);

        DocumentApproval::create([
            'id'          => (string) Str::ulid(),
            'document_id' => $doc->id,
            'approver_id' => $this->approver->id,
            'step_number' => 1,
            'status'      => 'pending',
        ]);

        // Create a second doc NOT assigned to approver
        $doc2 = $this->makeDocument(DataClassification::INTERNAL);
        $doc2->update(['status' => DocumentStatus::IN_REVIEW->value]);

        $otherApprover = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '198001012010011099',
            'name'            => 'Other OPD',
            'email'           => 'other_opd@test.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherApprover->assignRole('kepala_opd');

        DocumentApproval::create([
            'id'          => (string) Str::ulid(),
            'document_id' => $doc2->id,
            'approver_id' => $otherApprover->id,
            'step_number' => 1,
            'status'      => 'pending',
        ]);

        $response = $this->withoutVite()->actingAs($this->approver)
            ->get('/documents/approval-queue');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Documents/ApprovalQueue')
                 ->where('count', 1)
                 ->has('queue', 1)
                 ->where('queue.0.id', $doc->id)
        );
    }

    // 2. Approval queue requires document.approve — asn gets 403
    public function test_approval_queue_requires_approve_permission(): void
    {
        $response = $this->actingAs($this->asn)->get('/documents/approval-queue');
        $response->assertStatus(403);
    }

    // 3. Download is audited: an audit_logs row is written on download
    public function test_download_creates_audit_log(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        Storage::disk('local')->put("documents/{$this->org->id}/test.pdf", $file->getContent());

        $doc = $this->makeDocument(DataClassification::INTERNAL, "documents/{$this->org->id}/test.pdf", 'application/pdf');

        $response = $this->actingAs($this->owner)->get("/documents/{$doc->id}/download");
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'Document',
            'auditable_id'   => $doc->id,
            'action'         => 'downloaded',
            'user_id'        => $this->owner->id,
        ]);
    }

    // 4. Classification gate: asn can download INTERNAL but NOT CONFIDENTIAL
    public function test_asn_can_download_internal_but_not_confidential(): void
    {
        $file = UploadedFile::fake()->create('internal.pdf', 50, 'application/pdf');
        Storage::disk('local')->put("documents/{$this->org->id}/internal.pdf", $file->getContent());

        $internalDoc = $this->makeDocument(
            DataClassification::INTERNAL,
            "documents/{$this->org->id}/internal.pdf",
            'application/pdf'
        );

        $response = $this->actingAs($this->asn)->get("/documents/{$internalDoc->id}/download");
        $response->assertStatus(200);

        $confFile = UploadedFile::fake()->create('conf.pdf', 50, 'application/pdf');
        Storage::disk('local')->put("documents/{$this->org->id}/conf.pdf", $confFile->getContent());

        $confDoc = $this->makeDocument(
            DataClassification::CONFIDENTIAL,
            "documents/{$this->org->id}/conf.pdf",
            'application/pdf'
        );

        $response = $this->actingAs($this->asn)->get("/documents/{$confDoc->id}/download");
        $response->assertStatus(403);
    }

    // 5. Signed stream route rejects tampered URL and serves with valid signature
    public function test_signed_stream_route_rejects_invalid_signature(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        Storage::disk('local')->put("documents/{$this->org->id}/doc.pdf", $file->getContent());

        $doc = $this->makeDocument(
            DataClassification::INTERNAL,
            "documents/{$this->org->id}/doc.pdf",
            'application/pdf'
        );

        // Unsigned URL should be rejected
        $response = $this->actingAs($this->owner)->get("/documents/{$doc->id}/stream");
        $response->assertStatus(403);
    }

    public function test_signed_stream_route_serves_with_valid_signature(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        Storage::disk('local')->put("documents/{$this->org->id}/doc.pdf", $file->getContent());

        $doc = $this->makeDocument(
            DataClassification::INTERNAL,
            "documents/{$this->org->id}/doc.pdf",
            'application/pdf'
        );

        $signedUrl = URL::temporarySignedRoute('documents.stream', now()->addMinutes(5), ['document' => $doc->id]);

        $response = $this->actingAs($this->owner)->get($signedUrl);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 6. Image watermark: CONFIDENTIAL image returns 200 with image content-type
    public function test_confidential_image_download_returns_watermarked_image(): void
    {
        // Create a real PNG in fake storage
        $fakePng = UploadedFile::fake()->image('photo.png', 100, 100);
        $fakePngPath = "documents/{$this->org->id}/photo.png";
        Storage::disk('local')->put($fakePngPath, $fakePng->getContent());

        $doc = $this->makeDocument(DataClassification::CONFIDENTIAL, $fakePngPath, 'image/png');

        // kepala_bidang has document.download.confidential via role
        $response = $this->actingAs($this->owner)->get("/documents/{$doc->id}/download");
        $response->assertStatus(200);

        // Content-type must be an image
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type') ?? '');

        // If GD is loaded, assert that the output bytes differ from the original (watermark was applied)
        if (extension_loaded('gd')) {
            $original = Storage::disk('local')->get($fakePngPath);
            $this->assertNotEquals($original, $response->getContent(), 'Watermarked image should differ from original');
        }
    }
}
