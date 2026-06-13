<?php

namespace Tests\Feature\Ai;

use App\Enums\AiInteractionType;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AiActionConfirmationTest extends AiTestCase
{
    use RefreshDatabase;

    private function sendCreateTaskQuery(): AiMessage
    {
        $response = $this->actingAs($this->asn)->postJson('/ai/send', [
            'content' => 'buatkan tugas menyusun laporan bulanan',
        ]);
        $response->assertStatus(201);

        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();

        return AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();
    }

    // ── 2. AXIOM-04: proposed action stored, NOT executed ───────────────────

    public function test_proposed_action_is_stored_but_not_executed(): void
    {
        $assistant = $this->sendCreateTaskQuery();

        // The assistant message carries a proposed action, unconfirmed.
        $this->assertNotEmpty($assistant->proposed_actions);
        $this->assertNull($assistant->action_confirmed);
        $this->assertSame('create_task', $assistant->proposed_actions[0]['type']);

        // CRITICAL: no task exists yet — AI only proposed.
        $title = $assistant->proposed_actions[0]['payload']['title'];
        $this->assertDatabaseMissing('tasks', ['title' => $title]);

        // ACTION_REQUESTED was logged (but never ACTION_CONFIRMED).
        $this->assertDatabaseHas('ai_interaction_logs', [
            'message_id'  => $assistant->id,
            'interaction' => AiInteractionType::ACTION_REQUESTED->value,
        ]);
        $this->assertDatabaseMissing('ai_interaction_logs', [
            'message_id'  => $assistant->id,
            'interaction' => AiInteractionType::ACTION_CONFIRMED->value,
        ]);
    }

    public function test_confirm_action_executes_through_user_policy(): void
    {
        $assistant = $this->sendCreateTaskQuery();
        $title     = $assistant->proposed_actions[0]['payload']['title'];

        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(200);

        // NOW the task exists, created through TaskService as the user.
        $this->assertDatabaseHas('tasks', [
            'title'      => $title,
            'creator_id' => $this->asn->id,
        ]);

        // Message marked confirmed by the user.
        $assistant->refresh();
        $this->assertTrue($assistant->action_confirmed);
        $this->assertSame($this->asn->id, $assistant->confirmed_by);
        $this->assertNotNull($assistant->confirmed_at);

        // ACTION_CONFIRMED logged + audit row.
        $this->assertDatabaseHas('ai_interaction_logs', [
            'message_id'  => $assistant->id,
            'interaction' => AiInteractionType::ACTION_CONFIRMED->value,
            'status'      => 'completed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'ai_action_confirmed',
            'auditable_type' => 'Task',
        ]);
    }

    // ── 3. Permission inheritance / no privilege escalation ─────────────────

    public function test_confirm_action_denied_when_user_lacks_permission(): void
    {
        // Build a role with ai.query but WITHOUT task.create.
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'ai_no_task_create', 'guard_name' => 'web']
        );
        $role->syncPermissions(['ai.query', 'ai.conversation.view.own', 'dashboard.view.own']);

        $weakUser = $this->makeUser($this->org, '199701012020011077', 'Tanpa Task', 'notask@ai.id', 'ai_no_task_create');
        $this->assertFalse($weakUser->hasPermissionTo('task.create'));

        // weakUser asks for a task → proposed action stored.
        $this->actingAs($weakUser)->postJson('/ai/send', [
            'content' => 'buatkan tugas rapat mingguan',
        ])->assertStatus(201);

        $conversation = AiConversation::where('user_id', $weakUser->id)->firstOrFail();
        $assistant = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')->firstOrFail();
        $title = $assistant->proposed_actions[0]['payload']['title'];

        // Confirm → 403 (AuthorizationException), NO escalation, NO task.
        $this->actingAs($weakUser)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['title' => $title]);

        $assistant->refresh();
        $this->assertNull($assistant->action_confirmed);
    }

    // ── 4. Reject ───────────────────────────────────────────────────────────

    public function test_reject_action_marks_rejected_and_creates_nothing(): void
    {
        $assistant = $this->sendCreateTaskQuery();
        $title     = $assistant->proposed_actions[0]['payload']['title'];

        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/reject", ['action_index' => 0])
            ->assertStatus(200);

        $assistant->refresh();
        $this->assertFalse($assistant->action_confirmed);

        $this->assertDatabaseMissing('tasks', ['title' => $title]);
        $this->assertDatabaseHas('ai_interaction_logs', [
            'message_id'  => $assistant->id,
            'interaction' => AiInteractionType::ACTION_REJECTED->value,
        ]);
    }

    public function test_user_cannot_confirm_action_on_foreign_conversation(): void
    {
        $assistant = $this->sendCreateTaskQuery();

        // A different user (same org) must not be able to confirm asn's action.
        $other = $this->makeUser($this->org, '199801012020011088', 'Rekan', 'rekan2@ai.id', 'asn');
        $this->actingAs($other)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(403);

        $assistant->refresh();
        $this->assertNull($assistant->action_confirmed);
    }

    // ── AXIOM-04: one proposal → at most one execution (idempotency) ─────────

    public function test_double_confirm_creates_only_one_entity_and_rejects_the_second(): void
    {
        $assistant = $this->sendCreateTaskQuery();
        $title     = $assistant->proposed_actions[0]['payload']['title'];

        // First confirm succeeds and creates the task.
        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(200);

        // Second confirm of the same proposal is rejected (422), not re-executed.
        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(422);

        // Exactly ONE task was created from the single proposal.
        $this->assertSame(1, \App\Models\Task::where('title', $title)->count());

        // Exactly ONE ACTION_CONFIRMED log row exists.
        $this->assertSame(1, \App\Models\AiInteractionLog::where('message_id', $assistant->id)
            ->where('interaction', AiInteractionType::ACTION_CONFIRMED->value)
            ->where('status', \App\Enums\AiInteractionStatus::COMPLETED->value)
            ->count());
    }

    public function test_cannot_reject_an_already_confirmed_action(): void
    {
        $assistant = $this->sendCreateTaskQuery();

        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 0])
            ->assertStatus(200);

        // Rejecting after confirming is blocked (would desync the created entity).
        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/reject", ['action_index' => 0])
            ->assertStatus(422);

        $assistant->refresh();
        $this->assertTrue($assistant->action_confirmed);
    }

    public function test_out_of_range_action_index_returns_422_not_500(): void
    {
        $assistant = $this->sendCreateTaskQuery();

        $this->actingAs($this->asn)
            ->postJson("/ai/messages/{$assistant->id}/confirm", ['action_index' => 99])
            ->assertStatus(422);

        // No task created, message untouched.
        $assistant->refresh();
        $this->assertNull($assistant->action_confirmed);
    }
}
