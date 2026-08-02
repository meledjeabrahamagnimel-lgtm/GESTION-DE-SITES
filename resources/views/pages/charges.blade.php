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
    $points = PeriodeCalculateur::pointsHebdomadaires($debut, $fin);
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

    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px;">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des opérations ({{ $this->detail->count() }})</h3>
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:14.5px;">
                <thead>
                    <tr style="text-align:left; border-bottom:2px solid var(--th-ink,#191B20);">
                        <th style="padding:9px 12px;">Date</th>
                        <th style="padding:9px 12px;">Type d'opération</th>
                        <th style="padding:9px 12px;">Libellé</th>
                        <th style="padding:9px 12px;">Moyen</th>
                        <th style="padding:9px 12px;">Tiers</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th style="padding:9px 12px;">Site</th>
                        @endif
                        <th style="padding:9px 12px;">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:9px 12px;">{{ $ligne->date->format('d/m/Y') }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->type_operation }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->libelle }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->moyen }}</td>
                            <td style="padding:9px 12px; color:#6B6E76;">{{ $ligne->tiers ?? '—' }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td style="padding:9px 12px;">{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums; font-weight:700;">{{ ae($ligne->montant) }}</td>
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
