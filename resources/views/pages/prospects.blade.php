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
    $q = Prospection::query()->visibles()->whereBetween('date', [$debut, $fin]);

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
    $points = PeriodeCalculateur::points($debut, $fin);

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

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Clients visités" :value="$this->kpis['clients']" />
        <x-kpi-card label="Passages sur site" :value="$this->kpis['passages']" />
        <x-kpi-card label="Devis après passage" :value="$this->kpis['devisApres']" />
        <x-kpi-card label="Taux devis / passage"
            :value="an($this->kpis['passages'] > 0 ? $this->kpis['devisApres'] / $this->kpis['passages'] : null)" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Visites, passages et devis établis" id="prospects-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div class="carte">
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
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Clients visités</th>
                        <th>Localisation</th>
                        <th>Moyens</th>
                        <th>Commercial</th>
                        <th>Activité</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th>Site</th>
                        @endif
                        <th>Passage</th>
                        <th>Devis après passage</th>
                        <th>Observations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">{{ $ligne->numero }}</td>
                            <td>{{ $ligne->client }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->localisation ?? '—' }}</td>
                            <td>{{ $ligne->moyen }}</td>
                            <td>{{ $ligne->commercial->nom }}</td>
                            <td>{{ $ligne->activite }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td>{{ $ligne->passage ? '✓' : '—' }}</td>
                            <td>{{ $ligne->devis_apres_passage ? '✓' : '—' }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->observations ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 10 : 9" texte="Aucune prospection enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
