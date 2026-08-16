<?php

namespace Modules\Comptabilite\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Comptabilité d'une ville : encaissements et décaissements. Une seule caisse par
 * ville, quel que soit le nombre de sites qui s'y trouvent.
 */
class ComptabiliteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Comptabilite', 'resources/views'), 'comptabilite');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('Comptabilite', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « comptabilite.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('Comptabilite', 'resources/views')]);
    }
}
