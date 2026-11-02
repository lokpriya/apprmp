<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LocalGovernment;
use App\Support\GuardsSettingsLock;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Super admin: switch optional modules on or off for this municipality, and set their options. */
class ModulesController extends Controller
{
    use GuardsSettingsLock;

    public function index()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $lgId = auth()->user()->local_government_id;

        $modules = [];
        foreach (Modules::catalogue() as $key => $meta) {
            $modules[] = [
                'key'         => $key,
                'label'       => $meta['label'],
                'description' => $meta['description'],
                'icon'        => $meta['icon'],
                'enabled'     => Modules::enabled($key, $lgId),
                'settings'    => Modules::settings($key, $lgId),
            ];
        }

        return Inertia::render('Admin/Modules', ['modules' => $modules]);
    }

    public function update(Request $request, string $module): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        abort_unless(isset(Modules::catalogue()[$module]), 404);
        $this->abortIfSettingsLocked();

        $data = $request->validate(array_merge(
            ['enabled' => ['required', 'boolean']],
            $this->settingsRules($module),
        ));

        $lg     = LocalGovernment::findOrFail(auth()->user()->local_government_id);
        $config = $lg->modules_config ?? [];
        $before = $config[$module] ?? null;

        $config[$module] = [
            'enabled'  => (bool) $data['enabled'],
            'settings' => array_intersect_key($data['settings'] ?? [], Modules::catalogue()[$module]['settings']),
        ];
        $lg->update(['modules_config' => $config]);   // saved() busts the LG cache

        AuditLog::create([
            'user_id'        => auth()->id(),
            'event_type'     => 'module_config',
            'auditable_type' => LocalGovernment::class,
            'auditable_id'   => $lg->id,
            'old_values'     => [$module => $before],
            'new_values'     => [$module => $config[$module]],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        $label = Modules::catalogue()[$module]['label'];
        return back()->with('success', $label . ($data['enabled'] ? ' सक्रिय गरियो।' : ' बन्द गरियो।'));
    }

    private function settingsRules(string $module): array
    {
        return match ($module) {
            Modules::SHRAMDAAN => [
                'settings'                => ['required', 'array'],
                'settings.hours_per_day'  => ['required', 'numeric', 'min:1', 'max:24'],
                'settings.min_age'        => ['required', 'integer', 'min:0', 'max:100'],
                'settings.tier_gold'      => ['required', 'numeric', 'min:0', 'max:365', 'gte:settings.tier_silver'],
                'settings.tier_silver'    => ['required', 'numeric', 'min:0', 'max:365', 'gte:settings.tier_bronze'],
                'settings.tier_bronze'    => ['required', 'numeric', 'min:0', 'max:365'],
                'settings.incentive_text' => ['nullable', 'string', 'max:600'],
            ],
            default => [],
        };
    }
}
