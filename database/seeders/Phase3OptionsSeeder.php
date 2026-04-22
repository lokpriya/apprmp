<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

/**
 * Phase 3: Training, address/abroad, and business survey options.
 */
class Phase3OptionsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [

            // ── Vocational training subjects (75 options from XLS VocatioalTraining) ──
            'vocational_training_subject' => [
                ['value' => 'Mason',                        'label_ne' => 'मेसन',                                    'label_en' => 'Mason'],
                ['value' => 'Scaffolding',                  'label_ne' => 'स्काफोल्डिङ्ग',                          'label_en' => 'Scaffolding'],
                ['value' => 'SutteringCarpentry',           'label_ne' => 'सटरिङ्ग कार्पेन्ट्री',                   'label_en' => 'Suttering Carpentry'],
                ['value' => 'Steelfixtures',                'label_ne' => 'स्टिल फिक्स्चर',                         'label_en' => 'Steel Fixtures'],
                ['value' => 'Animalhealthworker',           'label_ne' => 'पशु स्वास्थ्य कार्यकर्ता',               'label_en' => 'Animal Health Worker'],
                ['value' => 'Agriculturalworker',           'label_ne' => 'कृषि कार्यकर्ता',                        'label_en' => 'Agricultural Worker'],
                ['value' => 'Vegetablefarming',             'label_ne' => 'तरकारी खेती',                            'label_en' => 'Vegetable Farming'],
                ['value' => 'Cashcrops',                    'label_ne' => 'नगदे बाली',                              'label_en' => 'Cash Crops'],
                ['value' => 'Processingofagriculturalproducts', 'label_ne' => 'कृषिजन्य पदार्थको प्रशोधन',          'label_en' => 'Processing of Agricultural Products'],
                ['value' => 'Foodproduction',               'label_ne' => 'खाद्य सामाग्री उत्पादन',                'label_en' => 'Food Production'],
                ['value' => 'Mushroomcultivation',          'label_ne' => 'च्याउ खेती',                             'label_en' => 'Mushroom Cultivation'],
                ['value' => 'Beekeeping',                   'label_ne' => 'मौरी पालन',                              'label_en' => 'Beekeeping'],
                ['value' => 'Bangurfarming',                'label_ne' => 'बंगुर पालन',                             'label_en' => 'Pig Farming'],
                ['value' => 'Poultryfarming',               'label_ne' => 'कुखुरा पालन',                            'label_en' => 'Poultry Farming'],
                ['value' => 'Cultivationofherbs',           'label_ne' => 'जडिबुटी खेती',                           'label_en' => 'Cultivation of Herbs'],
                ['value' => 'HerbProcessing',               'label_ne' => 'जडिबुटी प्रशोधन',                        'label_en' => 'Herb Processing'],
                ['value' => 'Aloeprocessing',               'label_ne' => 'अल्लो प्रशोधन',                          'label_en' => 'Aloe Processing'],
                ['value' => 'Handmadepaper',                'label_ne' => 'हाते कागज निर्माण',                      'label_en' => 'Hand Made Paper'],
                ['value' => 'Plumbing',                     'label_ne' => 'प्लम्बिङ्ग',                             'label_en' => 'Plumbing'],
                ['value' => 'Electricalconnection',         'label_ne' => 'विद्युत जडान',                           'label_en' => 'Electrical Connection'],
                ['value' => 'Automechanics',                'label_ne' => 'अटोमेकानिक्स',                           'label_en' => 'Auto Mechanics'],
                ['value' => 'Motorcyclerepair',             'label_ne' => 'मोटरसाइकल मर्मत',                        'label_en' => 'Motorcycle Repair'],
                ['value' => 'Beautyparlour',                'label_ne' => 'ब्यूटी पार्लर',                          'label_en' => 'Beauty Parlour'],
                ['value' => 'Securityguard',                'label_ne' => 'सेक्युरिटी गार्ड',                       'label_en' => 'Security Guard'],
                ['value' => 'Driving',                      'label_ne' => 'ड्राइभिङ्ग',                             'label_en' => 'Driving'],
                ['value' => 'CareGiver',                    'label_ne' => 'केयर गिभर',                              'label_en' => 'Care Giver'],
                ['value' => 'GeneralMechanics',             'label_ne' => 'जनरल मेकानिक्स',                         'label_en' => 'General Mechanics'],
                ['value' => 'Welding',                      'label_ne' => 'वेल्डिङ्ग',                              'label_en' => 'Welding'],
                ['value' => 'Pumpsetmechanics',             'label_ne' => 'पम्पसेट मेकानिक्स',                      'label_en' => 'Pumpset Mechanics'],
                ['value' => 'ElectronicsRepair',            'label_ne' => 'इलेक्ट्रोनिक्स मर्मत (मोबाइल, टि.भि.)', 'label_en' => 'Electronics Repair (Mobile/TV)'],
                ['value' => 'ComputeRepair',                'label_ne' => 'कम्प्युटर/इन्भर्टर निर्माण/मर्मत',       'label_en' => 'Computer/Inverter Manufacturing & Repair'],
                ['value' => 'Secretary',                    'label_ne' => 'सेक्रेटरी',                              'label_en' => 'Secretary'],
                ['value' => 'Thangka',                      'label_ne' => 'थाङ्का',                                 'label_en' => 'Thangka'],
                ['value' => 'Sculpture',                    'label_ne' => 'मूर्तिकला',                              'label_en' => 'Sculpture'],
                ['value' => 'Bamboo',                       'label_ne' => 'वेतबाँस',                                'label_en' => 'Bamboo/Rattan'],
                ['value' => 'MithilaPainting',              'label_ne' => 'मिथिला पेन्टिङ्ग',                       'label_en' => 'Mithila Painting'],
                ['value' => 'WoodCarving',                  'label_ne' => 'वुड कार्भिङ्ग',                          'label_en' => 'Wood Carving'],
                ['value' => 'StoneCarving',                 'label_ne' => 'स्टोन कार्भिङ्ग',                        'label_en' => 'Stone Carving'],
                ['value' => 'Chhavaliart',                  'label_ne' => 'छवाली कला',                              'label_en' => 'Chhavali Art'],
                ['value' => 'Zaristuffingandpainting',      'label_ne' => 'कपडामा जरी भर्ने र पेण्टिङ्ग गर्ने',    'label_en' => 'Zari Stuffing & Painting on Cloth'],
                ['value' => 'Makinggarlandspouchespurses',  'label_ne' => 'माला, थैली, पर्स बनाउने',               'label_en' => 'Making Garlands, Pouches & Purses'],
                ['value' => 'Painting',                     'label_ne' => 'पेन्टिङ्ग',                              'label_en' => 'Painting'],
                ['value' => 'Photoframelamination',         'label_ne' => 'फोटो फ्रेम लेमिनेशन',                   'label_en' => 'Photo Frame Lamination'],
                ['value' => 'Grandchildmala',               'label_ne' => 'पोते माला',                              'label_en' => 'Pote Mala'],
                ['value' => 'Decorativeitems',              'label_ne' => 'सजावटका सामान उत्पादन',                 'label_en' => 'Decorative Items Production'],
                ['value' => 'Cook',                         'label_ne' => 'कुक',                                    'label_en' => 'Cook'],
                ['value' => 'WaiterWaitress',               'label_ne' => 'वेटर/वेट्रेस',                           'label_en' => 'Waiter/Waitress'],
                ['value' => 'Housekeeping',                 'label_ne' => 'हाउस किपिङ्ग',                          'label_en' => 'House Keeping'],
                ['value' => 'HouseMaid',                    'label_ne' => 'हाउस मेड',                               'label_en' => 'House Maid'],
                ['value' => 'Barman',                       'label_ne' => 'बार मैन',                                'label_en' => 'Barman'],
                ['value' => 'Salesperson',                  'label_ne' => 'सेल्स पर्सन',                            'label_en' => 'Sales Person'],
                ['value' => 'FrontOfficeManagement',        'label_ne' => 'फ्रन्ट अफिस म्यानेजमेन्ट',              'label_en' => 'Front Office Management'],
                ['value' => 'Cuttingstitch',                'label_ne' => 'सिलाई कटाई',                             'label_en' => 'Cutting & Stitching'],
                ['value' => 'Dhakaweaving',                 'label_ne' => 'ढाका बुनाई',                             'label_en' => 'Dhaka Weaving'],
                ['value' => 'Garments',                     'label_ne' => 'गार्मेण्ट्स',                            'label_en' => 'Garments'],
                ['value' => 'thecarpet',                    'label_ne' => 'गलैंचा',                                 'label_en' => 'Carpet'],
                ['value' => 'RadiPakhiweaving',             'label_ne' => 'राडी पाखी बुनाई',                        'label_en' => 'Radi Pakhi Weaving'],
                ['value' => 'Embroidery',                   'label_ne' => 'इम्ब्रोईडरी',                            'label_en' => 'Embroidery'],
                ['value' => 'Fashiondesign',                'label_ne' => 'फेशन डिजाईन',                            'label_en' => 'Fashion Design'],
                ['value' => 'Makemoney',                    'label_ne' => 'बक्रम बनाउने',                           'label_en' => 'Bookbinding/Bakram Making'],
                ['value' => 'Cookies',                      'label_ne' => 'कुकिज',                                  'label_en' => 'Cookies'],
                ['value' => 'Puroti',                       'label_ne' => 'पउरोटी',                                 'label_en' => 'Bread (Puroti)'],
                ['value' => 'thejuice',                     'label_ne' => 'जुस',                                    'label_en' => 'Juice'],
                ['value' => 'Biscuits',                     'label_ne' => 'बिस्कुट',                                'label_en' => 'Biscuits'],
                ['value' => 'pickle',                       'label_ne' => 'अचार',                                   'label_en' => 'Pickle'],
                ['value' => 'Sauce',                        'label_ne' => 'सस',                                     'label_en' => 'Sauce'],
                ['value' => 'Varishtra',                    'label_ne' => 'वारिष्ट्रा',                             'label_en' => 'Varishtra'],
                ['value' => 'candle',                       'label_ne' => 'मैनबत्ती',                               'label_en' => 'Candle'],
                ['value' => 'Agarbatti',                    'label_ne' => 'अगरबत्ती',                               'label_en' => 'Agarbatti (Incense)'],
                ['value' => 'envelopemaker',                'label_ne' => 'खाम बनाउने',                             'label_en' => 'Envelope Making'],
                ['value' => 'Wickretconstruction',          'label_ne' => 'विक्रेट निर्माण',                        'label_en' => 'Wickret Construction'],
                ['value' => 'Plasticmolding',               'label_ne' => 'प्लाष्टिक मोल्डिङ्ग',                   'label_en' => 'Plastic Molding'],
                ['value' => 'Bookbinding',                  'label_ne' => 'बुक बाईण्डिङ्ग',                         'label_en' => 'Book Binding'],
                ['value' => 'other',                        'label_ne' => 'अन्य',                                   'label_en' => 'Other'],
            ],

            // ── Technical training subjects ────────────────────────────────
            'technical_training_subject' => [
                ['value' => 'Civil',          'label_ne' => 'सिभिल इन्जिनियरिङ',          'label_en' => 'Civil Engineering'],
                ['value' => 'Electrical',     'label_ne' => 'विद्युत इन्जिनियरिङ',         'label_en' => 'Electrical Engineering'],
                ['value' => 'Mechanical',     'label_ne' => 'मेकानिकल इन्जिनियरिङ',        'label_en' => 'Mechanical Engineering'],
                ['value' => 'Computer',       'label_ne' => 'कम्प्युटर/IT',                'label_en' => 'Computer/IT'],
                ['value' => 'Agriculture',    'label_ne' => 'कृषि',                         'label_en' => 'Agriculture'],
                ['value' => 'Health',         'label_ne' => 'स्वास्थ्य',                    'label_en' => 'Health/Medical'],
                ['value' => 'Education',      'label_ne' => 'शिक्षा',                       'label_en' => 'Education'],
                ['value' => 'Forestry',       'label_ne' => 'वन',                            'label_en' => 'Forestry'],
                ['value' => 'Accounting',     'label_ne' => 'लेखा',                          'label_en' => 'Accounting'],
                ['value' => 'Management',     'label_ne' => 'व्यवस्थापन',                    'label_en' => 'Management'],
                ['value' => 'Pharmacy',       'label_ne' => 'फार्मेसी',                     'label_en' => 'Pharmacy'],
                ['value' => 'Nursing',        'label_ne' => 'नर्सिङ',                        'label_en' => 'Nursing'],
                ['value' => 'other',          'label_ne' => 'अन्य',                          'label_en' => 'Other'],
            ],

            // ── SA (Skills for Abroad/Self-employment) training subjects ──
            'sa_training_subject' => [
                ['value' => 'Hospitality',   'label_ne' => 'आतिथ्य सेवा (Hospitality)',    'label_en' => 'Hospitality'],
                ['value' => 'Caregiving',    'label_ne' => 'हेरचाह सेवा (Caregiving)',      'label_en' => 'Caregiving'],
                ['value' => 'Construction',  'label_ne' => 'निर्माण (Construction)',         'label_en' => 'Construction'],
                ['value' => 'Tailoring',     'label_ne' => 'सिलाई (Tailoring)',              'label_en' => 'Tailoring'],
                ['value' => 'Cooking',       'label_ne' => 'पकाउने (Cooking)',               'label_en' => 'Cooking'],
                ['value' => 'Electronics',   'label_ne' => 'इलेक्ट्रोनिक्स',                'label_en' => 'Electronics'],
                ['value' => 'Handicraft',    'label_ne' => 'हस्तकला',                        'label_en' => 'Handicraft'],
                ['value' => 'Computer',      'label_ne' => 'कम्प्युटर',                     'label_en' => 'Computer'],
                ['value' => 'Language',      'label_ne' => 'भाषा (Language)',                'label_en' => 'Language Skills'],
                ['value' => 'other',         'label_ne' => 'अन्य',                           'label_en' => 'Other'],
            ],

            // ── Address / location ─────────────────────────────────────────
            'addrslocation' => [
                ['value' => 'inside_municipality',  'label_ne' => 'गणना गरेको ठाउँमा बसेको (यसै पालिका)',       'label_en' => 'Lives Here (This Municipality)'],
                ['value' => 'outside_withinNepal',  'label_ne' => 'स्वदेशमा अन्यत्र (६ महिना भन्दा बढी)',        'label_en' => 'Elsewhere in Nepal (>6 months)'],
                ['value' => 'Abroad',               'label_ne' => 'विदेशमा बसेको (६ महिना भन्दा बढी)',           'label_en' => 'Abroad (>6 months)'],
            ],

            // ── Purpose of living outside municipality ────────────────────
            'purpose_living_outside' => [
                ['value' => 'Job',          'label_ne' => 'रोजगारी/काम',           'label_en' => 'Job/Work'],
                ['value' => 'Education',    'label_ne' => 'शिक्षा',                'label_en' => 'Education'],
                ['value' => 'Business',     'label_ne' => 'व्यवसाय',              'label_en' => 'Business'],
                ['value' => 'Health',       'label_ne' => 'स्वास्थ्य उपचार',      'label_en' => 'Health Treatment'],
                ['value' => 'Family',       'label_ne' => 'पारिवारिक कारण',       'label_en' => 'Family Reason'],
                ['value' => 'Marriage',     'label_ne' => 'विवाहपछि',             'label_en' => 'After Marriage'],
                ['value' => 'other',        'label_ne' => 'अन्य',                 'label_en' => 'Other'],
            ],

            // ── Type of abroad job ─────────────────────────────────────────
            'typeofabroadjob' => [
                ['value' => 'Skilled',      'label_ne' => 'सीपमा आधारित',         'label_en' => 'Skilled'],
                ['value' => 'Semi-Skilled', 'label_ne' => 'अर्ध सीपमा आधारित',    'label_en' => 'Semi-Skilled'],
                ['value' => 'Labor',        'label_ne' => 'श्रमिक',               'label_en' => 'Labor'],
                ['value' => 'Indoor',       'label_ne' => 'घरभित्रको काम',        'label_en' => 'Indoor/Domestic'],
            ],

            // ── Abroad job status ─────────────────────────────────────────
            'abroadjobstatus' => [
                ['value' => 'Regular',      'label_ne' => 'नियमित छ',              'label_en' => 'Currently Working (Regular)'],
                ['value' => 'Returned',     'label_ne' => 'पूर्णरुपमा फर्किएको',   'label_en' => 'Returned Permanently'],
                ['value' => 'AtHomeinLeave','label_ne' => 'छुटीमा घरमा रहेको',     'label_en' => 'At Home on Leave'],
                ['value' => 'PlanningtoGo', 'label_ne' => 'अब फेरी जाने योजनामा', 'label_en' => 'Planning to Go Again'],
            ],

            // ── Common destination countries (Nepal migrant workers) ──────
            'residing_country' => [
                ['value' => 'India',           'label_ne' => 'भारत',               'label_en' => 'India'],
                ['value' => 'Qatar',           'label_ne' => 'कतार',               'label_en' => 'Qatar'],
                ['value' => 'UAE',             'label_ne' => 'संयुक्त अरब इमिरेट', 'label_en' => 'UAE'],
                ['value' => 'Malaysia',        'label_ne' => 'मलेशिया',            'label_en' => 'Malaysia'],
                ['value' => 'Saudi_Arabia',    'label_ne' => 'साउदी अरेबिया',      'label_en' => 'Saudi Arabia'],
                ['value' => 'Kuwait',          'label_ne' => 'कुवेत',              'label_en' => 'Kuwait'],
                ['value' => 'South_Korea',     'label_ne' => 'दक्षिण कोरिया',      'label_en' => 'South Korea'],
                ['value' => 'Japan',           'label_ne' => 'जापान',              'label_en' => 'Japan'],
                ['value' => 'Bahrain',         'label_ne' => 'बहराइन',             'label_en' => 'Bahrain'],
                ['value' => 'Oman',            'label_ne' => 'ओमान',               'label_en' => 'Oman'],
                ['value' => 'Israel',          'label_ne' => 'इजराइल',             'label_en' => 'Israel'],
                ['value' => 'Australia',       'label_ne' => 'अस्ट्रेलिया',        'label_en' => 'Australia'],
                ['value' => 'USA',             'label_ne' => 'संयुक्त राज्य अमेरिका', 'label_en' => 'USA'],
                ['value' => 'UK',              'label_ne' => 'बेलायत',             'label_en' => 'United Kingdom'],
                ['value' => 'Canada',          'label_ne' => 'क्यानडा',            'label_en' => 'Canada'],
                ['value' => 'Portugal',        'label_ne' => 'पोर्चुगल',           'label_en' => 'Portugal'],
                ['value' => 'Romania',         'label_ne' => 'रोमानिया',           'label_en' => 'Romania'],
                ['value' => 'Maldives',        'label_ne' => 'माल्दिभ्स',          'label_en' => 'Maldives'],
                ['value' => 'Cyprus',          'label_ne' => 'साइप्रस',            'label_en' => 'Cyprus'],
                ['value' => 'other',           'label_ne' => 'अन्य देश',           'label_en' => 'Other Country'],
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

        $this->command->info('Phase 3 options seeded successfully.');
    }
}
