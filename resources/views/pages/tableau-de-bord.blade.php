<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
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

$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get());

$synthese = computed(function () {
    [$debut, $fin] = $this->plage;

    return $this->sites->map(function ($site) use ($debut, $fin) {
        $caFacture = (int) Facture::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $charges = (int) Charge::where('site_id', $site->id)->where('type_operation', 'Charges')->whereBetween('date', [$debut, $fin])->sum('montant');
        $encaisse = (int) Encaissement::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $decaisse = (int) Charge::where('site_id', $site->id)->whereBetween('date', [$debut, $fin])->sum('montant');
        $devisAttente = Devis::where('site_id', $site->id)->where('statut', 'En attente')->whereBetween('date_emission', [$debut, $fin])->count();

        return [
            'site' => $site,
            'ca' => $caFacture,
            'charges' => $charges,
            'resultat' => $caFacture - $charges,
            'encaisse' => $encaisse,
            'treso' => $encaisse - $decaisse,
            'devisAttente' => $devisAttente,
        ];
    });
});

$kpis = computed(function () {
    return [
        'ca' => $this->synthese->sum('ca'),
        'charges' => $this->synthese->sum('charges'),
        'resultat' => $this->synthese->sum('resultat'),
        'treso' => $this->synthese->sum('treso'),
    ];
});

$graphique = computed(function () {
    return [
        'labels' => $this->synthese->pluck('site.nom')->all(),
        'datasets' => [
            ['label' => 'CA facturé', 'data' => $this->synthese->pluck('ca')->all(), 'color' => '#191B20'],
            ['label' => 'Charges', 'data' => $this->synthese->pluck('charges')->all(), 'color' => '#C8102E'],
            ['label' => 'Résultat net', 'data' => $this->synthese->pluck('resultat')->all(), 'color' => '#0E9F6E'],
        ],
    ];
});

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="CA groupe" :value="ae($this->kpis['ca'])" />
        <x-kpi-card label="Charges groupe" :value="ae($this->kpis['charges'])" />
        <x-kpi-card label="Résultat net groupe" :value="ae($this->kpis['resultat'])" sub="CA facturé − charges" :couleur="$this->kpis['resultat'] >= 0 ? '#0E9F6E' : '#C8102E'" />
        <x-kpi-card label="Trésorerie nette groupe" :value="ae($this->kpis['treso'])" :accent="$this->kpis['treso'] < 0" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Comparaison des sites : CA, charges, résultat net" id="cmp-sites"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px;">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Synthèse par site</h3>
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:14.5px;">
                <thead>
                    <tr style="text-align:left; border-bottom:2px solid var(--th-ink,#191B20);">
                        <th style="padding:9px 12px;">Site</th>
                        <th style="padding:9px 12px;">CA facturé</th>
                        <th style="padding:9px 12px;">Charges</th>
                        <th style="padding:9px 12px;">Résultat net</th>
                        <th style="padding:9px 12px;">Encaissé</th>
                        <th style="padding:9px 12px;">Trésorerie nette</th>
                        <th style="padding:9px 12px;">Devis en attente</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->synthese as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:9px 12px; font-weight:700;">
                                <span style="display:inline-block; width:9px; height:9px; border-radius:99px; background:{{ $ligne['site']->couleur }}; margin-right:8px;"></span>
                                {{ $ligne['site']->nom }}
                            </td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums;">{{ ae($ligne['ca']) }}</td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums;">{{ ae($ligne['charges']) }}</td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums; color:{{ $ligne['resultat'] >= 0 ? '#0E9F6E' : '#C8102E' }}; font-weight:700;">{{ ae($ligne['resultat']) }}</td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums;">{{ ae($ligne['encaisse']) }}</td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums; color:{{ $ligne['treso'] >= 0 ? 'inherit' : '#C8102E' }};">{{ ae($ligne['treso']) }}</td>
                            <td style="padding:9px 12px;">{{ $ligne['devisAttente'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
