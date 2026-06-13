<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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
            'code'      => 'TORG',
            'is_active' => true,
            'depth'     => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011002',
            'name'            => 'Reset Tester',
            'email'           => 'reset@test.id',
            'password'        => Hash::make('oldpassword123'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $this->org->id,
            'pemda_id'        => $this->org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $this->user->assignRole('asn');
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->withoutVite()->get('/forgot-password')->assertStatus(200);
    }

    public function test_reset_password_page_renders(): void
    {
        $this->withoutVite()->get('/reset-password/fake-token?email=reset@test.id')->assertStatus(200);
    }

    public function test_known_email_sends_reset_notification(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $this->user->email])
            ->assertRedirect();

        Notification::assertSentTo($this->user, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_unknown_email_returns_success_shaped_response(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@nowhere.test']);

        // No enumeration: always redirects back with flash (no error about email not found)
        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        Notification::fake();

        // Generate a real token via the broker
        $token = Password::broker()->createToken($this->user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $this->user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect('/login');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_reset_password_clears_lockout(): void
    {
        $this->user->update(['failed_login_count' => 5, 'locked_until' => now()->addMinutes(5)]);

        $token = Password::broker()->createToken($this->user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $this->user->email,
            'password'              => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ])->assertRedirect('/login');

        $this->user->refresh();
        $this->assertEquals(0, $this->user->failed_login_count);
        $this->assertNull($this->user->locked_until);
    }

    public function test_invalid_token_returns_error(): void
    {
        $response = $this->post('/reset-password', [
            'token'                 => 'invalid-token',
            'email'                 => $this->user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
