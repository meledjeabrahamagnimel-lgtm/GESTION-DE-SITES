<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Encaissement;
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
    'pageEncaissements' => 1,
    'pageDecaissements' => 1,
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

$encaissementsQ = computed(function () {
    [$debut, $fin] = $this->plage;

    return Encaissement::whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin]);
});

$chargesQ = computed(function () {
    [$debut, $fin] = $this->plage;

    return Charge::whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin]);
});

$facturesQ = computed(function () {
    [$debut, $fin] = $this->plage;

    return Facture::whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin]);
});

$kpis = computed(function () {
    $encaissements = (clone $this->encaissementsQ)->with('site:id,activite')->get();
    $charges = (clone $this->chargesQ)->with('site:id,activite')->get();
    $factures = (clone $this->facturesQ)->get();

    $encaisse = (int) $encaissements->sum('montant');
    $decaisse = (int) $charges->sum('montant');
    $facture = (int) $factures->sum('montant');

    $encaisseParActivite = fn ($activite) => (int) $encaissements->filter(fn ($e) => $e->site?->activite === $activite)->sum('montant');
    $decaisseParActivite = fn ($activite) => (int) $charges->filter(fn ($c) => $c->site?->activite === $activite)->sum('montant');
    $factureParActivite = fn ($activite) => (int) $factures->where('activite', $activite)->sum('montant');

    $encaisseMecanique = $encaisseParActivite('Mécanique');
    $encaisseSinistre = $encaisseParActivite('Sinistre');
    $decaisseMecanique = $decaisseParActivite('Mécanique');
    $decaisseSinistre = $decaisseParActivite('Sinistre');

    return [
        'encaisse' => $encaisse,
        'encaisseMecanique' => $encaisseMecanique,
        'encaisseSinistre' => $encaisseSinistre,
        'decaisse' => $decaisse,
        'decaisseMecanique' => $decaisseMecanique,
        'decaisseSinistre' => $decaisseSinistre,
        'net' => $encaisse - $decaisse,
        'netMecanique' => $encaisseMecanique - $decaisseMecanique,
        'netSinistre' => $encaisseSinistre - $decaisseSinistre,
        'nonEncaisse' => max(0, $facture - $encaisse),
        'nonEncaisseMecanique' => max(0, $factureParActivite('Mécanique') - $encaisseMecanique),
        'nonEncaisseSinistre' => max(0, $factureParActivite('Sinistre') - $encaisseSinistre),
    ];
});

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::points($debut, $fin);

    $labels = [];
    $entrees = [];
    $sorties = [];
    $cumul = [];
    $total = 0;

    foreach ($points as $point) {
        $e = (int) (clone $this->encaissementsQ)->whereBetween('date', [$point['debut'], $point['fin']])->sum('montant');
        $so = (int) (clone $this->chargesQ)->whereBetween('date', [$point['debut'], $point['fin']])->sum('montant');
        $total += $e - $so;

        $labels[] = $point['label'];
        $entrees[] = $e;
        $sorties[] = $so;
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

$detailEncaissements = computed(fn () => (clone $this->encaissementsQ)->with('site')->latest('date')->get());
$detailDecaissements = computed(fn () => (clone $this->chargesQ)->with('site')->latest('date')->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :villes="$this->mesVilles" :ville-unique="$this->villeUnique"
        :ville-filtre="$villeFiltre" :activite-filtre="$activiteFiltre"
        :mois-filtre="$moisFiltre" :semaine-filtre="$semaineFiltre" :jour-filtre="$jourFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Encaissements — {{ $this->libellePerimetre }}" :value="ae($this->kpis['encaisse'])" couleur="#0E9F6E"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['encaisseMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['encaisseSinistre'])" />
        <x-kpi-card label="Décaissements — {{ $this->libellePerimetre }}" :value="ae($this->kpis['decaisse'])" couleur="#C8102E"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['decaisseMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['decaisseSinistre'])" />
        <x-kpi-card label="Trésorerie nette — {{ $this->libellePerimetre }}" :value="ae($this->kpis['net'])" :accent="$this->kpis['net'] < 0" :couleur="$this->kpis['net'] >= 0 ? '#0E9F6E' : '#C8102E'"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['netMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['netSinistre'])" />
        <x-kpi-card label="Facturé non encaissé — {{ $this->libellePerimetre }}" :value="ae($this->kpis['nonEncaisse'])" sub="Créances clients"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['nonEncaisseMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['nonEncaisseSinistre'])" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Entrées et sorties" id="treso-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div class="carte">
            <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Encaissements ({{ $this->detailEncaissements->count() }})</h3>
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type d'encaissement</th>
                            <th>Moyens</th>
                            <th>Montant</th>
                            <th>Clients</th>
                            <th>Autres tiers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->detailEncaissements->forPage($pageEncaissements, 10) as $ligne)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                <td>{{ $ligne->type }}</td>
                                <td>{{ $ligne->moyen }}</td>
                                <td style="font-variant-numeric:tabular-nums; font-weight:700; color:#0E9F6E;">{{ ae($ligne->montant) }}</td>
                                <td>{{ $ligne->client ?? '—' }}</td>
                                <td style="color:#6B6E76;">{{ $ligne->autres_tiers ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun encaissement sur cette période." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageEncaissements" :total="$this->detailEncaissements->count()" prop="pageEncaissements" />
        </div>

        <div class="carte">
            <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Décaissements ({{ $this->detailDecaissements->count() }})</h3>
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type d'opération</th>
                            <th>Libellé d'opération</th>
                            <th>Moyens</th>
                            <th>Montant</th>
                            <th>Tiers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->detailDecaissements->forPage($pageDecaissements, 10) as $ligne)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                <td>{{ $ligne->type_operation }}</td>
                                <td>{{ $ligne->libelle }}</td>
                                <td>{{ $ligne->moyen }}</td>
                                <td style="font-variant-numeric:tabular-nums; font-weight:700; color:#C8102E;">{{ ae($ligne->montant) }}</td>
                                <td style="color:#6B6E76;">{{ $ligne->tiers ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun décaissement sur cette période." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageDecaissements" :total="$this->detailDecaissements->count()" prop="pageDecaissements" />
        </div>
    </div>
</div>
