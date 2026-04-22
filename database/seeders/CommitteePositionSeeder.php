<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommitteePositionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $positions = [
            ['adhyaksha',          'अध्यक्ष/सभापति',                           'Chairperson/President'],
            ['barishtha_upadhyaksha', 'बरिष्ठ उपाध्यक्ष/बरिष्ठ उप-सभापति',   'Senior Vice-Chairperson'],
            ['upadhyaksha',        'उपाध्यक्ष/उप-सभापति',                      'Vice-Chairperson'],
            ['mahasachib',         'महासचिब',                                   'General Secretary'],
            ['sachib',             'सचिब',                                      'Secretary'],
            ['saha_sachib',        'सह-सचिब',                                   'Joint Secretary'],
            ['koshaadhyaksha',     'कोषाध्यक्ष',                               'Treasurer'],
            ['sadasya',            'सदश्य',                                     'Member'],
        ];

        foreach ($positions as $i => [$value, $labelNe, $labelEn]) {
            DB::table('survey_options')->updateOrInsert(
                [
                    'category'            => 'inst_committee_position',
                    'value'               => $value,
                    'local_government_id' => null,
                ],
                [
                    'label_ne'   => $labelNe,
                    'label_en'   => $labelEn,
                    'sort_order' => $i + 1,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command->info('CommitteePositionSeeder: ' . count($positions) . ' positions seeded.');
    }
}
