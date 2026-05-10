<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $options = [
            ['value' => 'khet',       'label_ne' => 'खेत',              'label_en' => 'Khet (Paddy Field)'],
            ['value' => 'bari',       'label_ne' => 'बारी',             'label_en' => 'Bari (Upland Field)'],
            ['value' => 'pakho',      'label_ne' => 'पाखो',             'label_en' => 'Pakho (Sloped Land)'],
            ['value' => 'residential','label_ne' => 'बसोबास तथा आवास', 'label_en' => 'Residential'],
            ['value' => 'industrial', 'label_ne' => 'औधोगिक बाली',     'label_en' => 'Industrial Crops'],
        ];

        foreach ($options as $sort => $opt) {
            DB::table('survey_options')->insertOrIgnore([
                'local_government_id' => null,   // global — applies to all LGs
                'category'            => 'land_use_type',
                'value'               => $opt['value'],
                'label_ne'            => $opt['label_ne'],
                'label_en'            => $opt['label_en'],
                'sort_order'          => $sort + 1,
                'is_active'           => 1,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('survey_options')->where('category', 'land_use_type')->delete();
    }
};
