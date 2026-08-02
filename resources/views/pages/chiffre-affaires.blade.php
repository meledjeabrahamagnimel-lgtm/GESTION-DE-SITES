<?php

use App\Domain\Operations\Models\Charge;
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
    'recherche' => '',
    'commercialFiltre' => '',
]);

$estGerant = computed(fn () => auth()->user()->hasRole('gerant'));
$monSite = computed(fn () => $this->estGerant ? null : Site::where('responsable_id', auth()->id())->first());
$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));
$sites = computed(fn () => $this->estGerant ? Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get() : null);

$requeteBase = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Facture::query()->whereBetween('date', [$debut, $fin]);

    if ($this->estGerant) {
        if ($this->siteFiltre) {
            $q->where('site_id', $this->siteFiltre);
        }
    } else {
        $q->where('site_id', $this->monSite?->id ?? 0);
    }

    if ($this->recherche) {
        $q->where('client', 'like', '%'.$this->recherche.'%');
    }

    if ($this->commercialFiltre) {
        $q->where('commercial_id', $this->commercialFiltre);
    }

    return $q;
});

$commerciaux = computed(function () {
    $q = Commercial::where('est_spontane', false);
    if (! $this->estGerant) {
        $q->where('site_id', $this->monSite?->id ?? 0);
    } elseif ($this->siteFiltre) {
        $q->where('site_id', $this->siteFiltre);
    }

    return $q->orderBy('nom')->get();
});

$chargesPeriode = computed(function () {
    [$debut, $fin] = $this->plage;
    $q = Charge::where('type_operation', 'Charges')->whereBetween('date', [$debut, $fin]);

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
    $lignes = (clone $this->requeteBase)->get();
    $ca = (int) $lignes->sum('montant');

    return [
        'total' => $ca,
        'mecanique' => (int) $lignes->where('activite', 'Mécanique')->sum('montant'),
        'carrosserie' => (int) $lignes->where('activite', 'Carrosserie')->sum('montant'),
        'resultat' => $ca - $this->chargesPeriode,
    ];
});

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::points($debut, $fin);

    $labels = [];
    $mecanique = [];
    $carrosserie = [];
    $resultat = [];

    foreach ($points as $point) {
        $lignes = (clone $this->requeteBase)->whereBetween('date', [$point['debut'], $point['fin']])->get();
        $ca = (int) $lignes->sum('montant');
        $charges = (int) \App\Domain\Operations\Models\Charge::query()
            ->where('type_operation', 'Charges')
            ->whereBetween('date', [$point['debut'], $point['fin']])
            ->when(! $this->estGerant, fn ($q) => $q->where('site_id', $this->monSite?->id ?? 0))
            ->when($this->estGerant && $this->siteFiltre, fn ($q) => $q->where('site_id', $this->siteFiltre))
            ->sum('montant');

        $labels[] = $point['label'];
        $mecanique[] = (int) $lignes->where('activite', 'Mécanique')->sum('montant');
        $carrosserie[] = (int) $lignes->where('activite', 'Carrosserie')->sum('montant');
        $resultat[] = $ca - $charges;
    }

    return [
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Mécanique', 'data' => $mecanique, 'color' => '#191B20'],
            ['label' => 'Carrosserie', 'data' => $carrosserie, 'color' => '#C8102E'],
            ['label' => 'Résultat net', 'data' => $resultat, 'color' => '#0E9F6E', 'type' => 'line'],
        ],
    ];
});

$detail = computed(fn () => (clone $this->requeteBase)->with(['commercial', 'site'])->latest('date')->limit(50)->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="CA total facturé" :value="ae($this->kpis['total'])" :sub="$this->detail->count().' facture(s)'" />
        <x-kpi-card label="CA Mécanique" :value="ae($this->kpis['mecanique'])" />
        <x-kpi-card label="CA Carrosserie" :value="ae($this->kpis['carrosserie'])" />
        <x-kpi-card label="Charges" :value="ae($this->chargesPeriode)" />
        <x-kpi-card label="Résultat net (CA − charges)" :value="ae($this->kpis['resultat'])"
            :bon="$this->kpis['resultat'] >= 0" :accent="$this->kpis['resultat'] < 0" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="CA par activité" id="ca-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div class="carte">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des factures ({{ $this->detail->count() }})</h3>
        <div style="display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
            <input type="text" wire:model.live.debounce.400ms="recherche" placeholder="Client / tiers…"
                style="flex:1; min-width:200px; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px;">
            <select wire:model.live="commercialFiltre" style="padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px;">
                <option value="">Commercial : tous</option>
                @foreach ($this->commerciaux as $commercial)
                    <option value="{{ $commercial->id }}">{{ $commercial->nom }}</option>
                @endforeach
            </select>
        </div>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Date</th>
                        <th>Commercial</th>
                        <th>Clients</th>
                        <th>Type</th>
                        <th>N° de facture</th>
                        <th>Activité</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th>Site</th>
                        @endif
                        <th>Montant</th>
                        <th>Observations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">{{ $ligne->numero }}</td>
                            <td>{{ $ligne->date->format('d/m/Y') }}</td>
                            <td>{{ $ligne->commercial->nom }}</td>
                            <td>{{ $ligne->client }}</td>
                            <td>{{ $ligne->type }}</td>
                            <td>{{ $ligne->n_facture }}</td>
                            <td>{{ $ligne->activite }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="font-variant-numeric:tabular-nums; font-weight:700;">{{ ae($ligne->montant) }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->observations ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 10 : 9" texte="Aucune facture enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
