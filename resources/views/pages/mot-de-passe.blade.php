<?php

use Illuminate\Support\Facades\Hash;
use function Livewire\Volt\{state};

state([
    'motDePasseActuel' => '',
    'nouveauMotDePasse' => '',
    'nouveauMotDePasse_confirmation' => '',
    'premiereConnexion' => fn () => (bool) auth()->user()->doit_changer_mot_de_passe,
]);

$modifier = function () {
    $donnees = $this->validate([
        'motDePasseActuel' => ['required', 'current_password'],
        'nouveauMotDePasse' => ['required', 'string', 'min:8', 'confirmed'],
    ], [], [
        'motDePasseActuel' => 'mot de passe actuel',
        'nouveauMotDePasse' => 'nouveau mot de passe',
    ]);

    auth()->user()->forceFill([
        'password' => Hash::make($donnees['nouveauMotDePasse']),
        'doit_changer_mot_de_passe' => false,
    ])->save();

    $this->reset(['motDePasseActuel', 'nouveauMotDePasse', 'nouveauMotDePasse_confirmation']);

    $this->redirectRoute('redirection', navigate: true);
};

?>

<div style="max-width:480px; margin:40px auto;">
    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:26px;">
        <h1 style="font-size:20px; font-weight:800; margin:0 0 6px;">Changer mon mot de passe</h1>

        @if ($premiereConnexion)
            <p style="color:#D97706; font-size:14.5px; margin:0 0 18px; background:#FFFBEA; border:1px solid #D9770655; border-radius:8px; padding:10px 12px;">
                Pour des raisons de sécurité, vous devez choisir un nouveau mot de passe avant de continuer.
            </p>
        @else
            <p style="color:#6B6E76; font-size:15px; margin:0 0 18px;">Choisissez un mot de passe d'au moins 8 caractères.</p>
        @endif

        <form wire:submit="modifier">
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Mot de passe actuel</label>
            <input type="password" wire:model="motDePasseActuel"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('motDePasseActuel') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Nouveau mot de passe</label>
            <input type="password" wire:model="nouveauMotDePasse"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('nouveauMotDePasse') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Confirmer le nouveau mot de passe</label>
            <input type="password" wire:model="nouveauMotDePasse_confirmation"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">

            <button type="submit" wire:loading.attr="disabled"
                class="bouton">
                Enregistrer le nouveau mot de passe
            </button>
        </form>
    </div>
</div>
