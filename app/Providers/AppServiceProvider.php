<?php

namespace App\Providers;

use App\Models\MemberBusiness;
use App\Policies\BusinessPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // MemberBusiness → BusinessPolicy (auto-discovery fails: model name ≠ policy name)
        Gate::policy(MemberBusiness::class, BusinessPolicy::class);
    }
}
