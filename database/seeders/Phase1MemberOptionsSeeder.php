<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

/**
 * Phase 1: Re-seed member survey options to match XLS (Aamchowk_Profile_2082_V3) exactly.
 * Replaces old lowercase/snake_case values with XLS PascalCase values.
 */
class Phase1MemberOptionsSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. DELETE OLD CATEGORIES that will be replaced ──────────────────
        // Also clear any XLS-named duplicates from previous seeds
        $toDelete = [
            'education_level',   // old values (lowercase) — re-seeded with XLS values
            'education_status',  // old values: in_school → GoingSchool etc.
            'marital_status',    // old values (lowercase) — re-seeded with XLS values
            'school_performance',// old values: excellent → VeryGood etc.
            'school_type',       // old values: government → Government etc.
            'disability_card_color', // old values: red → Red etc.
            'eduqualification',  // clear any XLS-named dups from earlier seeds
            'maritalstatus',
            'schoolingstatus',
            'schooltype',
        ];
        SurveyOption::whereIn('category', $toDelete)->delete();

        // ── 2. SEED NEW CATEGORIES ───────────────────────────────────────────
        // Category keys match DB column names (not XLS list names) for consistency.
        $groups = [

            // XLS list: eduqualification → DB column: education_level (12 options)
            'education_level' => [
                ['value' => 'Illitrate',        'label_ne' => 'निरक्षर (पढ्न लेख्न नसक्ने)',                     'label_en' => 'Illiterate'],
                ['value' => 'Literate',         'label_ne' => 'साक्षर (पढ्न लेख्न सक्ने)',                      'label_en' => 'Literate'],
                ['value' => 'BasicEducation1_5','label_ne' => 'आधारभूत तह (कक्षा १ देखि ५ सम्म पढेको)',        'label_en' => 'Basic Education 1-5'],
                ['value' => 'BasicEducation6_8','label_ne' => 'आधारभूत तह (कक्षा ६ देखि ८ सम्म पढेको)',        'label_en' => 'Basic Education 6-8'],
                ['value' => 'SecondarySchool',  'label_ne' => 'माध्यमिक तहको शिक्षा (कक्षा १० सम्म)',           'label_en' => 'Secondary School'],
                ['value' => 'SLC',              'label_ne' => 'एस.एल.सी.',                                       'label_en' => 'SLC'],
                ['value' => 'Intermediate',     'label_ne' => 'प्रमाणपत्र तह (+२, आइए, आइकम सो सरह)',           'label_en' => 'Intermediate (+2)'],
                ['value' => 'Bachelor',         'label_ne' => 'स्नातक तह (ब्याचलर)',                             'label_en' => 'Bachelor'],
                ['value' => 'Master',           'label_ne' => 'स्नातकोतर (मास्टर डिग्री)',                       'label_en' => 'Master'],
                ['value' => 'MPhil',            'label_ne' => 'एमफिल',                                           'label_en' => 'MPhil'],
                ['value' => 'PhD',              'label_ne' => 'पी.एच.डी',                                        'label_en' => 'PhD'],
                ['value' => 'other',            'label_ne' => 'अन्य',                                            'label_en' => 'Other'],
            ],

            // XLS list: education_status (5 options, filtered by edu_groups)
            // edu_groups: illiterate+other → NotApplicable,NotAdmitted,Stopped
            //             school           → GoingSchool,Stopped
            //             higher           → RegularHigherEducation,Stopped
            // DB column: education_status (same key)
            'education_status' => [
                ['value' => 'NotApplicable',          'label_ne' => 'निरक्षर भएको हुँदा लागु नहुने',                  'label_en' => 'Not Applicable (Illiterate)'],
                ['value' => 'NotAdmitted',            'label_ne' => 'कुनै पनि शैक्षिक केन्द्रमा भर्ना नभएको',         'label_en' => 'Not Admitted in Any Center'],
                ['value' => 'GoingSchool',            'label_ne' => 'बिधालय गइरहेका छन्',                               'label_en' => 'Going to School'],
                ['value' => 'RegularHigherEducation', 'label_ne' => 'कलेज तथा बिश्व बिद्यालयको नियमित अध्ययन',         'label_en' => 'Regular at Higher Education'],
                ['value' => 'Stopped',                'label_ne' => 'पुरा गरेको/छोडेको',                                'label_en' => 'Completed or Stopped Studying'],
            ],

            // XLS list: maritalstatus → DB column: marital_status
            'marital_status' => [
                ['value' => 'Single',         'label_ne' => 'अबिबाहित',                    'label_en' => 'Single'],
                ['value' => 'SingleMarried',  'label_ne' => 'एकल विवाह',                   'label_en' => 'Single Married'],
                ['value' => 'MultiMarried',   'label_ne' => 'बहु विवाह',                   'label_en' => 'Multi Married'],
                ['value' => 'ReMarried',      'label_ne' => 'पुनः विवाह',                  'label_en' => 'Re-Married'],
                ['value' => 'Widow',          'label_ne' => 'विधुवा/बिदुर',                'label_en' => 'Widow/Widower'],
                ['value' => 'Divorced',       'label_ne' => 'सम्बन्ध विच्छेद',             'label_en' => 'Divorced'],
                ['value' => 'Seperated',      'label_ne' => 'विवाहित तर अलग बसेको',        'label_en' => 'Separated'],
            ],

            // XLS list: schoolingstatus → DB column: school_performance
            'school_performance' => [
                ['value' => 'VeryGood', 'label_ne' => 'उतम',       'label_en' => 'Very Good'],
                ['value' => 'Good',     'label_ne' => 'राम्रो',     'label_en' => 'Good'],
                ['value' => 'Normal',   'label_ne' => 'सामान्य',   'label_en' => 'Normal'],
                ['value' => 'Poor',     'label_ne' => 'कम',         'label_en' => 'Poor'],
                ['value' => 'VeryPoor', 'label_ne' => 'ज्यादै कम', 'label_en' => 'Very Poor'],
            ],

            // XLS list: schooltype → DB column: school_type
            'school_type' => [
                ['value' => 'Government', 'label_ne' => 'सरकारी',    'label_en' => 'Government'],
                ['value' => 'Private',    'label_ne' => 'निजी',      'label_en' => 'Private'],
                ['value' => 'Community',  'label_ne' => 'सामुदायिक', 'label_en' => 'Community'],
                ['value' => 'Religious',  'label_ne' => 'धार्मिक',   'label_en' => 'Religious'],
                ['value' => 'Gurukul',    'label_ne' => 'गुरुकुल',   'label_en' => 'Gurukul'],
                ['value' => 'Madrasa',    'label_ne' => 'मदरसा',     'label_en' => 'Madrasa'],
                ['value' => 'other',      'label_ne' => 'अन्य',      'label_en' => 'Other'],
            ],

            // Disability card color (XLS: disabletypeid — Red/Blue/Yellow/White)
            'disability_card_color' => [
                ['value' => 'Red',    'label_ne' => 'रातो',   'label_en' => 'Red'],
                ['value' => 'Blue',   'label_ne' => 'निलो',   'label_en' => 'Blue'],
                ['value' => 'Yellow', 'label_ne' => 'पहेलो',  'label_en' => 'Yellow'],
                ['value' => 'White',  'label_ne' => 'सेतो',   'label_en' => 'White'],
            ],

            // School/institution location (derived from addrslocation)
            'institutionlocation' => [
                ['value' => 'inside_municipality',  'label_ne' => 'यसै गाउँपालिका/नगरपालिका भित्र',   'label_en' => 'Inside This Municipality'],
                ['value' => 'outside_municipality', 'label_ne' => 'अन्य गाउँपालिका/नगरपालिका',         'label_en' => 'Other Municipality'],
                ['value' => 'outside_district',     'label_ne' => 'अन्य जिल्ला',                        'label_en' => 'Other District'],
                ['value' => 'other',                'label_ne' => 'अन्य',                                'label_en' => 'Other'],
            ],

            // Cause not in school (9 options from XLS cause_not_school)
            'cause_not_school' => [
                ['value' => 'NoAgeforSchool',         'label_ne' => 'बिद्यालय भर्ना गर्ने उमेर नपुगेर',            'label_en' => 'Not at School Age Yet'],
                ['value' => 'NoMoreFurtherStudy',     'label_ne' => 'त्यहाँ भन्दा माथि अध्ययन गर्न इच्छा नलागेर', 'label_en' => 'No Interest in Further Study'],
                ['value' => 'Remoteness',             'label_ne' => 'विद्यालय टाढा',                               'label_en' => 'School is Remote'],
                ['value' => 'LackofAwareness',        'label_ne' => 'चेतनाको कमि',                                 'label_en' => 'Lack of Awareness'],
                ['value' => 'Disability',             'label_ne' => 'अपांगता/असक्षमता',                            'label_en' => 'Disability'],
                ['value' => 'SupportingHouseholdWork','label_ne' => 'घरको काममा सहयोग',                            'label_en' => 'Supporting Household Work'],
                ['value' => 'FinancialProblem',       'label_ne' => 'आर्थिक समस्या',                               'label_en' => 'Financial Problem'],
                ['value' => 'HealthProblems',         'label_ne' => 'स्वास्थ्य को समस्या',                         'label_en' => 'Health Problems'],
                ['value' => 'EarlyChildMarriage',     'label_ne' => 'बाल बिबाह',                                   'label_en' => 'Early Child Marriage'],
            ],

            // Frequency of smoking/drinking (6 options)
            'frequency_smoking_drinking' => [
                ['value' => 'Daily',            'label_ne' => 'दैनिक',                      'label_en' => 'Daily'],
                ['value' => 'Sometimes',        'label_ne' => 'कहिलेकाही',                  'label_en' => 'Sometimes'],
                ['value' => 'Ritual_Occasion',  'label_ne' => 'सामाजिक रितिरिवाजहरुमा',    'label_en' => 'Ritual/Occasion'],
                ['value' => 'Festivals',        'label_ne' => 'चाडपर्वहरुमा',               'label_en' => 'Festivals'],
                ['value' => 'Social_Gathering', 'label_ne' => 'अन्य साथीभाई जमघटमा',       'label_en' => 'Social Gathering'],
                ['value' => 'Party',            'label_ne' => 'पार्टीहरुमा',                'label_en' => 'Party'],
            ],

            // Smoking/drinking items (common items — not in XLS choices export, using standard list)
            'smoking_drinking_items' => [
                ['value' => 'Cigarette',  'label_ne' => 'सिगरेट',    'label_en' => 'Cigarette'],
                ['value' => 'Tobacco',    'label_ne' => 'तम्बाकु',    'label_en' => 'Tobacco'],
                ['value' => 'Gutkha',     'label_ne' => 'गुड्खा',    'label_en' => 'Gutkha'],
                ['value' => 'Khaini',     'label_ne' => 'खैनी',       'label_en' => 'Khaini'],
                ['value' => 'LocalAlcohol','label_ne' => 'जाड/रक्सी', 'label_en' => 'Local Alcohol (Jad/Raksi)'],
                ['value' => 'ForeignAlcohol','label_ne' => 'विदेशी रक्सी', 'label_en' => 'Foreign Alcohol'],
                ['value' => 'Beer',       'label_ne' => 'बियर',       'label_en' => 'Beer'],
                ['value' => 'other',      'label_ne' => 'अन्य',       'label_en' => 'Other'],
            ],

            // Drivers license category (20 options from XLS)
            'drivers_license_category' => [
                ['value' => 'A',  'label_ne' => 'वर्ग A — मोटरसाइकल, स्कुटर र मोपेड',               'label_en' => 'Class A — Motorcycle, Scooter & Moped'],
                ['value' => 'B',  'label_ne' => 'वर्ग B — कार, जिप र डेलिभरी भ्यान',                'label_en' => 'Class B — Car, Jeep & Delivery Van'],
                ['value' => 'K',  'label_ne' => 'वर्ग K — स्कुटर र मोपेड मात्र',                    'label_en' => 'Class K — Scooter & Moped Only'],
                ['value' => 'F',  'label_ne' => 'वर्ग F — दमकल',                                     'label_en' => 'Class F — Fire Engine'],
                ['value' => 'G',  'label_ne' => 'वर्ग G — ट्रयाक्टर र पावर टिलर',                   'label_en' => 'Class G — Tractor & Power Tiller'],
                ['value' => 'H',  'label_ne' => 'वर्ग H — ई-रिक्सा',                                 'label_en' => 'Class H — E-Rickshaw'],
                ['value' => 'BE', 'label_ne' => 'वर्ग BE — ७५० केजीभन्दा बढी तौलका ट्रेलर',         'label_en' => 'Class BE — Trailer >750kg'],
                ['value' => 'C',  'label_ne' => 'वर्ग C — ३.५ टनभन्दा बढी मालवाहक (N2)',             'label_en' => 'Class C — Goods Vehicle >3.5T (N2)'],
                ['value' => 'C1', 'label_ne' => 'वर्ग C1 — ७.५ टनसम्मका मालवाहक',                   'label_en' => 'Class C1 — Goods Vehicle up to 7.5T'],
                ['value' => 'D',  'label_ne' => 'वर्ग D — ८ भन्दा बढी सिट यात्रुवाहक (M2,M3)',      'label_en' => 'Class D — Passenger Vehicle >8 Seats'],
                ['value' => 'D1', 'label_ne' => 'वर्ग D1 — ९ देखि १६ सिटसम्मका यात्रुवाहक',         'label_en' => 'Class D1 — Passenger Vehicle 9–16 Seats'],
                ['value' => 'I1', 'label_ne' => 'I1 — क्रेन',                                        'label_en' => 'I1 — Crane'],
                ['value' => 'I2', 'label_ne' => 'I2 — डोजर',                                         'label_en' => 'I2 — Dozer'],
                ['value' => 'I3', 'label_ne' => 'I3 — रोड रोलर',                                     'label_en' => 'I3 — Road Roller'],
                ['value' => 'I4', 'label_ne' => 'I4 — लोडर',                                         'label_en' => 'I4 — Loader'],
                ['value' => 'I5', 'label_ne' => 'I5 — एक्जाभेटर',                                    'label_en' => 'I5 — Excavator'],
                ['value' => 'I6', 'label_ne' => 'I6 — ब्याकहो लोडर',                                 'label_en' => 'I6 — Backhoe Loader'],
                ['value' => 'I7', 'label_ne' => 'I7 — ग्रेडर',                                       'label_en' => 'I7 — Grader'],
                ['value' => 'I8', 'label_ne' => 'I8 — फोर्कलिफ्ट',                                  'label_en' => 'I8 — Forklift'],
                ['value' => 'I9', 'label_ne' => 'I9 — अन्य विशेष सवारीहरू',                          'label_en' => 'I9 — Other Special Vehicles'],
            ],

            // blood_group — keep existing values (standard; not changed)
            // Already seeded by MemberEducationOptionSeeder — skip unless you want to refresh
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

        $this->command->info('Phase 1 member options seeded successfully.');
    }
}
