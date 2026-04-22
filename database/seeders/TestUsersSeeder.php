<?php

namespace Database\Seeders;

use App\Models\LocalGovernment;
use App\Models\User;
use App\Models\UserWardAssignment;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $lg = LocalGovernment::where('code', 'ILA-MUN-01')->first();

        if (! $lg) {
            $this->command->error('Run MasterDataSeeder first!');
            return;
        }

        $verified = now();

        // Super Admin
        User::firstOrCreate(['email' => 'superadmin@test.com'], [
            'name'                  => 'Super Administrator',
            'email'                 => 'superadmin@test.com',
            'password'              => Hash::make('password'),
            'role'                  => 'super_admin',
            'local_government_id'   => $lg->id,
            'is_active'             => true,
            'email_verified_at'     => $verified,
        ]);

        // Municipal Admin
        User::firstOrCreate(['email' => 'admin@test.com'], [
            'name'                  => 'नगर प्रशासک',
            'email'                 => 'admin@test.com',
            'password'              => Hash::make('password'),
            'role'                  => 'municipal_admin',
            'local_government_id'   => $lg->id,
            'is_active'             => true,
            'email_verified_at'     => $verified,
        ]);

        // Ward Reviewer (assigned to wards 1, 2, 3)
        $reviewer = User::firstOrCreate(['email' => 'reviewer@test.com'], [
            'name'                  => 'वडा समीक्षक',
            'email'                 => 'reviewer@test.com',
            'password'              => Hash::make('password'),
            'role'                  => 'ward_reviewer',
            'local_government_id'   => $lg->id,
            'is_active'             => true,
            'email_verified_at'     => $verified,
        ]);

        $ward1 = Ward::where(['local_government_id' => $lg->id, 'ward_number' => 1])->first();
        $ward2 = Ward::where(['local_government_id' => $lg->id, 'ward_number' => 2])->first();
        $ward3 = Ward::where(['local_government_id' => $lg->id, 'ward_number' => 3])->first();

        foreach ([$ward1, $ward2, $ward3] as $ward) {
            if ($ward) {
                UserWardAssignment::firstOrCreate(
                    ['user_id' => $reviewer->id, 'ward_id' => $ward->id],
                    ['assigned_at' => now(), 'assigned_by' => null]
                );
            }
        }

        // Enumerator (assigned to ward 1)
        $enumerator = User::firstOrCreate(['email' => 'enumerator@test.com'], [
            'name'                  => 'गणक',
            'email'                 => 'enumerator@test.com',
            'password'              => Hash::make('password'),
            'role'                  => 'enumerator',
            'local_government_id'   => $lg->id,
            'is_active'             => true,
            'email_verified_at'     => $verified,
        ]);

        if ($ward1) {
            UserWardAssignment::firstOrCreate(
                ['user_id' => $enumerator->id, 'ward_id' => $ward1->id],
                ['assigned_at' => now(), 'assigned_by' => null]
            );
        }

        $this->command->info('Test users created:');
        $this->command->table(['Email', 'Role', 'Password'], [
            ['superadmin@test.com', 'super_admin',      'password'],
            ['admin@test.com',      'municipal_admin',  'password'],
            ['reviewer@test.com',   'ward_reviewer',    'password (wards 1,2,3)'],
            ['enumerator@test.com', 'enumerator',       'password (ward 1)'],
        ]);
    }
}
