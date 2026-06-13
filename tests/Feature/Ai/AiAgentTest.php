<?php

namespace Tests\Feature\Ai;

use App\Enums\MeetingStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingDecision;
use App\Models\MeetingMinute;
use App\Models\Report;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Agent specialization (Phase 3, Sprint 15-16). Verifies the deterministic
 * agent behavior built on the existing orchestrator: drafting agents produce
 * reviewable drafts (never auto-saved) and the secretary agent only PROPOSES
 * actions that require explicit confirmation (AXIOM-04).
 */
class AiAgentTest extends AiTestCase
{
    use RefreshDatabase;

    // ── MeetingAgent: notulensi draft from agenda + decisions ───────────────

    public function test_meeting_agent_generates_minutes_draft_from_agenda_and_decisions(): void
    {
        $meeting = $this->makeMeetingWithContent();

        $response = $this->actingAs($this->kepalaOpd)
            ->post("/meetings/{$meeting->id}/minutes/ai-draft");

        $response->assertRedirect();

        /** @var MeetingMinute $minutes */
        $minutes = MeetingMinute::where('meeting_id', $meeting->id)->firstOrFail();

        // Draft is stored in ai_draft (NOT content) for human review (AXIOM-04).
        $this->assertNotNull($minutes->ai_draft);
        $this->assertNull($minutes->content);

        // Draft is derived from the meeting's actual agenda + decision text.
        $this->assertStringContainsString('Pembahasan Anggaran 2026', $minutes->ai_draft);
        $this->assertStringContainsString('Anggaran disetujui sebesar Rp 1 miliar', $minutes->ai_draft);
        $this->assertStringContainsString('Notulensi Rapat', $minutes->ai_draft);
        $this->assertStringContainsString('Keputusan', $minutes->ai_draft);
    }

    public function test_meeting_minutes_ai_draft_forbidden_for_non_host_non_secretary(): void
    {
        $meeting = $this->makeMeetingWithContent();

        // asn is neither host nor secretary and lacks meeting.minutes.create.
        $this->actingAs($this->asn)
            ->post("/meetings/{$meeting->id}/minutes/ai-draft")
            ->assertStatus(403);

        $this->assertDatabaseMissing('meeting_minutes', ['meeting_id' => $meeting->id]);
    }

    // ── ReportAgent: report draft derived from the report ───────────────────

    public function test_report_agent_generates_non_empty_draft_for_asn_author(): void
    {
        $report = Report::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'title'               => 'Laporan Kegiatan Bulanan Bidang Uji',
            'report_type'         => 'activity',
            'period_type'         => 'monthly',
            'period_start_date'   => '2026-05-01',
            'period_end_date'     => '2026-05-31',
            'data_classification' => 2,
            'content'             => 'Konten asli penulis.',
            'status'              => 'draft',
            'author_id'           => $this->asn->id,
            'created_by'          => $this->asn->id,
        ]);

        $response = $this->actingAs($this->asn)
            ->post("/reports/{$report->id}/ai-draft");

        $response->assertRedirect();

        $report->refresh();

        // Draft is non-empty and derived from the report (title + structure).
        $this->assertNotEmpty($report->ai_draft);
        $this->assertStringContainsString('Laporan Kegiatan Bulanan Bidang Uji', $report->ai_draft);
        $this->assertStringContainsString('Pendahuluan', $report->ai_draft);

        // AXIOM-04: the human's original content is never overwritten by the draft.
        $this->assertSame('Konten asli penulis.', $report->content);
    }

    // ── Confirm flow (panel-style send) regression ──────────────────────────

    public function test_panel_send_does_not_create_task_until_confirmed(): void
    {
        // A secretary-style request: the agent PROPOSES create_task, stores it.
        $send = $this->actingAs($this->asn)->postJson('/ai/send', [
            'content' => 'buatkan tugas menyusun laporan bulanan',
        ]);
        $send->assertStatus(201);

        // No task exists yet — the action is only proposed, not executed.
        $this->assertSame(0, Task::query()->count());

        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();
        /** @var AiMessage $assistant */
        $assistant = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')->firstOrFail();

        $this->assertTrue($assistant->hasPendingActions());
        $this->assertNotEmpty($assistant->proposed_actions);
        $this->assertSame('create_task', $assistant->proposed_actions[0]['type']);

        // Confirming runs the action through the user's own policies → task created.
        $confirm = $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0]);
        $confirm->assertStatus(200);

        $this->assertSame(1, Task::query()->count());
        $assistant->refresh();
        $this->assertTrue($assistant->action_confirmed);
    }

    private function makeMeetingWithContent(): Meeting
    {
        /** @var Meeting $meeting */
        $meeting = Meeting::create([
            'id'               => (string) Str::ulid(),
            'organization_id'  => $this->org->id,
            'pemda_id'         => $this->org->id,
            'title'            => 'Rapat Pembahasan Anggaran',
            'meeting_type'     => 'internal',
            'meeting_mode'     => 'offline',
            'status'           => MeetingStatus::SCHEDULED->value,
            'scheduled_at'     => now()->addDay(),
            'duration_minutes' => 60,
            'host_id'          => $this->kepalaOpd->id,
            'created_by'       => $this->kepalaOpd->id,
            'version'          => 1,
        ]);

        MeetingAgendaItem::create([
            'id'         => (string) Str::ulid(),
            'meeting_id' => $meeting->id,
            'title'      => 'Pembahasan Anggaran 2026',
            'sort_order' => 1,
            'is_completed' => false,
        ]);

        MeetingDecision::create([
            'id'          => (string) Str::ulid(),
            'meeting_id'  => $meeting->id,
            'content'     => 'Anggaran disetujui sebesar Rp 1 miliar.',
            'recorded_by' => $this->kepalaOpd->id,
        ]);

        return $meeting;
    }
}
