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
        Cell::setValueBinder(new SafeStringValueBinder());
    }
}
