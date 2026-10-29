<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One password strength policy for the whole app (readme: password
        // strength). Register, profile, admin user create/edit and reset all
        // validate through Password::defaults() so the rule can never drift
        // between screens again. The x-password component mirrors these
        // requirements client-side, but this stays the authoritative check.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
