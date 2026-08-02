<?php

use function Livewire\Volt\{computed};

$utilisateurs = computed(fn () => \App\Models\User::with('entreprise')->orderByDesc('created_at')->limit(50)->get());

$roles = computed(fn () => \App\Models\User::nomsRolesParUtilisateur($this->utilisateurs->pluck('id')));

?>

<x-a-venir titre="Gestion des accès"
        description="Créer, révoquer et forcer la réinitialisation des accès de toute personne, toutes entreprises confondues.">
        <a href="{{ route('super-admin.acces.creer') }}" wire:navigate
           style="display:inline-block; background:#C8102E; color:#fff; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13px; text-decoration:none; margin-bottom:16px;">
            + Créer un accès
        </a>
        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                        <th style="padding:8px 10px;">Utilisateur</th>
                        <th style="padding:8px 10px;">Entreprise</th>
                        <th style="padding:8px 10px;">Rôle</th>
                        <th style="padding:8px 10px;">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->utilisateurs as $utilisateur)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:8px 10px;">
                                <div style="font-weight:600;">{{ $utilisateur->name }}</div>
                                <div style="color:#6B6E76; font-size:12px;">{{ $utilisateur->email }}</div>
                            </td>
                            <td style="padding:8px 10px;">{{ $utilisateur->entreprise?->nom ?? '— Plateforme —' }}</td>
                            <td style="padding:8px 10px;">{{ $this->roles[$utilisateur->id] ?? '—' }}</td>
                            <td style="padding:8px 10px;">
                                @if ($utilisateur->est_actif)
                                    <span style="color:#0E9F6E; font-weight:600;">Actif</span>
                                @else
                                    <span style="color:#C8102E; font-weight:600;">Révoqué</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-a-venir>
