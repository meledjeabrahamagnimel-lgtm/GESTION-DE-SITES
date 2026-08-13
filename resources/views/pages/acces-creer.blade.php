<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Tenants\Actions\CreerAcces;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use function Livewire\Volt\{state, computed, mount, rules};

state([
    'roleActif' => null,
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    'perimetre' => '',
    'siteChoix' => '',
    'objectifGlobal' => Commercial::OBJECTIF_MENSUEL_DEFAUT,
    'pourcentageMecanique' => (int) (Commercial::PART_MECANIQUE_DEFAUT * 100),
    'confirmation' => null,
    'pageAcces' => 1,
]);

mount(function () {
    $this->roleActif = auth()->user()->hasRole('gerant') ? 'responsable_site' : 'commercial';

    if (! auth()->user()->hasRole('gerant') && count($this->optionsSiteCommercial) === 1) {
        $this->siteChoix = array_key_first($this->optionsSiteCommercial);
    }
});

$rolesDisponibles = computed(function () {
    $roles = [];
    if (auth()->user()->hasRole('gerant')) {
        $roles['responsable_site'] = 'Responsable de site';
        $roles['commercial'] = 'Commercial';
        $roles['caissier'] = 'Caissier';
    } elseif (auth()->user()->hasRole('responsable_site')) {
        $roles['commercial'] = 'Commercial';
        $roles['caissier'] = 'Caissier';
    }

    return $roles;
});

/** Périmètres proposés pour un nouveau responsable ou caissier : une ville entière, ou un site précis. */
$perimetres = computed(function () {
    $options = [];

    foreach (Ville::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get() as $ville) {
        $options['ville:'.$ville->id] = $ville->nom.' — toute la ville';
    }

    foreach (Site::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get() as $site) {
        $options['site:'.$site->id] = $site->nom.' (site précis)';
    }

    return $options;
});

/**
 * Le site choisi définit à lui seul l'activité — inutile de faire saisir deux fois la
 * même information. Un seul contrôle propose, par ville, ses deux sites précis plus une
 * option "les deux" (valeur "ville:<id>") qui couvre les deux activités à la fois ; la
 * fiche Commercial garde un site_id obligatoire, ancré sur le site Mécanique de la ville
 * dans ce dernier cas, mais c'est bien le champ activite qui fait foi pour le filtrage.
 */
$sitesPourCommercial = computed(fn () => auth()->user()->hasRole('gerant')
    ? Site::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->with('ville')->orderBy('nom')->get()
    : Site::visiblesPour(auth()->user())->load('ville'));

$optionsSiteCommercial = computed(function () {
    $options = [];

    foreach ($this->sitesPourCommercial->groupBy('ville_id') as $sitesDeLaVille) {
        $ville = $sitesDeLaVille->first()->ville;

        foreach ($sitesDeLaVille->sortBy('activite') as $site) {
            $options['site:'.$site->id] = ($ville ? $ville->nom.' — ' : '').$site->activite;
        }

        if ($sitesDeLaVille->count() > 1) {
            $options['ville:'.$ville?->id] = ($ville ? $ville->nom.' — ' : '').'Les deux (Mécanique + Sinistre)';
        }
    }

    return $options;
});

/** Activité résolue à partir du choix de site : celle du site précis, ou combinée pour "les deux". */
$activiteResolue = computed(function () {
    if (str_starts_with((string) $this->siteChoix, 'ville:')) {
        return 'Mécanique/Sinistre';
    }

    [$type, $id] = array_pad(explode(':', (string) $this->siteChoix, 2), 2, null);

    return $type === 'site' ? $this->sitesPourCommercial->firstWhere('id', (int) $id)?->activite : null;
});

/**
 * Répartition Mécanique/Sinistre de l'objectif global : au pourcentage saisi quand le
 * commercial couvre les deux activités, entièrement sur la seule activité choisie sinon
 * — inutile de faire deviner un pourcentage à quelqu'un qui n'a qu'un seul site.
 */
$objectifMecanique = computed(function () {
    $global = (int) $this->objectifGlobal;

    return match ($this->activiteResolue) {
        'Mécanique' => $global,
        'Sinistre' => 0,
        default => (int) round($global * ((int) $this->pourcentageMecanique) / 100),
    };
});

$objectifSinistre = computed(fn () => (int) $this->objectifGlobal - $this->objectifMecanique);

$objectifAnnuel = computed(fn () => (int) $this->objectifGlobal * 12);

$derniersAcces = computed(function () {
    $utilisateurs = \App\Models\User::where('entreprise_id', auth()->user()->entreprise_id)
        ->latest()
        ->get();

    $roles = \App\Models\User::nomsRolesParUtilisateur($utilisateurs->pluck('id'));
    $fiches = Commercial::whereIn('user_id', $utilisateurs->pluck('id'))->with('site')->get()->keyBy('user_id');

    return $utilisateurs->map(fn ($u) => [
        'utilisateur' => $u,
        'role' => $roles[$u->id] ?? '—',
        'commercial' => $fiches->get($u->id),
    ]);
});

$basculerActif = function (int $utilisateurId) {
    if ($utilisateurId === auth()->id()) {
        return;
    }

    $utilisateur = \App\Models\User::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($utilisateurId);
    $utilisateur->update(['est_actif' => ! $utilisateur->est_actif]);
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

    if ($this->roleActif === 'responsable_site' || $this->roleActif === 'caissier') {
        $regles['perimetre'] = ['required', 'in:'.implode(',', array_keys($this->perimetres))];
    }

    if ($this->roleActif === 'commercial') {
        $regles['siteChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsSiteCommercial))];
        $regles['objectifGlobal'] = ['required', 'numeric', 'min:0'];
        $regles['pourcentageMecanique'] = ['required', 'numeric', 'min:0', 'max:100'];
    }

    $donnees = $this->validate($regles, [], [
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'perimetre' => 'périmètre',
        'siteChoix' => 'site',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mécanique',
    ]);

    $siteId = null;
    if ($this->roleActif === 'commercial') {
        [$type, $id] = explode(':', $donnees['siteChoix'], 2);
        $siteId = $type === 'site'
            ? (int) $id
            : $this->sitesPourCommercial->where('ville_id', (int) $id)->sortBy('activite')->first()?->id;
    }

    $action->executer(auth()->user()->entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'perimetre' => $donnees['perimetre'] ?? null,
        'site_id' => $siteId,
        'activite' => $this->activiteResolue,
        'objectif_mecanique' => $this->objectifMecanique,
        'objectif_sinistre' => $this->objectifSinistre,
    ]);

    $this->reset(['nom', 'email', 'motDePasse']);
    $this->siteChoix = (! auth()->user()->hasRole('gerant') && count($this->optionsSiteCommercial) === 1)
        ? array_key_first($this->optionsSiteCommercial)
        : '';
    $this->objectifGlobal = Commercial::OBJECTIF_MENSUEL_DEFAUT;
    $this->pourcentageMecanique = (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    $this->perimetre = '';
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

            @if ($roleActif === 'responsable_site' || $roleActif === 'caissier')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Périmètre</label>
                <select wire:model="perimetre" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir une ville ou un site —</option>
                    @foreach ($this->perimetres as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('perimetre') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @endif

            @if ($roleActif === 'commercial')
                @if (auth()->user()->hasRole('gerant') || count($this->optionsSiteCommercial) > 1)
                    <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site (définit l'activité)</label>
                    <select wire:model.live="siteChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                        <option value="">— Choisir un site —</option>
                        @foreach ($this->optionsSiteCommercial as $valeur => $libelle)
                            <option value="{{ $valeur }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                    @error('siteChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
                @endif

                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:14px 0 6px;">Objectif mensuel global (FCFA)</label>
                <input type="number" wire:model.live="objectifGlobal"
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                @error('objectifGlobal') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                @if ($this->activiteResolue === 'Mécanique/Sinistre')
                    <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">
                        Répartition — % Mécanique (le reste va au Sinistre)
                    </label>
                    <input type="number" wire:model.live="pourcentageMecanique" min="0" max="100"
                        style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                    @error('pourcentageMecanique') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
                @endif

                <div style="display:flex; gap:16px; margin-top:10px; padding:10px 12px; background:#F9F9F7; border-radius:8px; font-size:13px; color:#4B4E55; flex-wrap:wrap;">
                    <span>Mécanique : <b>{{ ae($this->objectifMecanique) }}</b>{{ $this->activiteResolue === 'Mécanique/Sinistre' ? ' ('.$pourcentageMecanique.'%)' : '' }}</span>
                    <span>Sinistre : <b>{{ ae($this->objectifSinistre) }}</b>{{ $this->activiteResolue === 'Mécanique/Sinistre' ? ' ('.(100 - (int) $pourcentageMecanique).'%)' : '' }}</span>
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
        <h3 style="font-size:15px; font-weight:700; margin:0 0 12px;">Derniers accès créés</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Rôle</th>
                        <th>Site d'affectation</th>
                        <th>Objectif mensuel (FCFA)</th>
                        <th>Objectif par site</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->derniersAcces->forPage($pageAcces, 10) as $ligne)
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <td style="font-weight:600;">{{ $ligne['utilisateur']->name }}</td>
                            <td style="color:#6B6E76;">{{ $ligne['utilisateur']->email }}</td>
                            <td>{{ $ligne['role'] }}</td>
                            <td>{{ $ligne['commercial']?->site?->nom ?? '—' }}</td>
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
                            <td>
                                @if ($ligne['utilisateur']->id !== auth()->id())
                                    <button type="button" wire:click="basculerActif({{ $ligne['utilisateur']->id }})"
                                        wire:confirm="{{ $ligne['utilisateur']->est_actif ? 'Révoquer cet accès ?' : 'Réactiver cet accès ?' }}"
                                        style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:5px 10px; font-size:12.5px; font-weight:600; cursor:pointer; color:{{ $ligne['utilisateur']->est_actif ? '#C8102E' : '#0E9F6E' }};">
                                        {{ $ligne['utilisateur']->est_actif ? 'Révoquer' : 'Réactiver' }}
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
