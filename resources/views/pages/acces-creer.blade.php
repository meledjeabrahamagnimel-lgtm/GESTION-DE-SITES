<?php

use App\Domain\Tenants\Actions\CreerAcces;
use App\Domain\Tenants\Models\Site;
use function Livewire\Volt\{state, computed, mount, rules};

state([
    'roleActif' => null,
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    'siteId' => '',
    'activite' => 'Mécanique',
    'objectifMensuel' => '',
    'confirmation' => null,
]);

mount(function () {
    $this->roleActif = auth()->user()->hasRole('gerant') ? 'responsable_site' : 'commercial';

    if (auth()->user()->hasRole('responsable_site')) {
        $this->siteId = Site::where('responsable_id', auth()->id())->value('id');
    }
});

$rolesDisponibles = computed(function () {
    $roles = [];
    if (auth()->user()->hasRole('gerant')) {
        $roles['responsable_site'] = 'Responsable de site';
        $roles['commercial'] = 'Commercial';
    } elseif (auth()->user()->hasRole('responsable_site')) {
        $roles['commercial'] = 'Commercial';
    }

    return $roles;
});

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get());

$derniersAcces = computed(function () {
    return \App\Models\User::where('entreprise_id', auth()->user()->entreprise_id)
        ->latest()
        ->limit(8)
        ->get();
});

$choisirRole = function (string $role) {
    if (! array_key_exists($role, $this->rolesDisponibles)) {
        return;
    }
    $this->roleActif = $role;
    $this->resetValidation();
};

$creer = function (CreerAcces $action) {
    $regles = [
        'nom' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'motDePasse' => ['required', 'string', 'min:8'],
    ];

    if ($this->roleActif === 'responsable_site') {
        $regles['siteId'] = ['required', 'exists:sites,id'];
    }

    if ($this->roleActif === 'commercial') {
        $regles['siteId'] = ['required', 'exists:sites,id'];
        $regles['activite'] = ['required', 'in:Mécanique,Carrosserie'];
        $regles['objectifMensuel'] = ['nullable', 'numeric', 'min:0'];
    }

    $donnees = $this->validate($regles, [], [
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'siteId' => 'site',
        'activite' => 'activité',
    ]);

    $action->executer(auth()->user()->entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'site_id' => $donnees['siteId'] ?? (auth()->user()->hasRole('responsable_site') ? $this->siteId : null),
        'activite' => $donnees['activite'] ?? null,
        'objectif_mensuel' => $donnees['objectifMensuel'] ?? 0,
    ]);

    $this->reset(['nom', 'email', 'motDePasse', 'activite', 'objectifMensuel']);
    if (auth()->user()->hasRole('gerant')) {
        $this->siteId = '';
    }
    $this->confirmation = "Accès créé — mot de passe à changer à la première connexion.";
};

?>

<div>
    <div class="carte">
        <h1 style="font-size:18px; font-weight:800; margin:0 0 4px;">Ajouter un accès</h1>
        <p style="color:#6B6E76; font-size:15px; margin:0 0 18px;">Créez un compte pour un membre de votre équipe. Le mot de passe devra être changé à la première connexion.</p>

        @if (count($this->rolesDisponibles) > 1)
            <div style="display:flex; gap:8px; margin-bottom:18px;">
                @foreach ($this->rolesDisponibles as $cle => $libelle)
                    <button type="button" wire:click="choisirRole('{{ $cle }}')"
                        style="padding:9px 16px; border-radius:8px; font-size:14.5px; font-weight:700; cursor:pointer;
                               border:2px solid {{ $roleActif === $cle ? 'var(--th-accent,#C8102E)' : 'var(--th-ligne,#E2E0D8)' }};
                               background:{{ $roleActif === $cle ? '#FDF2F4' : '#fff' }};
                               color:{{ $roleActif === $cle ? 'var(--th-accent,#C8102E)' : '#4B4E55' }};">
                        {{ $libelle }}
                    </button>
                @endforeach
            </div>
        @endif

        @if ($confirmation)
            <div style="background:#EAF9F3; border:1px solid #0E9F6E55; color:#0E9F6E; border-radius:8px; padding:10px 12px; font-size:14.5px; margin-bottom:16px;">
                {{ $confirmation }}
            </div>
        @endif

        <form wire:submit="creer" style="max-width:480px;">
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Nom et prénoms</label>
            <input type="text" wire:model="nom"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('nom') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Adresse e-mail</label>
            <input type="email" wire:model="email"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('email') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Mot de passe provisoire</label>
            <input type="password" wire:model="motDePasse"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('motDePasse') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            @if ($roleActif === 'responsable_site')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site</label>
                <select wire:model="siteId" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir un site —</option>
                    @foreach ($this->sites as $site)
                        <option value="{{ $site->id }}">{{ $site->nom }}</option>
                    @endforeach
                </select>
                @error('siteId') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @endif

            @if ($roleActif === 'commercial')
                @if (auth()->user()->hasRole('gerant'))
                    <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site</label>
                    <select wire:model="siteId" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                        <option value="">— Choisir un site —</option>
                        @foreach ($this->sites as $site)
                            <option value="{{ $site->id }}">{{ $site->nom }}</option>
                        @endforeach
                    </select>
                    @error('siteId') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
                @endif

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Activité</label>
                        <select wire:model="activite" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                            <option value="Mécanique">Mécanique</option>
                            <option value="Carrosserie">Carrosserie</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Objectif mensuel (FCFA)</label>
                        <input type="number" wire:model="objectifMensuel"
                            style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    </div>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                class="bouton">
                + Ajouter
            </button>
        </form>
    </div>

    <div class="carte">
        <h3 style="font-size:15px; font-weight:700; margin:0 0 12px;">Derniers accès créés</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->derniersAcces as $utilisateur)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:600;">{{ $utilisateur->name }}</td>
                            <td style="color:#6B6E76;">{{ $utilisateur->email }}</td>
                            <td>
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
    </div>
</div>
