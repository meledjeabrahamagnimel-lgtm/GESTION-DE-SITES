<?php

use App\Domain\Tenants\Actions\CreerAcces;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use function Livewire\Volt\{state, computed};

state([
    'entrepriseId' => '',
    'roleActif' => 'gerant',
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    'siteId' => '',
    'activite' => 'Mécanique',
    'objectifMensuel' => '',
    'confirmation' => null,
]);

$entreprises = computed(fn () => Entreprise::where('est_active', true)->orderBy('nom')->get());

$sites = computed(fn () => $this->entrepriseId ? Site::where('entreprise_id', $this->entrepriseId)->where('est_actif', true)->orderBy('nom')->get() : collect());

$rolesDisponibles = computed(fn () => [
    'gerant' => 'Gérant',
    'responsable_site' => 'Responsable de site',
    'commercial' => 'Commercial',
]);

$choisirRole = function (string $role) {
    $this->roleActif = $role;
    $this->resetValidation();
};

$creer = function (CreerAcces $action) {
    $regles = [
        'entrepriseId' => ['required', 'exists:entreprises,id'],
        'nom' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'motDePasse' => ['required', 'string', 'min:8'],
    ];

    if (in_array($this->roleActif, ['responsable_site', 'commercial'], true)) {
        $regles['siteId'] = ['required', 'exists:sites,id'];
    }
    if ($this->roleActif === 'commercial') {
        $regles['activite'] = ['required', 'in:Mécanique,Carrosserie'];
        $regles['objectifMensuel'] = ['nullable', 'numeric', 'min:0'];
    }

    $donnees = $this->validate($regles, [], [
        'entrepriseId' => 'entreprise',
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'siteId' => 'site',
        'activite' => 'activité',
    ]);

    $entreprise = Entreprise::findOrFail($donnees['entrepriseId']);

    $action->executer($entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'site_id' => $donnees['siteId'] ?? null,
        'activite' => $donnees['activite'] ?? null,
        'objectif_mensuel' => $donnees['objectifMensuel'] ?? 0,
    ]);

    $this->reset(['nom', 'email', 'motDePasse', 'activite', 'objectifMensuel']);
    $this->confirmation = "Accès créé pour {$entreprise->nom} — mot de passe à changer à la première connexion.";
};

?>

<div>
    <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:24px;">
        <h1 style="font-size:18px; font-weight:800; margin:0 0 4px;">Créer un accès</h1>
        <p style="color:#6B6E76; font-size:15px; margin:0 0 18px;">Provisionnez un compte pour n'importe quel rôle, dans n'importe quelle entreprise cliente.</p>

        <div style="display:flex; gap:8px; margin-bottom:18px;">
            @foreach ($this->rolesDisponibles as $cle => $libelle)
                <button type="button" wire:click="choisirRole('{{ $cle }}')"
                    style="padding:9px 16px; border-radius:8px; font-size:14.5px; font-weight:700; cursor:pointer;
                           border:2px solid {{ $roleActif === $cle ? '#C8102E' : '#E2E0D8' }};
                           background:{{ $roleActif === $cle ? '#FDF2F4' : '#fff' }};
                           color:{{ $roleActif === $cle ? '#C8102E' : '#4B4E55' }};">
                    {{ $libelle }}
                </button>
            @endforeach
        </div>

        @if ($confirmation)
            <div style="background:#EAF9F3; border:1px solid #0E9F6E55; color:#0E9F6E; border-radius:8px; padding:10px 12px; font-size:14.5px; margin-bottom:16px;">
                {{ $confirmation }}
            </div>
        @endif

        <form wire:submit="creer" style="max-width:480px;">
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Entreprise</label>
            <select wire:model="entrepriseId" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
                <option value="">— Choisir une entreprise —</option>
                @foreach ($this->entreprises as $entreprise)
                    <option value="{{ $entreprise->id }}">{{ $entreprise->nom }}</option>
                @endforeach
            </select>
            @error('entrepriseId') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Nom et prénoms</label>
            <input type="text" wire:model="nom"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('nom') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Adresse e-mail</label>
            <input type="email" wire:model="email"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('email') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Mot de passe provisoire</label>
            <input type="password" wire:model="motDePasse"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('motDePasse') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            @if (in_array($roleActif, ['responsable_site', 'commercial'], true))
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site</label>
                <select wire:model="siteId" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir un site —</option>
                    @foreach ($this->sites as $site)
                        <option value="{{ $site->id }}">{{ $site->nom }}</option>
                    @endforeach
                </select>
                @error('siteId') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @endif

            @if ($roleActif === 'commercial')
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Activité</label>
                        <select wire:model="activite" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                            <option value="Mécanique">Mécanique</option>
                            <option value="Carrosserie">Carrosserie</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Objectif mensuel (FCFA)</label>
                        <input type="number" wire:model="objectifMensuel"
                            style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    </div>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                style="background:#C8102E; color:#fff; border:0; border-radius:8px; padding:10px 20px; font-weight:700; font-size:15.5px; cursor:pointer; margin-top:18px;">
                + Créer l'accès
            </button>
        </form>
    </div>
</div>
