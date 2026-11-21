<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DeviceAlerts;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // Links and forms follow APP_URL when it is https, even behind a tunnel or proxy that forwards plain HTTP.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->defineGates();
        $this->recordSignIns();
    }

    /**
     * Throttle login attempts and public registration submissions.
     */
    /**
     * Every sign-in and sign-out goes to the audit trail, whichever step completed it.
     */
    private function recordSignIns(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            app(ActivityLogger::class)->record('auth.login', null, $event->user, $event->user);
            app(DeviceAlerts::class)->check($event->user, request());
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(ActivityLogger::class)->record('auth.logout', null, $event->user, $event->user);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('login-ip', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
    }

    /**
     * Role rules for the committee area. Route middleware applies these with the can: middleware.
     */
    private function defineGates(): void
    {
        Gate::define('view-members', fn (User $user): bool => $user->canManageMembers());
        Gate::define('manage-members', fn (User $user): bool => $user->canManageMembers());
        Gate::define('export-members', fn (User $user): bool => $user->canManageMembers());
        Gate::define('manage-appointments', fn (User $user): bool => $user->canManageMembers());
        Gate::define('manage-positions', fn (User $user): bool => $user->isManagement());
        Gate::define('manage-categories', fn (User $user): bool => $user->isManagement());
        Gate::define('manage-accounts', fn (User $user): bool => $user->isManagement());
        Gate::define('manage-account-positions', fn (User $user): bool => $user->isSuperadmin());
        Gate::define('manage-settings', fn (User $user): bool => $user->isManagement());
        Gate::define('manage-registration', fn (User $user): bool => $user->isManagement());
        Gate::define('manage-branding', fn (User $user): bool => $user->isSuperadmin());
        Gate::define('manage-invitations', fn (User $user): bool => $user->canInvite());
    }
}
