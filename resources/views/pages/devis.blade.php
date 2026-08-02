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
        // Écart moyen entre le montant proposé et le montant réellement validé.
        'differenciation' => $valides->count() > 0
            ? (int) round($valides->sum('montant_devis') - $valides->sum('montant_valide')) / $valides->count()
            : null,
        // Délai moyen, en jours, entre la réception du véhicule et l'émission du devis.
        'delaiEnvoi' => $this->delaiMoyenEnvoi($lignes),
    ];
});

/** Délai moyen réception → émission, en jours (null si aucune date de réception connue). */
$delaiMoyenEnvoi = function ($lignes) {
    $avecReception = $lignes->filter(fn ($d) => $d->date_reception && $d->date_emission);

    if ($avecReception->isEmpty()) {
        return null;
    }

    return round($avecReception->sum(fn ($d) => $d->date_reception->diffInDays($d->date_emission)) / $avecReception->count(), 1);
};

$graphique = computed(function () {
    [$debut, $fin] = $this->plage;
    $points = PeriodeCalculateur::points($debut, $fin);

    $labels = [];
    $emis = [];
    $valides = [];
    $taux = [];

    foreach ($points as $point) {
        $lignes = (clone $this->requeteBase)->whereBetween('date_emission', [$point['debut'], $point['fin']])->get();
        $nbEmis = $lignes->count();
        $nbValides = $lignes->where('statut', 'Validé')->count();

        $labels[] = $point['label'];
        $emis[] = $nbEmis;
        $valides[] = $nbValides;
        $taux[] = $nbEmis > 0 ? round($nbValides / $nbEmis * 100, 1) : 0;
    }

    return [
        'labels' => $labels,
        'datasets' => [
            ['label' => 'Émis', 'data' => $emis, 'color' => '#191B20'],
            ['label' => 'Validés', 'data' => $valides, 'color' => '#0E9F6E'],
            ['label' => 'Taux transfo (%)', 'data' => $taux, 'color' => '#D97706', 'type' => 'line', 'axe' => 'y1'],
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
        <x-kpi-card label="Taux transfo (nb)" :value="an($this->kpis['tauxTransfo'])" />
        <x-kpi-card label="Différenciation moyenne" :value="ae($this->kpis['differenciation'] !== null ? (int) $this->kpis['differenciation'] : null)"
            sub="Écart moyen devis → validé" />
        <x-kpi-card label="Délai moyen d'envoi"
            :value="$this->kpis['delaiEnvoi'] !== null ? str_replace('.', ',', (string) $this->kpis['delaiEnvoi']).' j' : '—'"
            sub="Réception → émission" />
    </div>

    <div style="margin-bottom:20px;">
        <x-chart-card titre="Devis émis vs validés" id="devis-hebdo"
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
                        <th>Date de réception</th>
                        <th>N° fiche de réception</th>
                        <th>Client</th>
                        <th>Date d'émission</th>
                        <th>Commercial</th>
                        @if ($this->estGerant && ! $siteFiltre)
                            <th>Site</th>
                        @endif
                        <th>Statut du devis</th>
                        <th>Montant du devis</th>
                        <th>Montant validé</th>
                        <th>Activité</th>
                        <th>Observations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->detail as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">{{ $ligne->numero }}</td>
                            <td>{{ $ligne->date_reception?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $ligne->n_fiche_reception ?? '—' }}</td>
                            <td>{{ $ligne->client }}</td>
                            <td>{{ $ligne->date_emission->format('d/m/Y') }}</td>
                            <td>{{ $ligne->commercial->nom }}</td>
                            @if ($this->estGerant && ! $siteFiltre)
                                <td>{{ $ligne->site->nom }}</td>
                            @endif
                            <td>
                                <span style="font-weight:700; color:{{ ['En attente' => '#D97706', 'Validé' => '#0E9F6E', 'Refusé' => '#C8102E'][$ligne->statut] }};">{{ $ligne->statut }}</span>
                            </td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne->montant_devis) }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ ae($ligne->montant_valide) }}</td>
                            <td>{{ $ligne->activite }}</td>
                            <td style="color:#6B6E76;">{{ $ligne->observations ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-table-vide :colspan="$this->estGerant && ! $siteFiltre ? 12 : 11" texte="Aucun devis enregistré sur cette période." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->detail->count() >= 50)
            <p style="font-size:12.5px; color:#9A9DA5; margin-top:12px;">50 lignes affichées — affinez avec les filtres.</p>
        @endif
    </div>
</div>
