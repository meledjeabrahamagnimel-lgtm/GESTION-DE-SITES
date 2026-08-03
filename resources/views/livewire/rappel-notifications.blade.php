<?php

use App\Domain\Shared\Models\AbonnementPush;
use App\Domain\Shared\Services\WebPush\EnvoyeurPush;
use function Livewire\Volt\{computed, state};

/*
 * Rappel affiché tant qu'aucun appareil n'est enregistré, pour qu'on n'oublie pas
 * d'activer les alertes. Volontairement un bandeau, et non une demande d'autorisation
 * automatique : les navigateurs refusent une telle demande sans clic de l'utilisateur,
 * et Chrome sanctionne durablement les sites qui la déclenchent au chargement.
 */
state(['masque' => false]);

$aBesoin = computed(fn () => ! $this->masque
    && EnvoyeurPush::estConfigure()
    && ! AbonnementPush::where('user_id', auth()->id())->exists());

$masquer = function () {
    $this->masque = true;
};

?>

<div>
    @if ($this->aBesoin)
        <div class="rappel-notifications">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                 style="flex:0 0 auto;">
                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
            </svg>

            <span style="flex:1; min-width:180px;">
                <strong>Vous ne recevez pas encore les alertes sur cet appareil.</strong>
                Sans cela, vous devez ouvrir l'application pour voir les nouveautés.
            </span>

            <a href="{{ route('mes-notifications') }}" wire:navigate class="bouton bouton-petit">Activer</a>

            <button type="button" wire:click="masquer" class="rappel-fermer" aria-label="Masquer">×</button>
        </div>
    @endif
</div>
