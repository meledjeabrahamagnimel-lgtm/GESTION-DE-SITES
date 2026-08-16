<?php

use Modules\Noyau\Exploitation\Modeles\Charge;
use Modules\Noyau\Exploitation\Modeles\Devis;
use Modules\Noyau\Exploitation\Modeles\Encaissement;
use Modules\Noyau\Exploitation\Modeles\Facture;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Modules\Noyau\Entreprises\Actions\PurgerDonneesEntreprise;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use function Livewire\Volt\{state, computed};

state([
    'entrepriseId' => '',
    'purgerCommerciaux' => false,
    'confirmation' => '',
    'resultat' => null,
]);

$entreprises = computed(fn () => Entreprise::orderBy('nom')->get());

$cible = computed(fn () => $this->entrepriseId ? Entreprise::find($this->entrepriseId) : null);

$volumes = computed(function () {
    if (! $this->entrepriseId) {
        return null;
    }

    $id = $this->entrepriseId;

    return [
        'Prospections' => Prospection::withoutGlobalScopes()->where('entreprise_id', $id)->count(),
        'Devis' => Devis::withoutGlobalScopes()->where('entreprise_id', $id)->count(),
        'Factures' => Facture::withoutGlobalScopes()->where('entreprise_id', $id)->count(),
        'Encaissements' => Encaissement::withoutGlobalScopes()->where('entreprise_id', $id)->count(),
        'Charges' => Charge::withoutGlobalScopes()->where('entreprise_id', $id)->count(),
    ];
});

$purger = function (PurgerDonneesEntreprise $action) {
    $this->validate([
        'entrepriseId' => ['required', 'exists:entreprises,id'],
    ], [], ['entrepriseId' => 'entreprise']);

    // Garde-fou : le nom exact de l'entreprise doit être retapé.
    if (trim($this->confirmation) !== $this->cible->nom) {
        $this->addError('confirmation', "Saisissez exactement « {$this->cible->nom} » pour confirmer la purge.");

        return;
    }

    $this->resultat = $action->executer($this->cible, $this->purgerCommerciaux);

    activity()
        ->causedBy(auth()->user())
        ->performedOn($this->cible)
        ->withProperties($this->resultat)
        ->log('Purge des données d\'exploitation');

    $this->reset(['confirmation', 'purgerCommerciaux']);
    unset($this->volumes);
};

?>

<div>
    <x-carte-section titre="Purger les données d'une entreprise">
        <div class="encart encart-alerte">
            <b>Action irréversible.</b> Les écritures d'exploitation (prospections, devis, factures,
            encaissements, charges, saisies journalières) de l'entreprise choisie seront définitivement
            supprimées, et la numérotation repartira à zéro. Les comptes, les sites et la fiche entreprise
            sont conservés.
        </div>

        <div class="bloc-saisie">
            <x-champ label="Entreprise" model="entrepriseId" type="select" live="true" width="280"
                :options="collect(['' => '— Choisir une entreprise —'])->union($this->entreprises->pluck('nom', 'id'))" />
        </div>

        @if ($this->volumes)
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin:18px 0;">
                @foreach ($this->volumes as $libelle => $nombre)
                    <x-kpi-card :label="$libelle" :value="$nombre" />
                @endforeach
            </div>

            <div style="margin-top:16px;">
                <label style="display:flex; align-items:center; gap:8px; font-size:14px; margin-bottom:14px;">
                    <input type="checkbox" wire:model="purgerCommerciaux">
                    Supprimer également les fiches commerciales (les comptes utilisateurs sont conservés)
                </label>

                <label class="champ-libelle">
                    Pour confirmer, saisissez le nom exact de l'entreprise : <b>{{ $this->cible?->nom }}</b>
                </label>
                <input type="text" wire:model="confirmation" class="champ" style="max-width:420px;">
                @error('confirmation') <span class="champ-erreur">{{ $message }}</span> @enderror

                <div style="margin-top:16px;">
                    <button type="button" wire:click="purger"
                        wire:confirm="Confirmez-vous la suppression définitive des données d'exploitation de cette entreprise ?"
                        class="bouton bouton-danger">
                        Purger définitivement les données
                    </button>
                </div>
            </div>
        @endif

        @if ($resultat)
            <div class="encart encart-succes" style="margin-top:18px;">
                <b>Purge effectuée.</b>
                {{ collect($resultat)->map(fn ($n, $t) => "$t : $n")->implode(' · ') }}
            </div>
        @endif
    </x-carte-section>
</div>
