<?php

namespace App\Support;

use App\Models\LocalGovernment;

/**
 * Optional modules a super admin switches on per municipality.
 *
 * Stored in local_governments.modules_config as
 *     { "shramdaan": { "enabled": true, "settings": { … } } }
 *
 * Every module starts OFF. A site that has never opened the modules page has a
 * NULL column, and that must mean "nothing optional is running" — a module
 * appearing on a live site because an update shipped it would be a surprise to
 * the municipality and a privacy problem for this one (it prints citizenship
 * numbers).
 */
final class Modules
{
    public const SHRAMDAAN = 'shramdaan';

    /** Each module: its name, what it does, and its settings' defaults. */
    public static function catalogue(): array
    {
        return [
            self::SHRAMDAAN => [
                'label'       => 'श्रमदान पासबुक',
                'description' => 'परिवारको श्रमदान अभिलेख — टोल संयोजकले टोलका घरधुरीको पासबुक राख्ने, वडाले प्रमाणित गर्ने, PDF पासबुक।',
                'icon'        => 'fa-solid fa-hand-holding-heart',
                'settings'    => [
                    'hours_per_day'  => 8,       // 8 hours = 1 श्रमदान day
                    'min_age'        => 16,      // members listed on the passbook
                    'tier_gold'      => 15,      // days in a fiscal year
                    'tier_silver'    => 8,
                    'tier_bronze'    => 3,
                    'incentive_text' => '',      // printed only when a tier is reached; empty = not printed
                ],
            ],
        ];
    }

    public static function enabled(string $module, ?int $lgId): bool
    {
        if (!$lgId || !isset(self::catalogue()[$module])) return false;

        return (bool) (LocalGovernment::getCached($lgId)?->modules_config[$module]['enabled'] ?? false);
    }

    /** The module's settings: saved values over the defaults, unknown keys dropped. */
    public static function settings(string $module, ?int $lgId): array
    {
        $defaults = self::catalogue()[$module]['settings'] ?? [];
        $saved    = $lgId ? (LocalGovernment::getCached($lgId)?->modules_config[$module]['settings'] ?? []) : [];

        return array_merge($defaults, array_intersect_key((array) $saved, $defaults));
    }
}
