@props(['periode', 'sites' => null, 'siteFiltre' => null])

@php
    $onglets = ['jour' => 'Jour', 'semaine' => 'Semaine', 'mois' => 'Mois', 'periode' => 'Période'];
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

    @if ($sites !== null)
        <select wire:model.live="siteFiltre" class="champ" style="width:auto; font-weight:600; background:#fff;">
            <option value="">Tous les sites (consolidé)</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->nom }}</option>
            @endforeach
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
