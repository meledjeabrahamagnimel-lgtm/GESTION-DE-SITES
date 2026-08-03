<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
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

        /*
         * Supprime les balises <link rel="preload"> ajoutées devant les feuilles de
         * style et les scripts. Elles n'apportent rien ici : la balise qui charge
         * réellement la ressource suit immédiatement, le navigateur la télécharge donc
         * au même instant. En revanche, lors d'une navigation wire:navigate, la balise
         * de préchargement est réinjectée alors que la feuille est déjà en cache, et
         * la console affiche « was preloaded using link preload but not used ».
         */
        Vite::usePreloadTagAttributes(false);
    }
}
