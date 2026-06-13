<?php

namespace Tests\Feature\Ai;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared scaffolding for AI feature tests: seeds RBAC, builds an org + users.
 * AI_PROVIDER defaults to 'fake' so generation is deterministic + offline.
 */
abstract class AiTestCase extends TestCase
{
    protected Organization $org;
    protected User $asn;
    protected User $kepalaOpd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->org = $this->makeOrg('Dinas AI Test', 'DAI');
        $this->asn = $this->makeUser($this->org, '199001012020011001', 'Pegawai AI', 'asn@ai.id', 'asn');
        $this->kepalaOpd = $this->makeUser($this->org, '198001012010011001', 'Kepala OPD', 'opd@ai.id', 'kepala_opd');
    }

    protected function makeOrg(string $name, string $code): Organization
    {
        $org = Organization::create([
            'id'        => (string) Str::ulid(),
            'type'      => 'government',
            'name'      => $name,
            'code'      => $code,
            'is_active' => true,
            'depth'     => 0,
        ]);
        $org->update(['pemda_id' => $org->id]);

        return $org;
    }

    protected function makeUser(Organization $org, string $nip, string $name, string $email, string $role): User
    {
        $user = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => $nip,
            'name'            => $name,
            'email'           => $email,
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $org->id,
            'pemda_id'        => $org->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
