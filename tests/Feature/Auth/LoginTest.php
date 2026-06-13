<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = Organization::create([
            'id'         => (string) Str::ulid(),
            'type'       => 'government',
            'name'       => 'Test Pemda',
            'code'       => 'TEST',
            'is_active'  => true,
            'depth'      => 0,
        ]);
        $this->org->update(['pemda_id' => $this->org->id]);

        $this->user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Test ASN',
            'email'           => 'test@test.id',
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

    public function test_can_login_with_nip(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => '199001012020011001',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_can_login_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => 'test@test.id',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => 'test@test.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_locked_account_returns_403(): void
    {
        $this->user->update(['locked_until' => now()->addMinutes(5)]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => 'test@test.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(423);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'login'    => 'test@test.id',
            'password' => 'password123',
        ]);

        $token = $loginRes->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'test@test.id');
    }

    public function test_unauthenticated_me_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_token(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'login'    => 'test@test.id',
            'password' => 'password123',
        ]);
        $token = $loginRes->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // Token is deleted from DB — verify the personal_access_tokens table is empty
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
