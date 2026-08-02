@props(['periode', 'sites' => null, 'siteFiltre' => null])

@php
    $onglets = ['jour' => 'Jour', 'semaine' => 'Semaine', 'mois' => 'Mois', 'periode' => 'Période'];
@endphp

<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
    <div style="display:flex; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; overflow:hidden;">
        @foreach ($onglets as $cle => $libelle)
            <button type="button" wire:click="$set('periode', '{{ $cle }}')"
                style="padding:9px 18px; font-size:14px; font-weight:700; cursor:pointer; border:0; border-right:1px solid var(--th-ligne,#E2E0D8);
                       background:{{ $periode === $cle ? 'var(--th-ink,#191B20)' : '#fff' }};
                       color:{{ $periode === $cle ? '#fff' : '#4B4E55' }};">
                {{ $libelle }}
            </button>
        @endforeach
    </div>

    @if ($sites !== null)
        <select wire:model.live="siteFiltre" style="padding:9px 14px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px; background:#fff; font-weight:600;">
            <option value="">Tous les sites (consolidé)</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->nom }}</option>
            @endforeach
        </select>
    @endif
</div>

@if ($periode === 'periode')
    <div style="display:flex; gap:12px; align-items:center; margin:-8px 0 20px; font-size:14px;">
        <label style="color:#6B6E76; font-weight:600;">Du</label>
        <input type="date" wire:model.live="dateDebut" style="padding:7px 10px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:14px;">
        <label style="color:#6B6E76; font-weight:600;">Au</label>
        <input type="date" wire:model.live="dateFin" style="padding:7px 10px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:14px;">
    </div>
@endif
