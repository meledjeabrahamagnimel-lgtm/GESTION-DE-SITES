<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Facture;
use App\Domain\Shared\Services\PeriodeCalculateur;
use App\Domain\Tenants\Models\Site;
use function Livewire\Volt\{state, computed};

state([
    'periode' => 'semaine',
    'dateDebut' => null,
    'dateFin' => null,
    'siteFiltre' => '',
]);

$estGerant = computed(fn () => auth()->user()->hasRole('gerant'));
$monSite = computed(fn () => $this->estGerant ? null : Site::where('responsable_id', auth()->id())->first());
$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));
$sites = computed(fn () => $this->estGerant ? Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get() : null);

$classement = computed(function () {
    [$debut, $fin] = $this->plage;
    $joursPeriode = PeriodeCalculateur::nombreDeJours($debut, $fin);

    $q = Commercial::actifs()->where('est_spontane', false)->with('site');

    if ($this->estGerant) {
        if ($this->siteFiltre) {
            $q->where('site_id', $this->siteFiltre);
        }
    } else {
        $q->where('site_id', $this->monSite?->id ?? 0);
    }

    $commerciaux = $q->get();

    // CA du site sur la période, pour exprimer la contribution de chaque commercial.
    $caParSite = Facture::whereIn('site_id', $commerciaux->pluck('site_id')->unique())
        ->whereBetween('date', [$debut, $fin])
        ->selectRaw('site_id, SUM(montant) as total')
        ->groupBy('site_id')
        ->pluck('total', 'site_id');

    return $commerciaux->map(function ($commercial) use ($debut, $fin, $joursPeriode, $caParSite) {
        $realisation = (int) Facture::where('commercial_id', $commercial->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $objectifProrata = (int) round($commercial->objectif_mensuel / 30 * $joursPeriode);
        $ecart = $realisation - $objectifProrata;
        $caSite = (int) ($caParSite[$commercial->site_id] ?? 0);

        return [
            'commercial' => $commercial,
            'objectif' => $objectifProrata,
            'objectifJournalier' => (int) round($commercial->objectif_mensuel / 30),
            'realisation' => $realisation,
            'ecart' => $ecart,
            'taux' => $objectifProrata > 0 ? $realisation / $objectifProrata : null,
            'contribution' => $caSite > 0 ? $realisation / $caSite : null,
        ];
    })->sortByDesc(fn ($l) => $l['taux'] ?? -1)->values();
});

$kpis = computed(function () {
    $objectifTotal = $this->classement->sum('objectif');
    $realisationTotal = $this->classement->sum('realisation');

    return [
        'nombre' => $this->classement->count(),
        'objectif' => $objectifTotal,
        'realisation' => $realisationTotal,
        'ecart' => $realisationTotal - $objectifTotal,
        'taux' => $objectifTotal > 0 ? $realisationTotal / $objectifTotal : null,
    ];
});

$graphique = computed(fn () => [
    'labels' => $this->classement->pluck('commercial.nom')->all(),
    'datasets' => [
        ['label' => 'Objectif (prorata)', 'data' => $this->classement->pluck('objectif')->all(), 'color' => '#191B20'],
        ['label' => 'Réalisation', 'data' => $this->classement->pluck('realisation')->all(), 'color' => '#C8102E'],
    ],
]);

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Commerciaux" :value="$this->kpis['nombre']" />
        <x-kpi-card label="Objectif de la période" :value="ae($this->kpis['objectif'])" />
        <x-kpi-card label="Réalisation totale" :value="ae($this->kpis['realisation'])" />
        <x-kpi-card label="Écart global" :value="ae($this->kpis['ecart'])" :couleur="$this->kpis['ecart'] >= 0 ? '#0E9F6E' : '#C8102E'" />
        <x-kpi-card label="Taux d'atteinte global" :value="an($this->kpis['taux'])" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Objectif vs réalisation par commercial" id="commerciaux-objectif"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div class="carte">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Classement des commerciaux par performance</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>N°</th>
                        <th>Commercial</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th>Site</th>
                        @endif
                        <th>Activité</th>
                        <th>Objectif mensuel</th>
                        <th>Objectif journalier (mensuel/30)</th>
                        <th>Objectif de la période</th>
                        <th>Réalisation</th>
                        <th>Écart</th>
                        <th>Taux d'atteinte</th>
                        <th>Contribution au CA du site</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->classement as $i => $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8); {{ $i === 0 ? 'background:#FFFBEA;' : '' }}">
                            <td>
                                @php
                                    // Or, argent, bronze pour le podium, comme dans la maquette.
                                    $medaille = ['#D4AF37', '#9CA3AF', '#B87333'][$i] ?? null;
                                @endphp
                                @if ($medaille)
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px;
                                                 border-radius:99px; background:{{ $medaille }}; color:#fff; font-weight:700;
                                                 font-family:'Barlow Condensed',sans-serif; font-size:14px;">{{ $i + 1 }}</span>
                                @else
                                    <span style="font-weight:800;">{{ $i + 1 }}</span>
                                @endif
                            </td>
                            <td style="font-weight:700;">{{ $ligne['commercial']->numero }}</td>
                            <td style="font-weight:700;">{{ $ligne['commercial']->nom }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td>{{ $ligne['commercial']->site->nom }}</td>
                            @endif
                            <td>{{ $ligne['commercial']->activite }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['commercial']->objectif_mensuel) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['objectifJournalier']) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['objectif']) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['realisation']) }}</td>
                            <td style="font-variant-numeric:tabular-nums; color:{{ $ligne['ecart'] >= 0 ? '#0E9F6E' : '#C8102E' }};">{{ ae($ligne['ecart']) }}</td>
                            <td style="font-weight:700; color:{{ ($ligne['taux'] ?? 0) >= 1 ? '#0E9F6E' : '#D97706' }};">{{ an($ligne['taux']) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ an($ligne['contribution']) }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 11 : 10" texte="Aucun commercial actif pour ce filtre." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
