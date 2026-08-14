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
    'villeChoix' => '',
    'objectifGlobal' => Commercial::OBJECTIF_MENSUEL_DEFAUT,
    'pourcentageMecanique' => (int) (Commercial::PART_MECANIQUE_DEFAUT * 100),
    'confirmation' => null,
    'pageAcces' => 1,
]);

mount(function () {
    $this->roleActif = auth()->user()->hasRole('gerant') ? 'responsable_site' : 'commercial';

    if (! auth()->user()->hasRole('gerant') && count($this->optionsVilleCommercial) === 1) {
        $this->villeChoix = array_key_first($this->optionsVilleCommercial);
    }
});

$rolesDisponibles = computed(function () {
    $roles = [];
    if (auth()->user()->hasRole('gerant')) {
        $roles['responsable_site'] = 'Responsable de site';
        $roles['commercial'] = 'Commercial';
        $roles['caissier'] = 'Comptabilité';
    } elseif (auth()->user()->hasRole('responsable_site')) {
        $roles['commercial'] = 'Commercial';
        $roles['caissier'] = 'Comptabilité';
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
 * Un commercial n'est pas rattaché à une activité mais à une ville entière : il
 * prospecte pour l'une ou l'autre selon le client, l'activité étant portée par chaque
 * prospection et non par sa fiche.
 */
$villesPourCommercial = computed(fn () => auth()->user()->hasRole('gerant')
    ? Ville::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get()
    : Ville::whereIn('id', Site::visiblesPour(auth()->user())->pluck('ville_id')->unique())->orderBy('nom')->get());

$optionsVilleCommercial = computed(fn () => $this->villesPourCommercial->pluck('nom', 'id')->all());

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
        $regles['villeChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsVilleCommercial))];
        $regles['objectifGlobal'] = ['required', 'numeric', 'min:0'];
        $regles['pourcentageMecanique'] = ['required', 'numeric', 'min:0', 'max:100'];
    }

    $donnees = $this->validate($regles, [], [
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'perimetre' => 'périmètre',
        'villeChoix' => 'ville',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mécanique',
    ]);

    $action->executer(auth()->user()->entreprise, $this->roleActif, [
        'nom' => $donnees['nom'],
        'email' => $donnees['email'],
        'mot_de_passe' => $donnees['motDePasse'],
        'perimetre' => $donnees['perimetre'] ?? null,
        'ville_id' => $donnees['villeChoix'] ?? null,
        'objectif_mecanique' => $this->objectifMecanique,
        'objectif_sinistre' => $this->objectifSinistre,
    ]);

    $this->reset(['nom', 'email', 'motDePasse']);
    $this->villeChoix = (! auth()->user()->hasRole('gerant') && count($this->optionsVilleCommercial) === 1)
        ? array_key_first($this->optionsVilleCommercial)
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
                @if (auth()->user()->hasRole('gerant') || count($this->optionsVilleCommercial) > 1)
                    <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Ville</label>
                    <select wire:model.live="villeChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:15.5px;">
                        <option value="">— Choisir une ville —</option>
                        @foreach ($this->optionsVilleCommercial as $id => $nom)
                            <option value="{{ $id }}">{{ $nom }}</option>
                        @endforeach
                    </select>
                    @error('villeChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
                @endif

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
        <h3 style="font-size:15px; font-weight:700; margin:0 0 12px;">Derniers accès créés</h3>
        <div class="tableau-conteneur">
            <table class="tableau">
                <thead>
                    <tr>
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
                        <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
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
