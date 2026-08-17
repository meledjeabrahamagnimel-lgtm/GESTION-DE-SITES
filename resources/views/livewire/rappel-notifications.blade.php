<?php

use Modules\Noyau\Commun\Modeles\AbonnementPush;
use Modules\Noyau\Commun\Services\WebPush\EnvoyeurPush;
use Illuminate\Support\Facades\Validator;
use function Livewire\Volt\{computed, state};

/*
 * Rappel affiché tant qu'aucun appareil n'est enregistré, pour qu'on n'oublie pas
 * d'activer les alertes. Volontairement un bandeau, et non une demande d'autorisation
 * automatique : les navigateurs refusent une telle demande sans clic de l'utilisateur,
 * et Chrome sanctionne durablement les sites qui la déclenchent au chargement.
 *
 * Le bouton active tout depuis le bandeau : autorisation du navigateur, agent de
 * service et abonnement de l'appareil, en un seul geste. Il renvoyait auparavant vers
 * la page des réglages, où il fallait cliquer une seconde fois sur le même bouton —
 * un aller-retour pour rien, là où le clic du bandeau suffit à satisfaire
 * l'exigence du navigateur.
 */
state(['masque' => false]);

$aBesoin = computed(fn () => ! $this->masque
    && EnvoyeurPush::estConfigure()
    && ! AbonnementPush::where('user_id', auth()->id())->exists());

$masquer = function () {
    $this->masque = true;
};

/**
 * Enregistre l'abonnement obtenu par le navigateur.
 *
 * Même traitement que sur la page des réglages, et mêmes vérifications : les clés
 * viennent du navigateur, donc du client, et ne sont pas dignes de confiance par
 * nature. Le bandeau disparaît ensuite de lui-même, l'appareil étant désormais connu.
 */
$enregistrerAbonnement = function (string $endpoint, string $p256dh, string $auth, ?string $appareil = null) {
    Validator::make(
        ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth],
        [
            'endpoint' => ['required', 'url', 'max:2000'],
            'p256dh' => ['required', 'string', 'max:255'],
            'auth' => ['required', 'string', 'max:255'],
        ],
    )->validate();

    AbonnementPush::updateOrCreate(
        ['user_id' => auth()->id(), 'empreinte' => AbonnementPush::empreinteDe($endpoint)],
        [
            'endpoint' => $endpoint,
            'cle_p256dh' => $p256dh,
            'cle_auth' => $auth,
            'appareil' => $appareil ? str($appareil)->limit(190)->value() : null,
        ],
    );

    unset($this->aBesoin);
    $this->dispatch('annonce', texte: 'Alertes activées sur cet appareil.');
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

            {{-- Tout se fait ici : l'autorisation du navigateur, l'agent de service et
                 l'abonnement. Si l'appareil ne s'y prête pas, on renvoie vers les
                 réglages, qui expliquent précisément ce qui manque. --}}
            <span x-data="{
                    occupe: false,
                    async activer() {
                        if (! window.activerPush || ! window.pushDisponible || ! window.pushDisponible()) {
                            window.location = @js(route('mes-notifications'));
                            return;
                        }
                        this.occupe = true;
                        const issue = await window.activerPush(@js(config('webpush.cle_publique')), $wire);
                        this.occupe = false;
                        if (issue.echec || issue.etat !== 'granted') {
                            window.location = @js(route('mes-notifications'));
                        }
                    },
                }">
                <button type="button" @click="activer()" :disabled="occupe" class="bouton bouton-petit">
                    <span x-show="!occupe">Activer</span>
                    <span x-show="occupe" x-cloak>Activation…</span>
                </button>
            </span>

            <button type="button" wire:click="masquer" class="rappel-fermer" aria-label="Masquer">×</button>
        </div>
    @endif
</div>
