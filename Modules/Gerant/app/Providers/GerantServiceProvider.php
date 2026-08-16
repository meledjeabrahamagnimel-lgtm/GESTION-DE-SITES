<?php

namespace Modules\Gerant\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Direction : le tableau de bord à 360 sur toutes les villes, et les paramètres de
 * l'entreprise. Le gérant consulte et administre, il ne saisit jamais d'écriture.
 */
class GerantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Gerant', 'resources/views'), 'gerant');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('Gerant', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « gerant.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('Gerant', 'resources/views')]);
    }
}
