<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

/**
 * Ne monte plus ici que les composants Livewire transverses (cloche de notifications,
 * rappel). Les écrans, eux, appartiennent chacun à un module et sont montés par le
 * fournisseur de ce module : c'est ce qui garantit qu'un écran ne peut pas exister
 * en dehors du rôle auquel il se rapporte.
 */
class VoltServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Volt::mount([
            config('livewire.view_path', resource_path('views/livewire')),
        ]);
    }
}
