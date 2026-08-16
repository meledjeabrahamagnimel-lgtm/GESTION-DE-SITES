<?php

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use function Livewire\Volt\{state, computed};

state([
    'entrepriseId' => '',
    'roleActif' => 'gerant',
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    'villeChoix' => '',
    'siteChoix' => '',
    'objectifGlobal' => Commercial::OBJECTIF_MENSUEL_DEFAUT,
    'pourcentageMecanique' => (int) (Commercial::PART_MECANIQUE_DEFAUT * 100),
    'confirmation' => null,
]);

$entreprises = computed(fn () => Entreprise::where('est_active', true)->orderBy('nom')->get());

/** Villes de l'entreprise choisie : périmètre du responsable de ville, du commercial et de la comptabilité. */
$optionsVille = computed(fn () => $this->entrepriseId
    ? Ville::where('entreprise_id', $this->entrepriseId)->where('est_actif', true)->orderBy('nom')->pluck('nom', 'id')->all()
    : []);

/** Lieux de l'entreprise choisie : périmètre du seul responsable de site. */
$optionsSite = computed(fn () => $this->entrepriseId
    ? Site::where('entreprise_id', $this->entrepriseId)->where('est_actif', true)->orderBy('nom')->pluck('nom', 'id')->all()
    : []);

$rolesDisponibles = computed(fn () => [
    'gerant' => 'Gérant',
    'responsable_ville' => 'Superviseur de ville',
    'responsable_site' => 'Responsable de site',
    'commercial' => 'Commercial',
    'caissier' => 'Comptabilité',
]);

/** Rôles dont le titulaire prospecte : il reçoit une fiche commercial et des objectifs. */
$roleAvecObjectifs = computed(fn () => in_array($this->roleActif, ['responsable_ville', 'responsable_site', 'commercial'], true));

/** Répartition Mécanique/Sinistre de l'objectif global : tout lieu accueille les deux activités. */
$objectifMecanique = computed(fn () => (int) round((int) $this->objectifGlobal * ((int) $this->pourcentageMecanique) / 100));

$objectifSinistre = computed(fn () => (int) $this->objectifGlobal - $this->objectifMecanique);

$objectifAnnuel = computed(fn () => (int) $this->objectifGlobal * 12);

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

    if ($this->roleActif === 'responsable_site') {
        $regles['siteChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsSite))];
    } elseif ($this->roleActif !== 'gerant') {
        $regles['villeChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsVille))];
    }

    if ($this->roleAvecObjectifs) {
        $regles['objectifGlobal'] = ['required', 'numeric', 'min:0'];
        $regles['pourcentageMecanique'] = ['required', 'numeric', 'min:0', 'max:100'];
    }

    $donnees = $this->validate($regles, [], [
        'entrepriseId' => 'entreprise',
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'villeChoix' => 'ville',
        'siteChoix' => 'site',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mécanique',
    ]);

    $entreprise = Entreprise::findOrFail($donnees['entrepriseId']);

    $action->executer($entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'ville_id' => $donnees['villeChoix'] ?? null,
        'site_id' => $donnees['siteChoix'] ?? null,
        'objectif_mecanique' => $this->objectifMecanique,
        'objectif_sinistre' => $this->objectifSinistre,
    ]);

    $this->reset(['nom', 'email', 'motDePasse', 'siteChoix', 'villeChoix']);
    $this->objectifGlobal = Commercial::OBJECTIF_MENSUEL_DEFAUT;
    $this->pourcentageMecanique = (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    $this->confirmation = "Accès créé pour {$entreprise->nom} — mot de passe à changer à la première connexion.";
};

?>

<div>
    <div class="carte">
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

            {{-- Un responsable de site répond d'un lieu précis ; le responsable de ville,
                 le commercial et la comptabilité couvrent une ville entière. --}}
            @if ($roleActif === 'responsable_site')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site (lieu dont il répond)</label>
                <select wire:model="siteChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir un site —</option>
                    @foreach ($this->optionsSite as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
                @error('siteChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @elseif ($roleActif !== 'gerant')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Ville</label>
                <select wire:model="villeChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir une ville —</option>
                    @foreach ($this->optionsVille as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
                @error('villeChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @endif

            @if ($this->roleAvecObjectifs)
                {{-- Les responsables prospectent eux aussi : ils apparaissent parmi les
                     commerciaux et portent donc leurs propres objectifs. --}}
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:14px 0 6px;">Objectif mensuel global (FCFA)</label>
                <input type="number" wire:model.live="objectifGlobal"
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                @error('objectifGlobal') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Répartition — % Mécanique (le reste va au Sinistre)</label>
                <input type="number" wire:model.live="pourcentageMecanique" min="0" max="100"
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                @error('pourcentageMecanique') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                <div style="display:flex; gap:16px; margin-top:10px; padding:10px 12px; background:#F9F9F7; border-radius:8px; font-size:13px; color:#4B4E55; flex-wrap:wrap;">
                    <span>Mécanique : <b>{{ ae($this->objectifMecanique) }}</b> ({{ $pourcentageMecanique }}%)</span>
                    <span>Sinistre : <b>{{ ae($this->objectifSinistre) }}</b> ({{ 100 - (int) $pourcentageMecanique }}%)</span>
                    <span>Équivalent annuel : <b>{{ ae($this->objectifAnnuel) }}</b></span>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                style="background:#C8102E; color:#fff; border:0; border-radius:8px; padding:10px 20px; font-weight:700; font-size:15.5px; cursor:pointer; margin-top:18px;">
                + Créer l'accès
            </button>
        </form>
    </div>
</div>
