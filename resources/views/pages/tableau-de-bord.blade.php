<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Commercial;
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

/**
 * Alertes de gestion, classées par gravité : un résultat ou une trésorerie négative,
 * des devis restés sans statut, et surtout les véhicules restitués sans facture.
 */
$alertes = computed(function () {
    $alertes = [];

    foreach ($this->synthese as $ligne) {
        $site = $ligne['site']->nom;

        if ($ligne['sansFacture'] > 0) {
            $alertes[] = ['niveau' => 'CRITIQUE', 'texte' => "$site : {$ligne['sansFacture']} véhicule(s) restitué(s) sans facture"];
        }
        if ($ligne['resultat'] < 0) {
            $alertes[] = ['niveau' => 'ÉLEVÉ', 'texte' => "$site : résultat net négatif (".ae($ligne['resultat']).')'];
        }
        if ($ligne['treso'] < 0) {
            $alertes[] = ['niveau' => 'ÉLEVÉ', 'texte' => "$site : trésorerie nette négative (".ae($ligne['treso']).')'];
        }
        if ($ligne['devisAttente'] > 0) {
            $alertes[] = ['niveau' => 'MOYEN', 'texte' => "$site : {$ligne['devisAttente']} devis en attente de statut"];
        }
    }

    $ordre = ['CRITIQUE' => 0, 'ÉLEVÉ' => 1, 'MOYEN' => 2];

    return collect($alertes)->sortBy(fn ($a) => $ordre[$a['niveau']])->values();
});

/** Classement des commerciaux du groupe par taux d'atteinte de leur objectif au prorata. */
$topCommerciaux = computed(function () {
    [$debut, $fin] = $this->plage;
    $jours = PeriodeCalculateur::nombreDeJours($debut, $fin);

    return Commercial::actifs()->where('est_spontane', false)
        ->whereIn('site_id', $this->sitesRetenus->pluck('id'))
        ->with('site')->get()
        ->map(function ($c) use ($debut, $fin, $jours) {
            $realisation = (int) Facture::where('commercial_id', $c->id)->whereBetween('date', [$debut, $fin])->sum('montant');
            $objectif = (int) round($c->objectif_mensuel / 30 * $jours);

            return [
                'commercial' => $c,
                'realisation' => $realisation,
                'taux' => $objectif > 0 ? $realisation / $objectif : null,
            ];
        })
        ->sortByDesc(fn ($l) => $l['taux'] ?? -1)
        ->take(3)->values();
});

/** Commentaires laissés par les responsables de site dans leur saisie journalière. */
$commentaires = computed(function () {
    [$debut, $fin] = $this->plage;

    $saisies = SaisieJournaliere::whereIn('site_id', $this->sitesRetenus->pluck('id'))
        ->whereBetween('date', [$debut, $fin])
        ->with('site')->orderByDesc('date')->get();

    $rubriques = [
        'Prospects' => 'commentaire_prospects',
        'Devis' => 'commentaire_devis',
        "Chiffre d'affaires" => 'commentaire_ca',
        'Trésorerie' => 'commentaire_tresorerie',
        'Charges' => 'commentaire_charges',
    ];

    return collect($rubriques)->map(fn ($colonne) => $saisies
        ->filter(fn ($s) => filled($s->$colonne))
        ->map(fn ($s) => ['site' => $s->site->nom, 'date' => $s->date, 'texte' => $s->$colonne])
        ->take(6)->values());
});

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    @php $portee = $siteFiltre ? $this->sitesRetenus->first()?->nom : 'groupe'; @endphp

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; margin-bottom:16px;">
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

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(380px, 1fr)); gap:16px; margin-bottom:20px;">
        <div class="carte">
            <h3 class="titre-section">Alertes ({{ $this->alertes->count() }})</h3>
            @forelse ($this->alertes as $alerte)
                @php
                    $couleur = ['CRITIQUE' => 'pastille-rouge', 'ÉLEVÉ' => 'pastille-ambre', 'MOYEN' => 'pastille-bleu'][$alerte['niveau']];
                @endphp
                <div style="display:flex; gap:9px; align-items:flex-start; padding:7px 0; border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                    <span class="pastille {{ $couleur }}" style="flex:0 0 auto;">{{ $alerte['niveau'] }}</span>
                    <span style="font-size:13px; line-height:1.4;">{{ $alerte['texte'] }}</span>
                </div>
            @empty
                <div class="etat-vide" style="min-height:130px;">
                    <span class="etat-vide-texte">Aucune alerte sur cette période.</span>
                </div>
            @endforelse
        </div>

        <div class="carte">
            <h3 class="titre-section">Top commerciaux (taux d'atteinte)</h3>
            @forelse ($this->topCommerciaux as $rang => $ligne)
                @php
                    // Or, argent, bronze : la couleur du rang, comme dans la maquette.
                    $medaille = ['#D4AF37', '#9CA3AF', '#B87333'][$rang] ?? '#9CA3AF';
                @endphp
                <div style="display:flex; gap:11px; align-items:center; padding:8px 0; border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                    <span style="flex:0 0 auto; width:26px; height:26px; border-radius:99px; background:{{ $medaille }};
                                 color:#fff; display:inline-flex; align-items:center; justify-content:center;
                                 font-family:'Barlow Condensed',sans-serif; font-weight:700; font-size:15px;">{{ $rang + 1 }}</span>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; font-size:13.5px;">{{ $ligne['commercial']->nom }}</div>
                        <div style="font-size:11.5px; color:var(--th-gris,#6B6E76);">
                            {{ $ligne['commercial']->site->nom }} · {{ $ligne['commercial']->activite }}
                        </div>
                        <div style="font-size:11.5px; color:var(--th-gris,#6B6E76);">
                            Réalisation {{ ae($ligne['realisation']) }} · atteinte
                            <b style="color:{{ ($ligne['taux'] ?? 0) >= 1 ? '#0E9F6E' : '#D97706' }};">{{ an($ligne['taux']) }}</b>
                        </div>
                    </div>
                </div>
            @empty
                <div class="etat-vide" style="min-height:130px;">
                    <span class="etat-vide-texte">Aucun commercial sur cette période.</span>
                </div>
            @endforelse
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px; margin-bottom:20px;">
        @foreach ($this->commentaires as $rubrique => $lignes)
            <div class="carte">
                <h3 class="titre-section">Commentaires — {{ $rubrique }}</h3>
                @forelse ($lignes as $c)
                    <div style="padding:7px 0; border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                        <div style="font-size:11.5px; color:var(--th-gris,#6B6E76); font-weight:600;">
                            {{ $c['site'] }} · {{ $c['date']->format('d/m/Y') }}
                        </div>
                        <div style="font-size:13px; line-height:1.45;">{{ $c['texte'] }}</div>
                    </div>
                @empty
                    <div class="etat-vide" style="min-height:110px;">
                        <span class="etat-vide-texte">Aucun commentaire saisi sur la période.</span>
                    </div>
                @endforelse
            </div>
        @endforeach
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
