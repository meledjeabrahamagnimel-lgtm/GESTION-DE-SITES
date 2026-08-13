@props(['periode', 'villes' => null, 'villeUnique' => null, 'villeFiltre' => null, 'activiteFiltre' => null])

@php
    $onglets = ['jour' => 'Jour', 'semaine' => 'Semaine', 'mois' => 'Mois', 'periode' => 'Période'];
    // La précision Mécanique/Sinistre/Consolidé n'a de sens qu'une fois une ville
    // précise en contexte : soit l'utilisateur n'en a qu'une (fixe), soit le Gérant
    // vient d'en choisir une dans la liste.
    $afficherPrecision = $villeUnique !== null || ($villes !== null && $villeFiltre);
@endphp

<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
    <div style="display:flex; gap:6px;">
        @foreach ($onglets as $cle => $libelle)
            <button type="button" wire:click="$set('periode', '{{ $cle }}')"
                class="onglet {{ $periode === $cle ? 'est-actif' : '' }}">
                {{ $libelle }}
            </button>
        @endforeach
    </div>

    @if ($villeUnique)
        <span style="font-size:13.5px; font-weight:700; color:var(--th-ink,#191B20); padding:9px 14px; background:#F4F3EF; border-radius:8px; white-space:nowrap;">
            Ville : {{ $villeUnique->nom }}
        </span>
    @elseif ($villes !== null)
        <select wire:model.live="villeFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Toutes les villes (consolidé)</option>
            @foreach ($villes as $ville)
                <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
            @endforeach
        </select>
    @endif

    @if ($afficherPrecision)
        <select wire:model.live="activiteFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Consolidé (les deux sites)</option>
            <option value="Mécanique">Mécanique</option>
            <option value="Sinistre">Sinistre</option>
        </select>
    @endif
</div>

@if ($periode === 'periode')
    <div style="display:flex; gap:12px; align-items:center; margin:-8px 0 20px; font-size:14px;">
        <label style="color:var(--th-gris); font-weight:600;">Du</label>
        <input type="date" wire:model.live="dateDebut" class="champ" style="width:auto;">
        <label style="color:var(--th-gris); font-weight:600;">Au</label>
        <input type="date" wire:model.live="dateFin" class="champ" style="width:auto;">
    </div>
@endif
