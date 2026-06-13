<?php

namespace Tests\Feature\Settings;

use App\Models\MfaBackupCode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => 'Test Org',
            'code'      => 'TORG2',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011003',
            'name'            => 'Settings User',
            'email'           => 'settings@test.id',
            'password'        => Hash::make('password123'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->user->assignRole('asn');
    }

    public function test_settings_page_requires_auth(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_settings_page_renders_for_authenticated_user(): void
    {
        $this->withoutVite()->actingAs($this->user, 'web')
            ->get('/settings')
            ->assertStatus(200);
    }

    public function test_update_profile_persists_name_phone_bio(): void
    {
        $this->actingAs($this->user, 'web')
            ->patch('/settings/profile', [
                'name'     => 'Updated Name',
                'phone'    => '082112345678',
                'bio'      => 'Ini bio baru saya.',
                'timezone' => 'Asia/Makassar',
                'locale'   => 'en',
            ])
            ->assertRedirect();

        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
        $this->assertEquals('082112345678', $this->user->phone);
        $this->assertEquals('Ini bio baru saya.', $this->user->bio);
        $this->assertEquals('Asia/Makassar', $this->user->timezone);
        $this->assertEquals('en', $this->user->locale);
    }

    public function test_update_profile_requires_name(): void
    {
        $this->actingAs($this->user, 'web')
            ->patch('/settings/profile', ['name' => ''])
            ->assertSessionHasErrors(['name']);
    }

    public function test_update_password_wrong_current_returns_error(): void
    {
        $this->actingAs($this->user, 'web')
            ->patch('/settings/password', [
                'current_password'      => 'wrongcurrent',
                'password'              => 'newpass12345',
                'password_confirmation' => 'newpass12345',
            ])
            ->assertSessionHasErrors(['current_password']);
    }

    public function test_update_password_correct_current_changes_password(): void
    {
        $this->actingAs($this->user, 'web')
            ->patch('/settings/password', [
                'current_password'      => 'password123',
                'password'              => 'newpassword99',
                'password_confirmation' => 'newpassword99',
            ])
            ->assertRedirect();

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword99', $this->user->password));
    }

    public function test_mfa_setup_returns_secret(): void
    {
        $this->actingAs($this->user, 'web')
            ->post('/settings/mfa/setup')
            ->assertRedirect()
            ->assertSessionHas('mfa_setup');
    }

    public function test_mfa_enable_flow_sets_mfa_enabled_and_creates_backup_codes(): void
    {
        $this->actingAs($this->user, 'web');

        // Setup: get pending secret stored in session
        $this->post('/settings/mfa/setup');
        $secret = session('mfa_pending_secret');

        $this->assertNotNull($secret);

        // Generate a valid OTP
        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $otp = $google2fa->getCurrentOtp($secret);

        $response = $this->post('/settings/mfa/enable', ['otp_code' => $otp]);
        $response->assertRedirect();

        $this->user->refresh();
        $this->assertTrue($this->user->mfa_enabled);
        $this->assertDatabaseCount('mfa_backup_codes', 10);
        $this->assertCount(10, $this->user->mfaBackupCodes()->get());
    }

    public function test_mfa_disable_with_correct_password_clears_mfa(): void
    {
        // Enable MFA first
        $this->actingAs($this->user, 'web');
        $this->post('/settings/mfa/setup');
        $secret = session('mfa_pending_secret');
        $google2fa = app(\PragmaRX\Google2FALaravel\Google2FA::class);
        $otp = $google2fa->getCurrentOtp($secret);
        $this->post('/settings/mfa/enable', ['otp_code' => $otp]);

        $this->user->refresh();
        $this->assertTrue($this->user->mfa_enabled);

        // Now disable
        $this->post('/settings/mfa/disable', ['password' => 'password123'])
            ->assertRedirect();

        $this->user->refresh();
        $this->assertFalse($this->user->mfa_enabled);
        $this->assertNull($this->user->mfa_secret);
        $this->assertDatabaseCount('mfa_backup_codes', 0);
    }

    public function test_revoke_session_deletes_token(): void
    {
        $this->actingAs($this->user, 'web');

        // Create a token to revoke
        $token = $this->user->createToken('test-token');
        $tokenId = $token->accessToken->id;

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $this->delete("/settings/sessions/{$tokenId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_cannot_revoke_another_users_token(): void
    {
        $otherUser = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011099',
            'name'            => 'Other User',
            'email'           => 'other@test.id',
            'password'        => Hash::make('password123'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $otherUser->assignRole('asn');

        $otherToken = $otherUser->createToken('other-token');
        $otherTokenId = $otherToken->accessToken->id;

        $this->actingAs($this->user, 'web')
            ->delete("/settings/sessions/{$otherTokenId}")
            ->assertRedirect();

        // Token of other user must still exist
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenId]);
    }
}
