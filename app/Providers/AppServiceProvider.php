<?php

namespace App\Providers;

use App\Exports\SafeStringValueBinder;
use App\Models\MemberBusiness;
use App\Policies\BusinessPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // MemberBusiness → BusinessPolicy (auto-discovery fails: model name ≠ policy name)
        Gate::policy(MemberBusiness::class, BusinessPolicy::class);

        // Exported spreadsheets carry enumerator-entered free text. PhpSpreadsheet
        // binds any string starting with "=" as a formula, so a crafted respondent
        // name would execute when a municipal officer opens the download.
        //
        // Setting the binder on Cell alone was NOT enough: Laravel Excel's Writer
        // and Reader reset it to config('excel.value_binder.default') whenever
        // they are constructed, i.e. at the start of every export — so from
        // September to November 2026 every download used the stock binder and
        // the protection was silently off (found 2026-11-02 by opening a real
        // export). The config entry is what Laravel Excel re-applies; the direct
        // call still covers PhpSpreadsheet used without Laravel Excel.
        config(['excel.value_binder.default' => SafeStringValueBinder::class]);
        Cell::setValueBinder(new SafeStringValueBinder());
    }
}
