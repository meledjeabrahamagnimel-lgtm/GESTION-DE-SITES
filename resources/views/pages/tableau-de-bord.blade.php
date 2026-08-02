<?php

use function Livewire\Volt\{state, computed};

state(['periode' => 'semaine']);

$kpis = computed(function () {
    $entrepriseId = auth()->user()->entreprise_id;

    return [
        'sites' => \App\Domain\Tenants\Models\Site::where('entreprise_id', $entrepriseId)->count(),
        'commerciaux' => \App\Domain\Operations\Models\Commercial::actifs()->count(),
        'ca_facture' => (int) \App\Domain\Operations\Models\Facture::sum('montant'),
        'devis_en_attente' => \App\Domain\Operations\Models\Devis::enAttente()->count(),
    ];
});

?>

<div>
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px;">
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6B6E76; margin-bottom:6px;">Sites actifs</div>
            <div style="font-size:26px; font-weight:800;">{{ $this->kpis['sites'] }}</div>
        </div>
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6B6E76; margin-bottom:6px;">Commerciaux actifs</div>
            <div style="font-size:26px; font-weight:800;">{{ $this->kpis['commerciaux'] }}</div>
        </div>
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6B6E76; margin-bottom:6px;">CA facturé (cumul)</div>
            <div style="font-size:26px; font-weight:800;">{{ number_format($this->kpis['ca_facture'], 0, ',', ' ') }} F</div>
        </div>
        <div style="background:#fff; border:2px solid var(--th-accent,#C8102E); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6B6E76; margin-bottom:6px;">Devis en attente</div>
            <div style="font-size:26px; font-weight:800; color:var(--th-accent,#C8102E);">{{ $this->kpis['devis_en_attente'] }}</div>
        </div>
    </div>

    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px; color:#6B6E76; font-size:13.5px;">
        Alertes, comparaison des sites et classement des commerciaux arrivent dans cette section.
    </div>
</div>
