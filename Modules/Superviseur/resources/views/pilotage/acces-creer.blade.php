<?php

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Actions\SupprimerAcces;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\Annuaire;
use Modules\Noyau\Entreprises\Support\HierarchieAcces;
use function Livewire\Volt\{state, computed, mount, protect, rules};

state([
    'roleActif' => null,
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
    'pageAcces' => 1,

    // Accès cochés en vue d'une activation groupée : préparer une équipe puis l'ouvrir
    // le jour venu, en un geste plutôt qu'un par personne.
    'selection' => [],
]);

mount(function () {
    $this->roleActif = array_key_first($this->rolesDisponibles);

    if (count($this->optionsVilleCommercial) === 1) {
        $this->villeChoix = array_key_first($this->optionsVilleCommercial);
    }
});

/**
 * Rôles créables, du plus large au plus étroit : chacun ne peut nommer que des accès
 * strictement en dessous du sien. Un responsable de site, dernier maillon encadrant,
 * ne crée donc que des commerciaux et la comptabilité de sa ville.
 */
$rolesDisponibles = computed(function () {
    if (auth()->user()->hasRole('gerant')) {
        return [
            'responsable_ville' => 'Superviseur de ville',
            'responsable_site' => 'Responsable de site',
            'commercial' => 'Commercial',
            'caissier' => 'Comptabilité',
        ];
    }

    if (auth()->user()->hasRole('responsable_ville')) {
        return [
            'responsable_site' => 'Responsable de site',
            'commercial' => 'Commercial',
            'caissier' => 'Comptabilité',
        ];
    }

    return ['commercial' => 'Commercial', 'caissier' => 'Comptabilité'];
});

/** Rôles dont le titulaire prospecte : il reçoit une fiche commercial et des objectifs. */
$roleAvecObjectifs = computed(fn () => in_array($this->roleActif, ['responsable_ville', 'responsable_site', 'commercial'], true));

/**
 * Villes proposées. Un commercial, un responsable de ville et la comptabilité sont
 * rattachés à une ville entière : le commercial prospecte pour l'une ou l'autre
 * activité selon le client, et la comptabilité couvre toute la ville, pas un lieu.
 */
$villesPourCommercial = computed(fn () => auth()->user()->hasRole('gerant')
    ? Ville::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get()
    : Ville::whereIn('id', Site::visiblesPour(auth()->user())->pluck('ville_id')->unique())->orderBy('nom')->get());

$optionsVilleCommercial = computed(fn () => $this->villesPourCommercial->pluck('nom', 'id')->all());

/** Lieux proposés à un responsable de site : ceux du périmètre de celui qui le nomme. */
$optionsSite = computed(fn () => (auth()->user()->hasRole('gerant')
        ? Site::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get()
        : Site::visiblesPour(auth()->user()))
    ->pluck('nom', 'id')->all());

/** Répartition Mécanique/Sinistre de l'objectif global, au pourcentage saisi. */
$objectifMecanique = computed(fn () => (int) round((int) $this->objectifGlobal * ((int) $this->pourcentageMecanique) / 100));

$objectifSinistre = computed(fn () => (int) $this->objectifGlobal - $this->objectifMecanique);

$objectifAnnuel = computed(fn () => (int) $this->objectifGlobal * 12);

$derniersAcces = computed(function () {
    $utilisateurs = \App\Models\User::where('entreprise_id', auth()->user()->entreprise_id)
        ->latest()
        ->get();

    $roles = \App\Models\User::nomsRolesParUtilisateur($utilisateurs->pluck('id'));
    $fiches = Commercial::whereIn('user_id', $utilisateurs->pluck('id'))->with('ville')->get()->keyBy('user_id');

    return $utilisateurs->map(fn ($u) => [
        'utilisateur' => $u,
        'role' => $roles[$u->id] ?? '—',
        'commercial' => $fiches->get($u->id),
    ]);
});

/** Vrai si l'annuaire est ouvert à ce lecteur : gérant et superviseur, pas plus bas. */
$annuaireOuvert = computed(fn () => Annuaire::ouvertA(auth()->user()));

/** Accès préparés visibles ici : ce sont eux que l'activation groupée vise. */
$inactifs = computed(fn () => $this->derniersAcces
    ->filter(fn ($ligne) => ! $ligne['utilisateur']->est_actif
        && HierarchieAcces::autorise(auth()->user(), $ligne['utilisateur']))
    ->map(fn ($ligne) => (string) $ligne['utilisateur']->id)
    ->values()->all());

/**
 * Le compte visé, ou null si l'on n'a pas le droit d'y toucher.
 *
 * Un bouton absent de l'écran n'empêche rien : la méthode reste appelable depuis le
 * navigateur, avec l'identifiant que l'on veut. Le contrôle est donc ici, sur chaque
 * appel, et non dans la condition qui affiche le bouton.
 */
$cibleAutorisee = protect(function (int $utilisateurId): ?\App\Models\User {
    $utilisateur = \App\Models\User::find($utilisateurId);

    if (! $utilisateur) {
        return null;
    }

    $motif = HierarchieAcces::motifDuRefus(auth()->user(), $utilisateur);

    if ($motif !== null) {
        $this->dispatch('annonce', texte: $motif, ton: 'alerte');

        return null;
    }

    return $utilisateur;
});

$basculerActif = function (int $utilisateurId) {
    $utilisateur = $this->cibleAutorisee($utilisateurId);

    if (! $utilisateur) {
        return;
    }

    if ($utilisateur->est_actif) {
        $utilisateur->update(['est_actif' => false]);
        $this->dispatch('annonce', texte: "Accès de {$utilisateur->name} révoqué.", ton: 'alerte');

        return;
    }

    // C'est l'action qui ouvre l'accès, car c'est elle qui sait souhaiter la bienvenue :
    // un accès préparé inactif n'a reçu aucun courriel, il le reçoit maintenant.
    app(CreerAcces::class)->activer($utilisateur);
    $this->dispatch('annonce', texte: "Accès de {$utilisateur->name} activé — courriel de bienvenue envoyé.");
};

$toutSelectionner = function () {
    $this->selection = $this->inactifs;
};

$viderSelection = function () {
    $this->selection = [];
};

$activerSelection = function (CreerAcces $action) {
    // On repart de la base : les identifiants viennent du navigateur, et seuls comptent
    // ceux qui existent, sont inactifs, et relèvent bien de celui qui clique.
    $comptes = \App\Models\User::whereIn('id', array_map('intval', $this->selection))
        ->where('est_actif', false)
        ->get()
        ->filter(fn ($compte) => HierarchieAcces::autorise(auth()->user(), $compte));

    $this->selection = [];

    if ($comptes->isEmpty()) {
        $this->dispatch('annonce', texte: 'Aucun accès activable dans la sélection.', ton: 'alerte');

        return;
    }

    $ouverts = $comptes->filter(fn ($compte) => $action->activer($compte));

    $this->dispatch('annonce', texte: $ouverts->count().' accès '
        .($ouverts->count() > 1 ? 'activés' : 'activé').' — courriel de bienvenue envoyé.');
};

/**
 * Supprime un accès, actif ou préparé.
 *
 * Ce que la personne a saisi reste : une prospection appartient à l'entreprise, pas à
 * celui qui l'a tapée. L'action conserve la fiche commerciale dès qu'elle porte des
 * écritures, et se contente alors de la détacher.
 */
$supprimer = function (int $utilisateurId, SupprimerAcces $action) {
    $utilisateur = \App\Models\User::find($utilisateurId);

    if (! $utilisateur) {
        return;
    }

    try {
        $bilan = $action->executer(auth()->user(), $utilisateur);
    } catch (\RuntimeException $e) {
        $this->dispatch('annonce', texte: $e->getMessage(), ton: 'alerte');

        return;
    }

    $this->selection = array_values(array_diff($this->selection, [(string) $utilisateurId]));
    unset($this->derniersAcces, $this->inactifs);

    $this->dispatch('annonce', texte: "Accès de {$utilisateur->name} supprimé — fiche commerciale : {$bilan['fiche commerciale']}.");
};

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
        $regles['siteChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsSite))];
    } else {
        $regles['villeChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsVilleCommercial))];
    }

    if ($this->roleAvecObjectifs) {
        $regles['objectifGlobal'] = ['required', 'numeric', 'min:0'];
        $regles['pourcentageMecanique'] = ['required', 'numeric', 'min:0', 'max:100'];
    }

    $donnees = $this->validate($regles, [], [
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'siteChoix' => 'site',
        'villeChoix' => 'ville',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mécanique',
    ]);

    $action->executer(auth()->user()->entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'ville_id' => $donnees['villeChoix'] ?? null,
        'site_id' => $donnees['siteChoix'] ?? null,
        'objectif_mecanique' => $this->objectifMecanique,
        'objectif_sinistre' => $this->objectifSinistre,
        'est_actif' => $this->ouverture === 'actif',
    ]);

    $this->reset(['nom', 'email', 'motDePasse', 'siteChoix']);
    $this->villeChoix = count($this->optionsVilleCommercial) === 1 ? array_key_first($this->optionsVilleCommercial) : '';
    $this->objectifGlobal = Commercial::OBJECTIF_MENSUEL_DEFAUT;
    $this->pourcentageMecanique = (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    $this->confirmation = $this->ouverture === 'actif'
        ? "Accès créé — courriel envoyé, mot de passe à choisir à la première connexion."
        : "Accès préparé, non activé — aucun courriel envoyé. Activez-le quand la place sera prête.";
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

            {{-- Un accès préparé n'est pas un accès ouvert. Créé inactif, il existe avec
                 son rôle et son périmètre, mais son titulaire n'en sait rien : aucun
                 courriel ne part, et la connexion lui est refusée. C'est le brouillon
                 d'un accès — utile quand on prépare une arrivée à l'avance. --}}
            <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Ouverture de l'accès</label>
            <select wire:model.live="ouverture" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                <option value="actif">Actif — courriel envoyé, le titulaire peut se connecter</option>
                <option value="inactif">Inactif — accès préparé, aucun courriel, connexion refusée</option>
            </select>
            <p style="font-size:11.5px; color:#9A9DA5; margin:5px 0 0;">
                Le courriel de bienvenue partira le jour où vous activerez l'accès, pas avant.
            </p>

            @if ($roleActif === 'responsable_site')
                {{-- Un responsable de site répond d'un lieu précis ; les autres rôles
                     couvrent une ville entière, lieux et activités confondus. --}}
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site (lieu dont il répond)</label>
                <select wire:model="siteChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir un site —</option>
                    @foreach ($this->optionsSite as $id => $nom)
                        <option value="{{ $id }}">{{ $nom }}</option>
                    @endforeach
                </select>
                @error('siteChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @else
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Ville</label>
                <select wire:model.live="villeChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir une ville —</option>
                    @foreach ($this->optionsVilleCommercial as $id => $nom)
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
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                @error('objectifGlobal') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">
                    Répartition — % Mécanique (le reste va au Sinistre)
                </label>
                <input type="number" wire:model.live="pourcentageMecanique" min="0" max="100"
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                @error('pourcentageMecanique') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                <div style="display:flex; gap:16px; margin-top:10px; padding:10px 12px; background:#F9F9F7; border-radius:8px; font-size:13px; color:#4B4E55; flex-wrap:wrap;">
                    <span>Mécanique : <b>{{ ae($this->objectifMecanique) }}</b> ({{ $pourcentageMecanique }}%)</span>
                    <span>Sinistre : <b>{{ ae($this->objectifSinistre) }}</b> ({{ 100 - (int) $pourcentageMecanique }}%)</span>
                    <span>Équivalent annuel : <b>{{ ae($this->objectifAnnuel) }}</b></span>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                class="bouton">
                + Ajouter
            </button>
        </form>
    </div>

    <div class="carte">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
            <h3 style="font-size:15px; font-weight:700; margin:0;">Derniers accès créés</h3>

            @if ($this->annuaireOuvert)
                {{-- Lien ordinaire, sans wire:navigate : un téléchargement demande une
                     vraie réponse HTTP, que Livewire chargerait sans jamais l'ouvrir. --}}
                <a href="{{ route('annuaire') }}"
                   style="border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:8px; padding:8px 14px; font-weight:700; font-size:13.5px; text-decoration:none; background:#fff;">
                    ↓ Annuaire PDF
                </a>
            @endif
        </div>

        @if ($this->annuaireOuvert)
            <p style="font-size:12px; color:#9A9DA5; margin:-4px 0 14px; max-width:640px;">
                Rôle, nom, adresse et périmètre de chacun, groupés par ville.
                {{ auth()->user()->hasRole('gerant')
                    ? "Vous y voyez toute l'entreprise."
                    : 'Vous y voyez votre ville.' }}
            </p>
        @endif

        @if (count($this->inactifs) > 0)
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#FFFBEA; border:1px solid #D9770633; border-radius:8px; padding:10px 14px; margin-bottom:14px;">
                <span style="font-size:13.5px; color:#4B4E55;">
                    <b>{{ count($this->inactifs) }}</b> accès préparé{{ count($this->inactifs) > 1 ? 's' : '' }},
                    en attente d'activation.
                    @if (count($selection))
                        <b>{{ count($selection) }}</b> sélectionné{{ count($selection) > 1 ? 's' : '' }}.
                    @endif
                </span>

                <button type="button" wire:click="toutSelectionner"
                    style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); color:#4B4E55; border-radius:6px; padding:6px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">
                    Tout sélectionner
                </button>

                @if (count($selection))
                    <button type="button" wire:click="viderSelection"
                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76; border-radius:6px; padding:6px 12px; font-size:12.5px; font-weight:600; cursor:pointer;">
                        Tout décocher
                    </button>

                    <button type="button" wire:click="activerSelection"
                        wire:confirm="Activer les {{ count($selection) }} accès sélectionnés ? Un courriel de bienvenue partira vers chacun."
                        style="background:#0E9F6E; border:0; color:#fff; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:700; cursor:pointer;">
                        Activer la sélection ({{ count($selection) }})
                    </button>
                @endif
            </div>
        @endif

        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th style="width:28px;"></th>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Rôle</th>
                        <th>Ville d'affectation</th>
                        <th>Objectif mensuel (FCFA)</th>
                        <th>Objectif par activité</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->derniersAcces->forPage($pageAcces, 10) as $ligne)
                        <tr wire:key="acces-{{ $ligne['utilisateur']->id }}" style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td>
                                {{-- La case ne s'affiche que sur un accès préparé qui relève
                                     bien de vous : le reste n'a rien à activer. --}}
                                @if (in_array((string) $ligne['utilisateur']->id, $this->inactifs, true))
                                    <input type="checkbox" wire:model.live="selection" value="{{ $ligne['utilisateur']->id }}"
                                        aria-label="Sélectionner {{ $ligne['utilisateur']->name }}">
                                @endif
                            </td>
                            <td style="font-weight:600;">{{ $ligne['utilisateur']->name }}</td>
                            <td style="color:#6B6E76;">{{ $ligne['utilisateur']->email }}</td>
                            <td>{{ $ligne['role'] }}</td>
                            <td>{{ $ligne['commercial']?->ville?->nom ?? '—' }}</td>
                            <td style="font-variant-numeric:tabular-nums;">{{ $ligne['commercial'] ? ae($ligne['commercial']->objectif_mensuel) : '—' }}</td>
                            <td style="font-size:12.5px; color:#6B6E76;">
                                @if ($ligne['commercial'])
                                    Méca. {{ ae($ligne['commercial']->objectif_mecanique) }} · Sin. {{ ae($ligne['commercial']->objectif_sinistre) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($ligne['utilisateur']->est_actif)
                                    <span style="color:#0E9F6E; font-weight:600;">Actif</span>
                                @else
                                    <span style="color:#C8102E; font-weight:600;">Révoqué</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                @if (\Modules\Noyau\Entreprises\Support\HierarchieAcces::autorise(auth()->user(), $ligne['utilisateur']))
                                    <button type="button" wire:click="basculerActif({{ $ligne['utilisateur']->id }})"
                                        wire:confirm="{{ $ligne['utilisateur']->est_actif ? 'Révoquer cet accès ?' : 'Réactiver cet accès ?' }}"
                                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; margin-right:6px; color:{{ $ligne['utilisateur']->est_actif ? '#C8102E' : '#0E9F6E' }};">
                                        {{ $ligne['utilisateur']->est_actif ? 'Révoquer' : 'Réactiver' }}
                                    </button>

                                    <button type="button" wire:click="supprimer({{ $ligne['utilisateur']->id }})"
                                        wire:confirm="Supprimer définitivement l'accès de {{ $ligne['utilisateur']->name }} ?&#10;&#10;La personne ne pourra plus se connecter. Ses saisies, elles, sont conservées : elles appartiennent à l'entreprise."
                                        style="background:#C8102E; border:0; color:#fff; border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer;">
                                        Supprimer
                                    </button>
                                @else
                                    <span style="color:#B7B9BE; font-size:12.5px;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :page="$pageAcces" :total="$this->derniersAcces->count()" prop="pageAcces" />
    </div>
</div>
