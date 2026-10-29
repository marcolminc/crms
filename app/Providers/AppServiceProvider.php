<?php

namespace App\Providers;

use App\Contracts\ContactMessageChannel;
use App\Services\Contact\AdminAlertContactChannel;
use App\Services\Contact\ContactMessageDispatcher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Public contact enquiries travel through a dispatcher so delivery is
        // decoupled from the controller. Registering another
        // ContactMessageChannel (e.g. an e-mail channel) here is all it takes
        // to fan the enquiry out to a new destination.
        $this->app->bind(ContactMessageChannel::class, AdminAlertContactChannel::class);
        $this->app->bind(
            ContactMessageDispatcher::class,
            fn ($app) => new ContactMessageDispatcher([
                $app->make(AdminAlertContactChannel::class),
            ])
        );
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
