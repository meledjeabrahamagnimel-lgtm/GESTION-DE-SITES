@props(['titre', 'id', 'type' => 'bar', 'labels' => [], 'datasets' => [], 'vide' => false, 'messageVide' => 'Aucune donnée sur cette période.'])

@php
    $aDesDonnees = ! $vide && count($labels) > 0 && collect($datasets)->contains(fn ($d) => collect($d['data'] ?? [])->sum() > 0);
    $cle = $id.'-'.md5(json_encode(['l' => $labels, 'd' => $datasets, 'a' => $aDesDonnees]));
@endphp

<div class="carte">
    <h3 class="titre-section">{{ $titre }}</h3>

    @if ($aDesDonnees)
        <div wire:key="{{ $cle }}" wire:ignore style="position:relative; height:250px;">
            <canvas
                x-data
                x-init="
                    new Chart($el.getContext('2d'), {
                        type: @js($type),
                        data: {
                            labels: @js($labels),
                            datasets: @js($datasets).map(d => ({
                                label: d.label,
                                data: d.data,
                                backgroundColor: d.type === 'line' ? 'transparent' : (d.color ?? '#C8102E'),
                                borderColor: d.color ?? '#C8102E',
                                type: d.type ?? @js($type),
                                borderWidth: d.type === 'line' ? 2 : 0,
                                borderRadius: d.type === 'line' ? 0 : 4,
                                pointRadius: d.type === 'line' ? 2 : undefined,
                                // Sans plafond, deux ou trois barres occupent toute la largeur
                                // et le graphique devient illisible.
                                maxBarThickness: 46,
                                categoryPercentage: .7,
                                barPercentage: .9,
                                yAxisID: d.axe ?? 'y',
                                tension: .3,
                            }))
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { display: @js(count($datasets) > 1), position: 'bottom', labels: { boxWidth: 12, font: { size: 12.5 } } } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 11.5 }, maxRotation: 0, autoSkip: true } },
                                y: {
                                    grid: { color: '#F0EFEA' },
                                    beginAtZero: true,
                                    ticks: {
                                        font: { size: 11.5 },
                                        maxTicksLimit: 6,
                                        // Montants en FCFA abrégés : 1 250 000 devient « 1,3 M ».
                                        callback: v => Math.abs(v) >= 1e6 ? (v/1e6).toFixed(1).replace('.', ',') + ' M'
                                               : (Math.abs(v) >= 1e3 ? Math.round(v/1e3) + ' k' : v),
                                    },
                                },
                                // Axe secondaire, utilisé par les séries en pourcentage (taux de transformation).
                                y1: {
                                    position: 'right',
                                    display: @js(collect($datasets)->contains(fn ($d) => ($d['axe'] ?? '') === 'y1')),
                                    beginAtZero: true,
                                    grid: { drawOnChartArea: false },
                                    ticks: { font: { size: 11.5 }, maxTicksLimit: 5, callback: v => v + ' %' },
                                },
                            },
                        },
                    })
                "
            ></canvas>
        </div>
    @else
        {{--
            Pas de données : on garde la carte, son titre et sa légende, avec un filigrane.
            Aucune graduation d'axe n'est affichée puisqu'il n'y a rien à graduer.
        --}}
        <div class="etat-vide">
            <span class="etat-vide-texte">{{ $messageVide }}</span>
            <span class="etat-vide-sous">Le graphique s'affichera dès la première saisie.</span>
        </div>
        @if (count($datasets) > 0)
            <div class="legende-vide">
                @foreach ($datasets as $serie)
                    <span><i style="background:{{ $serie['color'] ?? '#C8102E' }};"></i>{{ $serie['label'] ?? '' }}</span>
                @endforeach
            </div>
        @endif
    @endif
</div>
