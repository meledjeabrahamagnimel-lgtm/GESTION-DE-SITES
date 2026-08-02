<?php

use App\Domain\Operations\Models\Charge;
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

$estGerant = computed(fn () => auth()->user()->hasRole('gerant'));
$monSite = computed(fn () => $this->estGerant ? null : Site::where('responsable_id', auth()->id())->first());
$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));
$sites = computed(fn () => $this->estGerant ? Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get() : null);

$encaissementsQ = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Encaissement::whereBetween('date', [$debut, $fin]);

    return $this->estGerant
        ? ($this->siteFiltre ? $q->where('site_id', $this->siteFiltre) : $q)
        : $q->where('site_id', $this->monSite?->id ?? 0);
});

$chargesQ = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Charge::whereBetween('date', [$debut, $fin]);

    return $this->estGerant
        ? ($this->siteFiltre ? $q->where('site_id', $this->siteFiltre) : $q)
        : $q->where('site_id', $this->monSite?->id ?? 0);
});

$facturesQ = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Facture::whereBetween('date', [$debut, $fin]);

    return $this->estGerant
        ? ($this->siteFiltre ? $q->where('site_id', $this->siteFiltre) : $q)
        : $q->where('site_id', $this->monSite?->id ?? 0);
});

$kpis = computed(function () {
    $encaisse = (int) (clone $this->encaissementsQ)->sum('montant');
    $decaisse = (int) (clone $this->chargesQ)->sum('montant');
    $facture = (int) (clone $this->facturesQ)->sum('montant');

    return [
        'encaisse' => $encaisse,
        'decaisse' => $decaisse,
        'net' => $encaisse - $decaisse,
        'nonEncaisse' => max(0, $facture - $encaisse),
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

$detailEncaissements = computed(fn () => (clone $this->encaissementsQ)->with('site')->latest('date')->limit(30)->get());
$detailDecaissements = computed(fn () => (clone $this->chargesQ)->with('site')->latest('date')->limit(30)->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Encaissements" :value="ae($this->kpis['encaisse'])" couleur="#0E9F6E" />
        <x-kpi-card label="Décaissements" :value="ae($this->kpis['decaisse'])" couleur="#C8102E" />
        <x-kpi-card label="Trésorerie nette" :value="ae($this->kpis['net'])" :accent="$this->kpis['net'] < 0" :couleur="$this->kpis['net'] >= 0 ? '#0E9F6E' : '#C8102E'" />
        <x-kpi-card label="Facturé non encaissé" :value="ae($this->kpis['nonEncaisse'])" sub="Créances clients" />
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
                        @forelse ($this->detailEncaissements as $ligne)
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
                        @forelse ($this->detailDecaissements as $ligne)
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
        </div>
    </div>
</div>
