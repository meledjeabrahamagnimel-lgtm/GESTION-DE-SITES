<?php

use App\Domain\Operations\Models\Charge;
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
    'pageDetail' => 1,
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

$requeteBase = computed(function () {
    [$debut, $fin] = $this->plage;

    return Charge::query()->whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin]);
});

$caPeriode = computed(function () {
    [$debut, $fin] = $this->plage;

    return (int) Facture::whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin])->sum('montant');
});

$kpis = computed(function () {
    $lignes = (clone $this->requeteBase)->where('type_operation', 'Charges')->with('site:id,activite')->get();
    $total = (int) $lignes->sum('montant');
    $mecanique = (int) $lignes->filter(fn ($l) => $l->site?->activite === 'Mécanique')->sum('montant');
    $sinistre = (int) $lignes->filter(fn ($l) => $l->site?->activite === 'Sinistre')->sum('montant');

    return [
        'total' => $total,
        'totalMecanique' => $mecanique,
        'totalSinistre' => $sinistre,
        'pieces' => (int) $lignes->where('libelle', 'Achats pièces')->sum('montant'),
        'salaires' => (int) $lignes->where('libelle', 'Salaires & personnel')->sum('montant'),
        'resultat' => $this->caPeriode - $total,
    ];
});

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::points($debut, $fin);
    $natures = ['Achats pièces' => '#191B20', 'Salaires & personnel' => '#2563EB', 'Fonctionnement' => '#D97706', 'Autres décaissements' => '#9A9DA5'];

    $labels = [];
    $series = array_fill_keys(array_keys($natures), []);

    foreach ($points as $point) {
        $lignes = (clone $this->requeteBase)->where('type_operation', 'Charges')->whereBetween('date', [$point['debut'], $point['fin']])->get();
        $labels[] = $point['label'];
        foreach ($natures as $nature => $couleur) {
            $series[$nature][] = (int) $lignes->where('libelle', $nature)->sum('montant');
        }
    }

    return [
        'labels' => $labels,
        'datasets' => collect($natures)->map(fn ($couleur, $nature) => ['label' => $nature, 'data' => $series[$nature], 'color' => $couleur])->values()->all(),
    ];
});

$detail = computed(fn () => (clone $this->requeteBase)->with('site')->latest('date')->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :villes="$this->mesVilles" :ville-unique="$this->villeUnique"
        :ville-filtre="$villeFiltre" :activite-filtre="$activiteFiltre"
        :mois-filtre="$moisFiltre" :semaine-filtre="$semaineFiltre" :jour-filtre="$jourFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Total charges — {{ $this->libellePerimetre }}" :value="ae($this->kpis['total'])" sub="Hors transferts et décaissements DG"
            :mecanique="$activiteFiltre ? null : ae($this->kpis['totalMecanique'])" :sinistre="$activiteFiltre ? null : ae($this->kpis['totalSinistre'])" />
        <x-kpi-card label="Achats pièces" :value="ae($this->kpis['pieces'])" />
        <x-kpi-card label="Salaires & personnel" :value="ae($this->kpis['salaires'])" />
        <x-kpi-card label="Résultat net — {{ $this->libellePerimetre }}" :value="ae($this->kpis['resultat'])" :couleur="$this->kpis['resultat'] >= 0 ? '#0E9F6E' : '#C8102E'" sub="CA facturé − charges" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Charges par nature" id="charges-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div class="carte">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des opérations ({{ $this->detail->count() }})</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type d'opération</th>
                        <th>Libellé d'opération</th>
                        <th>Moyens</th>
                        <th>Tiers</th>
                        @if (! $activiteFiltre && count($this->idsSites) > 1)
                            <th>Site</th>
                        @endif
                        <th>Montant</th>
                        <th>Observations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail->forPage($pageDetail, 10) as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td>{{ $ligne->date->format('d/m/Y') }}</td>
                            <td>{{ $ligne->type_operation }}</td>
                            <td>{{ $ligne->libelle }}</td>
                            <td>{{ $ligne->moyen }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->tiers ?? '—' }}</td>
                            @if (! $activiteFiltre && count($this->idsSites) > 1)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="font-variant-numeric:tabular-nums; font-weight:700;">{{ ae($ligne->montant) }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->observations ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="! $activiteFiltre && count($this->idsSites) > 1 ? 8 : 7" texte="Aucune charge enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageDetail" :total="$this->detail->count()" prop="pageDetail" />
    </div>
</div>
