<?php

namespace Modules\SuperAdmin\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Plateforme : entreprises clientes, accès, journal d'activité et maintenance.
 * Seul module dont le périmètre dépasse une entreprise.
 */
class SuperAdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(module_path('SuperAdmin', 'resources/views'), 'superadmin');
        // Les routes d'un module doivent traverser le groupe « web » (session, cookies,
        // protection CSRF) : chargées seules, elles s'en trouveraient privées et toute
        // page authentifiée échouerait faute de session.
        Route::middleware('web')->group(module_path('SuperAdmin', 'routes/web.php'));

        // Les écrans de ce module sont des composants Volt : ils sont résolus par leur
        // chemin relatif à ce dossier — « superadmin.mon-ecran » — ce qui garantit qu'aucun
        // nom n'entre en collision avec celui d'un autre module.
        Volt::mount([module_path('SuperAdmin', 'resources/views')]);
    }
}
