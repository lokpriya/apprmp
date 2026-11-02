<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

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
        'modules_config',
        'google_sheets_config',
        'licensed_until',
        'license_key',
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
        'modules_config'        => 'array',
        'google_sheets_config'  => 'array',
        'settings_locked_at'    => 'datetime',
        'licensed_until'        => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::saved(fn (LocalGovernment $lg) => static::bustCache($lg->id));
    }

    public static function getCached(int $id): ?self
    {
        return Cache::rememberForever("lg_{$id}", fn () => static::find($id));
    }

    /**
     * The active local government, without relations.
     *
     * Both queries order by id explicitly. `first()` on an unordered query
     * returns whatever row the storage engine happens to yield, so without this
     * the identity of the *entire public site* — every report, every export —
     * depends on physical row order. The fallback matters in practice: the
     * local_governments table carries all 753 Nepal municipalities as master
     * data, and on a freshly imported database none of them is flagged active.
     */
    public static function getFirstCached(): ?self
    {
        return Cache::rememberForever('lg_first', fn () => static::activeQuery()->first());
    }

    /**
     * The active local government with district and province eager-loaded.
     *
     * Five public controllers used to resolve this themselves, four of them
     * uncached — a database hit with a join on every public request, which is
     * the last thing the CloudLinux entry-process budget needs. Worse, the
     * uncached copies could disagree with the cached ones after a change.
     * Cached under the key SettingsController already busts.
     */
    public static function getActiveFull(): ?self
    {
        return Cache::remember('active_lg_full', 600, fn () => static::activeQuery()->with(['district.province'])->first());
    }

    /** Shared, deterministic definition of "the active LG". */
    private static function activeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->orderByRaw('is_active DESC')
            ->orderBy('id');
    }

    public static function bustCache(int $id): void
    {
        Cache::forget("lg_{$id}");
        Cache::forget('lg_first');
        Cache::forget("survey_frozen_{$id}");
        Cache::forget('active_lg_full');
    }

    public function isLicensed(): bool
    {
        if (!$this->licensed_until) return false;
        return $this->licensed_until->greaterThanOrEqualTo(now()->startOfDay());
    }

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
