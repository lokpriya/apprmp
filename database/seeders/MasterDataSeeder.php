<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\LocalGovernment;
use App\Models\Province;
use App\Models\Settlement;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Province 1 — Koshi
        $p1 = Province::firstOrCreate(['code' => 'P1'], ['name_np' => 'कोशी प्रदेश', 'name_en' => 'Koshi Province', 'code' => 'P1']);

        // Sample District
        $dist = District::updateOrCreate(['code' => 'ILA'], [
            'province_id' => $p1->id, 'name_np' => 'इलाम', 'name_en' => 'Ilam', 'code' => 'ILA',
        ]);

        // Sample Local Government
        $lg = LocalGovernment::updateOrCreate(['code' => 'ILA-MUN-01'], [
            'district_id'    => $dist->id,
            'name_np'        => 'इलाम नगरपालिका',
            'name_en'        => 'Ilam Municipality',
            'type'           => 'municipality',
            'code'           => 'ILA-MUN-01',
            'ward_count'     => 10,
            'contact_email'  => 'info@ilammunicipality.gov.np',
            'is_active'      => true,
        ]);

        // Create 10 wards
        for ($w = 1; $w <= 10; $w++) {
            $ward = Ward::firstOrCreate(
                ['local_government_id' => $lg->id, 'ward_number' => $w],
                ['local_government_id' => $lg->id, 'ward_number' => $w]
            );

            // Create 2 sample settlements per ward
            Settlement::firstOrCreate(
                ['ward_id' => $ward->id, 'name_np' => "वडा {$w} - टोल १"],
                ['ward_id' => $ward->id, 'name_np' => "वडा {$w} - टोल १", 'name_en' => "Ward {$w} Tole 1", 'settlement_type' => 'tole']
            );
            Settlement::firstOrCreate(
                ['ward_id' => $ward->id, 'name_np' => "वडा {$w} - बस्ती १"],
                ['ward_id' => $ward->id, 'name_np' => "वडा {$w} - बस्ती १", 'name_en' => "Ward {$w} Basti 1", 'settlement_type' => 'basti']
            );
        }

        $this->command->info("Master data seeded: 1 Province, 1 District, 1 Municipality, 10 Wards, 20 Settlements");
    }
}
