<?php

namespace Database\Seeders;

use App\Models\InstitutionType;
use Illuminate\Database\Seeder;

class InstitutionTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name_np' => 'विद्यालय',              'name_en' => 'School',                'slug' => 'school',            'icon' => 'school',    'has_education' => true,  'has_health' => false, 'sort_order' => 1],
            ['name_np' => 'स्वास्थ्य चौकी/अस्पताल', 'name_en' => 'Health Post/Hospital', 'slug' => 'health_post',       'icon' => 'hospital',  'has_education' => false, 'has_health' => true,  'sort_order' => 2],
            ['name_np' => 'बैंक/वित्तीय संस्था',   'name_en' => 'Bank/Financial Inst.',  'slug' => 'bank',              'icon' => 'bank',      'has_education' => false, 'has_health' => false, 'sort_order' => 3],
            ['name_np' => 'सहकारी',                'name_en' => 'Cooperative',           'slug' => 'cooperative',       'icon' => 'coop',      'has_education' => false, 'has_health' => false, 'sort_order' => 4],
            ['name_np' => 'सरकारी कार्यालय',        'name_en' => 'Govt. Office',          'slug' => 'govt_office',       'icon' => 'office',    'has_education' => false, 'has_health' => false, 'sort_order' => 5],
            ['name_np' => 'गैसस/एनजीओ/सीबीओ',     'name_en' => 'NGO/CBO',               'slug' => 'ngo_cbo',           'icon' => 'ngo',       'has_education' => false, 'has_health' => false, 'sort_order' => 6],
            ['name_np' => 'धार्मिक स्थल',           'name_en' => 'Religious Institution', 'slug' => 'religious',         'icon' => 'temple',    'has_education' => false, 'has_health' => false, 'sort_order' => 7],
            ['name_np' => 'बजार/बाणिज्य केन्द्र',   'name_en' => 'Market/Trade Center',  'slug' => 'market',            'icon' => 'market',    'has_education' => false, 'has_health' => false, 'sort_order' => 8],
            ['name_np' => 'उद्योग/कलकारखाना',       'name_en' => 'Industry/Factory',      'slug' => 'industry',          'icon' => 'industry',  'has_education' => false, 'has_health' => false, 'sort_order' => 9],
            ['name_np' => 'सामुदायिक भवन',          'name_en' => 'Community Building',    'slug' => 'community_infra',   'icon' => 'building',  'has_education' => false, 'has_health' => false, 'sort_order' => 10],
            ['name_np' => 'उद्यान/पर्यटन स्थल',     'name_en' => 'Park/Tourism Site',     'slug' => 'park_tourism',      'icon' => 'park',      'has_education' => false, 'has_health' => false, 'sort_order' => 11],
            ['name_np' => 'मिडिया/सञ्चार',          'name_en' => 'Media/Communication',   'slug' => 'media',             'icon' => 'media',     'has_education' => false, 'has_health' => false, 'sort_order' => 12],
            ['name_np' => 'वन उपभोक्ता समूह',       'name_en' => 'Forest User Group',     'slug' => 'forest_group',      'icon' => 'forest',    'has_education' => false, 'has_health' => false, 'sort_order' => 13],
            ['name_np' => 'खेल संस्था/क्लब',         'name_en' => 'Sports Club',           'slug' => 'sports_club',       'icon' => 'sports',    'has_education' => false, 'has_health' => false, 'sort_order' => 14],
            ['name_np' => 'अन्य',                   'name_en' => 'Other',                 'slug' => 'other',             'icon' => 'other',     'has_education' => false, 'has_health' => false, 'sort_order' => 99],
        ];

        foreach ($types as $type) {
            InstitutionType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
