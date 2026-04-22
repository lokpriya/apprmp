<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

/**
 * Phase 2: Child health & development survey options.
 * Covers age 0-3, 4-5, and 6-15 XLS groups.
 */
class Phase2ChildOptionsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [

            // ── Age 0-3: Place of birth ────────────────────────────────────
            'childbornplace' => [
                ['value' => 'Hospital',     'label_ne' => 'अस्पताल',                    'label_en' => 'Hospital'],
                ['value' => 'HealthPost',   'label_ne' => 'स्वास्थ्य चौकी',              'label_en' => 'Health Post'],
                ['value' => 'PHC',          'label_ne' => 'प्राथमिक स्वास्थ्य केन्द्र', 'label_en' => 'Primary Health Centre (PHC)'],
                ['value' => 'Home',         'label_ne' => 'घरमा',                        'label_en' => 'Home'],
                ['value' => 'InTransit',    'label_ne' => 'बाटोमा',                      'label_en' => 'In Transit'],
                ['value' => 'other',        'label_ne' => 'अन्य',                        'label_en' => 'Other'],
            ],

            // ── Age 0-3: Vaccines received (multi-select) ──────────────────
            'childvaccine' => [
                ['value' => 'BCG',              'label_ne' => 'बिसिजी (BCG)',                          'label_en' => 'BCG'],
                ['value' => 'OPV1',             'label_ne' => 'ओपिभि पहिलो मात्रा (OPV1)',            'label_en' => 'OPV 1st Dose'],
                ['value' => 'OPV2',             'label_ne' => 'ओपिभि दोस्रो मात्रा (OPV2)',           'label_en' => 'OPV 2nd Dose'],
                ['value' => 'OPV3',             'label_ne' => 'ओपिभि तेस्रो मात्रा (OPV3)',           'label_en' => 'OPV 3rd Dose'],
                ['value' => 'Pentavalent1',     'label_ne' => 'पेन्टाभ्यालेन्ट पहिलो (DPT-HepB-Hib 1)', 'label_en' => 'Pentavalent 1st Dose'],
                ['value' => 'Pentavalent2',     'label_ne' => 'पेन्टाभ्यालेन्ट दोस्रो (DPT-HepB-Hib 2)', 'label_en' => 'Pentavalent 2nd Dose'],
                ['value' => 'Pentavalent3',     'label_ne' => 'पेन्टाभ्यालेन्ट तेस्रो (DPT-HepB-Hib 3)', 'label_en' => 'Pentavalent 3rd Dose'],
                ['value' => 'PCV1',             'label_ne' => 'पिसिभि पहिलो मात्रा (PCV1)',           'label_en' => 'PCV 1st Dose'],
                ['value' => 'PCV2',             'label_ne' => 'पिसिभि दोस्रो मात्रा (PCV2)',          'label_en' => 'PCV 2nd Dose'],
                ['value' => 'PCV3',             'label_ne' => 'पिसिभि तेस्रो मात्रा (PCV3)',          'label_en' => 'PCV 3rd Dose'],
                ['value' => 'Measles_MR1',      'label_ne' => 'दादुरा-रुबेला पहिलो मात्रा (MR1)',    'label_en' => 'Measles-Rubella 1st Dose (MR1)'],
                ['value' => 'Measles_MR2',      'label_ne' => 'दादुरा-रुबेला दोस्रो मात्रा (MR2)',   'label_en' => 'Measles-Rubella 2nd Dose (MR2)'],
                ['value' => 'JE',               'label_ne' => 'जापानिज इन्सेफलाइटिस (JE)',           'label_en' => 'Japanese Encephalitis (JE)'],
                ['value' => 'Typhoid',          'label_ne' => 'टाइफाइड',                              'label_en' => 'Typhoid'],
                ['value' => 'FullVaccination',  'label_ne' => 'सम्पूर्ण खोप लगाइएको',               'label_en' => 'Fully Vaccinated'],
                ['value' => 'NotVaccinated',    'label_ne' => 'खोप लगाइएको छैन',                     'label_en' => 'Not Vaccinated'],
                ['value' => 'other',            'label_ne' => 'अन्य',                                 'label_en' => 'Other'],
            ],

            // ── Age 0-3: Primary caregiver ─────────────────────────────────
            'caregiver' => [
                ['value' => 'mother',       'label_ne' => 'आमा',                    'label_en' => 'Mother'],
                ['value' => 'father',       'label_ne' => 'बाबु',                   'label_en' => 'Father'],
                ['value' => 'grandparents', 'label_ne' => 'हजुरबुबा-हजुरआमा',       'label_en' => 'Grandparents'],
                ['value' => 'other',        'label_ne' => 'अन्य',                   'label_en' => 'Other'],
            ],

            // ── Age 4-5 & 6-15: Physical development ─────────────────────
            'childphydev' => [
                ['value' => 'Normal',        'label_ne' => 'सामान्य',               'label_en' => 'Normal'],
                ['value' => 'Underweight',   'label_ne' => 'कम तौल (अल्पपोषण)',    'label_en' => 'Underweight (Malnourished)'],
                ['value' => 'Overweight',    'label_ne' => 'अधिक तौल',             'label_en' => 'Overweight'],
                ['value' => 'Stunted',       'label_ne' => 'उचाइमा कमजोर',         'label_en' => 'Stunted'],
                ['value' => 'Wasted',        'label_ne' => 'तौलमा गम्भीर कमी',     'label_en' => 'Wasted (Severely Malnourished)'],
                ['value' => 'other',         'label_ne' => 'अन्य',                  'label_en' => 'Other'],
            ],

            // ── Age 4-5 & 6-15: Language development ─────────────────────
            'childlgdev' => [
                ['value' => 'Normal',          'label_ne' => 'सामान्य विकास',       'label_en' => 'Normal Development'],
                ['value' => 'SlightlyDelayed', 'label_ne' => 'थोरै ढिलाइ',         'label_en' => 'Slightly Delayed'],
                ['value' => 'Delayed',         'label_ne' => 'ढिलाइ',               'label_en' => 'Delayed'],
                ['value' => 'SeverelyDelayed', 'label_ne' => 'गम्भीर ढिलाइ',       'label_en' => 'Severely Delayed'],
                ['value' => 'other',           'label_ne' => 'अन्य',                'label_en' => 'Other'],
            ],

            // ── Age 4-5: Socialization ────────────────────────────────────
            'childsocialization' => [
                ['value' => 'Good',      'label_ne' => 'राम्रो',   'label_en' => 'Good'],
                ['value' => 'Normal',    'label_ne' => 'सामान्य', 'label_en' => 'Normal'],
                ['value' => 'Poor',      'label_ne' => 'कमजोर',   'label_en' => 'Poor'],
                ['value' => 'other',     'label_ne' => 'अन्य',    'label_en' => 'Other'],
            ],

            // ── Age 6-15: School interest ─────────────────────────────────
            // XLS list: schoolinterest (same scale as schoolingstatus but for interest)
            'schoolinterest' => [
                ['value' => 'Good',     'label_ne' => 'राम्रो',     'label_en' => 'Good'],
                ['value' => 'Normal',   'label_ne' => 'सामान्य',   'label_en' => 'Normal'],
                ['value' => 'Poor',     'label_ne' => 'कम',         'label_en' => 'Poor'],
                ['value' => 'VeryPoor', 'label_ne' => 'ज्यादै कम', 'label_en' => 'Very Poor'],
            ],

            // ── Age 6-15: Interesting subject ─────────────────────────────
            'interestingsubject' => [
                ['value' => 'Nepali',      'label_ne' => 'नेपाली',                      'label_en' => 'Nepali'],
                ['value' => 'English',     'label_ne' => 'अंग्रेजी',                    'label_en' => 'English'],
                ['value' => 'Mathematics', 'label_ne' => 'गणित',                        'label_en' => 'Mathematics'],
                ['value' => 'Science',     'label_ne' => 'बिज्ञान',                     'label_en' => 'Science'],
                ['value' => 'Art',         'label_ne' => 'कला साहित्य (सामाजिक बिषय)', 'label_en' => 'Art/Social Studies'],
                ['value' => 'Other',       'label_ne' => 'अन्य बिषय',                  'label_en' => 'Other Subject'],
                ['value' => 'Not_Known',   'label_ne' => 'थाहा छैन',                    'label_en' => 'Not Known'],
            ],

            // ── Age 6-15: Homework support ────────────────────────────────
            'hwsupport' => [
                ['value' => 'Self',          'label_ne' => 'आफै',               'label_en' => 'Self'],
                ['value' => 'BrotherSister', 'label_ne' => 'दाजु दिदी',         'label_en' => 'Brother/Sister'],
                ['value' => 'Parent',        'label_ne' => 'अभिभावक',           'label_en' => 'Parent/Guardian'],
                ['value' => 'None',          'label_ne' => 'कसैले गर्दैन',      'label_en' => 'No Support'],
            ],

            // ── Age 6-15: Digital/screen time habit ───────────────────────
            'digital_habit' => [
                ['value' => 'LessthanOneHour', 'label_ne' => 'दिनमा १ घण्टा भन्दा कम',     'label_en' => 'Less than 1 Hour/Day'],
                ['value' => 'TwotoThreeHours', 'label_ne' => 'दिनमा २–३ घण्टासम्म',         'label_en' => '2–3 Hours/Day'],
                ['value' => 'ThreeHourse+',    'label_ne' => 'दिनमा ३ घण्टाभन्दा बढी',      'label_en' => 'More than 3 Hours/Day'],
                ['value' => 'Not_Known',       'label_ne' => 'थाहा छैन',                     'label_en' => 'Not Known'],
            ],

            // ── Age 6-15: Other interests / extracurricular ───────────────
            'other_interest' => [
                ['value' => 'Dance',             'label_ne' => 'नृत्य',                              'label_en' => 'Dance'],
                ['value' => 'Singing',           'label_ne' => 'गायन',                               'label_en' => 'Singing'],
                ['value' => 'Art',               'label_ne' => 'चित्रकला/हस्तकला',                  'label_en' => 'Art/Craft'],
                ['value' => 'MusicalInstrument', 'label_ne' => 'बाजा बजाउन (बाँसुरी, मादल आदि)',   'label_en' => 'Musical Instrument'],
                ['value' => 'Sports',            'label_ne' => 'खेलकुद',                            'label_en' => 'Sports'],
                ['value' => 'Not_Known',         'label_ne' => 'थाहा छैन',                          'label_en' => 'Not Known'],
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

        $this->command->info('Phase 2 child options seeded successfully.');
    }
}
