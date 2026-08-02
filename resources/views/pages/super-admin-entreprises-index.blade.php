<?php

use function Livewire\Volt\{computed};

$entreprises = computed(fn () => \App\Domain\Tenants\Models\Entreprise::withCount(['sites', 'utilisateurs'])->get());

?>

<x-a-venir titre="Entreprises" description="Toutes les entreprises clientes de la plateforme.">
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:14.5px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                        <th style="padding:8px 10px;">Entreprise</th>
                        <th style="padding:8px 10px;">Plan</th>
                        <th style="padding:8px 10px;">Sites</th>
                        <th style="padding:8px 10px;">Utilisateurs</th>
                        <th style="padding:8px 10px;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->entreprises as $entreprise)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:8px 10px; font-weight:700;">
                                <a href="{{ route('super-admin.entreprises.show', $entreprise) }}" wire:navigate style="color:inherit;">{{ $entreprise->nom }}</a>
                            </td>
                            <td style="padding:8px 10px; text-transform:capitalize;">{{ $entreprise->plan }}</td>
                            <td style="padding:8px 10px;">{{ $entreprise->sites_count }}</td>
                            <td style="padding:8px 10px;">{{ $entreprise->utilisateurs_count }}</td>
                            <td style="padding:8px 10px;">
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
    </x-a-venir>
