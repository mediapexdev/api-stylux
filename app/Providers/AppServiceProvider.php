<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */

    public const POMPISTE_ROLE=1;
    public const ADMIN_ROLE=4;
    public const GERANT_ROLE=2;
    public const CHEFPIST_ROLE=3;
    
    public function boot()
    {
        Schema::defaultStringLength(191);
    }
}
