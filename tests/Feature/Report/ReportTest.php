<?php

namespace Tests\Feature\Report;

use App\Enums\ReportStatus;
use App\Models\Organization;
use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $author;
    private User $approver;
    private User $asnUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Pemda Laporan',
            'code'      => 'TPLAP',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->author = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011010',
            'name'            => 'Penulis Laporan',
            'email'           => 'penulis@report.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->author->assignRole('asn');

        $this->approver = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '198001012010011010',
            'name'            => 'Kepala OPD Laporan',
            'email'           => 'kepala@report.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->approver->assignRole('kepala_opd');

        $this->asnUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199501012023011010',
            'name'            => 'ASN Biasa',
            'email'           => 'asn@report.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->asnUser->assignRole('asn');
    }

    private function reportPayload(array $override = []): array
    {
        return array_merge([
            'title'               => 'Laporan Kegiatan Bulanan Dinas Uji',
            'report_type'         => 'activity',
            'period_type'         => 'monthly',
            'period_start_date'   => '2026-05-01',
            'period_end_date'     => '2026-05-31',
            'data_classification' => 2,
            'content'             => 'Isi laporan kegiatan.',
        ], $override);
    }

    // 1. Happy path: author creates report, status draft, author set correctly
    public function test_author_can_create_report(): void
    {
        $response = $this->actingAs($this->author)
            ->post('/reports', $this->reportPayload());

        $response->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'title'     => 'Laporan Kegiatan Bulanan Dinas Uji',
            'author_id' => $this->author->id,
            'status'    => ReportStatus::DRAFT->value,
            'organization_id' => $this->org->id,
        ]);
    }

    // 2. RBAC: asn cannot publish (no report.publish perm) → 403
    public function test_asn_cannot_publish_report(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();
        $this->assertNotNull($report);

        // Force status to approved so publish attempt is at least valid state-machine-wise
        $report->update(['status' => ReportStatus::APPROVED->value]);

        // asnUser has no report.publish — use author who also has no publish
        $response = $this->actingAs($this->asnUser)
            ->post("/reports/{$report->id}/publish");

        $response->assertStatus(403);
    }

    // 3. Tenant isolation: cross-org report view denied (404 via BelongsToOrganization scope)
    public function test_tenant_isolation_prevents_cross_org_access(): void
    {
        $otherOrg = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Other Org',
            'code'      => 'OORG',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $otherOrg->update(['pemda_id' => $otherOrg->id]);

        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011099',
            'name'            => 'Other User',
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

        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();
        $this->assertNotNull($report);

        $response = $this->actingAs($otherUser)->get("/reports/{$report->id}");
        $response->assertStatus(404);
    }

    // 4. State machine: submit draft → submitted writes status history row + sets submitted_at
    public function test_submit_writes_status_history_and_submitted_at(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();
        $this->assertNotNull($report);

        $response = $this->actingAs($this->author)
            ->post("/reports/{$report->id}/submit");

        $response->assertRedirect();

        $report->refresh();
        $this->assertEquals(ReportStatus::SUBMITTED->value, $report->status->value);
        $this->assertNotNull($report->submitted_at);

        $this->assertDatabaseHas('report_status_histories', [
            'report_id'   => $report->id,
            'from_status' => ReportStatus::DRAFT->value,
            'to_status'   => ReportStatus::SUBMITTED->value,
            'changed_by'  => $this->author->id,
        ]);
    }

    // 4b. Invalid transition draft → published: service throws ValidationException,
    //     Inertia web redirects back with errors (302 + session errors).
    public function test_invalid_transition_draft_to_published_rejected(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();

        // approver tries transition draft→published (not allowed by state machine)
        $response = $this->actingAs($this->approver)
            ->from("/reports/{$report->id}")
            ->post("/reports/{$report->id}/transition", [
                'status' => 'published',
                'notes'  => null,
            ]);

        // Inertia web redirects back with session errors on ValidationException
        $response->assertSessionHasErrors('status');

        $this->assertDatabaseHas('reports', [
            'id'     => $report->id,
            'status' => ReportStatus::DRAFT->value,
        ]);
    }

    // 5. Full approval flow: submit → in_review → approve → published
    public function test_full_approval_flow_leads_to_published(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();

        // Submit
        $this->actingAs($this->author)->post("/reports/{$report->id}/submit");
        $report->refresh();
        $this->assertEquals(ReportStatus::SUBMITTED->value, $report->status->value);

        // Transition to in_review
        $this->actingAs($this->approver)->post("/reports/{$report->id}/transition", [
            'status' => 'in_review',
        ]);
        $report->refresh();
        $this->assertEquals(ReportStatus::IN_REVIEW->value, $report->status->value);

        // Approve
        $this->actingAs($this->approver)->post("/reports/{$report->id}/transition", [
            'status' => 'approved',
        ]);
        $report->refresh();
        $this->assertEquals(ReportStatus::APPROVED->value, $report->status->value);
        $this->assertNotNull($report->approved_by);
        $this->assertNotNull($report->approved_at);

        // Publish
        $this->actingAs($this->approver)->post("/reports/{$report->id}/publish");
        $report->refresh();
        $this->assertEquals(ReportStatus::PUBLISHED->value, $report->status->value);
        $this->assertNotNull($report->published_at);
    }

    // 6. Author cannot approve own report (403)
    public function test_author_cannot_approve_own_report(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();

        // Bring to submitted
        $this->actingAs($this->author)->post("/reports/{$report->id}/submit");

        // Author (asn) tries to approve — 403: asn has no report.approve
        $response = $this->actingAs($this->author)
            ->post("/reports/{$report->id}/transition", [
                'status' => 'approved',
                'notes'  => null,
            ]);

        $response->assertStatus(403);
    }

    // 7. Revision flow: in_review → revision requires notes; author can resubmit
    public function test_revision_flow_requires_notes_and_author_can_resubmit(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();

        $this->actingAs($this->author)->post("/reports/{$report->id}/submit");
        $this->actingAs($this->approver)->post("/reports/{$report->id}/transition", ['status' => 'in_review']);

        // Revision without notes should fail validation
        $response = $this->actingAs($this->approver)
            ->from("/reports/{$report->id}")
            ->post("/reports/{$report->id}/transition", ['status' => 'revision', 'notes' => '']);

        $response->assertSessionHasErrors('notes');

        // Revision with notes succeeds
        $this->actingAs($this->approver)->post("/reports/{$report->id}/transition", [
            'status' => 'revision',
            'notes'  => 'Perlu perbaikan di bagian III.',
        ]);
        $report->refresh();
        $this->assertEquals(ReportStatus::REVISION->value, $report->status->value);

        // Author resubmits
        $this->actingAs($this->author)->post("/reports/{$report->id}/submit");
        $report->refresh();
        $this->assertEquals(ReportStatus::SUBMITTED->value, $report->status->value);
    }

    // 8. generateAiDraft populates ai_draft
    public function test_generate_ai_draft_populates_ai_draft(): void
    {
        $this->actingAs($this->author)->post('/reports', $this->reportPayload());
        $report = Report::where('author_id', $this->author->id)->first();
        $this->assertNull($report->ai_draft);

        $response = $this->actingAs($this->author)
            ->post("/reports/{$report->id}/ai-draft");

        $response->assertRedirect();

        $report->refresh();
        // The draft is now produced (deterministically) by the ReportAgent from
        // the report's own data — derived from the title + period, never empty.
        $this->assertNotNull($report->ai_draft);
        $this->assertStringContainsString('Laporan Kegiatan Bulanan Dinas Uji', $report->ai_draft);
        $this->assertStringContainsString('Pendahuluan', $report->ai_draft);
        // AXIOM-04: the draft is NEVER auto-saved to the report content; the
        // author's original content is left untouched for human review/edit.
        $this->assertSame('Isi laporan kegiatan.', $report->content);
    }
}
