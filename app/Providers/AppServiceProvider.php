<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsStaff;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Carbon::setLocale($this->app->getLocale());

        Livewire::addPersistentMiddleware([
            EnsureUserIsStaff::class,
        ]);
    }
}
