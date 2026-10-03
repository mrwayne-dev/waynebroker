<?php

namespace App\Providers;

use App\Domains\Wallet\Observers\UserObserver as WalletUserObserver;
use App\Listeners\StampSessionVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureDefaults();

        // Explicit rather than relying on listener auto-discovery: a stamp
        // that silently stops being applied disables revocation without
        // breaking anything visible.
        Event::listen(Login::class, StampSessionVersion::class);

        // Explicit for the same reason, and because the alternative is the
        // #[ObservedBy] attribute on User, which would put a Wallet dependency
        // in the Identity model. Registration is the only wallet creation
        // trigger, so it is worth being able to read that fact in one place.
        User::observe(WalletUserObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

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
