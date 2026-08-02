<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Shared\Services\PeriodeCalculateur;
use App\Domain\Tenants\Models\Site;
use function Livewire\Volt\{state, computed, mount};

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
    $q = Prospection::query()->whereBetween('date', [$debut, $fin]);

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

    return [
        'clients' => $lignes->count(),
        'passages' => $lignes->where('passage', true)->count(),
        'devisApres' => $lignes->where('devis_apres_passage', true)->count(),
    ];
});

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::pointsHebdomadaires($debut, $fin);

    $labels = [];
    $visites = [];
    $passages = [];
    $devisApres = [];

    foreach ($points as $point) {
        $q = (clone $this->requeteBase)->whereBetween('date', [$point['debut'], $point['fin']]);
        $lignes = $q->get();
        $labels[] = $point['label'];
        $visites[] = $lignes->count();
        $passages[] = $lignes->where('passage', true)->count();
        $devisApres[] = $lignes->where('devis_apres_passage', true)->count();
    }

    return [
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Clients visités', 'data' => $visites, 'color' => '#191B20'],
            ['label' => 'Passages', 'data' => $passages, 'color' => '#2563EB'],
            ['label' => 'Devis après passage', 'data' => $devisApres, 'color' => '#0E9F6E'],
        ],
    ];
});

$detail = computed(fn () => (clone $this->requeteBase)->with(['commercial', 'site'])->latest('date')->limit(50)->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :sites="$this->sites" :site-filtre="$siteFiltre" />

    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px;">
        <x-kpi-card label="Clients visités" :value="$this->kpis['clients']" />
        <x-kpi-card label="Passages sur site" :value="$this->kpis['passages']" />
        <x-kpi-card label="Devis après passage" :value="$this->kpis['devisApres']"
            :sub="$this->kpis['passages'] > 0 ? 'Taux : '.an($this->kpis['devisApres'] / $this->kpis['passages']) : null" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Visites, passages et devis établis" id="prospects-hebdo"
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
                        <th style="padding:9px 12px;">Localisation</th>
                        <th style="padding:9px 12px;">Moyen</th>
                        <th style="padding:9px 12px;">Commercial</th>
                        <th style="padding:9px 12px;">Activité</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th style="padding:9px 12px;">Site</th>
                        @endif
                        <th style="padding:9px 12px;">Passage</th>
                        <th style="padding:9px 12px;">Devis après passage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:9px 12px; font-weight:700;">{{ $ligne->numero }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->client }}</td>
                            <td style="padding:9px 12px; color:#6B6E76;">{{ $ligne->localisation ?? '—' }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->moyen }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->commercial->nom }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->activite }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td style="padding:9px 12px;">{{ $ligne->site->nom }}</td>
                            @endif
                            <td style="padding:9px 12px;">{{ $ligne->passage ? '✓' : '—' }}</td>
                            <td style="padding:9px 12px;">{{ $ligne->devis_apres_passage ? '✓' : '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 9 : 8" texte="Aucune prospection enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
