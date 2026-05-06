<?php

namespace App\Support;

class RolePermissions
{
    /** All permission groups and their individual permissions. */
    public static function groups(): array
    {
        return [
            [
                'key'   => 'households',
                'label' => 'घरधुरी',
                'icon'  => 'fa-solid fa-house',
                'perms' => [
                    'households.view'    => ['label' => 'हेर्ने',           'en' => 'View'],
                    'households.create'  => ['label' => 'नयाँ थप्ने',       'en' => 'Create'],
                    'households.edit'    => ['label' => 'सम्पादन गर्ने',    'en' => 'Edit'],
                    'households.delete'  => ['label' => 'मेट्ने',           'en' => 'Delete'],
                    'households.submit'        => ['label' => 'पेस गर्ने',             'en' => 'Submit'],
                    'households.approve'       => ['label' => 'स्वीकृत गर्ने',      'en' => 'Approve'],
                    'households.reject'        => ['label' => 'अस्वीकार गर्ने',     'en' => 'Reject'],
                    'households.publish'       => ['label' => 'प्रकाशित गर्ने',     'en' => 'Publish'],
                    'households.mark_deceased' => ['label' => 'मृत्यु दर्ज गर्ने', 'en' => 'Mark Member Deceased'],
                ],
            ],
            [
                'key'   => 'institutions',
                'label' => 'संस्था',
                'icon'  => 'fa-solid fa-building',
                'perms' => [
                    'institutions.view'    => ['label' => 'हेर्ने',           'en' => 'View'],
                    'institutions.create'  => ['label' => 'नयाँ थप्ने',       'en' => 'Create'],
                    'institutions.edit'    => ['label' => 'सम्पादन गर्ने',    'en' => 'Edit'],
                    'institutions.delete'  => ['label' => 'मेट्ने',           'en' => 'Delete'],
                    'institutions.submit'  => ['label' => 'पेस गर्ने',        'en' => 'Submit'],
                    'institutions.approve' => ['label' => 'स्वीकृत गर्ने',   'en' => 'Approve'],
                    'institutions.reject'  => ['label' => 'अस्वीकार गर्ने',  'en' => 'Reject'],
                    'institutions.publish' => ['label' => 'प्रकाशित गर्ने',  'en' => 'Publish'],
                ],
            ],
            [
                'key'   => 'businesses',
                'label' => 'व्यवसाय',
                'icon'  => 'fa-solid fa-briefcase',
                'perms' => [
                    'businesses.view'    => ['label' => 'हेर्ने',           'en' => 'View'],
                    'businesses.create'  => ['label' => 'नयाँ थप्ने',       'en' => 'Create'],
                    'businesses.edit'    => ['label' => 'सम्पादन गर्ने',    'en' => 'Edit'],
                    'businesses.delete'  => ['label' => 'मेट्ने',           'en' => 'Delete'],
                    'businesses.submit'  => ['label' => 'पेस गर्ने',        'en' => 'Submit'],
                    'businesses.approve' => ['label' => 'स्वीकृत गर्ने',   'en' => 'Approve'],
                    'businesses.reject'  => ['label' => 'अस्वीकार गर्ने',  'en' => 'Reject'],
                    'businesses.publish' => ['label' => 'प्रकाशित गर्ने',  'en' => 'Publish'],
                ],
            ],
            [
                'key'   => 'users',
                'label' => 'प्रयोगकर्ता',
                'icon'  => 'fa-solid fa-users',
                'perms' => [
                    'users.view'           => ['label' => 'हेर्ने',             'en' => 'View'],
                    'users.create'         => ['label' => 'नयाँ थप्ने',         'en' => 'Create'],
                    'users.edit'           => ['label' => 'सम्पादन गर्ने',      'en' => 'Edit'],
                    'users.delete'         => ['label' => 'मेट्ने',             'en' => 'Delete'],
                    'users.toggle_active'  => ['label' => 'सक्रिय/निष्क्रिय', 'en' => 'Toggle Active'],
                    'users.reset_password' => ['label' => 'पासवर्ड रिसेट',     'en' => 'Reset Password'],
                ],
            ],
            [
                'key'   => 'review',
                'label' => 'समीक्षा',
                'icon'  => 'fa-solid fa-magnifying-glass-chart',
                'perms' => [
                    'review.view'     => ['label' => 'समीक्षा हेर्ने',   'en' => 'View Queue'],
                    'review.approve'  => ['label' => 'स्वीकृत गर्ने',   'en' => 'Approve'],
                    'review.reject'   => ['label' => 'अस्वीकार गर्ने', 'en' => 'Reject'],
                    'review.escalate' => ['label' => 'माथि पठाउने',     'en' => 'Escalate'],
                ],
            ],
            [
                'key'   => 'edit_requests',
                'label' => 'सम्पादन अनुरोध',
                'icon'  => 'fa-solid fa-pen-to-square',
                'perms' => [
                    'edit_requests.view'    => ['label' => 'हेर्ने',           'en' => 'View'],
                    'edit_requests.request' => ['label' => 'अनुरोध गर्ने',    'en' => 'Submit Request'],
                    'edit_requests.approve' => ['label' => 'स्वीकृत गर्ने',   'en' => 'Approve'],
                    'edit_requests.reject'  => ['label' => 'अस्वीकार गर्ने', 'en' => 'Reject'],
                ],
            ],
            [
                'key'   => 'reports',
                'label' => 'रिपोर्ट',
                'icon'  => 'fa-solid fa-chart-column',
                'perms' => [
                    'reports.view'   => ['label' => 'हेर्ने',        'en' => 'View'],
                    'reports.export' => ['label' => 'निर्यात गर्ने', 'en' => 'Export'],
                ],
            ],
            [
                'key'   => 'import',
                'label' => 'बल्क आयात',
                'icon'  => 'fa-solid fa-file-import',
                'perms' => [
                    'import.households'   => ['label' => 'घरधुरी आयात',  'en' => 'Import Households'],
                    'import.institutions' => ['label' => 'संस्था आयात',  'en' => 'Import Institutions'],
                    'import.businesses'   => ['label' => 'व्यवसाय आयात', 'en' => 'Import Businesses'],
                ],
            ],
            [
                'key'   => 'settings',
                'label' => 'सेटिङ्ग',
                'icon'  => 'fa-solid fa-gear',
                'perms' => [
                    'settings.general'           => ['label' => 'सामान्य सेटिङ्ग',      'en' => 'General Settings'],
                    'settings.wards'             => ['label' => 'वडा व्यवस्थापन',       'en' => 'Ward Management'],
                    'settings.survey_options'    => ['label' => 'सर्वेक्षण विकल्प',    'en' => 'Survey Options'],
                    'settings.representatives'   => ['label' => 'जनप्रतिनिधि',         'en' => 'Representatives'],
                    'settings.employees'         => ['label' => 'कर्मचारी',             'en' => 'Employees'],
                    'settings.gallery'           => ['label' => 'फोटो ग्यालरी',         'en' => 'Photo Gallery'],
                    'settings.downloads'         => ['label' => 'डाउनलोड लिंक',        'en' => 'Download Links'],
                    'settings.content'           => ['label' => 'सामग्री (प्रोफाइल/नक्सा/स्वागत)', 'en' => 'Content (Profile/Map/Welcome)'],
                    'settings.lock_unlock'       => ['label' => 'लक/अनलक',             'en' => 'Lock / Unlock'],
                ],
            ],
        ];
    }

    /** Default permissions for each role. */
    public static function defaults(): array
    {
        return [
            'super_admin' => self::allPermissions(),

            'municipal_admin' => [
                // Households
                'households.view','households.create','households.edit','households.delete',
                'households.submit','households.approve','households.reject','households.publish',
                'households.mark_deceased',
                // Institutions
                'institutions.view','institutions.create','institutions.edit','institutions.delete',
                'institutions.submit','institutions.approve','institutions.reject','institutions.publish',
                // Businesses
                'businesses.view','businesses.create','businesses.edit','businesses.delete',
                'businesses.submit','businesses.approve','businesses.reject','businesses.publish',
                // Users
                'users.view','users.create','users.edit','users.delete',
                'users.toggle_active','users.reset_password',
                // Review
                'review.view','review.approve','review.reject','review.escalate',
                // Edit Requests
                'edit_requests.view','edit_requests.approve','edit_requests.reject',
                // Reports
                'reports.view','reports.export',
                // Import
                'import.households','import.institutions','import.businesses',
                // Settings
                'settings.general','settings.wards','settings.survey_options',
                'settings.representatives','settings.employees','settings.gallery',
                'settings.downloads','settings.content','settings.lock_unlock',
            ],

            'ward_admin' => [
                // Households
                'households.view','households.create','households.edit','households.delete',
                'households.submit','households.approve','households.reject','households.publish',
                'households.mark_deceased',
                // Institutions
                'institutions.view','institutions.create','institutions.edit','institutions.delete',
                'institutions.submit','institutions.approve','institutions.reject','institutions.publish',
                // Businesses
                'businesses.view','businesses.create','businesses.edit','businesses.delete',
                'businesses.submit','businesses.approve','businesses.reject','businesses.publish',
                // Review queue
                'review.view','review.approve','review.reject','review.escalate',
                // Edit requests
                'edit_requests.view','edit_requests.approve','edit_requests.reject',
                // Reports
                'reports.view','reports.export',
            ],

            'ward_reviewer' => [
                // Households
                'households.view','households.approve','households.reject','households.mark_deceased',
                // Institutions
                'institutions.view','institutions.approve','institutions.reject',
                // Businesses
                'businesses.view','businesses.approve','businesses.reject',
                // Review
                'review.view','review.approve','review.reject','review.escalate',
                // Edit Requests
                'edit_requests.view','edit_requests.request',
                // Reports
                'reports.view',
            ],

            'enumerator' => [
                // Households
                'households.view','households.create','households.edit','households.submit',
                // Institutions
                'institutions.view','institutions.create','institutions.edit','institutions.submit',
                // Businesses
                'businesses.view','businesses.create','businesses.edit','businesses.submit',
                // Reports
                'reports.view',
            ],
        ];
    }

    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::groups() as $group) {
            $all = array_merge($all, array_keys($group['perms']));
        }
        return $all;
    }

    /** Roles available in the system. */
    public static function roles(): array
    {
        return [
            ['name' => 'super_admin',     'label' => 'Super Admin',     'icon' => 'fa-solid fa-crown'],
            ['name' => 'municipal_admin', 'label' => 'Municipal Admin', 'icon' => 'fa-solid fa-shield-halved'],
            ['name' => 'ward_admin',      'label' => 'Ward Admin',      'icon' => 'fa-solid fa-user-shield'],
            ['name' => 'ward_reviewer',   'label' => 'Ward Reviewer',   'icon' => 'fa-solid fa-eye'],
            ['name' => 'enumerator',      'label' => 'Enumerator',      'icon' => 'fa-solid fa-clipboard-user'],
        ];
    }
}
