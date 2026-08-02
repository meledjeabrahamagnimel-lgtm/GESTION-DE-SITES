<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, computed};

state(['motDePasseGenerePour' => null, 'motDePasseGenere' => null]);

$utilisateurs = computed(fn () => User::with('entreprise')->orderByDesc('created_at')->limit(80)->get());

$roles = computed(fn () => User::nomsRolesParUtilisateur($this->utilisateurs->pluck('id')));

$basculerActif = function (int $id) {
    if ($id === auth()->id()) {
        return;
    }

    $utilisateur = User::findOrFail($id);
    $utilisateur->update(['est_actif' => ! $utilisateur->est_actif]);
};

$forcerReinitialisation = function (int $id) {
    $utilisateur = User::findOrFail($id);
    $motDePasse = Str::password(12);

    $utilisateur->forceFill([
        'password' => Hash::make($motDePasse),
        'doit_changer_mot_de_passe' => true,
    ])->save();

    $this->motDePasseGenerePour = $utilisateur->name;
    $this->motDePasseGenere = $motDePasse;
};

?>

<x-a-venir titre="Gestion des accès"
        description="Créer, révoquer et forcer la réinitialisation des accès de toute personne, toutes entreprises confondues.">
        <a href="{{ route('super-admin.acces.creer') }}" wire:navigate
           style="display:inline-block; background:#C8102E; color:#fff; border-radius:8px; padding:9px 16px; font-weight:700; font-size:14.5px; text-decoration:none; margin-bottom:16px;">
            + Créer un accès
        </a>

        @if ($motDePasseGenere)
            <div style="background:#FFFBEA; border:1px solid #D97706; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:14px;">
                Nouveau mot de passe provisoire pour <b>{{ $motDePasseGenerePour }}</b> :
                <code style="background:#fff; border:1px solid #D9770655; border-radius:6px; padding:2px 8px; font-size:14.5px; margin:0 4px;">{{ $motDePasseGenere }}</code>
                — à transmettre à la personne concernée. Elle devra le changer à sa prochaine connexion.
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table style="border-collapse:collapse; width:100%; font-size:14.5px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                        <th style="padding:8px 10px;">Utilisateur</th>
                        <th style="padding:8px 10px;">Entreprise</th>
                        <th style="padding:8px 10px;">Rôle</th>
                        <th style="padding:8px 10px;">Statut</th>
                        <th style="padding:8px 10px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->utilisateurs as $utilisateur)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="padding:8px 10px;">
                                <div style="font-weight:600;">{{ $utilisateur->name }}</div>
                                <div style="color:#6B6E76; font-size:13.5px;">{{ $utilisateur->email }}</div>
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
                            <td style="padding:8px 10px; text-align:right; white-space:nowrap;">
                                @if ($utilisateur->id !== auth()->id())
                                    <button type="button" wire:click="basculerActif({{ $utilisateur->id }})"
                                        wire:confirm="{{ $utilisateur->est_actif ? 'Révoquer l\'accès de '.$utilisateur->name.' ?' : 'Réactiver l\'accès de '.$utilisateur->name.' ?' }}"
                                        style="background:transparent; border:1px solid {{ $utilisateur->est_actif ? '#C8102E55' : '#0E9F6E55' }}; color:{{ $utilisateur->est_actif ? '#C8102E' : '#0E9F6E' }}; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; margin-right:6px;">
                                        {{ $utilisateur->est_actif ? 'Révoquer' : 'Réactiver' }}
                                    </button>
                                    <button type="button" wire:click="forcerReinitialisation({{ $utilisateur->id }})"
                                        wire:confirm="Générer un nouveau mot de passe provisoire pour {{ $utilisateur->name }} ?"
                                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer;">
                                        Forcer la réinitialisation
                                    </button>
                                @else
                                    <span style="color:#9A9DA5; font-size:12.5px;">— vous-même —</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-a-venir>
