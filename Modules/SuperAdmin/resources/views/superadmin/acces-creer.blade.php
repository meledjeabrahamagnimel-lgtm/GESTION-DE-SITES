<?php

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;
use function Livewire\Volt\{state, computed, mount};

/*
|--------------------------------------------------------------------------
| Ouvrir un acces, ou le reprendre
|--------------------------------------------------------------------------
| Le meme ecran sert aux deux : les champs sont les memes, et corriger un acces
| dans une ligne de tableau donnait un formulaire a l'etroit, illisible des que
| l'adresse depassait la largeur de la colonne.
|
| Le role et l'entreprise ne se reprennent que sur un acces jamais ouvert. Une
| fois qu'il a servi, ses ecritures lui sont rattachees : le deplacer les
| laisserait derriere lui, rattachees a un perimetre qui n'est plus le sien.
*/

state([
    'entrepriseId' => '',
    'roleActif' => 'gerant',
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    // Un acces prepare n'est pas un acces ouvert : cree inactif, il attend qu'on
    // l'active. Aucun courriel ne part, et la connexion est refusee entre-temps.
    'ouverture' => 'actif',
    'villeChoix' => '',
    'siteChoix' => '',
    'objectifGlobal' => Commercial::OBJECTIF_MENSUEL_DEFAUT,
    'pourcentageMecanique' => (int) (Commercial::PART_MECANIQUE_DEFAUT * 100),
    'confirmation' => null,

    // Renseigne quand on reprend un acces existant ; null a la creation.
    'modifieId' => null,
    'telephone' => '',
]);

mount(function (?int $utilisateur = null) {
    if (! $utilisateur) {
        return;
    }

    $compte = User::withoutGlobalScopes()->find($utilisateur);

    abort_if(! $compte, 404);

    $this->modifieId = $compte->id;
    $this->entrepriseId = $compte->entreprise_id ?? '';
    $this->nom = $compte->name;
    $this->email = $compte->email;
    $this->telephone = $compte->telephone ?? '';
    $this->ouverture = $compte->est_actif ? 'actif' : 'inactif';
    $this->villeChoix = $compte->ville_id ?? '';
    $this->siteChoix = $compte->site_id ?? '';

    app(PermissionRegistrar::class)->setPermissionsTeamId($compte->entreprise_id);
    $this->roleActif = $compte->getRoleNames()->first() ?? 'commercial';

    $fiche = Commercial::withoutGlobalScopes()->where('user_id', $compte->id)->first();

    if ($fiche) {
        $global = (int) $fiche->objectif_mecanique + (int) $fiche->objectif_sinistre;
        $this->objectifGlobal = $global;
        $this->pourcentageMecanique = $global > 0
            ? (int) round((int) $fiche->objectif_mecanique * 100 / $global)
            : (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    }
});

/** Vrai quand on reprend un acces au lieu d'en ouvrir un. */
$enModification = computed(fn () => $this->modifieId !== null);

/** L'acces repris, ou null. */
$compteModifie = computed(fn () => $this->modifieId
    ? User::withoutGlobalScopes()->find($this->modifieId)
    : null);

/**
 * Un acces jamais ouvert se remodele entierement ; un acces en service, non.
 *
 * Changer le role ou l'entreprise d'un compte qui a deja saisi laisserait ses
 * ecritures derriere lui, rattachees a un perimetre qui n'est plus le sien. Tant
 * qu'il est inactif et ne s'est jamais connecte, il n'a rien saisi : tout est
 * encore repris sans consequence.
 */
$structureModifiable = computed(function () {
    $compte = $this->compteModifie;

    return ! $compte || (! $compte->est_actif && $compte->derniere_connexion_le === null);
});

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
    if (! array_key_exists($role, $this->rolesDisponibles) || ! $this->structureModifiable) {
        return;
    }

    $this->roleActif = $role;
    $this->resetValidation();
};

/**
 * Reprend un acces existant.
 *
 * Le mot de passe reste facultatif : le laisser vide ne le touche pas. Le
 * remplacer ici est un geste separe, qui coupe la connexion en cours du
 * titulaire — on ne le fait pas par inadvertance en corrigeant une adresse.
 */
$enregistrer = function () {
    $compte = $this->modifieId ? User::withoutGlobalScopes()->find($this->modifieId) : null;

    if (! $compte) {
        $this->confirmation = null;

        return;
    }

    $regles = [
        'nom' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($compte->id)],
        'telephone' => ['nullable', 'string', 'max:40'],
        'motDePasse' => ['nullable', 'string', 'min:8'],
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
        'nom' => 'nom et prenoms',
        'email' => 'adresse e-mail',
        'telephone' => 'telephone',
        'motDePasse' => 'mot de passe',
        'villeChoix' => 'ville',
        'siteChoix' => 'site',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mecanique',
    ]);

    // Le perimetre depend du role : un responsable de site porte un lieu, les
    // autres une ville, le gerant ni l'un ni l'autre. Les regles de validation ne
    // posent donc pas toujours les deux clefs.
    $lieu = $this->roleActif === 'responsable_site' ? ($donnees['siteChoix'] ?? null) : null;
    $ville = $this->roleActif === 'responsable_site'
        ? Site::where('id', $lieu)->value('ville_id')
        : ($donnees['villeChoix'] ?? null);

    $compte->forceFill([
        'name' => $donnees['nom'],
        'email' => $donnees['email'],
        'telephone' => $donnees['telephone'] ?: null,
        'ville_id' => $ville ?: null,
        'site_id' => $lieu ?: null,
    ]);

    if ($donnees['motDePasse']) {
        // Reposer un mot de passe coupe la connexion en cours du titulaire : on ne
        // le fait que si le champ a ete rempli, jamais en corrigeant une adresse.
        $compte->password = \Illuminate\Support\Facades\Hash::make($donnees['motDePasse']);
        $compte->doit_changer_mot_de_passe = true;
    }

    $compte->save();

    // La fiche commerciale porte le meme nom : la laisser en arriere ferait
    // apparaitre deux orthographes de la meme personne dans les tableaux.
    $fiche = ['nom' => $donnees['nom']];

    if ($ville) {
        $fiche['ville_id'] = $ville;
    }

    if ($this->roleAvecObjectifs) {
        $fiche['objectif_mecanique'] = $this->objectifMecanique;
        $fiche['objectif_sinistre'] = $this->objectifSinistre;
    }

    Commercial::withoutGlobalScopes()->where('user_id', $compte->id)->update($fiche);

    $this->confirmation = "Acces de {$compte->name} mis a jour.";
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
        'est_actif' => $this->ouverture === 'actif',
    ]);

    $this->reset(['nom', 'email', 'motDePasse', 'siteChoix', 'villeChoix']);
    $this->objectifGlobal = Commercial::OBJECTIF_MENSUEL_DEFAUT;
    $this->pourcentageMecanique = (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    $this->confirmation = "Accès créé pour {$entreprise->nom} — mot de passe à changer à la première connexion.";
};

?>

<div>
    <div class="carte">
        @if ($this->enModification)
            <h1 style="font-size:18px; font-weight:800; margin:0 0 4px;">Modifier un accès</h1>
            <p style="color:#6B6E76; font-size:15px; margin:0 0 12px;">
                {{ $this->compteModifie?->email }} — les champs sont pré-remplis. Laissez le mot de passe vide
                pour ne pas y toucher.
            </p>
            @unless ($this->structureModifiable)
                <div class="encart encart-info">
                    Cet accès a déjà servi : son rôle et son entreprise ne se reprennent plus. Ses écritures y sont
                    rattachées, et le déplacer les laisserait derrière lui.
                </div>
            @endunless
            <a href="{{ route('super-admin.acces.index') }}" wire:navigate
                style="display:inline-block; font-size:13px; color:var(--th-gris,#6B6E76); margin-bottom:14px;">‹ Retour à la liste des accès</a>
        @else
            <h1 style="font-size:18px; font-weight:800; margin:0 0 4px;">Créer un accès</h1>
            <p style="color:#6B6E76; font-size:15px; margin:0 0 18px;">Provisionnez un compte pour n'importe quel rôle, dans n'importe quelle entreprise cliente.</p>
        @endif

        <div style="display:flex; gap:8px; margin-bottom:18px;">
            @foreach ($this->rolesDisponibles as $cle => $libelle)
                <button type="button" wire:click="choisirRole('{{ $cle }}')" @disabled(! $this->structureModifiable)
                    style="padding:9px 16px; border-radius:8px; font-size:14.5px; font-weight:700;
                           cursor:{{ $this->structureModifiable ? 'pointer' : 'not-allowed' }};
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

        <form wire:submit="{{ $this->enModification ? 'enregistrer' : 'creer' }}" style="max-width:480px;">
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin-bottom:6px;">Entreprise</label>
            <select wire:model="entrepriseId" @disabled(! $this->structureModifiable)
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px; {{ $this->structureModifiable ? '' : 'background:#F1EFE9; color:#6B6E76;' }}">
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

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Téléphone</label>
            <input type="text" wire:model="telephone" placeholder="+225 07 ..."
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('telephone') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">
                {{ $this->enModification ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe provisoire' }}
            </label>
            <input type="password" wire:model="motDePasse"
                placeholder="{{ $this->enModification ? 'Laisser vide pour ne pas y toucher' : '' }}"
                style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px; margin-bottom:4px;">
            @error('motDePasse') <div style="color:#C8102E; font-size:13.5px; margin-bottom:8px;">{{ $message }}</div> @enderror

            {{-- Un accès préparé n'est pas un accès ouvert. Créé inactif, il existe avec
                 son rôle et son périmètre, mais son titulaire n'en sait rien : aucun
                 courriel ne part, et la connexion lui est refusée. C'est le brouillon
                 d'un accès — utile quand on prépare une arrivée à l'avance. --}}
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Ouverture de l'accès</label>
            <select wire:model.live="ouverture" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                <option value="actif">Actif — courriel envoyé, le titulaire peut se connecter</option>
                <option value="inactif">Inactif — accès préparé, aucun courriel, connexion refusée</option>
            </select>
            <p style="font-size:11.5px; color:#9A9DA5; margin:5px 0 0;">
                Le courriel de bienvenue partira le jour où vous activerez l'accès, pas avant.
            </p>

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
                {{ $this->enModification ? 'Enregistrer les modifications' : "+ Créer l'accès" }}
            </button>
        </form>
    </div>
</div>
