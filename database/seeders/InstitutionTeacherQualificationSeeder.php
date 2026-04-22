<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionTeacherQualificationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $options = [
            ['slc_see',        'एसएलसी / एसइइ',           'SLC / SEE'],
            ['plus_two',       'प्लस टु / प्रमाणपत्र',     'Plus Two / Certificate'],
            ['bachelor',       'स्नातक',                    'Bachelor'],
            ['master',         'स्नातकोत्तर',               'Master'],
            ['mphil',          'एम.फिल.',                   'M.Phil.'],
            ['phd',            'पीएचडी',                    'PhD'],
            ['professor',      'प्रोफेसर',                  'Professor'],
        ];

        foreach ($options as $i => [$value, $labelNe, $labelEn]) {
            DB::table('survey_options')->updateOrInsert(
                [
                    'category'            => 'inst_teacher_qualification',
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

        $this->command->info('InstitutionTeacherQualificationSeeder: ' . count($options) . ' options seeded.');
    }
}
