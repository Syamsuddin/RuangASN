<?php
namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Pemda Demo
        $pemda = Organization::create([
            'id'                  => (string) Str::ulid(),
            'type'                => 'government',
            'name'                => 'Pemerintah Kabupaten Demo',
            'short_name'          => 'Pemkab Demo',
            'code'                => 'PEMDA-DEMO',
            'is_active'           => true,
            'effective_start_date'=> now(),
            'depth'               => 0,
        ]);
        $pemda->update(['pemda_id' => $pemda->id]);

        // Sekretariat Daerah
        $setda = Organization::create([
            'id'                  => (string) Str::ulid(),
            'parent_id'           => $pemda->id,
            'pemda_id'            => $pemda->id,
            'type'                => 'department',
            'name'                => 'Sekretariat Daerah',
            'short_name'          => 'Setda',
            'code'                => 'SETDA-001',
            'is_active'           => true,
            'effective_start_date'=> now(),
            'depth'               => 1,
        ]);

        // Dinas Komunikasi dan Informatika
        Organization::create([
            'id'                  => (string) Str::ulid(),
            'parent_id'           => $pemda->id,
            'pemda_id'            => $pemda->id,
            'type'                => 'department',
            'name'                => 'Dinas Komunikasi dan Informatika',
            'short_name'          => 'Diskominfo',
            'code'                => 'DISKOMINFO-001',
            'is_active'           => true,
            'effective_start_date'=> now(),
            'depth'               => 1,
        ]);
    }
}
