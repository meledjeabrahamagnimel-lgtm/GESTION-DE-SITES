<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Facture;
use App\Domain\Shared\Services\PeriodeCalculateur;
use function Livewire\Volt\{state, computed};

state([
    'periode' => 'mois',
    'dateDebut' => null,
    'dateFin' => null,
]);

$commercial = computed(fn () => Commercial::where('user_id', auth()->id())->with('site')->first());

$plage = computed(fn () => PeriodeCalculateur::plage($this->periode, $this->dateDebut, $this->dateFin));

$factures = computed(function () {
    if (! $this->commercial) {
        return collect();
    }
    [$debut, $fin] = $this->plage;

    return Facture::where('commercial_id', $this->commercial->id)->whereBetween('date', [$debut, $fin])->latest('date')->get();
});

$kpis = computed(function () {
    if (! $this->commercial) {
        return null;
    }
    [$debut, $fin] = $this->plage;
    $joursPeriode = PeriodeCalculateur::nombreDeJours($debut, $fin);
    $realisation = (int) $this->factures->sum('montant');
    $objectif = (int) round($this->commercial->objectif_mensuel / 30 * $joursPeriode);

    $caSite = (int) Facture::where('site_id', $this->commercial->site_id)->whereBetween('date', [$debut, $fin])->sum('montant');

    return [
        'objectif' => $objectif,
        'realisation' => $realisation,
        'ecart' => $realisation - $objectif,
        'taux' => $objectif > 0 ? $realisation / $objectif : null,
        'contribution' => $caSite > 0 ? $realisation / $caSite : null,
    ];
});

?>

<div>
    @if (! $this->commercial)
        <x-a-venir titre="Aucune fiche commerciale associée" description="Contactez votre responsable de site." />
    @else
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:22px; margin-bottom:20px;">
            <h1 style="font-size:22px; font-weight:800; margin:0 0 4px;">{{ $this->commercial->nom }}</h1>
            <p style="color:#6B6E76; font-size:14.5px; margin:0;">{{ $this->commercial->site->nom }} · {{ $this->commercial->activite ?? '—' }}</p>
        </div>

        <x-filtre-periode :periode="$periode" />

        <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:20px;">
            <x-kpi-card label="Objectif de la période" :value="ae($this->kpis['objectif'])" />
            <x-kpi-card label="Réalisation" :value="ae($this->kpis['realisation'])" />
            <x-kpi-card label="Écart" :value="ae($this->kpis['ecart'])" :couleur="$this->kpis['ecart'] >= 0 ? '#0E9F6E' : '#C8102E'" />
            <x-kpi-card label="Taux d'atteinte" :value="an($this->kpis['taux'])" :accent="($this->kpis['taux'] ?? 0) >= 1" />
            <x-kpi-card label="Contribution au CA du site" :value="an($this->kpis['contribution'])" />
        </div>

        <div class="carte">
            <h3 style="font-size:15px; font-weight:700; margin:0 0 14px;">Détail des facturations ({{ $this->factures->count() }})</h3>
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Activité</th>
                            <th>Type</th>
                            <th>Montant facturé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->factures as $facture)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="font-weight:700;">{{ $facture->numero }}</td>
                                <td>{{ $facture->date->format('d/m/Y') }}</td>
                                <td>{{ $facture->client }}</td>
                                <td>{{ $facture->activite }}</td>
                                <td>{{ $facture->type }}</td>
                                <td style="font-variant-numeric:tabular-nums; font-weight:700;">{{ ae($facture->montant) }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucune facture sur cette période." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
