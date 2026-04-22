<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionTeacherSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $options = [
            ['nepali',          'नेपाली',                   'Nepali'],
            ['english',         'अंग्रेजी',                  'English'],
            ['math',            'गणित',                     'Mathematics'],
            ['science',         'विज्ञान',                   'Science'],
            ['social',          'सामाजिक',                  'Social Studies'],
            ['economics',       'अर्थशास्त्र',               'Economics'],
            ['law',             'कानुन',                    'Law'],
            ['agri_horticulture','कृषि तथा बागवानी',         'Agriculture & Horticulture'],
            ['civil',           'सिभिल',                    'Civil Engineering'],
            ['mechanical',      'मेकानिकल',                 'Mechanical Engineering'],
            ['computer',        'कम्प्युटर',                 'Computer Science'],
            ['electronics',     'इलेक्ट्रोनिक',              'Electronics'],
            ['politics',        'राजनीति',                  'Political Science'],
            ['language',        'भाषा',                     'Language'],
            ['literature_arts', 'साहित्य / कला',             'Literature & Arts'],
            ['history',         'इतिहास',                   'History'],
            ['anthropology',    'मानवशास्त्र',               'Anthropology'],
            ['finance',         'वित्तीय शास्त्र',           'Finance'],
        ];

        foreach ($options as $i => [$value, $labelNe, $labelEn]) {
            DB::table('survey_options')->updateOrInsert(
                [
                    'category'            => 'inst_teacher_subject',
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

        $this->command->info('InstitutionTeacherSubjectSeeder: ' . count($options) . ' subjects seeded.');
    }
}
