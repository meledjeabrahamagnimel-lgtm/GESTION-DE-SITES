<?php

use App\Domain\Operations\Models\Commercial;
use App\Domain\Tenants\Actions\CreerAcces;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use function Livewire\Volt\{state, computed};

state([
    'entrepriseId' => '',
    'roleActif' => 'gerant',
    'nom' => '',
    'email' => '',
    'motDePasse' => '',
    'perimetre' => '',
    'siteChoix' => '',
    'objectifGlobal' => Commercial::OBJECTIF_MENSUEL_DEFAUT,
    'pourcentageMecanique' => (int) (Commercial::PART_MECANIQUE_DEFAUT * 100),
    'confirmation' => null,
]);

$entreprises = computed(fn () => Entreprise::where('est_active', true)->orderBy('nom')->get());

$sites = computed(fn () => $this->entrepriseId ? Site::where('entreprise_id', $this->entrepriseId)->where('est_actif', true)->with('ville')->orderBy('nom')->get() : collect());

/** Un seul contrôle Site : le site précis définit l'activité, plus une option "les deux" par ville. */
$optionsSiteCommercial = computed(function () {
    $options = [];

    foreach ($this->sites->groupBy('ville_id') as $sitesDeLaVille) {
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

$activiteResolue = computed(function () {
    if (str_starts_with((string) $this->siteChoix, 'ville:')) {
        return 'Mécanique/Sinistre';
    }

    [$type, $id] = array_pad(explode(':', (string) $this->siteChoix, 2), 2, null);

    return $type === 'site' ? $this->sites->firstWhere('id', (int) $id)?->activite : null;
});

/** Périmètres proposés pour un nouveau responsable : une ville entière, ou un site précis. */
$perimetres = computed(function () {
    if (! $this->entrepriseId) {
        return [];
    }

    $options = [];

    foreach (Ville::where('entreprise_id', $this->entrepriseId)->where('est_actif', true)->orderBy('nom')->get() as $ville) {
        $options['ville:'.$ville->id] = $ville->nom.' — toute la ville';
    }

    foreach ($this->sites as $site) {
        $options['site:'.$site->id] = $site->nom.' (site précis)';
    }

    return $options;
});

$rolesDisponibles = computed(fn () => [
    'gerant' => 'Gérant',
    'responsable_site' => 'Responsable de site',
    'commercial' => 'Commercial',
    'caissier' => 'Caissier',
]);

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

    if ($this->roleActif === 'responsable_site' || $this->roleActif === 'caissier') {
        $regles['perimetre'] = ['required', 'in:'.implode(',', array_keys($this->perimetres))];
    }
    if ($this->roleActif === 'commercial') {
        $regles['siteChoix'] = ['required', 'in:'.implode(',', array_keys($this->optionsSiteCommercial))];
        $regles['objectifGlobal'] = ['required', 'numeric', 'min:0'];
        $regles['pourcentageMecanique'] = ['required', 'numeric', 'min:0', 'max:100'];
    }

    $donnees = $this->validate($regles, [], [
        'entrepriseId' => 'entreprise',
        'nom' => 'nom et prénoms',
        'email' => 'adresse e-mail',
        'motDePasse' => 'mot de passe',
        'perimetre' => 'périmètre',
        'siteChoix' => 'site',
        'objectifGlobal' => 'objectif mensuel',
        'pourcentageMecanique' => 'pourcentage Mécanique',
    ]);

    $entreprise = Entreprise::findOrFail($donnees['entrepriseId']);

    $siteId = null;
    if ($this->roleActif === 'commercial') {
        [$type, $id] = explode(':', $donnees['siteChoix'], 2);
        $siteId = $type === 'site'
            ? (int) $id
            : $this->sites->where('ville_id', (int) $id)->sortBy('activite')->first()?->id;
    }

    $action->executer($entreprise, $this->roleActif, [
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
    $this->siteChoix = '';
    $this->objectifGlobal = Commercial::OBJECTIF_MENSUEL_DEFAUT;
    $this->pourcentageMecanique = (int) (Commercial::PART_MECANIQUE_DEFAUT * 100);
    $this->perimetre = '';
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

            @if ($roleActif === 'responsable_site' || $roleActif === 'caissier')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Périmètre</label>
                <select wire:model="perimetre" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir une ville ou un site —</option>
                    @foreach ($this->perimetres as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('perimetre') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
            @endif

            @if ($roleActif === 'commercial')
                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Site (définit l'activité)</label>
                <select wire:model.live="siteChoix" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    <option value="">— Choisir un site —</option>
                    @foreach ($this->optionsSiteCommercial as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('siteChoix') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:14px 0 6px;">Objectif mensuel global (FCFA)</label>
                <input type="number" wire:model.live="objectifGlobal"
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                @error('objectifGlobal') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror

                @if ($this->activiteResolue === 'Mécanique/Sinistre')
                    <label style="display:block; font-size:14px; font-weight:600; color:#4B4E55; margin:10px 0 6px;">Répartition — % Mécanique</label>
                    <input type="number" wire:model.live="pourcentageMecanique" min="0" max="100"
                        style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #E2E0D8; border-radius:8px; font-size:15.5px;">
                    @error('pourcentageMecanique') <div style="color:#C8102E; font-size:13.5px; margin-top:6px;">{{ $message }}</div> @enderror
                @endif

                <div style="display:flex; gap:16px; margin-top:10px; padding:10px 12px; background:#F9F9F7; border-radius:8px; font-size:13px; color:#4B4E55; flex-wrap:wrap;">
                    <span>Mécanique : <b>{{ ae($this->objectifMecanique) }}</b></span>
                    <span>Sinistre : <b>{{ ae($this->objectifSinistre) }}</b></span>
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
