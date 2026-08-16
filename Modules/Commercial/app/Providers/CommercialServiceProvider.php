<?php

namespace Modules\Commercial\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Commercial : ses prospections, sa performance individuelle et ses notes. Il est
 * rattaché à une ville, jamais à une activité ni à un lieu précis.
 */
class CommercialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Commercial', 'resources/views'), 'commercial');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('Commercial', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « commercial.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('Commercial', 'resources/views')]);
    }
}
