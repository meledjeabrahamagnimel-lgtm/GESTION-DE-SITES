{{--
    Pagination générique pour les tableaux alimentés par une Collection déjà chargée
    (computed() Volt). Pilotée uniquement par un $set() sur une propriété de state —
    jamais de lien/href — pour rester une simple mise à jour Livewire sur place, sans
    navigation ni rechargement : la position de défilement ne bouge donc jamais.
--}}
@props(['page', 'total', 'prop', 'parPage' => 10])
@php
    $dernierePage = max(1, (int) ceil($total / $parPage));
    $page = min($page, $dernierePage);
@endphp
{{--
    Le décompte s'affiche dès qu'il y a une ligne, même quand tout tient sur une page :
    ne rien montrer laissait croire que la pagination manquait, alors qu'il n'y avait
    simplement rien à tourner. Les deux boutons, eux, n'apparaissent qu'à partir de
    deux pages — désactivés en permanence, ils n'auraient fait qu'encombrer.
--}}
@if ($total > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:10px; padding-top:10px; border-top:1px solid var(--th-ligne,#E2E0D8); font-size:12.5px; color:var(--th-gris,#6B6E76); flex-wrap:wrap;">
        <span>Page {{ $page }} / {{ $dernierePage }} — {{ $total }} élément(s)</span>
        @if ($dernierePage > 1)
            <div style="display:flex; gap:6px;">
                <button type="button" wire:click="$set('{{ $prop }}', {{ max(1, $page - 1) }})" @if ($page <= 1) disabled @endif
                    style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:5px 12px; font-size:12.5px; font-weight:600; cursor:pointer; color:#4B4E55; {{ $page <= 1 ? 'opacity:.45; cursor:not-allowed;' : '' }}">
                    ‹ Précédent
                </button>
                <button type="button" wire:click="$set('{{ $prop }}', {{ min($dernierePage, $page + 1) }})" @if ($page >= $dernierePage) disabled @endif
                    style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:5px 12px; font-size:12.5px; font-weight:600; cursor:pointer; color:#4B4E55; {{ $page >= $dernierePage ? 'opacity:.45; cursor:not-allowed;' : '' }}">
                    Suivant ›
                </button>
            </div>
        @endif
    </div>
@endif
