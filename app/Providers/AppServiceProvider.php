<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Hors production : une clé absente de #[Fillable] lève une exception au lieu
        // d'être silencieusement ignorée (a déjà causé un bug réel de sécurité).
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
    }
}
