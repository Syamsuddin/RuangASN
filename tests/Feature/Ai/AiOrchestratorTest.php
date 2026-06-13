<?php

namespace Tests\Feature\Ai;

use App\Enums\AiAgentType;
use App\Enums\AiInteractionType;
use App\Enums\AiIntent;
use App\Models\AiConversation;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\IntentClassifier;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AiOrchestratorTest extends AiTestCase
{
    use RefreshDatabase;

    // ── 1. AXIOM-06: every interaction is traced ────────────────────────────

    public function test_send_message_logs_query_routed_and_response(): void
    {
        $response = $this->actingAs($this->asn)->postJson('/ai/send', [
            'content' => 'apa itu SOP cuti tahunan',
        ]);

        $response->assertStatus(201);

        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();

        foreach ([
            AiInteractionType::QUERY_RECEIVED,
            AiInteractionType::AGENT_ROUTED,
            AiInteractionType::RESPONSE_GENERATED,
        ] as $interaction) {
            $this->assertDatabaseHas('ai_interaction_logs', [
                'organization_id' => $this->org->id,
                'user_id'         => $this->asn->id,
                'conversation_id' => $conversation->id,
                'interaction'     => $interaction->value,
            ]);
        }

        // Routed to KNOWLEDGE agent for an "apa itu" query.
        $this->assertDatabaseHas('ai_interaction_logs', [
            'conversation_id' => $conversation->id,
            'interaction'     => AiInteractionType::AGENT_ROUTED->value,
            'agent_type'      => AiAgentType::KNOWLEDGE->value,
            'intent'          => AiIntent::KNOWLEDGE_QA->value,
        ]);

        // Assistant message persisted + labelled.
        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
        ]);
    }

    // ── 6. Provider fallback ────────────────────────────────────────────────

    public function test_provider_falls_back_to_fake_when_primary_throws(): void
    {
        // A stub provider that is "available" but always throws, placed before
        // 'fake' in the fallback order → manager must fall through to fake.
        config()->set('ai.fallback_order', ['boom', 'fake']);

        $boom = new class implements AiProvider {
            public function name(): string
            {
                return 'boom';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $options = []): \App\Services\Ai\AiResult
            {
                throw new AiProviderException('boom: simulated upstream failure');
            }
        };

        $this->app->instance(
            AiProviderManager::class,
            new AiProviderManager([$boom, new FakeAiProvider()])
        );

        $manager  = $this->app->make(AiProviderManager::class);
        $outcome  = $manager->chatWithFallback(
            [['role' => 'user', 'content' => 'halo']],
            []
        );

        $this->assertSame('fake', $outcome['provider']);
        $this->assertTrue($outcome['fellBack']);
        $this->assertNotSame('', $outcome['result']->content);

        // And a full sendMessage still completes + logs RESPONSE_GENERATED.
        $this->actingAs($this->asn)->postJson('/ai/send', [
            'content' => 'halo asisten',
        ])->assertStatus(201);

        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();
        $this->assertDatabaseHas('ai_interaction_logs', [
            'conversation_id' => $conversation->id,
            'interaction'     => AiInteractionType::RESPONSE_GENERATED->value,
            'status'          => 'completed',
        ]);
    }

    // ── 7. Tenant isolation ─────────────────────────────────────────────────

    public function test_user_cannot_view_other_users_conversation(): void
    {
        // asn creates a conversation.
        $this->actingAs($this->asn)->postJson('/ai/send', ['content' => 'halo'])->assertStatus(201);
        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();

        // Another user in the SAME org cannot view it (own-only policy → 403).
        $sameOrgOther = $this->makeUser($this->org, '199501012020011055', 'Rekan', 'rekan@ai.id', 'asn');
        $this->actingAs($sameOrgOther)
            ->getJson("/ai/conversations/{$conversation->id}")
            ->assertStatus(403);

        // A user in a DIFFERENT org cannot even resolve it (org scope → 404).
        $otherOrg  = $this->makeOrg('Dinas Lain', 'DLN');
        $otherUser = $this->makeUser($otherOrg, '199901012020011099', 'ASN Lain', 'lain@ai.id', 'asn');
        $this->actingAs($otherUser)
            ->getJson("/ai/conversations/{$conversation->id}")
            ->assertStatus(404);
    }

    // ── 8. Intent classification (unit-style) ───────────────────────────────

    public function test_intent_classifier_maps_keywords(): void
    {
        $classifier = new IntentClassifier();

        $this->assertSame(
            AiIntent::CREATE_TASK,
            $classifier->classify('buatkan tugas laporan bulanan')['intent']
        );
        $this->assertSame(
            AiAgentType::SECRETARY,
            $classifier->classify('buatkan tugas laporan bulanan')['agent']
        );

        $this->assertSame(
            AiIntent::KNOWLEDGE_QA,
            $classifier->classify('apa itu SOP cuti')['intent']
        );

        $this->assertSame(
            AiIntent::SCHEDULE_MEETING,
            $classifier->classify('jadwalkan rapat koordinasi besok')['intent']
        );

        $this->assertSame(
            AiIntent::GENERAL_CHAT,
            $classifier->classify('selamat pagi')['intent']
        );
    }

    // ── ai.query gate ───────────────────────────────────────────────────────

    public function test_user_without_ai_query_permission_is_forbidden(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'no_ai', 'guard_name' => 'web']
        );
        $role->syncPermissions(['dashboard.view.own']);

        $user = $this->makeUser($this->org, '199601012020011066', 'Tanpa AI', 'noai@ai.id', 'no_ai');

        $this->actingAs($user)->postJson('/ai/send', ['content' => 'halo'])
            ->assertStatus(403);
    }
}
