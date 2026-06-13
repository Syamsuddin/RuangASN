<?php
namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pemda = Organization::where('code', 'PEMDA-DEMO')->first();
        $setda = Organization::where('code', 'SETDA-001')->first();

        // Super Admin
        $superAdmin = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '000000000000000001',
            'name'            => 'Super Administrator',
            'email'           => 'superadmin@ruangasn.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $pemda->id,
            'pemda_id'        => $pemda->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $superAdmin->assignRole('super_admin');

        // Admin Pemda
        $adminPemda = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '000000000000000002',
            'name'            => 'Admin Pemda Demo',
            'email'           => 'admin@ruangasn.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $pemda->id,
            'pemda_id'        => $pemda->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $adminPemda->assignRole('admin_pemda');

        // Kepala OPD
        $kepalaOpd = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199001012020011001',
            'name'            => 'Kepala Dinas Demo',
            'email'           => 'kadis@ruangasn.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $setda->id,
            'pemda_id'        => $pemda->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $kepalaOpd->assignRole('kepala_opd');

        // Staf ASN
        $staf = User::create([
            'id'              => (string) Str::ulid(),
            'nip'             => '199501012023011001',
            'name'            => 'Staf ASN Demo',
            'email'           => 'staf@ruangasn.id',
            'password'        => Hash::make('password'),
            'user_type'       => 'pns',
            'status'          => 'active',
            'organization_id' => $setda->id,
            'pemda_id'        => $pemda->id,
            'timezone'        => 'Asia/Jakarta',
            'locale'          => 'id',
        ]);
        $staf->assignRole('asn');
    }
}
