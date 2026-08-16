<?php

use function Livewire\Volt\{computed, state};

state(['pageEntreprises' => 1]);

$entreprises = computed(fn () => \Modules\Noyau\Entreprises\Modeles\Entreprise::withCount(['sites', 'utilisateurs'])->get());

?>

<x-a-venir titre="Entreprises" description="Toutes les entreprises clientes de la plateforme.">
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Plan</th>
                        <th>Sites</th>
                        <th>Utilisateurs</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->entreprises->forPage($pageEntreprises, 10) as $entreprise)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:700;">
                                <a href="{{ route('super-admin.entreprises.show', $entreprise) }}" wire:navigate style="color:inherit;">{{ $entreprise->nom }}</a>
                            </td>
                            <td style="text-transform:capitalize;">{{ $entreprise->plan }}</td>
                            <td>{{ $entreprise->sites_count }}</td>
                            <td>{{ $entreprise->utilisateurs_count }}</td>
                            <td>
                                @if ($entreprise->est_active)
                                    <span style="color:#0E9F6E; font-weight:600;">Active</span>
                                @else
                                    <span style="color:#C8102E; font-weight:600;">Suspendue</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageEntreprises" :total="$this->entreprises->count()" prop="pageEntreprises" />
    </x-a-venir>
