<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Shared\Services\PeriodeCalculateur;
use App\Domain\Tenants\Models\Site;
use function Livewire\Volt\{state, computed};

state([
    'periode' => 'semaine',
    'dateDebut' => null,
    'dateFin' => null,
    'siteFiltre' => '',
]);

$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get());

/** Sites réellement pris en compte : tous, ou uniquement celui sélectionné dans le filtre. */
$sitesRetenus = computed(fn () => $this->siteFiltre
    ? $this->sites->where('id', (int) $this->siteFiltre)->values()
    : $this->sites);

$synthese = computed(function () {
    [$debut, $fin] = $this->plage;

    return $this->sitesRetenus->map(function ($site) use ($debut, $fin) {
        $caFacture = (int) Facture::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $charges = (int) Charge::where('site_id', $site->id)->where('type_operation', 'Charges')->whereBetween('date', [$debut, $fin])->sum('montant');
        $encaisse = (int) Encaissement::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $decaisse = (int) Charge::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $devisAttente = Devis::where('site_id', $site->id)->where('statut', 'En attente')->whereBetween('date_emission', [$debut, $fin])->count();
        $sansFacture = (int) SaisieJournaliere::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('vehicules_sans_facture');

        return [
            'site' => $site,
            'ca' => $caFacture,
            'charges' => $charges,
            'resultat' => $caFacture - $charges,
            'encaisse' => $encaisse,
            'treso' => $encaisse - $decaisse,
            'devisAttente' => $devisAttente,
            'sansFacture' => $sansFacture,
        ];
    });
});

$kpis = computed(function () {
    [$debut, $fin] = $this->plage;
    $idsSites = $this->sitesRetenus->pluck('id');

    $devisEmis = Devis::whereIn('site_id', $idsSites)->whereBetween('date_emission', [$debut, $fin]);
    $nbEmis = (clone $devisEmis)->count();
    $nbValides = (clone $devisEmis)->where('statut', 'Validé')->count();

    return [
        'ca' => $this->synthese->sum('ca'),
        'charges' => $this->synthese->sum('charges'),
        'resultat' => $this->synthese->sum('resultat'),
        'encaisse' => $this->synthese->sum('encaisse'),
        'treso' => $this->synthese->sum('treso'),
        // Taux de transformation des devis émis sur la période.
        'tauxTransfo' => $nbEmis > 0 ? $nbValides / $nbEmis : null,
        // Anomalie critique remontée par les responsables de site.
        'sansFacture' => (int) SaisieJournaliere::whereIn('site_id', $idsSites)
            ->whereBetween('date', [$debut, $fin])->sum('vehicules_sans_facture'),
    ];
});

$graphiqueSites = computed(fn () => [
    'labels' => $this->synthese->pluck('site.nom')->all(),
    'datasets' => [
        ['label' => 'CA facturé', 'data' => $this->synthese->pluck('ca')->all(), 'color' => '#191B20'],
        ['label' => 'Charges', 'data' => $this->synthese->pluck('charges')->all(), 'color' => '#C8102E'],
        ['label' => 'Résultat net', 'data' => $this->synthese->pluck('resultat')->all(), 'color' => '#0E9F6E'],
    ],
]);

/** Flux de trésorerie hebdomadaire : entrées, sorties et cumul net. */
$graphiqueFlux = computed(function () {
    [$debut, $fin] = $this->plage;
    $idsSites = $this->sitesRetenus->pluck('id');
    $points = PeriodeCalculateur::points($debut, $fin);

    $labels = [];
    $entrees = [];
    $sorties = [];
    $cumul = [];
    $total = 0;

    foreach ($points as $point) {
        $e = (int) Encaissement::whereIn('site_id', $idsSites)->whereBetween('date', [$point['debut'], $point['fin']])->sum('montant');
        $s = (int) Charge::whereIn('site_id', $idsSites)->whereBetween('date', [$point['debut'], $point['fin']])->sum('montant');
        $total += $e - $s;

        $labels[] = $point['label'];
        $entrees[] = $e;
        $sorties[] = $s;
        $cumul[] = $total;
    }

    return [
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Entrées', 'data' => $entrees, 'color' => '#0E9F6E'],
            ['label' => 'Sorties', 'data' => $sorties, 'color' => '#C8102E'],
            ['label' => 'Trésorerie nette cumulée', 'data' => $cumul, 'color' => '#2563EB', 'type' => 'line'],
        ],
    ];
});

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    @php $portee = $siteFiltre ? $this->sitesRetenus->first()?->nom : 'groupe'; @endphp

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="CA {{ $portee }}" :value="ae($this->kpis['ca'])" />
        <x-kpi-card label="Charges {{ $portee }}" :value="ae($this->kpis['charges'])" />
        <x-kpi-card label="Résultat net {{ $portee }}" :value="ae($this->kpis['resultat'])"
            sub="CA facturé − charges" :bon="$this->kpis['resultat'] >= 0" :accent="$this->kpis['resultat'] < 0" />
        <x-kpi-card label="Encaissé" :value="ae($this->kpis['encaisse'])"
            :sub="$this->kpis['ca'] > 0 ? an($this->kpis['encaisse'] / $this->kpis['ca']).' du CA facturé' : null" />
        <x-kpi-card label="Trésorerie nette {{ $portee }}" :value="ae($this->kpis['treso'])" :accent="$this->kpis['treso'] < 0" />
        <x-kpi-card label="Taux transfo devis" :value="an($this->kpis['tauxTransfo'])" />
        <x-kpi-card label="Véhicules sans facture" :value="$this->kpis['sansFacture']"
            :sub="$this->kpis['sansFacture'] > 0 ? 'Anomalie à signaler à la Direction' : 'Aucune anomalie'"
            :accent="$this->kpis['sansFacture'] > 0" :bon="$this->kpis['sansFacture'] === 0" />
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(420px, 1fr)); gap:16px; margin-bottom:20px;">
        <x-chart-card titre="Comparaison des sites : CA, charges, résultat net" id="cmp-sites"
            :labels="$this->graphiqueSites['labels']" :datasets="$this->graphiqueSites['datasets']" />
        <x-chart-card titre="Flux de trésorerie" id="flux-treso"
            :labels="$this->graphiqueFlux['labels']" :datasets="$this->graphiqueFlux['datasets']" />
    </div>

    <div class="carte">
        <h3 class="titre-section">Synthèse par site</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>CA facturé</th>
                        <th>Charges</th>
                        <th>Résultat net</th>
                        <th>Encaissé</th>
                        <th>Trésorerie nette</th>
                        <th>Devis en attente</th>
                        <th>Sans facture</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->synthese as $ligne)
                        <tr>
                            <td style="font-weight:700;">
                                <span style="display:inline-block; width:9px; height:9px; border-radius:99px; background:{{ $ligne['site']->couleur }}; margin-right:8px;"></span>
                                {{ $ligne['site']->nom }}
                            </td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['ca']) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['charges']) }}</td>
                            <td style="font-variant-numeric:tabular-nums; color:{{ $ligne['resultat'] >= 0 ? '#0E9F6E' : '#C8102E' }}; font-weight:700;">{{ ae($ligne['resultat']) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne['encaisse']) }}</td>
                            <td style="font-variant-numeric:tabular-nums; color:{{ $ligne['treso'] >= 0 ? 'inherit' : '#C8102E' }};">{{ ae($ligne['treso']) }}</td>
                            <td>{{ $ligne['devisAttente'] }}</td>
                            <td style="font-weight:700; color:{{ $ligne['sansFacture'] > 0 ? '#C8102E' : 'inherit' }};">{{ $ligne['sansFacture'] }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="8" texte="Aucun site à afficher." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
