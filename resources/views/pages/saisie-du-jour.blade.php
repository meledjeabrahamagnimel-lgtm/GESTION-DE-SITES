<?php

use function Livewire\Volt\{state, computed};

$site = computed(fn () => \App\Domain\Tenants\Models\Site::where('responsable_id', auth()->id())->first());

?>

<x-a-venir titre="Saisie du jour — {{ $this->site?->nom }}"
    description="Commerciaux, prospections, devis, encaissements, charges et véhicules sans facture : le formulaire de saisie immédiate arrive ici.">
    <div style="display:flex; gap:10px; flex-wrap:wrap; font-size:14px; color:#6B6E76;">
        <span style="background:var(--th-paper,#F4F3EF); border:1px solid var(--th-ligne,#E2E0D8); border-radius:99px; padding:4px 10px;">
            {{ \App\Domain\Operations\Models\Commercial::where('site_id', $this->site?->id)->count() }} commerciaux rattachés
        </span>
    </div>
</x-a-venir>
