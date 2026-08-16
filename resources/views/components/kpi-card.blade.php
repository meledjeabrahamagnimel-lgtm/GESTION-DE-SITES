@props([
    'label',
    'value',
    'sub' => null,
    'accent' => false,
    'couleur' => null,
    'bon' => false,
    'mecanique' => null,
    'sinistre' => null,
    // Part de l'indicateur dont l'activité n'est pas renseignée. Affichée seulement si
    // elle n'est pas nulle : les trois lignes doivent toujours refaire le total exact,
    // faute de quoi le lecteur croirait à une erreur de calcul.
    'nonVentile' => null,
])

<div class="carte carte-kpi {{ $accent ? 'est-alerte' : ($bon ? 'est-bon' : '') }}">
    <div class="kpi-libelle">{{ $label }}</div>
    <div class="kpi-valeur {{ $accent ? 'est-alerte' : ($bon ? 'est-bon' : '') }}"
        @if ($couleur) style="color:{{ $couleur }};" @endif>{{ $value }}</div>
    @if ($sub)
        <div class="kpi-sous">{{ $sub }}</div>
    @endif
    @if ($mecanique !== null || $sinistre !== null || $nonVentile !== null)
        <div style="margin-top:7px; padding-top:7px; border-top:1px solid var(--th-ligne,#E2E0D8); display:flex; flex-direction:column; gap:3px;">
            @if ($mecanique !== null)
                <div style="display:flex; align-items:center; gap:6px; font-size:11.5px; color:#4B4E55;">
                    <span style="display:inline-block; width:3px; height:11px; border-radius:2px; background:#2563EB;"></span>
                    Mécanique&nbsp;<b style="font-variant-numeric:tabular-nums;">{{ $mecanique }}</b>
                </div>
            @endif
            @if ($sinistre !== null)
                <div style="display:flex; align-items:center; gap:6px; font-size:11.5px; color:#4B4E55;">
                    <span style="display:inline-block; width:3px; height:11px; border-radius:2px; background:#D97706;"></span>
                    Sinistre&nbsp;<b style="font-variant-numeric:tabular-nums;">{{ $sinistre }}</b>
                </div>
            @endif
            @if ($nonVentile !== null)
                <div style="display:flex; align-items:center; gap:6px; font-size:11.5px; color:var(--th-gris,#6B6E76);"
                    title="Opérations saisies sans précision d'activité.">
                    <span style="display:inline-block; width:3px; height:11px; border-radius:2px; background:#9CA3AF;"></span>
                    Non ventilé&nbsp;<b style="font-variant-numeric:tabular-nums;">{{ $nonVentile }}</b>
                </div>
            @endif
        </div>
    @endif
</div>
