<?php

use App\Domain\Tenants\Actions\CreerEntreprise;
use function Livewire\Volt\{state, rules, layout, title};

layout('layouts.guest');
title('Créer votre entreprise');

state([
    'entrepriseNom' => '',
    'siteNom' => '',
    'gerantNom' => '',
    'gerantEmail' => '',
    'gerantMotDePasse' => '',
    'gerantMotDePasse_confirmation' => '',
]);

rules([
    'entrepriseNom' => ['required', 'string', 'max:255'],
    'siteNom' => ['required', 'string', 'max:255'],
    'gerantNom' => ['required', 'string', 'max:255'],
    'gerantEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
    'gerantMotDePasse' => ['required', 'string', 'min:8', 'confirmed'],
]);

$creerEntreprise = function (CreerEntreprise $action) {
    $donnees = $this->validate();

    $gerant = $action->executer([
        'entreprise_nom' => $donnees['entrepriseNom'],
        'site_nom' => $donnees['siteNom'],
        'gerant_nom' => $donnees['gerantNom'],
        'gerant_email' => $donnees['gerantEmail'],
        'gerant_mot_de_passe' => $donnees['gerantMotDePasse'],
    ]);

    auth()->login($gerant);

    $this->redirect(route('acces.creer'), navigate: true);
};

?>

<div style="min-height:100vh; background:#191B20; display:flex; align-items:center; justify-content:center; padding:20px;">
    <div style="width:100%; max-width:480px;">
        <div style="text-align:center; margin-bottom:20px;">
            <h1 style="color:#fff; font-size:20px; font-weight:800; margin:0;">{{ config('app.name') }}</h1>
            <p style="color:#9A9DA5; font-size:12px; margin-top:8px;">Créer le compte de votre entreprise</p>
        </div>

        <div style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,.35);">
            <h2 style="font-size:15px; font-weight:700; margin:0 0 4px; color:#191B20;">Inscrire mon entreprise</h2>
            <p style="font-size:12.5px; color:#6B6E76; margin:0 0 18px;">Vous créez le premier compte Gérant. Vous pourrez ensuite ajouter vos responsables de site et commerciaux.</p>

            <form wire:submit="creerEntreprise">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Nom de l'entreprise</label>
                <input type="text" wire:model="entrepriseNom" placeholder="Ex. : Garage Excellence"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:4px;">
                @error('entrepriseNom') <div style="color:#C8102E; font-size:12px; margin-bottom:10px;">{{ $message }}</div> @enderror

                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Nom de votre premier site</label>
                <input type="text" wire:model="siteNom" placeholder="Ex. : Siège, Abidjan"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:4px;">
                @error('siteNom') <div style="color:#C8102E; font-size:12px; margin-bottom:10px;">{{ $message }}</div> @enderror

                <div style="height:1px; background:#E2E0D8; margin:16px 0;"></div>
                <p style="font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#9A9DA5; margin:0 0 10px; font-weight:700;">Votre compte Gérant</p>

                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Nom et prénoms</label>
                <input type="text" wire:model="gerantNom"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:4px;">
                @error('gerantNom') <div style="color:#C8102E; font-size:12px; margin-bottom:10px;">{{ $message }}</div> @enderror

                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Adresse e-mail</label>
                <input type="email" wire:model="gerantEmail"
                    style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px; margin-bottom:4px;">
                @error('gerantEmail') <div style="color:#C8102E; font-size:12px; margin-bottom:10px;">{{ $message }}</div> @enderror

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Mot de passe</label>
                        <input type="password" wire:model="gerantMotDePasse"
                            style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Confirmation</label>
                        <input type="password" wire:model="gerantMotDePasse_confirmation"
                            style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:14px;">
                    </div>
                </div>
                @error('gerantMotDePasse') <div style="color:#C8102E; font-size:12px; margin-top:6px;">{{ $message }}</div> @enderror

                <button type="submit" wire:loading.attr="disabled"
                    style="width:100%; background:#C8102E; color:#fff; border:0; border-radius:8px; padding:12px; font-weight:700; font-size:14px; cursor:pointer; margin-top:18px;">
                    <span wire:loading.remove>Créer l'entreprise</span>
                    <span wire:loading>Création…</span>
                </button>
            </form>

            <div style="text-align:center; margin-top:16px;">
                <a href="{{ route('login') }}" wire:navigate style="font-size:12.5px; color:#6B6E76; text-decoration:none;">Déjà un compte ? Se connecter</a>
            </div>
        </div>
    </div>
</div>
