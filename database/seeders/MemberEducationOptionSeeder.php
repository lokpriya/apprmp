<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

class MemberEducationOptionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'blood_group' => [
                ['value' => 'O-',  'label_ne' => 'ओ नेगेटिभ (O-)',   'label_en' => 'O Negative (O-)'],
                ['value' => 'O+',  'label_ne' => 'ओ पोजेटिभ (O+)',   'label_en' => 'O Positive (O+)'],
                ['value' => 'A-',  'label_ne' => 'ए नेगेटिभ (A-)',   'label_en' => 'A Negative (A-)'],
                ['value' => 'A+',  'label_ne' => 'ए पोजिटिभ (A+)',   'label_en' => 'A Positive (A+)'],
                ['value' => 'B-',  'label_ne' => 'बि नेगेटिभ (B-)',  'label_en' => 'B Negative (B-)'],
                ['value' => 'B+',  'label_ne' => 'बि पोजेटिभ (B+)',  'label_en' => 'B Positive (B+)'],
                ['value' => 'AB-', 'label_ne' => 'एबि नेगेटिभ (AB-)', 'label_en' => 'AB Negative (AB-)'],
                ['value' => 'AB+', 'label_ne' => 'एबि पोजिटिभ (AB+)', 'label_en' => 'AB Positive (AB+)'],
            ],
            'disability_card_color' => [
                ['value' => 'red',    'label_ne' => 'रातो',   'label_en' => 'Red'],
                ['value' => 'blue',   'label_ne' => 'निलो',   'label_en' => 'Blue'],
                ['value' => 'yellow', 'label_ne' => 'पहेलो',  'label_en' => 'Yellow'],
                ['value' => 'white',  'label_ne' => 'सेतो',   'label_en' => 'White'],
            ],
            'education_status' => [
                ['value' => 'not_applicable',  'label_ne' => 'निरक्षर भएको हुँदा लागु नहुने',           'label_en' => 'Not Applicable (Illiterate)'],
                ['value' => 'not_enrolled',    'label_ne' => 'कुनै पनि शैक्षिक केन्द्रमा भर्ना नभएको', 'label_en' => 'Not Enrolled in Any Institution'],
                ['value' => 'in_school',       'label_ne' => 'बिधालय गइरहेका छन्',                      'label_en' => 'Currently in School'],
                ['value' => 'in_college',      'label_ne' => 'कलेज तथा बिश्व बिद्यालयको नियमित अध्ययन', 'label_en' => 'Regular Study in College/University'],
                ['value' => 'completed_dropout','label_ne' => 'पुरा गरेको/छोडेको',                     'label_en' => 'Completed / Dropped Out'],
            ],
            'school_type' => [
                ['value' => 'government',  'label_ne' => 'सरकारी',    'label_en' => 'Government'],
                ['value' => 'private',     'label_ne' => 'निजी',      'label_en' => 'Private'],
                ['value' => 'community',   'label_ne' => 'सामुदायिक', 'label_en' => 'Community'],
                ['value' => 'religious',   'label_ne' => 'धार्मिक',   'label_en' => 'Religious'],
                ['value' => 'gurukul',     'label_ne' => 'गुरुकुल',   'label_en' => 'Gurukul'],
                ['value' => 'madrasa',     'label_ne' => 'मदरसा',     'label_en' => 'Madrasa'],
            ],
            'school_performance' => [
                ['value' => 'excellent', 'label_ne' => 'उतम',      'label_en' => 'Excellent'],
                ['value' => 'good',      'label_ne' => 'राम्रो',    'label_en' => 'Good'],
                ['value' => 'average',   'label_ne' => 'सामान्य',  'label_en' => 'Average'],
                ['value' => 'low',       'label_ne' => 'कम',        'label_en' => 'Low'],
                ['value' => 'very_low',  'label_ne' => 'ज्यादै कम', 'label_en' => 'Very Low'],
            ],
        ];

        foreach ($groups as $category => $options) {
            foreach ($options as $i => $opt) {
                SurveyOption::updateOrCreate(
                    ['category' => $category, 'value' => $opt['value']],
                    [
                        'label_ne'            => $opt['label_ne'],
                        'label_en'            => $opt['label_en'],
                        'sort_order'          => $i + 1,
                        'is_active'           => true,
                        'local_government_id' => null,
                    ]
                );
            }
        }
    }
}
