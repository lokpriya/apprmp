<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalGovernment extends Model
{
    protected $fillable = [
        'district_id', 'name_np', 'name_en', 'type', 'code', 'ward_count',
        'logo', 'established_date', 'contact_email', 'contact_phone', 'website', 'is_active',
        'profile_content',
        'map_config',
        'role_permissions',
        'settings_locked_at',
        'settings_locked_by',
        'photo_dir',
        'welcome_config',
        'maintenance_config',
        'survey_config',
        'google_sheets_config',
    ];

    protected $casts = [
        'established_date'  => 'date',
        'is_active'         => 'boolean',
        'profile_content'   => 'array',
        'map_config'        => 'array',
        'role_permissions'  => 'array',
        'photo_dir'         => 'array',
        'welcome_config'     => 'array',
        'maintenance_config'    => 'array',
        'survey_config'         => 'array',
        'google_sheets_config'  => 'array',
        'settings_locked_at'    => 'datetime',
    ];

    public function isSettingsLocked(): bool
    {
        return $this->settings_locked_at !== null;
    }

    public function isSurveyFrozen(): bool
    {
        $config = $this->survey_config ?? [];
        if (!empty($config['manual_freeze'])) return true;
        $today = now()->toDateString();
        if (!empty($config['start_date']) && $today < $config['start_date']) return true;
        if (!empty($config['end_date'])   && $today > $config['end_date'])   return true;
        return false;
    }

    /** Ordered list of narrative profile sections shown on the public portal. */
    public static function profileSections(): array
    {
        return [
            'brief_intro'    => 'संक्षिप्त परिचय',
            'history'        => 'ऐतिहासिक पृष्ठभूमी',
            'naming'         => 'नामाकरण',
            'geography'      => 'भौगोलिक स्वरुप',
            'geology'        => 'भौगर्भिक अवस्था',
            'water_system'   => 'जलाधार प्रणाली',
            'climate'        => 'जलवायु',
            'flora'          => 'प्राकृतिक वनस्पती',
            'biodiversity'   => 'जैविक विविधता र वातावरण',
            'social_economy' => 'सामाजिक जनजीवन तथा आर्थिक अवस्था',
            'population'     => 'जनसंख्या र जात/जातीयता',
            'culture'        => 'साँस्कृतिक तथा धार्मिक पक्ष',
            'attractions'    => 'प्रमुख आकर्षणहरु',
            'political'      => 'राजनैतिक विभाजन',
            'tourism'        => 'पर्यटकीय गन्तब्य स्थानहरु',
            'regulations'    => 'नियम/विनियमहरुको विवरण',
            'challenges'     => 'अवसर, चुनौती र समस्याहरु',
            'development'    => 'विकास सम्भावनाहरु',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
