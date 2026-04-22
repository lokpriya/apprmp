<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionTeacherLevelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $options = [
            ['early_childhood', 'बालविकास तह',           'Early Childhood Level'],
            ['basic_1_5',       'आधारभूत तह १–५',         'Basic Level 1–5'],
            ['basic_6_8',       'आधारभूत तह ६–८',         'Basic Level 6–8'],
            ['secondary_9_10',  'माध्यमिक तह ९–१०',       'Secondary Level 9–10'],
            ['secondary_11_12', 'माध्यमिक तह ११–१२',      'Secondary Level 11–12'],
            ['bachelor',        'स्नातक तह',              'Bachelor Level'],
            ['master',          'स्नातकोत्तर तह',          'Master Level'],
        ];

        foreach ($options as $i => [$value, $labelNe, $labelEn]) {
            DB::table('survey_options')->updateOrInsert(
                [
                    'category'            => 'inst_teacher_level',
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

        $this->command->info('InstitutionTeacherLevelSeeder: ' . count($options) . ' levels seeded.');
    }
}
