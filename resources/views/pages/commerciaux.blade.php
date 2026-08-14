<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Facture;
use App\Domain\Shared\Services\PeriodeCalculateur;
use App\Domain\Tenants\Support\PerimetreSites;
use function Livewire\Volt\{state, computed, mount};

state([
    'periode' => 'calendrier',
    'dateDebut' => null,
    'dateFin' => null,
    'moisFiltre' => '',
    'semaineFiltre' => '',
    'jourFiltre' => '',
    'villeFiltre' => '',
    'activiteFiltre' => '',
    'commercialFiltre' => '',
    'pageClassement' => 1,
]);

mount(function () {
    $this->dateDebut ??= now()->startOfYear()->format('Y-m');
    $this->dateFin ??= now()->format('Y-m');
});

$updatedMoisFiltre = function () { $this->semaineFiltre = ''; $this->jourFiltre = ''; };
$updatedSemaineFiltre = function () { $this->jourFiltre = ''; };

$plage = computed(fn () => PeriodeCalculateur::plage(
    $this->periode, $this->dateDebut, $this->dateFin, $this->moisFiltre ?: null, $this->semaineFiltre ?: null, $this->jourFiltre ?: null
));
$mesVilles = computed(fn () => PerimetreSites::optionsVilles(auth()->user()));
$villeUnique = computed(fn () => PerimetreSites::villeUnique(auth()->user()));
$idsSites = computed(fn () => PerimetreSites::idsRetenus(auth()->user(), $this->villeFiltre, $this->activiteFiltre));
$libellePerimetre = computed(fn () => PerimetreSites::libellePerimetre(auth()->user(), $this->villeFiltre, $this->activiteFiltre));

$optionsCommerciaux = computed(fn () => Commercial::actifs()->where('est_spontane', false)
    ->whereIn('site_id', $this->idsSites)->orderBy('nom')->get());

$classement = computed(function () {
    [$debut, $fin] = $this->plage;

    $commerciaux = Commercial::actifs()->where('est_spontane', false)->with('site')
        ->whereIn('site_id', $this->idsSites)
        ->when($this->commercialFiltre, fn ($q) => $q->where('id', $this->commercialFiltre))
        ->get();

    // CA du site sur la période, pour exprimer la contribution de chaque commercial.
    $caParSite = Facture::whereIn('site_id', $commerciaux->pluck('site_id')->unique())
        ->whereBetween('date', [$debut, $fin])
        ->selectRaw('site_id, SUM(montant) as total')
        ->groupBy('site_id')
        ->pluck('total', 'site_id');

    return $commerciaux->map(function ($commercial) use ($debut, $fin, $caParSite) {
        $lignesFactures = Facture::where('commercial_id', $commercial->id)->whereBetween('date', [$debut, $fin])->get(['montant', 'activite']);
        $realisation = (int) $lignesFactures->sum('montant');
        $objectifProrata = (int) round(PeriodeCalculateur::objectifProrata((float) $commercial->objectif_mensuel, $debut, $fin));
        $ecart = $realisation - $objectifProrata;
        $caSite = (int) ($caParSite[$commercial->site_id] ?? 0);

        return [
            'commercial' => $commercial,
            'objectif' => $objectifProrata,
            'objectifMecanique' => (int) round(PeriodeCalculateur::objectifProrata((float) $commercial->objectif_mecanique, $debut, $fin)),
            'objectifSinistre' => (int) round(PeriodeCalculateur::objectifProrata((float) $commercial->objectif_sinistre, $debut, $fin)),
            'objectifJournalier' => (int) round($commercial->objectif_mensuel / 30),
            'realisation' => $realisation,
            'realisationMecanique' => (int) $lignesFactures->where('activite', 'Mécanique')->sum('montant'),
            'realisationSinistre' => (int) $lignesFactures->where('activite', 'Sinistre')->sum('montant'),
            'ecart' => $ecart,
            'taux' => $objectifProrata > 0 ? $realisation / $objectifProrata : null,
            'contribution' => $caSite > 0 ? $realisation / $caSite : null,
        ];
    })->sortByDesc(fn ($l) => $l['taux'] ?? -1)->values();
});

$kpis = computed(function () {
    $objectifTotal = $this->classement->sum('objectif');
    $realisationTotal = $this->classement->sum('realisation');
    $objectifMecanique = $this->classement->sum('objectifMecanique');
    $objectifSinistre = $this->classement->sum('objectifSinistre');
    $realisationMecanique = $this->classement->sum('realisationMecanique');
    $realisationSinistre = $this->classement->sum('realisationSinistre');

    return [
        'nombre' => $this->classement->count(),
        'objectif' => $objectifTotal,
        'objectifMecanique' => $objectifMecanique,
        'objectifSinistre' => $objectifSinistre,
        'realisation' => $realisationTotal,
        'realisationMecanique' => $realisationMecanique,
        'realisationSinistre' => $realisationSinistre,
        'ecart' => $realisationTotal - $objectifTotal,
        'ecartMecanique' => $realisationMecanique - $objectifMecanique,
        'ecartSinistre' => $realisationSinistre - $objectifSinistre,
        'taux' => $objectifTotal > 0 ? $realisationTotal / $objectifTotal : null,
        'tauxMecanique' => $objectifMecanique > 0 ? $realisationMecanique / $objectifMecanique : null,
        'tauxSinistre' => $objectifSinistre > 0 ? $realisationSinistre / $objectifSinistre : null,
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
    <x-filtre-periode :periode="$periode" :villes="$this->mesVilles" :ville-unique="$this->villeUnique"
        :ville-filtre="$villeFiltre" :activite-filtre="$activiteFiltre"
        :mois-filtre="$moisFiltre" :semaine-filtre="$semaineFiltre" :jour-filtre="$jourFiltre"
        :commerciaux="$this->optionsCommerciaux" :commercial-filtre="$commercialFiltre" />

    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Commerciaux — {{ $this->libellePerimetre }}" :value="$this->kpis['nombre']" />
        <x-kpi-card label="Objectif de la période" :value="ae($this->kpis['objectif'])"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['objectifMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['objectifSinistre'])" />
        <x-kpi-card label="Réalisation totale" :value="ae($this->kpis['realisation'])"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['realisationMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['realisationSinistre'])" />
        <x-kpi-card label="Écart global — {{ $this->libellePerimetre }}" :value="ae($this->kpis['ecart'])" :couleur="$this->kpis['ecart'] >= 0 ? '#0E9F6E' : '#C8102E'"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['ecartMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['ecartSinistre'])" />
        <x-kpi-card label="Taux de Réalisation — {{ $this->libellePerimetre }}" :value="an($this->kpis['taux'])"
            :mecanique="$activiteFiltre ? null : an($this->kpis['tauxMecanique'])" :sinistre="$activiteFiltre ? null : an($this->kpis['tauxSinistre'])" />
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
                        @if (! $activiteFiltre && count($this->idsSites) > 1)
                            <th>Site</th>
                        @endif
                        <th>Activité</th>
                        <th>Objectif mensuel</th>
                        <th>Objectif journalier (mensuel/30)</th>
                        <th>Objectif de la période</th>
                        <th>Réalisation</th>
                        <th>Écart</th>
                        <th>Taux de Réalisation</th>
                        <th>Contribution au CA du site</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->classement->forPage($pageClassement, 10) as $i => $ligne)
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
                            @if (! $activiteFiltre && count($this->idsSites) > 1)
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
                        <x-table-vide :colspan="! $activiteFiltre && count($this->idsSites) > 1 ? 11 : 10" texte="Aucun commercial actif pour ce filtre." />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageClassement" :total="$this->classement->count()" prop="pageClassement" />
    </div>
</div>
