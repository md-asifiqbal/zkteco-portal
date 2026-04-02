<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['name' => 'SaltSync'],
            ['company_name' => 'SaltSync',
                'domain' => 'saltsync.com',
                'api_key' => Str::random(32),
            ]
        );

        $devices = [
            ['name' => 'Main Gate', 'serial_number' => 'UDP3251600733', 'ip_address' => '10.1.2.2', 'model' => 'ZKTeco ProFace X'],
            ['name' => 'Device 2', 'serial_number' => 'CQZ7231261030', 'ip_address' => '10.1.2.3', 'model' => 'ZKTeco ProFace X'],
        ];

        foreach ($devices as $device) {
            $tenant->devices()->updateOrCreate(
                ['serial_number' => $device['serial_number']],
                $device
            );
        }
    }
}
