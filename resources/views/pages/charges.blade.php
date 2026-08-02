<?php

use App\Domain\Operations\Models\Charge;
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

$requeteBase = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Charge::query()->whereBetween('date', [$debut, $fin]);

    if ($this->estGerant) {
        if ($this->siteFiltre) {
            $q->where('site_id', $this->siteFiltre);
        }
    } else {
        $q->where('site_id', $this->monSite?->id ?? 0);
    }

    return $q;
});

$caPeriode = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Facture::whereBetween('date', [$debut, $fin]);

    if ($this->estGerant) {
        if ($this->siteFiltre) {
            $q->where('site_id', $this->siteFiltre);
        }
    } else {
        $q->where('site_id', $this->monSite?->id ?? 0);
    }

    return (int) $q->sum('montant');
});

$kpis = computed(function () {
    $lignes = (clone $this->requeteBase)->where('type_operation', 'Charges')->get();
    $total = (int) $lignes->sum('montant');

    return [
        'total' => $total,
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

$detail = computed(fn () => (clone $this->requeteBase)->with('site')->latest('date')->limit(50)->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="Total charges" :value="ae($this->kpis['total'])" sub="Hors transferts et décaissements DG" />
        <x-kpi-card label="Achats pièces" :value="ae($this->kpis['pieces'])" />
        <x-kpi-card label="Salaires & personnel" :value="ae($this->kpis['salaires'])" />
        <x-kpi-card label="Résultat net" :value="ae($this->kpis['resultat'])" :couleur="$this->kpis['resultat'] >= 0 ? '#0E9F6E' : '#C8102E'" sub="CA facturé − charges" />
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
                        <th>Libellé</th>
                        <th>Moyen</th>
                        <th>Tiers</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th>Site</th>
                        @endif
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td>{{ $ligne->date->format('d/m/Y') }}</td>
                            <td>{{ $ligne->type_operation }}</td>
                            <td>{{ $ligne->libelle }}</td>
                            <td>{{ $ligne->moyen }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->tiers ?? '—' }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="font-variant-numeric:tabular-nums; font-weight:700;">{{ ae($ligne->montant) }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 7 : 6" texte="Aucune charge enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
