<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

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
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => rtrim(config('smartbite.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
