@props(['label', 'value', 'sub' => null, 'accent' => false, 'couleur' => null, 'bon' => false])

<div class="carte carte-kpi {{ $accent ? 'est-alerte' : ($bon ? 'est-bon' : '') }}">
    <div class="kpi-libelle">{{ $label }}</div>
    <div class="kpi-valeur {{ $accent ? 'est-alerte' : ($bon ? 'est-bon' : '') }}"
        @if ($couleur) style="color:{{ $couleur }};" @endif>{{ $value }}</div>
    @if ($sub)
        <div class="kpi-sous">{{ $sub }}</div>
    @endif
</div>
