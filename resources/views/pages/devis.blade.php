<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Devis;
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
    $q = Devis::query()->whereBetween('date_emission', [$debut, $fin]);

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

$kpis = computed(function () {
    $lignes = (clone $this->requeteBase)->get();
    $emis = $lignes->count();
    $valides = $lignes->where('statut', 'Validé');
    $refuses = $lignes->where('statut', 'Refusé')->count();
    $attente = $lignes->where('statut', 'En attente')->count();

    return [
        'emis' => $emis,
        'montantEmis' => $lignes->sum('montant_devis'),
        'valides' => $valides->count(),
        'montantValide' => $valides->sum('montant_valide'),
        'refuses' => $refuses,
        'attente' => $attente,
        'tauxTransfo' => $emis > 0 ? $valides->count() / $emis : null,
    ];
});

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::pointsHebdomadaires($debut, $fin);

    $labels = [];
    $emis = [];
    $valides = [];

    foreach ($points as $point) {
        $lignes = (clone $this->requeteBase)->whereBetween('date_emission', [$point['debut'], $point['fin']])->get();
        $labels[] = $point['label'];
        $emis[] = $lignes->count();
        $valides[] = $lignes->where('statut', 'Validé')->count();
    }

    return [
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Émis', 'data' => $emis, 'color' => '#191B20'],
            ['label' => 'Validés', 'data' => $valides, 'color' => '#0E9F6E'],
        ],
    ];
});

$detail = computed(fn () => (clone $this->requeteBase)->with(['commercial', 'site'])->latest('date_emission')->limit(50)->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="Devis émis" :value="$this->kpis['emis']" :sub="ae($this->kpis['montantEmis'])" />
        <x-kpi-card label="Validés" :value="$this->kpis['valides']" :sub="ae($this->kpis['montantValide'])" couleur="#0E9F6E" />
        <x-kpi-card label="Refusés" :value="$this->kpis['refuses']" couleur="#C8102E" />
        <x-kpi-card label="En attente" :value="$this->kpis['attente']" :accent="$this->kpis['attente'] > 0" />
        <x-kpi-card label="Taux transfo" :value="an($this->kpis['tauxTransfo'])" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Devis émis vs validés" id="devis-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px;">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des opérations ({{ $this->detail->count() }})</h3>
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
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:14.5px;">
                <thead>
                    <tr style="text-align:left; border-bottom:2px solid var(--th-ink,#191B20);">
                        <th style="padding:9px 12px;">N°</th>
                        <th style="padding:9px 12px;">Client</th>
                        <th style="padding:9px 12px;">Date d'émission</th>
                        <th style="padding:9px 12px;">Commercial</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th style="padding:9px 12px;">Site</th>
                        @endif
                        <th style="padding:9px 12px;">Statut</th>
                        <th style="padding:9px 12px;">Montant du devis</th>
                        <th style="padding:9px 12px;">Montant validé</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:9px 12px; font-weight:700;">{{ $ligne->numero }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->client }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->date_emission->format('d/m/Y') }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->commercial->nom }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td style="padding:9px 12px;">{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="padding:9px 12px;">
                                <span style="font-weight:700; color:{{ ['En attente' => '#D97706', 'Validé' => '#0E9F6E', 'Refusé' => '#C8102E'][$ligne->statut] }};">{{ $ligne->statut }}</span>
                            </td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums;">{{ ae($ligne->montant_devis) }}</td>
                            <td style="padding:9px 12px; font-variant-numeric:tabular-nums;">{{ ae($ligne->montant_valide) }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 8 : 7" texte="Aucun devis enregistré sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
