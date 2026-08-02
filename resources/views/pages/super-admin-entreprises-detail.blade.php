<?php

use App\Domain\Tenants\Models\Entreprise;
use function Livewire\Volt\{state, mount, computed};

state(['entrepriseId']);

mount(function (Entreprise $entreprise) {
    $this->entrepriseId = $entreprise->id;
});

$entreprise = computed(fn () => Entreprise::with(['sites.responsable'])->findOrFail($this->entrepriseId));

$repartition = computed(function () {
    return $this->entreprise->sites->map(function ($site) {
        return [
            'site' => $site,
            'responsables' => \App\Models\User::where('entreprise_id', $this->entrepriseId)->whereHas('roles', fn ($q) => $q->where('name', 'responsable_site'))->where('id', $site->responsable_id)->count(),
            'commerciaux' => \App\Domain\Operations\Models\Commercial::where('site_id', $site->id)->where('est_spontane', false)->count(),
        ];
    });
});

?>

<x-a-venir titre="{{ $this->entreprise->nom }}" description="Répartition des personnes par catégorie et par site.">
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Responsables</th>
                        <th>Commerciaux</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->repartition as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:600;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:99px; background:{{ $ligne['site']->couleur }}; margin-right:6px;"></span>
                                {{ $ligne['site']->nom }}
                            </td>
                            <td>{{ $ligne['responsables'] }}</td>
                            <td>{{ $ligne['commerciaux'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-a-venir>
