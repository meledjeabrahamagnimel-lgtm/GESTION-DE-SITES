<?php

namespace Modules\Encadrement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Superviseur de ville et responsable de site. Les deux partagent exactement les
 * mêmes écrans — seul leur périmètre diffère, résolu par Site::visiblesPour() — d'où
 * un module unique plutôt que deux dossiers au contenu identique.
 *
 * Deux familles d'écrans : « saisie » (la journée d'atelier) et « pilotage » (les
 * indicateurs, que le gérant consulte également).
 */
class EncadrementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Encadrement', 'resources/views'), 'encadrement');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('Encadrement', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « encadrement.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('Encadrement', 'resources/views')]);
    }
}
