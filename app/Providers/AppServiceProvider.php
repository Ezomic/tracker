<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The app is passwordless: turn off Fortify's password-confirmation gate
        // on the passkey routes. The EnsureEmailConfirmed middleware re-gates
        // them with an email-code re-auth instead.
        config(['fortify-options.passkeys.confirmPassword' => false]);

        // Pin the product name in code so it no longer depends on the server's
        // APP_NAME (which shipped as "Laravel"). Covers the shared Inertia name
        // prop and the outgoing mail "from" name.
        config([
            'app.name' => 'Tracker',
            'mail.from.name' => 'Tracker',
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Surface N+1 lazy loads and silently discarded mass-assignments during
        // development and tests; stay lenient in prod. Missing-attribute strictness
        // is deliberately left off: the app legitimately selects partial columns.
        $strict = ! app()->isProduction();
        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // The API docs (Scramble at /docs/api) are internal reference; any
        // signed-in user may view them, in every environment.
        Gate::define('viewApiDocs', fn (?User $user): bool => $user !== null);

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
