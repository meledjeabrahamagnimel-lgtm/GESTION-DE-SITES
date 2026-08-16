<?php

namespace Modules\Superviseur\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Superviseur de ville : le pilotage. Prospects, devis, chiffre d'affaires,
 * charges, trésorerie, commerciaux, et la nomination des accès de sa ville.
 */
class SuperviseurServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Superviseur', 'resources/views'), 'superviseur');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('Superviseur', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « pilotage.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('Superviseur', 'resources/views')]);
    }
}
