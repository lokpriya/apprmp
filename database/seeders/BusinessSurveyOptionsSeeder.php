<?php

namespace Database\Seeders;

use App\Models\SurveyOption;
use Illuminate\Database\Seeder;

class BusinessSurveyOptionsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = $this->data();

        foreach ($groups as $group => $items) {
            foreach ($items as $sort => $item) {
                SurveyOption::updateOrCreate(
                    ['category' => $group, 'value' => $item['value']],
                    [
                        'label_en'             => $item['en'],
                        'label_ne'             => $item['np'],
                        'sort_order'           => $sort + 1,
                        'is_active'            => true,
                        'local_government_id'  => null,
                    ]
                );
            }
        }
    }

    private function data(): array
    {
        return [

            // ── Business Location ──────────────────────────────────────────
            'business_location' => [
                ['value' => 'UrbanArea',   'en' => 'Urban Area',   'np' => 'शहरी क्षेत्र'],
                ['value' => 'MarketArea',  'en' => 'Market Area',  'np' => 'बजार क्षेत्र'],
                ['value' => 'RuralArea',   'en' => 'Rural Area',   'np' => 'ग्रामीण क्षेत्र'],
            ],

            // ── Business Type ──────────────────────────────────────────────
            'business_type' => [
                ['value' => 'Partnership',   'en' => 'Partnership',    'np' => 'साझेदारी'],
                ['value' => 'Single',        'en' => 'Single/Sole',    'np' => 'एकल स्वामित्व'],
                ['value' => 'PublicCompany', 'en' => 'Public Company', 'np' => 'सार्वजनिक कम्पनी'],
            ],

            // ── Business Field (Sector) ────────────────────────────────────
            'business_field' => [
                ['value' => 'ProductionIndustry',          'en' => 'Production Industry',              'np' => 'उत्पादन उद्योग'],
                ['value' => 'AgricultureAnimalHusbandary', 'en' => 'Agriculture & Animal Husbandary',  'np' => 'कृषि तथा पशुपालन'],
                ['value' => 'Suppliers',                   'en' => 'Suppliers',                        'np' => 'सप्लायर्स'],
                ['value' => 'InformationAndCommunication', 'en' => 'Information & Communication',      'np' => 'सूचना र संचार'],
                ['value' => 'HotelAndRestaurant',          'en' => 'Hotel & Restaurant',               'np' => 'होटल तथा रेस्टुरेन्ट'],
                ['value' => 'VehicleAndTrasnportation',    'en' => 'Vehicle & Transportation',         'np' => 'सवारी साधन तथा यातायात'],
                ['value' => 'EducationalInstutions',       'en' => 'Educational Institutions',         'np' => 'शैक्षिक संस्था'],
                ['value' => 'ConsultancyBusiness',         'en' => 'Consultancy Business',             'np' => 'परामर्श सेवा'],
                ['value' => 'MechinaryMaintenance',        'en' => 'Machinery Maintenance',            'np' => 'यन्त्र उपकरण मर्मत'],
                ['value' => 'HospitalandClinc',            'en' => 'Hospital & Clinic',                'np' => 'अस्पताल र क्लिनिक'],
                ['value' => 'FinancialInstutions',         'en' => 'Financial Institutions',           'np' => 'वित्तीय संस्था'],
                ['value' => 'AbroadjobsSecurity',          'en' => 'Abroad Jobs & Security',           'np' => 'वैदेशिक रोजगार तथा सुरक्षा'],
                ['value' => 'FancyBusiness',               'en' => 'Fancy Business',                   'np' => 'फ्यान्सी व्यवसाय'],
                ['value' => 'OtherShopBusiness',           'en' => 'Other Shop Business',              'np' => 'अन्य पसल व्यवसाय'],
            ],

            // ── Sub: Production Industry ───────────────────────────────────
            'sub_ProductionIndustry' => [
                ['value' => 'TeaIndustry',               'en' => 'Tea Industry',                     'np' => 'चिया उद्योग'],
                ['value' => 'FurnitureIndustry',         'en' => 'Furniture Industry',               'np' => 'फर्निचर उद्योग'],
                ['value' => 'BricksandTileIndustry',     'en' => 'Bricks & Tile Industry',           'np' => 'इटा तथा टायल उद्योग'],
                ['value' => 'SteamAutoMill',             'en' => 'Steam Automatic Mill',             'np' => 'बाष्पशक्तिबाट चल्ने स्वचालित मिल'],
                ['value' => 'StationaryandCopyIndustry', 'en' => 'Stationary & Copy Industry',       'np' => 'स्टेशनरी तथा कपी उद्योग'],
                ['value' => 'IceCreamandBakeryIndustry', 'en' => 'Ice Cream & Bakery Industry',      'np' => 'आइस क्रिम तथा बेकरी उद्योग'],
                ['value' => 'GrillIndustry',             'en' => 'Grill Industry',                   'np' => 'ग्रिल उद्योग'],
                ['value' => 'CarpetIndustry',            'en' => 'Carpet Industry',                  'np' => 'गलैचा उद्योग'],
                ['value' => 'SawMill',                   'en' => 'Saw Mill',                         'np' => 'काठ मिल'],
                ['value' => 'PapadChipsDalmod',          'en' => 'Papad, Chips & Dalmod Industry',   'np' => 'पापड, चिप्स, दालमोड तथा भुजिया उद्योग'],
                ['value' => 'PlasticsProductsIndustry',  'en' => 'Plastics Products Industry',       'np' => 'प्लास्टिक सामग्री उद्योग'],
                ['value' => 'LeatherMaterialIndustry',   'en' => 'Leather Material Industry',        'np' => 'छालाका सामग्री उद्योग'],
                ['value' => 'ChemicalIndustry',          'en' => 'Chemical Industry',                'np' => 'औषधि तथा रसायन उद्योग'],
                ['value' => 'AutomaticMill',             'en' => 'Automatic Mill',                   'np' => 'अटोमेटिक स्वचालित मिल'],
                ['value' => 'FoodProcessingMill',        'en' => 'Food Processing Mill',             'np' => 'होलर, सेलर, कुटनी पिसानी मिल'],
                ['value' => 'BetBambooIndustry',         'en' => 'Bet Bamboo Industry',              'np' => 'बेतबाँस उद्योग'],
                ['value' => 'BeatenRiceIndustry',        'en' => 'Beaten Rice Industry',             'np' => 'चिउरा उद्योग'],
                ['value' => 'SweaterCapIndustry',        'en' => 'Sweater & Topi Industry',          'np' => 'सुइटर तथा टोपी उद्योग'],
                ['value' => 'RikshaBodyIndustry',        'en' => 'Riksha Body Industry',             'np' => 'रिक्सा बडी उत्पादन उद्योग'],
                ['value' => 'UtensilIndustry',           'en' => 'Utensil Industry',                 'np' => 'भाडावर्तन उद्योग'],
                ['value' => 'NoodlesIndustry',           'en' => 'Noodles Industry',                 'np' => 'चाउचाउ थुक्पा उद्योग'],
                ['value' => 'SpeciesIndustry',           'en' => 'Species Industry',                 'np' => 'मसला उद्योग'],
                ['value' => 'TobaccoIndustry',           'en' => 'Tobacco Industry',                 'np' => 'सुर्ती खैनी बिडी उद्योग'],
                ['value' => 'BeatBoard',                 'en' => 'Bit Board Industry',               'np' => 'बिट बोर्ड उत्पादन उद्योग'],
                ['value' => 'OffSetScreenPrintPress',    'en' => 'Off Set & Screen Printing Press',  'np' => 'अफसेट तथा स्क्रिन प्रिन्टिंग छापाखाना'],
                ['value' => 'PalantNursery',             'en' => 'Plant Nursery Industry',           'np' => 'चिया नर्सरी बोटबिरुवा नर्सरी उद्योग'],
                ['value' => 'MobilRefineIndustry',       'en' => 'Mobil Refine Industry',            'np' => 'मोबिल प्रशोधन उद्योग'],
                ['value' => 'InstanceStick',             'en' => 'Instance Stick (Agarbatti)',       'np' => 'अगरबत्ती उद्योग'],
                ['value' => 'CottonIndustry',            'en' => 'Cotton Industry',                  'np' => 'कपास तथा धागो उद्योग'],
                ['value' => 'ElectricalsIndustry',       'en' => 'Electricals Industry',             'np' => 'इलेक्ट्रिक सामग्री उद्योग'],
                ['value' => 'VeneerIndustry',            'en' => 'Veneer Industry',                  'np' => 'भेनियर उद्योग'],
                ['value' => 'CoalDepotCenter',           'en' => 'Coal Depot Center',                'np' => 'कोइला डिपो'],
                ['value' => 'DairyPrivateCoop',          'en' => 'Dairy Private Cooperative',        'np' => 'निजी डेरी सहकारी उद्योग'],
                ['value' => 'BeatalnutProcessing',       'en' => 'Beatalnut Processing Industry',    'np' => 'सुपारी प्रशोधन उद्योग'],
                ['value' => 'PlyWoodIndustry',           'en' => 'Ply Wood Industry',                'np' => 'प्लाई कारखाना'],
                ['value' => 'DairyIndustry',             'en' => 'Dairy Industry',                   'np' => 'डेरी उद्योग'],
                ['value' => 'GarmentIndustry',           'en' => 'Garment Industry',                 'np' => 'गार्मेन्ट उद्योग'],
                ['value' => 'MineralWaterIndustry',      'en' => 'Mineral Water Industry',           'np' => 'मिनरल वाटर उद्योग'],
                ['value' => 'SodaandJuiceIndustry',      'en' => 'Soda & Juice Industry',            'np' => 'सोडा, सकस तथा सर्बत उद्योग'],
                ['value' => 'PickleIndustry',            'en' => 'Pickle Industry',                  'np' => 'अचार उद्योग'],
                ['value' => 'SanitaryIndustry',          'en' => 'Sanitary Industry',                'np' => 'सेनेटरी उद्योग'],
                ['value' => 'HumPipeWellRingIndustry',   'en' => 'Hum Pipe & Well Ring Industry',   'np' => 'ह्युमपाइप तथा इनारको रिंग उद्योग'],
                ['value' => 'JuteIndustry',              'en' => 'Jute & Jute Products Industry',   'np' => 'जुट तथा जुट सामग्री उद्योग'],
                ['value' => 'ColdStorage',               'en' => 'Cold Storage',                     'np' => 'कोल्ड स्टोरेज'],
                ['value' => 'CementIndustry',            'en' => 'Cement Industry',                  'np' => 'सिमेन्ट उद्योग'],
                ['value' => 'RosinTurpentineIndustry',   'en' => 'Rosin & Turpentine Industry',     'np' => 'रेजिन तथा टरपेनटाइन उद्योग'],
                ['value' => 'IronIndustry',              'en' => 'Iron Industry',                    'np' => 'फलाम उद्योग'],
                ['value' => 'KatthaIndustry',            'en' => 'Kattha Industry',                  'np' => 'कत्थ्था उद्योग'],
                ['value' => 'GasIndustry',               'en' => 'Gas Industry',                     'np' => 'ग्यास उद्योग'],
                ['value' => 'Others',                    'en' => 'Others',                           'np' => 'अन्य उद्योग'],
            ],

            // ── Sub: Agriculture & Animal Husbandary ──────────────────────
            'sub_AgricultureAnimalHusbandary' => [
                ['value' => 'AgriShop',             'en' => 'Agri Shop',                   'np' => 'कृषि पसल'],
                ['value' => 'MilkDairyShop',        'en' => 'Milk Dairy Shop',             'np' => 'दुध डेरी पसल'],
                ['value' => 'AnimalPoultryFarm',    'en' => 'Animal or Poultry Farm',      'np' => 'पशु तथा पन्छी पालन फर्म'],
                ['value' => 'AgroVetShop',          'en' => 'Agro-Vet Shop',               'np' => 'कृषि तथा पशुसेवा औषधि पसल'],
                ['value' => 'HandyCraft',           'en' => 'Handy Craft',                 'np' => 'हस्तकला व्यवसाय'],
            ],

            // ── Sub: Suppliers ─────────────────────────────────────────────
            'sub_Suppliers' => [
                ['value' => 'GeneralSupplier',      'en' => 'General Supplier',            'np' => 'जनरल सप्लायर्स'],
                ['value' => 'ComputerSupplier',     'en' => 'Computer Supplier',           'np' => 'कम्प्युटर सप्लायर्स'],
                ['value' => 'HardwareSupplier',     'en' => 'Hardware Supplier',           'np' => 'हार्डवेयर सप्लायर्स'],
                ['value' => 'ConsultancyService',   'en' => 'Consultancy Service',         'np' => 'परामर्श सेवा'],
            ],

            // ── Sub: Information & Communication ──────────────────────────
            'sub_InformationAndCommunication' => [
                ['value' => 'Television',           'en' => 'Television',                  'np' => 'टेलिभिजन'],
                ['value' => 'FMStation',            'en' => 'FM Station',                  'np' => 'एफएम रेडियो स्टेशन'],
                ['value' => 'CableTelevision',      'en' => 'Cable Television',            'np' => 'केबल टेलिभिजन'],
                ['value' => 'ISPCyberCafe',         'en' => 'ISP / Cyber Cafe',            'np' => 'इन्टरनेट सेवा प्रदायक'],
                ['value' => 'PhotocopyandPCO',      'en' => 'Photocopy & PCO',             'np' => 'फोटोकपी तथा पिसिओ'],
                ['value' => 'Transportation',       'en' => 'Transportation Service',      'np' => 'यातायात तथा ढुवानी सेवा'],
                ['value' => 'CourierService',       'en' => 'Courier & Home Delivery',     'np' => 'कुरियर तथा होम डेलिभरी सेवा'],
            ],

            // ── Sub: Hotel & Restaurant ────────────────────────────────────
            'sub_HotelAndRestaurant' => [
                ['value' => 'FiveStarHotel',        'en' => 'Five Star Hotel & Lodge',     'np' => 'पाँच तारे होटल तथा लज'],
                ['value' => 'FourStarHotel',        'en' => 'Four Star Hotel & Lodge',     'np' => 'चार तारे होटल तथा लज'],
                ['value' => 'ThreeStarHotel',       'en' => 'Three Star Hotel & Lodge',    'np' => 'तीन तारे होटल तथा लज'],
                ['value' => 'TwoStarHotel',         'en' => 'Two Star Hotel & Lodge',      'np' => 'दुइ तारे होटल तथा लज'],
                ['value' => 'PartyPalaceHotel',     'en' => 'Party Palace & One Star Hotel','np' => 'पार्टी प्यालेस तथा एकतारे होटेल'],
                ['value' => 'HotelWithLiquor',      'en' => 'Hotel With Liquor',           'np' => 'मदिरा सहितको होटल'],
                ['value' => 'FoodStation',          'en' => 'Food Station',                'np' => 'खाना नास्ता घर'],
                ['value' => 'RestaurantandBar',     'en' => 'Restaurant & Bar',            'np' => 'रेस्टुरेन्ट तथा बार'],
                ['value' => 'LokDohariRestaurant',  'en' => 'Lok Dohari / Gajal Restaurant','np' => 'लोक दोहोरी तथा गजल रेस्टुरेन्ट'],
                ['value' => 'HotelWithSweets',      'en' => 'Hotel with Sweets Shop',      'np' => 'मिस्टान् भण्डार सहितको होटेल'],
                ['value' => 'CateringService',      'en' => 'Catering Service',            'np' => 'क्याटरिंग सेवा'],
                ['value' => 'SweetShop',            'en' => 'Sweet Shop',                  'np' => 'मिठाइ पसल'],
                ['value' => 'TeaShop',              'en' => 'Tea Shop',                    'np' => 'चिया पसल'],
                ['value' => 'ColdCenter',           'en' => 'Cold Center',                 'np' => 'कोल्ड सेन्टर'],
                ['value' => 'DealerofLiquor',       'en' => 'Dealer of Liquor',            'np' => 'मदिराको डिलर'],
            ],

            // ── Sub: Vehicle & Transportation ─────────────────────────────
            'sub_VehicleAndTransportation' => [
                ['value' => 'TractorShowRoom',      'en' => 'Tractor Show Room',           'np' => 'ट्रयाक्टर शो रुम'],
                ['value' => 'CarLightVehicle',      'en' => 'Car or Light Vehicle',        'np' => 'कार तथा हलुका सवारी शोरुम'],
                ['value' => 'MotorCycleShowRoom',   'en' => 'Motor Cycle Show Room',       'np' => 'मोटरबाइक शोरुम'],
                ['value' => 'AuthorisedDealer',     'en' => 'Authorised Dealer',           'np' => 'अधिकृत बिक्रेता'],
                ['value' => 'PetrolPump',           'en' => 'Petrol Pump',                 'np' => 'पेट्रोल पम्प'],
                ['value' => 'LandPlotting',         'en' => 'Land Plotting & Selling',     'np' => 'जग्गा प्लानिग र जग्गा खरिद बिक्री'],
                ['value' => 'HeavyMotorDealer',     'en' => 'Heavy Motor Dealer',          'np' => 'हेभीमोटर शोरुम'],
                ['value' => 'GasDealer',            'en' => 'Gas Dealer',                  'np' => 'ग्यास डिलर'],
                ['value' => 'TireandSpareParts',    'en' => 'Tire & Spare Parts',          'np' => 'टायर तथा स्पेयर सामग्री पसल'],
            ],

            // ── Sub: Educational Institutions ─────────────────────────────
            'sub_EducationalInstutions' => [
                ['value' => 'MontessoriorPlayGroup','en' => 'Montessori or Play Group',    'np' => 'प्ले ग्रुप तथा मोन्टेसोरी शिक्षा'],
                ['value' => 'PrivatePrimarySchool', 'en' => 'Private Primary School',      'np' => 'निजि प्राथमिक तह सम्मको विद्यालय'],
                ['value' => 'PlusTwoPrivateCampus', 'en' => 'Plus Two Level Private Campus','np' => 'प्लस २ सम्मको निजि क्याम्पस'],
                ['value' => 'ComputerInstitute',    'en' => 'Computer Institute',          'np' => 'कम्प्युटर इन्स्टिच्युट'],
                ['value' => 'DrivingInstitute',     'en' => 'Driving Institute',           'np' => 'ड्राइभिंग इन्स्टिच्युट'],
                ['value' => 'TuitionLanguageCenter','en' => 'Tuition & Language Center',   'np' => 'ट्युसन तथा भाषा प्रशिक्षण'],
                ['value' => 'OtherTrainingCenter',  'en' => 'Other Training Center',       'np' => 'अन्य प्रशिक्षण केन्द्र'],
                ['value' => 'GirlsHostel',          'en' => 'Girls Hostel',                'np' => 'छात्राबास'],
                ['value' => 'PolyTechnicalInstitute','en' => 'Poly Technical Institute',   'np' => 'बहु प्राविधिक शिक्षालय'],
                ['value' => 'NursingTrainingCenter','en' => 'Nursing Training Center',     'np' => 'नर्सिङ प्रशिक्षण केन्द्र'],
                ['value' => 'EducationalConsultancy','en' => 'Educational Consultancy',    'np' => 'शैक्षिक परामर्श केन्द्र'],
                ['value' => 'TrainingResearchInstitute','en' => 'Training & Research Institute','np' => 'तालिम तथा अनुसन्धान केन्द्र'],
                ['value' => 'LanguageCoachingCenter','en' => 'Language & Coaching Center', 'np' => 'भाषा शिक्षा तथा कोचिंग सेन्टर'],
            ],

            // ── Sub: Consultancy Business ──────────────────────────────────
            'sub_ConsultancyBusiness' => [
                ['value' => 'ClinicalDoctor',       'en' => 'Clinical Doctor',             'np' => 'चिकित्सक'],
                ['value' => 'VetenaryDoctor',       'en' => 'Vetenary Doctor',             'np' => 'पशु चिकित्सक'],
                ['value' => 'DentalDoctor',         'en' => 'Dental Doctor',               'np' => 'दन्त चिकित्सक'],
                ['value' => 'Engineer',             'en' => 'Engineer',                    'np' => 'इन्जिनियर'],
                ['value' => 'LegalPractitioner',    'en' => 'Legal Practitioner',          'np' => 'कानुनी व्यवसायी'],
                ['value' => 'LegalAdvocate',        'en' => 'Legal Advocate',              'np' => 'अधिवक्ता'],
                ['value' => 'Compounder',           'en' => 'Compounder',                  'np' => 'कम्पाउन्डर'],
                ['value' => 'SharedBroker',         'en' => 'Share Broker',                'np' => 'शेयर ब्रोकर'],
                ['value' => 'KabiRaj',              'en' => 'Kabiraj (Herbalist)',         'np' => 'कविराज'],
                ['value' => 'Researcher',           'en' => 'Researcher',                  'np' => 'अनुसन्धानकर्ता'],
                ['value' => 'Surveyor',             'en' => 'Surveyor',                    'np' => 'सर्भेयर'],
                ['value' => 'ConstructionCompany',  'en' => 'Construction Company',        'np' => 'निर्माण कम्पनी'],
                ['value' => 'ForeignConstructionCo','en' => 'Foreign Construction Company','np' => 'विदेशी निर्माण कम्पनी'],
                ['value' => 'BuildingConstructor',  'en' => 'Building Constructor',        'np' => 'भवन निर्माता'],
                ['value' => 'Auditor',              'en' => 'Auditor',                     'np' => 'लेखापरीक्षक'],
                ['value' => 'ComputerAnalyst',      'en' => 'Computer Analyst/Programmer', 'np' => 'कम्प्युटर विश्लेषक/प्रोग्रामर'],
                ['value' => 'Translator',           'en' => 'Translator',                  'np' => 'अनुवादक'],
            ],

            // ── Sub: Machinery Maintenance ─────────────────────────────────
            'sub_MechinaryMaintenance' => [
                ['value' => 'MillandMachineryShop', 'en' => 'Mill & Machinery Shop',       'np' => 'मिल तथा मेसिनरी पसल'],
                ['value' => 'MotorCycleWorkshop',   'en' => 'Motor Cycle Workshop',        'np' => 'मोटरसाइकल मर्मत वर्कशप'],
                ['value' => 'LightVehicleWorkshop', 'en' => 'Light Vehicle Workshop',      'np' => 'हलुका सवारी मर्मत वर्कशप'],
                ['value' => 'BicycleWorkshop',      'en' => 'Bicycle Workshop',            'np' => 'साइकल मर्मत केन्द्र'],
                ['value' => 'RadioTVRefWorkshop',   'en' => 'Fridge/TV/Radio Workshop',    'np' => 'फ्रिज, टेलिभिजन, रेडियो, पंखा मर्मत'],
                ['value' => 'TireResoling',         'en' => 'Tire Resoling',               'np' => 'टायर रेसोलिंग केन्द्र'],
                ['value' => 'AirRefilling',         'en' => 'Air Refilling',               'np' => 'गाडीको हावा भर्ने ठाउँ'],
                ['value' => 'HeavyVehicleWorkshop', 'en' => 'Heavy Vehicle Workshop',      'np' => 'हेभी सवारी मर्मत वर्कशप'],
            ],

            // ── Sub: Hospital & Clinic ─────────────────────────────────────
            'sub_HospitalandClinc' => [
                ['value' => 'PrivateHospital',      'en' => 'Private Hospital',            'np' => 'निजी अस्पताल'],
                ['value' => 'DiagnosticCenter',     'en' => 'Diagnostic Center',           'np' => 'डाइग्नोस्टिक केन्द्र'],
                ['value' => 'PharmacywithClinc',    'en' => 'Pharmacy with Clinic',        'np' => 'क्लिनिक सहितको फार्मेसी'],
                ['value' => 'PharmacyOnly',         'en' => 'Pharmacy Only',               'np' => 'फार्मेसी पसल'],
                ['value' => 'AgroVetenaryShop',     'en' => 'Agro-Vetenary Shop',          'np' => 'कृषि पशु औषधी पसल'],
                ['value' => 'XRayService',          'en' => 'X-Ray Service',               'np' => 'एक्सरे सेवा'],
                ['value' => 'LaboratoryService',    'en' => 'Laboratory Service',          'np' => 'ल्याब (रगत तथा अन्य परीक्षण)'],
                ['value' => 'MedicalEquipments',    'en' => 'Medical Equipments Seller',   'np' => 'मेडिकल सामग्री बिक्री'],
                ['value' => 'AcupunctureService',   'en' => 'Acupuncture Service',         'np' => 'अकुपन्चर प्राकृतिक चिकित्सा'],
                ['value' => 'AyuHomeoPharmacy',     'en' => 'Ayurvedic & Homeo Pharmacy',  'np' => 'आयुर्वेदिक तथा होमियो औषधी पसल'],
            ],

            // ── Sub: Financial Institutions ────────────────────────────────
            'sub_FinancialInstutions' => [
                ['value' => 'CommercialBank',       'en' => 'A Class Commercial Bank',     'np' => 'क श्रेणी बाणिज्य बैंक'],
                ['value' => 'DevelopmentalBank',    'en' => 'Developmental Bank',          'np' => 'विकास बैंक'],
                ['value' => 'Cooperative',          'en' => 'Cooperative',                 'np' => 'सहकारी'],
                ['value' => 'MicroFinance',         'en' => 'Micro Finance Company',       'np' => 'लघु वित्त कम्पनी'],
                ['value' => 'FinancialInstitution', 'en' => 'Financial Institution',       'np' => 'वित्तीय संस्था'],
                ['value' => 'MoneyTransfer',        'en' => 'Money Transfer',              'np' => 'मनि ट्रान्सफर'],
                ['value' => 'InsuranceCompany',     'en' => 'Insurance Company',           'np' => 'बिमा कम्पनी'],
                ['value' => 'OtherFinancial',       'en' => 'Other Financial Institution', 'np' => 'अन्य वित्तीय संस्था'],
            ],

            // ── Sub: Abroad Jobs & Security ────────────────────────────────
            'sub_AbroadjobsSecurity' => [
                ['value' => 'ManPower',             'en' => 'Man Power & Overseas',        'np' => 'म्यान पावर तथा ओभरसिज सेवा'],
                ['value' => 'SecurityService',      'en' => 'Security Service',            'np' => 'सुरक्षा गार्ड सेवा'],
            ],

            // ── Sub: Fancy Business ────────────────────────────────────────
            'sub_FancyBusiness' => [
                ['value' => 'PhotoVideoStudio',     'en' => 'Photo & Video Studio',        'np' => 'फोटो तथा भिडियो स्टुडियो'],
                ['value' => 'PhotoPrintingLab',     'en' => 'Photo Printing Lab',          'np' => 'फोटो प्रिन्टिंग ल्याब'],
                ['value' => 'CyberCafe',            'en' => 'Cyber Cafe',                  'np' => 'साइबर क्याफे'],
                ['value' => 'SnookerHouse',         'en' => 'Snooker House',               'np' => 'स्नुकर हाउस'],
                ['value' => 'BeautyParlor',         'en' => 'Beauty Parlor',               'np' => 'ब्युटी पार्लर'],
                ['value' => 'Saloon',               'en' => 'Saloon',                      'np' => 'सैलुन'],
                ['value' => 'Tailors',              'en' => 'Tailors',                     'np' => 'दर्जी'],
                ['value' => 'GlassHouse',           'en' => 'Glass House',                 'np' => 'काँच घर'],
                ['value' => 'BoutiqueEmbroidery',   'en' => 'Boutique & Embroidery',       'np' => 'बुटिक तथा कढाई'],
                ['value' => 'DryCleaners',          'en' => 'Dry Cleaners',                'np' => 'ड्राई क्लिनर्स'],
                ['value' => 'DepartmentStore',      'en' => 'Department Store',            'np' => 'डिपार्टमेन्ट स्टोर'],
            ],

            // ── Sub: Other Shop Business ───────────────────────────────────
            'sub_OtherShopBusiness' => [
                ['value' => 'Grocery',              'en' => 'Grocery',                     'np' => 'किराना पसल'],
                ['value' => 'GarmentShop',          'en' => 'Garment Shop',                'np' => 'गार्मेन्ट पसल'],
                ['value' => 'TeaShop',              'en' => 'Tea Shop',                    'np' => 'चिया पसल'],
                ['value' => 'JewelerShop',          'en' => 'Jeweler Shop',                'np' => 'गहना पसल'],
                ['value' => 'CosmeticsItemShop',    'en' => 'Cosmetics Item Shop',         'np' => 'सौन्दर्य सामग्री पसल'],
                ['value' => 'ChuriShop',            'en' => 'Churi Shop',                  'np' => 'चुरी पसल'],
                ['value' => 'ElectronicShop',       'en' => 'Electronic Shop',             'np' => 'इलेक्ट्रोनिक पसल'],
                ['value' => 'MobileShop',           'en' => 'Mobile Shop',                 'np' => 'मोबाइल पसल'],
                ['value' => 'ReadyMadeGarment',     'en' => 'Ready Made Garment',          'np' => 'रेडिमेड कपडा पसल'],
                ['value' => 'ShoeandSandalShop',    'en' => 'Shoe & Sandal Shop',          'np' => 'जुत्ता चप्पल पसल'],
                ['value' => 'ShoeShowRoom',         'en' => 'Shoe Show Room',              'np' => 'जुत्ता शोरुम'],
                ['value' => 'RiceTrading',          'en' => 'Rice Trading',                'np' => 'चामल व्यापार'],
                ['value' => 'GiftCenter',           'en' => 'Gift Center',                 'np' => 'गिफ्ट सेन्टर'],
                ['value' => 'BookandStationary',    'en' => 'Book & Stationary',           'np' => 'किताब तथा स्टेशनरी'],
                ['value' => 'UtensilShops',         'en' => 'Utensil Shops',               'np' => 'भाडा कुँडा पसल'],
                ['value' => 'SweeperNangloShop',    'en' => 'Sweeper / Nanglo Shop',       'np' => 'बुहारी नाङ्लो पसल'],
                ['value' => 'KawadiShop',           'en' => 'Kawadi Shop',                 'np' => 'कबाडी पसल'],
                ['value' => 'GhumtiPasal',          'en' => 'Ghumti Pasal',                'np' => 'घुम्ती पसल'],
                ['value' => 'PANShop',              'en' => 'PAN Shop',                    'np' => 'पान पसल'],
                ['value' => 'PhotoFraming',         'en' => 'Photo Framing',               'np' => 'फोटो फ्रेमिंग'],
                ['value' => 'TobaccoShop',          'en' => 'Tobacco Shop',                'np' => 'तम्बाकु पसल'],
                ['value' => 'ReadyMadeFancy',       'en' => 'Ready Made Fancy',            'np' => 'रेडिमेड फ्यान्सी'],
                ['value' => 'SwingTraining',        'en' => 'Swing Training',              'np' => 'सिलाई तालिम'],
                ['value' => 'CartMaintinance',      'en' => 'Cart Maintenance',            'np' => 'ठेलागाडा मर्मत'],
                ['value' => 'ShoeMaterial',         'en' => 'Shoe Material',               'np' => 'जुत्ता सामग्री'],
                ['value' => 'JatoSilautaShop',      'en' => 'Jato Silauta Shop',           'np' => 'जाँतो सिलौटा पसल'],
                ['value' => 'IronShop',             'en' => 'Iron Shop',                   'np' => 'फलाम पसल'],
                ['value' => 'SoundSystem',          'en' => 'Sound System',                'np' => 'साउण्ड सिस्टम'],
                ['value' => 'CDShop',               'en' => 'CD Shop',                     'np' => 'सिडी पसल'],
                ['value' => 'BrashBandMikeRent',    'en' => 'Brass Band & Mike Rent',      'np' => 'ब्रास ब्याण्ड तथा माइक भाडा'],
                ['value' => 'MeatShop',             'en' => 'Meat Shop',                   'np' => 'मासु पसल'],
                ['value' => 'LeatherShop',          'en' => 'Leather Shop',                'np' => 'छाला पसल'],
                ['value' => 'VegetableShop',        'en' => 'Vegetable Shop',              'np' => 'तरकारी पसल'],
                ['value' => 'FruitsShop',           'en' => 'Fruits Shop',                 'np' => 'फलफूल पसल'],
                ['value' => 'FurnishingHouse',      'en' => 'Furnishing House',            'np' => 'फर्निसिङ हाउस'],
                ['value' => 'VideoShow',            'en' => 'Video Show',                  'np' => 'भिडियो शो'],
                ['value' => 'EventManagement',      'en' => 'Event Management',            'np' => 'इभेन्ट म्यानेजमेन्ट'],
                ['value' => 'PlasticItems',         'en' => 'Plastic Items',               'np' => 'प्लास्टिक सामग्री'],
                ['value' => 'JimHouse',             'en' => 'Jim House (Gym)',             'np' => 'जिम हाउस'],
                ['value' => 'TentHouse',            'en' => 'Tent House',                  'np' => 'टेन्ट हाउस'],
                ['value' => 'EggHouse',             'en' => 'Egg House',                   'np' => 'अण्डा पसल'],
                ['value' => 'LaundryHouse',         'en' => 'Laundry House',               'np' => 'लण्ड्री हाउस'],
            ],

            // ── Place of Registration ──────────────────────────────────────
            'business_registration_place' => [
                ['value' => 'Municipality',         'en' => 'Municipality',                'np' => 'पालिकामा'],
                ['value' => 'HomeandCottageInd',    'en' => 'Home & Cottage Industry',     'np' => 'जिल्ला घरेलु कार्यालयमा'],
                ['value' => 'CompanyRegistrar',     'en' => 'Company Registrar',           'np' => 'कम्पनी रजिस्ट्रारको कार्यालयमा'],
                ['value' => 'FCCI',                 'en' => 'Chamber of Commerce (FCCI)',  'np' => 'उद्योग बाणिज्य संघ'],
                ['value' => 'GovernmentOffice',     'en' => 'Government Office',           'np' => 'सम्बन्धित सरकारी कार्यालय'],
            ],

        ];
    }
}
