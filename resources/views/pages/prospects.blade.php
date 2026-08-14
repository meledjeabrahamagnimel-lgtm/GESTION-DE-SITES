<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Prospection;
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
    'recherche' => '',
    'commercialFiltre' => '',
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
    $q = Prospection::query()->visibles()->whereIn('site_id', $this->idsSites)->whereBetween('date', [$debut, $fin]);

    if ($this->recherche) {
        $q->where('client', 'like', '%'.$this->recherche.'%');
    }

    if ($this->commercialFiltre) {
        $q->where('commercial_id', $this->commercialFiltre);
    }

    return $q;
});

$commerciaux = computed(fn () => Commercial::where('est_spontane', false)
    ->whereIn('site_id', $this->idsSites)->orderBy('nom')->get());

/** Répartition des prospections par commercial sur la période retenue. */
$parCommercial = computed(function () {
    $lignes = (clone $this->requeteBase)->get();

    return $this->commerciaux->map(function ($commercial) use ($lignes) {
        $siens = $lignes->where('commercial_id', $commercial->id);
        $passages = $siens->where('passage', true);
        $devisApres = $siens->where('devis_apres_passage', true)->count();

        return [
            'commercial' => $commercial,
            'clients' => $siens->count(),
            'passages' => $passages->count(),
            'devisApres' => $devisApres,
            'taux' => $passages->count() > 0 ? $devisApres / $passages->count() : null,
        ];
    })->filter(fn ($l) => $l['clients'] > 0)->sortByDesc('clients')->values();
});

$kpis = computed(function () {
    $lignes = (clone $this->requeteBase)->get();
    $passages = $lignes->where('passage', true);
    $passagesMecanique = $passages->where('activite', 'Mécanique');
    $passagesSinistre = $passages->where('activite', 'Sinistre');
    $devisApresMecanique = $passagesMecanique->where('devis_apres_passage', true)->count();
    $devisApresSinistre = $passagesSinistre->where('devis_apres_passage', true)->count();

    return [
        'clients' => $lignes->count(),
        'clientsMecanique' => $lignes->where('activite', 'Mécanique')->count(),
        'clientsSinistre' => $lignes->where('activite', 'Sinistre')->count(),
        'passages' => $passages->count(),
        'passagesMecanique' => $passagesMecanique->count(),
        'passagesSinistre' => $passagesSinistre->count(),
        'devisApres' => $lignes->where('devis_apres_passage', true)->count(),
        'devisApresMecanique' => $devisApresMecanique,
        'devisApresSinistre' => $devisApresSinistre,
        'tauxMecanique' => $passagesMecanique->count() > 0 ? $devisApresMecanique / $passagesMecanique->count() : null,
        'tauxSinistre' => $passagesSinistre->count() > 0 ? $devisApresSinistre / $passagesSinistre->count() : null,
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

$detail = computed(fn () => (clone $this->requeteBase)->with(['commercial', 'site'])->latest('date')->get());

?>

<div>
    <x-filtre-periode :periode="$periode" :villes="$this->mesVilles" :ville-unique="$this->villeUnique"
        :ville-filtre="$villeFiltre" :activite-filtre="$activiteFiltre"
        :mois-filtre="$moisFiltre" :semaine-filtre="$semaineFiltre" :jour-filtre="$jourFiltre"
        :commerciaux="$this->commerciaux" :commercial-filtre="$commercialFiltre" />

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; margin-bottom:16px;">
        <x-kpi-card label="Clients visités — {{ $this->libellePerimetre }}" :value="$this->kpis['clients']"
            :mecanique="$activiteFiltre ? null : $this->kpis['clientsMecanique']" :sinistre="$activiteFiltre ? null : $this->kpis['clientsSinistre']" />
        <x-kpi-card label="Passages sur site — {{ $this->libellePerimetre }}" :value="$this->kpis['passages']"
            :mecanique="$activiteFiltre ? null : $this->kpis['passagesMecanique']" :sinistre="$activiteFiltre ? null : $this->kpis['passagesSinistre']" />
        <x-kpi-card label="Devis après passage — {{ $this->libellePerimetre }}" :value="$this->kpis['devisApres']"
            :mecanique="$activiteFiltre ? null : $this->kpis['devisApresMecanique']" :sinistre="$activiteFiltre ? null : $this->kpis['devisApresSinistre']" />
        <x-kpi-card label="Taux devis / passage — {{ $this->libellePerimetre }}"
            :value="an($this->kpis['passages'] > 0 ? $this->kpis['devisApres'] / $this->kpis['passages'] : null)"
            :mecanique="$activiteFiltre ? null : an($this->kpis['tauxMecanique'])" :sinistre="$activiteFiltre ? null : an($this->kpis['tauxSinistre'])" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Visites, passages et devis établis" id="prospects-hebdo"
            :labels="$this->graphique['labels']" :datasets="$this->graphique['datasets']" />
    </div>

    <div class="carte" style="margin-bottom:20px;">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Répartition par commercial</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Commercial</th>
                        <th>Clients visités</th>
                        <th>Passages</th>
                        <th>Devis après passage</th>
                        <th>Taux devis / passage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->parCommercial as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">{{ $ligne['commercial']->nom }}</td>
                            <td>{{ $ligne['clients'] }}</td>
                            <td>{{ $ligne['passages'] }}</td>
                            <td>{{ $ligne['devisApres'] }}</td>
                            <td style="font-weight:700; color:{{ ($ligne['taux'] ?? 0) >= 0.5 ? '#0E9F6E' : '#D97706' }};">{{ an($ligne['taux']) }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="5" texte="Aucune prospection enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="carte">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des opérations ({{ $this->detail->count() }})</h3>
        <div style="display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
            <input type="text" wire:model.live.debounce.400ms="recherche" placeholder="Client / tiers…"
                style="flex:1; min-width:200px; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px;">
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
                        @if (! $activiteFiltre && count($this->idsSites) > 1)
                            <th>Site</th>
                        @endif
                        <th>Passage</th>
                        <th>Devis après passage</th>
                        <th>Observations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail->forPage($pageDetail, 10) as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">{{ $ligne->numero }}</td>
                            <td>{{ $ligne->client }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->localisation ?? '—' }}</td>
                            <td>{{ $ligne->moyen }}</td>
                            <td>{{ $ligne->commercial->nom }}</td>
                            <td>{{ $ligne->activite }}</td>
                            @if (! $activiteFiltre && count($this->idsSites) > 1)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td>{{ $ligne->passage ? '✓' : '—' }}</td>
                            <td>{{ $ligne->devis_apres_passage ? '✓' : '—' }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->observations ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="! $activiteFiltre && count($this->idsSites) > 1 ? 10 : 9" texte="Aucune prospection enregistrée sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageDetail" :total="$this->detail->count()" prop="pageDetail" />
    </div>
</div>
