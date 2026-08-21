<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Modules\Noyau\Tracabilite\Ecouteurs\FermeLaSession;
use Modules\Noyau\Tracabilite\Ecouteurs\OuvreLaSession;

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

        /*
         * Traçabilité des connexions. Déclarée ici plutôt que découverte
         * automatiquement : les écouteurs vivent dans un module, hors du dossier que
         * Laravel inspecte, et une traçabilité qui ne s'enregistre pas ne se remarque
         * qu'au moment où l'on a besoin du journal — c'est-à-dire trop tard.
         */
        Event::listen(Login::class, OuvreLaSession::class);
        Event::listen(Logout::class, FermeLaSession::class);
    }
}
