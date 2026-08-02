<?php

use function Livewire\Volt\{computed};

$stats = computed(fn () => [
    'entreprises' => \App\Domain\Tenants\Models\Entreprise::count(),
    'entreprises_actives' => \App\Domain\Tenants\Models\Entreprise::where('est_active', true)->count(),
    'utilisateurs' => \App\Models\User::count(),
    'sites' => \App\Domain\Tenants\Models\Site::count(),
]);

?>

<div>
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px;">
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; color:#6B6E76; margin-bottom:6px;">Entreprises</div>
            <div style="font-size:26px; font-weight:800;">{{ $this->stats['entreprises'] }}</div>
            <div style="font-size:12px; color:#0E9F6E;">{{ $this->stats['entreprises_actives'] }} actives</div>
        </div>
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; color:#6B6E76; margin-bottom:6px;">Utilisateurs</div>
            <div style="font-size:26px; font-weight:800;">{{ $this->stats['utilisateurs'] }}</div>
        </div>
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:16px 18px;">
            <div style="font-size:11px; text-transform:uppercase; color:#6B6E76; margin-bottom:6px;">Sites</div>
            <div style="font-size:26px; font-weight:800;">{{ $this->stats['sites'] }}</div>
        </div>
    </div>

    <x-a-venir titre="Répartition par entreprise"
        description="Nombre de personnes par catégorie et par site, pour chaque entreprise cliente." />
</div>
