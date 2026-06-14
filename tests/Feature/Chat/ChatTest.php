<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatChannelType;
use App\Events\ChatMessageSent;
use App\Models\AppNotification;
use App\Models\ChatChannel;
use App\Models\ChatChannelMember;
use App\Models\ChatMessage;
use App\Models\Organization;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $alice;     // admin_pemda (full chat perms)
    private User $bob;       // asn (base chat perms)
    private User $otherUser; // user in another org

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org      = $this->makeOrg('Pemda A', 'PA');
        $this->otherOrg = $this->makeOrg('Pemda B', 'PB');

        $this->alice     = $this->makeUser($this->org, 'alice@a.id', '199001012020011001', 'admin_pemda');
        $this->bob       = $this->makeUser($this->org, 'bob@a.id', '199501012023011001', 'asn');
        $this->otherUser = $this->makeUser($this->otherOrg, 'other@b.id', '199001012020011099', 'admin_pemda');
    }

    private function makeOrg(string $name, string $code): Organization
    {
        $org = Organization::create([
            'id' => (string) Str::ulid(), 'type' => 'government', 'name' => $name,
            'code' => $code, 'is_active' => true, 'depth' => 0,
        ]);
        $org->update(['pemda_id' => $org->id]);
        return $org;
    }

    private function makeUser(Organization $org, string $email, string $nip, string $role): User
    {
        $user = User::create([
            'id' => (string) Str::ulid(), 'nip' => $nip, 'name' => explode('@', $email)[0],
            'email' => $email, 'password' => Hash::make('password'), 'user_type' => 'pns',
            'status' => 'active', 'organization_id' => $org->id, 'pemda_id' => $org->id,
            'timezone' => 'Asia/Jakarta', 'locale' => 'id',
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function chat(): ChatService
    {
        return app(ChatService::class);
    }

    // 1. Group channel: creator auto-member; member reads, non-member 403 on view + send.
    public function test_group_channel_creator_is_member_and_non_member_is_blocked(): void
    {
        $this->actingAs($this->alice)->post('/chat/channels', [
            'channel_type' => ChatChannelType::GROUP->value,
            'name'         => 'Tim Perencanaan',
            'member_ids'   => [$this->bob->id],
        ])->assertRedirect();

        $channel = ChatChannel::withoutGlobalScopes()->where('name', 'Tim Perencanaan')->first();
        $this->assertNotNull($channel);
        $this->assertDatabaseHas('chat_channel_members', [
            'channel_id' => $channel->id, 'user_id' => $this->alice->id, 'role' => 'owner',
        ]);
        $this->assertDatabaseHas('chat_channel_members', [
            'channel_id' => $channel->id, 'user_id' => $this->bob->id, 'role' => 'member',
        ]);

        // Member (alice) can send.
        $this->actingAs($this->alice)
            ->postJson("/chat/channels/{$channel->id}/messages", ['content' => 'Halo tim'])
            ->assertStatus(201);

        // Member (bob) can read.
        $this->actingAs($this->bob)
            ->getJson("/chat/channels/{$channel->id}/messages")
            ->assertOk();

        // A non-member in the same org cannot view or send.
        $carol = $this->makeUser($this->org, 'carol@a.id', '199001012020011050', 'asn');
        $this->actingAs($carol)
            ->getJson("/chat/channels/{$channel->id}/messages")
            ->assertStatus(403);
        $this->actingAs($carol)
            ->postJson("/chat/channels/{$channel->id}/messages", ['content' => 'intip'])
            ->assertStatus(403);
    }

    // 2. DM findOrCreateDm idempotent; both users members.
    public function test_dm_find_or_create_is_idempotent(): void
    {
        $this->actingAs($this->alice);

        $first  = $this->chat()->findOrCreateDm($this->alice, $this->bob);
        $second = $this->chat()->findOrCreateDm($this->bob, $this->alice);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(ChatChannelType::DM, $first->channel_type);
        $this->assertTrue($first->isMember($this->alice));
        $this->assertTrue($first->isMember($this->bob));
        $this->assertSame(2, $first->members()->count());
    }

    // 3. Tenant isolation: org B user cannot view/send to an org A channel.
    public function test_tenant_isolation_blocks_cross_org_access(): void
    {
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'Rahasia A',
        ], $this->alice);

        // otherUser is in org B — global scope hides the channel → 404 on route bind.
        $this->actingAs($this->otherUser)
            ->getJson("/chat/channels/{$channel->id}/messages")
            ->assertStatus(404);
        $this->actingAs($this->otherUser)
            ->postJson("/chat/channels/{$channel->id}/messages", ['content' => 'x'])
            ->assertStatus(404);
    }

    // 4. RBAC: no chat.send -> 403; delete own vs any.
    public function test_rbac_send_and_delete_permissions(): void
    {
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'RBAC', 'member_ids' => [$this->bob->id],
        ], $this->alice);

        // A user without chat.send (role removed, only view perms granted) is a
        // member but cannot send -> 403.
        $noSend = $this->makeUser($this->org, 'nosend@a.id', '199001012020011066', 'asn');
        $this->chat()->addMember($channel, $this->alice, $noSend->id);
        $noSend->removeRole('asn');
        $noSend->givePermissionTo(['chat.view.channel', 'chat.view.dm']); // can view, NOT send
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->actingAs($noSend->fresh())
            ->postJson("/chat/channels/{$channel->id}/messages", ['content' => 'tidak boleh'])
            ->assertStatus(403);

        // Bob sends a message.
        $msg = $this->chat()->sendMessage($channel, $this->bob, ['content' => 'pesan bob']);

        // A different non-owner without delete.any cannot delete bob's message.
        $this->bob->refresh();
        $eve = $this->makeUser($this->org, 'eve@a.id', '199001012020011077', 'asn');
        $this->chat()->addMember($channel, $this->alice, $eve->id);
        $this->actingAs($eve)
            ->deleteJson("/chat/messages/{$msg->id}")
            ->assertStatus(422); // ValidationException -> 422 on json

        // Admin (alice, has delete.any) can delete it.
        $this->actingAs($this->alice)
            ->deleteJson("/chat/messages/{$msg->id}")
            ->assertOk();
        $this->assertSoftDeleted('chat_messages', ['id' => $msg->id]);
    }

    // 5. markRead updates last_read_at; unread count reflects new messages.
    public function test_mark_read_and_unread_count(): void
    {
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'Unread', 'member_ids' => [$this->bob->id],
        ], $this->alice);

        // Alice sends 2 messages -> bob has 2 unread.
        $this->travelTo(now()->subMinutes(10));
        $this->chat()->sendMessage($channel, $this->alice, ['content' => 'm1']);
        $this->chat()->sendMessage($channel, $this->alice, ['content' => 'm2']);
        $this->assertSame(2, $channel->unreadCountFor($this->bob));

        // Bob marks read (5 min after the first two messages).
        $this->travelTo(now()->addMinutes(5));
        $this->chat()->markRead($channel, $this->bob);
        $member = ChatChannelMember::where('channel_id', $channel->id)->where('user_id', $this->bob->id)->first();
        $this->assertNotNull($member->last_read_at);
        $this->assertSame(0, $channel->fresh()->unreadCountFor($this->bob));

        // New message after read -> unread = 1.
        $this->travelTo(now()->addMinutes(5));
        $this->chat()->sendMessage($channel, $this->alice, ['content' => 'm3']);
        $this->assertSame(1, $channel->fresh()->unreadCountFor($this->bob));
        $this->travelBack();
    }

    // 6. react toggles; editMessage sets edited_at; soft delete hides message.
    public function test_react_edit_and_soft_delete(): void
    {
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'Edit',
        ], $this->alice);
        $msg = $this->chat()->sendMessage($channel, $this->alice, ['content' => 'awal']);

        // React toggle on / off.
        $this->chat()->react($msg, $this->alice, '👍');
        $this->assertSame([$this->alice->id], $msg->fresh()->reactions['👍']);
        $this->chat()->react($msg->fresh(), $this->alice, '👍');
        $this->assertNull($msg->fresh()->reactions);

        // Edit sets edited_at.
        $this->chat()->editMessage($msg->fresh(), $this->alice, 'diubah');
        $fresh = $msg->fresh();
        $this->assertSame('diubah', $fresh->content);
        $this->assertNotNull($fresh->edited_at);

        // Soft delete hides it.
        $this->chat()->deleteMessage($fresh, $this->alice);
        $this->assertSoftDeleted('chat_messages', ['id' => $msg->id]);
        $this->assertSame(0, $channel->messages()->count());
    }

    // 7. broadcast: ChatMessageSent dispatched on sendMessage.
    public function test_send_message_dispatches_broadcast_event(): void
    {
        Event::fake([ChatMessageSent::class]);
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'Broadcast',
        ], $this->alice);

        $this->chat()->sendMessage($channel, $this->alice, ['content' => 'realtime']);

        Event::assertDispatched(ChatMessageSent::class, fn ($e) => $e->message->channel_id === $channel->id);
    }

    // 8. mention notification: mentioning a user creates an AppNotification.
    public function test_mention_creates_notification(): void
    {
        $this->actingAs($this->alice);
        $channel = $this->chat()->createChannel([
            'channel_type' => ChatChannelType::GROUP->value, 'name' => 'Mention', 'member_ids' => [$this->bob->id],
        ], $this->alice);

        $this->chat()->sendMessage($channel, $this->alice, [
            'content'  => 'Tolong dicek ya @bob',
            'mentions' => [$this->bob->id],
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'recipient_id'      => $this->bob->id,
            'notification_type' => 'mention',
        ]);
        $this->assertSame(1, AppNotification::where('recipient_id', $this->bob->id)->count());
    }
}
