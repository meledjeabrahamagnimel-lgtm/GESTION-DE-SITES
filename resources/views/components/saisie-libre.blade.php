@props(['sujet', 'ouvert' => false])

@php $donnees = $sujet->relationLoaded('donneesLibres') ? $sujet->donneesLibres : $sujet->donneesLibres()->get(); @endphp

<div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
    @foreach ($donnees as $donnee)
        <span class="pastille pastille-bleu" style="font-weight:600;">
            {{ $donnee->intitule }} : {{ $donnee->valeur ?? '—' }}
            <button type="button" wire:click="supprimerSaisieLibre({{ $donnee->id }})"
                title="Retirer cette information"
                style="background:none; border:0; cursor:pointer; color:inherit; padding:0 0 0 4px; font-weight:700;">×</button>
        </span>
    @endforeach

    <button type="button"
        wire:click="ouvrirSaisieLibre(@js(get_class($sujet)), {{ $sujet->id }})"
        class="bouton bouton-secondaire bouton-petit" title="Ajouter une information libre">+ Saisie libre</button>
</div>

@if ($ouvert)
    <div style="display:flex; gap:8px; align-items:flex-end; margin-top:8px; flex-wrap:wrap; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:10px;">
        <x-champ label="Intitulé" model="libreIntitule" width="200" requis="true" placeholder="Ex : Immatriculation" />
        <x-champ label="Valeur" model="libreValeur" placeholder="Ex : 1234 AB 01" />
        <button type="button" wire:click="enregistrerSaisieLibre" class="bouton bouton-petit">Enregistrer</button>
        <button type="button" wire:click="fermerSaisieLibre" class="bouton bouton-secondaire bouton-petit">Annuler</button>
    </div>
@endif
