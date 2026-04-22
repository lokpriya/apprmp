<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

class DisasterOptionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'death_cause' => [
                ['value' => 'accident',          'label_ne' => 'दुर्घटना',                  'label_en' => 'Accident'],
                ['value' => 'old_age',            'label_ne' => 'बृद्ध अवस्था',              'label_en' => 'Old Age'],
                ['value' => 'natural_disaster',   'label_ne' => 'प्राकृतिक प्रकोप',          'label_en' => 'Natural Disaster'],
                ['value' => 'wildlife_attack',    'label_ne' => 'वन्यजन्तुको आक्रमण',        'label_en' => 'Wildlife Attack'],
                ['value' => 'chronic_illness',    'label_ne' => 'दिर्घ रोग',                 'label_en' => 'Chronic Illness'],
                ['value' => 'foreign_employment', 'label_ne' => 'बैदेशिक रोजगारीको क्रममा', 'label_en' => 'During Foreign Employment'],
                ['value' => 'snake_bite',         'label_ne' => 'सर्पदंश',                   'label_en' => 'Snake Bite'],
                ['value' => 'other',              'label_ne' => 'अन्य',                      'label_en' => 'Other'],
            ],
            'disaster_property_loss' => [
                ['value' => 'house',       'label_ne' => 'घर',          'label_en' => 'House'],
                ['value' => 'farmland',    'label_ne' => 'खेतबारी',      'label_en' => 'Farmland'],
                ['value' => 'crops',       'label_ne' => 'बाली',         'label_en' => 'Crops'],
                ['value' => 'livestock',   'label_ne' => 'पशु पन्छी',    'label_en' => 'Livestock'],
                ['value' => 'utensils',    'label_ne' => 'भाडाकुडा',     'label_en' => 'Utensils'],
                ['value' => 'jewelry',     'label_ne' => 'गरगहना',       'label_en' => 'Jewelry'],
                ['value' => 'other',       'label_ne' => 'अन्य',         'label_en' => 'Other'],
            ],
            'relief_type' => [
                ['value' => 'clothing',    'label_ne' => 'लत्ता कपडा',   'label_en' => 'Clothing'],
                ['value' => 'food',        'label_ne' => 'खाद्यान्न',    'label_en' => 'Food Grains'],
                ['value' => 'house_repair','label_ne' => 'घर मर्मत',     'label_en' => 'House Repair'],
                ['value' => 'house_build', 'label_ne' => 'घर निर्माण',   'label_en' => 'House Construction'],
                ['value' => 'education',   'label_ne' => 'शैक्षिक सामग्री','label_en' => 'Educational Materials'],
                ['value' => 'livestock',   'label_ne' => 'पशु बस्तु',    'label_en' => 'Livestock'],
                ['value' => 'cash',        'label_ne' => 'नगद',          'label_en' => 'Cash'],
            ],
            'relief_unit' => [
                ['value' => 'set',    'label_ne' => 'सेट',     'label_en' => 'Set'],
                ['value' => 'kg',     'label_ne' => 'केजी',    'label_en' => 'KG'],
                ['value' => 'rupee',  'label_ne' => 'रुपयाँ',  'label_en' => 'Rupees'],
                ['value' => 'count',  'label_ne' => 'संख्या',  'label_en' => 'Count'],
                ['value' => 'piece',  'label_ne' => 'थान',     'label_en' => 'Piece'],
                ['value' => 'litre',  'label_ne' => 'लिटर',    'label_en' => 'Litre'],
            ],
            'relief_source' => [
                ['value' => 'local_government', 'label_ne' => 'स्थानीयपालिका',              'label_en' => 'Local Government'],
                ['value' => 'red_cross',        'label_ne' => 'रेडक्रस',                    'label_en' => 'Red Cross'],
                ['value' => 'local_club',       'label_ne' => 'स्थानीय क्लब',              'label_en' => 'Local Club'],
                ['value' => 'district_relief',  'label_ne' => 'जिल्ला राहत कोष',           'label_en' => 'District Relief Fund'],
                ['value' => 'local_relief',     'label_ne' => 'स्थानीय राहत कोष',          'label_en' => 'Local Relief Fund'],
                ['value' => 'ngo',              'label_ne' => 'गैर सरकारी संस्था',         'label_en' => 'NGO'],
                ['value' => 'ingo',             'label_ne' => 'अन्तराष्ट्रिय गैर सरकारी संस्था', 'label_en' => 'INGO'],
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
