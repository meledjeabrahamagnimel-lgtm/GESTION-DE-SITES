<?php

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Exercice;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\EnregistreurLogo;
use App\Models\User;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount, usesFileUploads};

usesFileUploads();

// Lié à l'URL : un lien externe (ex. le badge Exercice de l'en-tête) peut ouvrir
// directement le bon onglet via /parametres?onglet=exercices.
state(['onglet' => 'entreprise'])->url(except: 'entreprise');

state([
    'message' => null,

    // Fiche entreprise
    'nom' => '', 'gerantNom' => '', 'gerantPrenom' => '', 'gerantFonction' => '', 'gerantEmail' => '',
    'adresse' => '', 'telephone' => '', 'email' => '', 'rccm' => '',
    'ncc' => '', 'regimeImposition' => '', 'centreImpots' => '', 'compteContribuable' => '',
    'idu' => '', 'commune' => '', 'quartier' => '', 'referenceCadastrale' => '', 'proprietaireLocal' => '',
    'codeEntreprise' => '',
    'logo' => null,

    // Mon compte
    'monNom' => '', 'monEmail' => '', 'monTelephone' => '',

    // Nouvelle ville (crée aussitôt ses deux sites : Mécanique et Sinistre)
    'villeNom' => '', 'villeCommune' => '', 'villeTelephone' => '', 'villeAdresse' => '',

    // Nouvel exercice
    'exerciceAnnee' => (int) date('Y'),

    // Réaffectation d'un commercial à une nouvelle ville
    'reaffectVilleId' => [],

    'pageVilles' => 1,
    'pagePersonnel' => 1,
]);

$entreprise = computed(fn () => Entreprise::find(auth()->user()->entreprise_id));

$villes = computed(fn () => Ville::where('entreprise_id', auth()->user()->entreprise_id)
    ->with(['sites' => fn ($q) => $q->orderBy('activite')])->orderBy('code')->get());

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('nom')->get());

$exercices = computed(fn () => Exercice::where('entreprise_id', auth()->user()->entreprise_id)
    ->with('villes')->orderByDesc('annee')->get());

$villesActives = computed(fn () => Ville::where('entreprise_id', auth()->user()->entreprise_id)->where('est_actif', true)->orderBy('nom')->get());

$personnel = computed(function () {
    $utilisateurs = User::where('entreprise_id', auth()->user()->entreprise_id)->orderByDesc('id')->get();
    $roles = User::nomsRolesParUtilisateur($utilisateurs->pluck('id'));

    return $utilisateurs->map(fn ($u) => ['utilisateur' => $u, 'role' => $roles[$u->id] ?? '—']);
});

/** Commerciaux nommés de l'entreprise, pour la réaffectation de ville (Gérant uniquement). */
$commerciauxReaffectation = computed(fn () => Commercial::where('entreprise_id', auth()->user()->entreprise_id)
    ->where('est_spontane', false)->with('ville')->orderBy('nom')->get());

mount(function () {
    $e = $this->entreprise;

    $this->nom = $e->nom;
    $this->gerantNom = $e->gerant_nom ?? '';
    $this->gerantPrenom = $e->gerant_prenom ?? '';
    $this->gerantFonction = $e->gerant_fonction ?? 'Gérant';
    $this->gerantEmail = $e->gerant_email ?? '';
    $this->adresse = $e->adresse ?? '';
    $this->telephone = $e->telephone ?? '';
    $this->email = $e->email ?? '';
    $this->rccm = $e->rccm ?? '';
    $this->ncc = $e->ncc ?? '';
    $this->regimeImposition = $e->regime_imposition ?? array_key_first(Entreprise::REGIMES);
    $this->centreImpots = $e->centre_impots ?? '';
    $this->compteContribuable = $e->compte_contribuable ?? '';
    $this->idu = $e->idu ?? '';
    $this->commune = $e->commune ?? '';
    $this->quartier = $e->quartier ?? '';
    $this->referenceCadastrale = $e->reference_cadastrale ?? '';
    $this->proprietaireLocal = $e->proprietaire_local ?? '';
    $this->codeEntreprise = $e->code_entreprise ?? '';

    $this->monNom = auth()->user()->name;
    $this->monEmail = auth()->user()->email;
    $this->monTelephone = auth()->user()->telephone ?? '';

    $this->persSiteId = $this->sites->first()->id ?? '';
});

$enregistrerEntreprise = function (EnregistreurLogo $enregistreurLogo) {
    $donnees = $this->validate([
        'nom' => ['required', 'string', 'max:255'],
        'gerantNom' => ['nullable', 'string', 'max:255'],
        'gerantPrenom' => ['nullable', 'string', 'max:255'],
        'gerantFonction' => ['nullable', 'string', 'max:255'],
        'gerantEmail' => ['nullable', 'email', 'max:255'],
        'adresse' => ['nullable', 'string', 'max:255'],
        'telephone' => ['nullable', 'string', 'max:40'],
        'email' => ['nullable', 'email', 'max:255'],
        'rccm' => ['nullable', 'string', 'max:60'],
        'ncc' => ['nullable', 'string', 'max:40'],
        'regimeImposition' => ['nullable', 'string', 'max:255'],
        'centreImpots' => ['nullable', 'string', 'max:255'],
        'compteContribuable' => ['nullable', 'string', 'max:40'],
        'idu' => ['nullable', 'string', 'max:60'],
        'commune' => ['nullable', 'string', 'max:255'],
        'quartier' => ['nullable', 'string', 'max:255'],
        'referenceCadastrale' => ['nullable', 'string', 'max:255'],
        'proprietaireLocal' => ['nullable', 'string', 'max:255'],
        'logo' => EnregistreurLogo::REGLES,
    ]);

    $this->entreprise->update([
        'nom' => $donnees['nom'],
        'gerant_nom' => $donnees['gerantNom'],
        'gerant_prenom' => $donnees['gerantPrenom'],
        'gerant_fonction' => $donnees['gerantFonction'],
        'gerant_email' => $donnees['gerantEmail'],
        'adresse' => $donnees['adresse'],
        'telephone' => $donnees['telephone'],
        'email' => $donnees['email'],
        'rccm' => $donnees['rccm'],
        'ncc' => $donnees['ncc'],
        'regime_imposition' => $donnees['regimeImposition'],
        'centre_impots' => $donnees['centreImpots'],
        'compte_contribuable' => $donnees['compteContribuable'],
        'idu' => $donnees['idu'],
        'commune' => $donnees['commune'],
        'quartier' => $donnees['quartier'],
        'reference_cadastrale' => $donnees['referenceCadastrale'],
        'proprietaire_local' => $donnees['proprietaireLocal'],
    ]);

    if ($this->logo) {
        $this->entreprise->update([
            'logo_chemin' => $enregistreurLogo->enregistrer($this->logo, $this->entreprise),
        ]);
        $this->reset('logo');
    }

    unset($this->entreprise);
    $this->message = 'Fiche entreprise enregistrée.';
};

$regenererCode = function () {
    $this->codeEntreprise = Entreprise::genererCode($this->nom);
    $this->entreprise->update(['code_entreprise' => $this->codeEntreprise]);
    unset($this->entreprise);
    $this->message = 'Nouveau code entreprise généré. L\'ancien code ne fonctionne plus.';
};

$enregistrerMonCompte = function () {
    $donnees = $this->validate([
        'monNom' => ['required', 'string', 'max:255'],
        'monEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
        'monTelephone' => ['nullable', 'string', 'max:40'],
    ], [], ['monNom' => 'nom', 'monEmail' => 'adresse e-mail', 'monTelephone' => 'téléphone']);

    auth()->user()->update([
        'name' => $donnees['monNom'],
        'email' => $donnees['monEmail'],
        'telephone' => $donnees['monTelephone'] ?: null,
    ]);

    $this->message = 'Vos informations ont été mises à jour.';
};

$ajouterVille = function () {
    $donnees = $this->validate([
        'villeNom' => ['required', 'string', 'max:255'],
        'villeCommune' => ['nullable', 'string', 'max:255'],
        'villeTelephone' => ['nullable', 'string', 'max:40'],
        'villeAdresse' => ['nullable', 'string', 'max:255'],
    ], [], ['villeNom' => 'nom de la ville']);

    $rang = $this->villes->count() + 1;
    $couleurs = ['#2563EB', '#0E9F6E', '#D97706', '#C8102E', '#7C3AED', '#0891B2'];

    $ville = Ville::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'code' => 'V'.$rang,
        'nom' => $donnees['villeNom'],
        'commune' => $donnees['villeCommune'] ?: null,
        'telephone' => $donnees['villeTelephone'] ?: null,
        'adresse' => $donnees['villeAdresse'] ?: null,
        'couleur' => $couleurs[($rang - 1) % count($couleurs)],
        'est_actif' => true,
    ]);

    foreach (['Mécanique', 'Sinistre'] as $activite) {
        Site::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'ville_id' => $ville->id,
            'nom' => $donnees['villeNom'].' — '.$activite,
            'activite' => $activite,
            'est_actif' => true,
        ]);
    }

    $this->reset(['villeNom', 'villeCommune', 'villeTelephone', 'villeAdresse']);
    unset($this->villes, $this->sites);
    $this->message = 'Ville créée avec ses deux sites (Mécanique et Sinistre).';
};

/**
 * Réaffecte un commercial à une nouvelle ville : un commercial travaille dans une
 * ville, jamais figé sur une activité, et peut être déplacé au besoin (mutation,
 * renfort d'une nouvelle ville…). Réservé au Gérant.
 */
$reaffecterCommercial = function (int $commercialId) {
    if (! auth()->user()->hasRole('gerant')) {
        return;
    }

    $nouvelleVilleId = $this->reaffectVilleId[$commercialId] ?? null;
    if (! $nouvelleVilleId) {
        return;
    }

    $commercial = Commercial::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($commercialId);
    $ville = Ville::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($nouvelleVilleId);

    $commercial->update(['ville_id' => $ville->id]);

    unset($this->commerciauxReaffectation);
    $this->message = "{$commercial->nom} réaffecté à {$ville->nom}.";
};

$creerExercice = function () {
    $donnees = $this->validate([
        'exerciceAnnee' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('exercices', 'annee')->where('entreprise_id', auth()->user()->entreprise_id)],
    ], [], ['exerciceAnnee' => 'année']);

    Exercice::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'annee' => $donnees['exerciceAnnee'],
        'statut' => 'Ouvert',
        // Le tout premier exercice de l'entreprise devient le défaut d'office, sinon le
        // badge d'en-tête n'aurait rien à afficher tant que le gérant n'a rien choisi.
        'est_defaut' => ! Exercice::where('entreprise_id', auth()->user()->entreprise_id)->exists(),
    ]);

    $this->exerciceAnnee = (int) $donnees['exerciceAnnee'] + 1;
    unset($this->exercices);
    $this->message = 'Exercice créé.';
};

$definirExerciceParDefaut = function (int $exerciceId) {
    $exercice = Exercice::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($exerciceId);
    $exercice->definirParDefaut();
    unset($this->exercices);
    $this->message = "Exercice {$exercice->annee} marqué par défaut — c'est celui-ci que toute l'équipe voit dans l'en-tête.";
};

$clorePourVille = function (int $exerciceId, int $villeId) {
    $exercice = Exercice::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($exerciceId);
    $ville = Ville::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($villeId);

    $exercice->clorePourVille($ville, auth()->user());
    unset($this->exercices);
    $this->message = "Exercice {$exercice->annee} clos pour {$ville->nom}.";
};

$reouvrirPourVille = function (int $exerciceId, int $villeId) {
    $exercice = Exercice::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($exerciceId);
    $ville = Ville::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($villeId);

    $exercice->reouvrirPourVille($ville);
    unset($this->exercices);
    $this->message = "Exercice {$exercice->annee} réouvert pour {$ville->nom}.";
};

$cloreExercice = function (int $exerciceId) {
    $exercice = Exercice::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($exerciceId);
    $exercice->update(['statut' => 'Clos', 'cloture_le' => now()]);
    unset($this->exercices);
    $this->message = "Exercice {$exercice->annee} clos.";
};

$reouvrirExercice = function (int $exerciceId) {
    $exercice = Exercice::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($exerciceId);
    $exercice->update(['statut' => 'Ouvert', 'cloture_le' => null]);
    unset($this->exercices);
    $this->message = "Exercice {$exercice->annee} réouvert.";
};

?>

<div>
    <div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
        @foreach (['entreprise' => 'Fiche entreprise', 'compte' => 'Mon compte', 'villes' => 'Villes', 'personnel' => 'Personnel', 'acces' => 'Ajouter un accès', 'exercices' => 'Exercices'] as $cle => $libelle)
            <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                class="onglet {{ $onglet === $cle ? 'est-actif' : '' }}">{{ $libelle }}</button>
        @endforeach
    </div>

    @if ($message)
        <div class="encart encart-succes">{{ $message }}</div>
    @endif

    {{-- ------------------------------------------------ Fiche entreprise --}}
    @if ($onglet === 'entreprise')
        <x-carte-section titre="Code entreprise">
            <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                <div style="font-family:'Barlow Condensed',sans-serif; font-size:34px; font-weight:700; letter-spacing:3px; color:var(--th-accent,#C8102E);">
                    {{ $codeEntreprise ?: '— non généré —' }}
                </div>
                <button type="button" wire:click="regenererCode"
                    wire:confirm="Générer un nouveau code ? L'ancien code ne permettra plus de s'inscrire."
                    class="bouton bouton-secondaire">Régénérer</button>
                <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0; flex:1; min-width:240px;">
                    Communiquez ce code à vos commerciaux et responsables : ils s'inscrivent seuls sur
                    <b>{{ route('inscription.personnel') }}</b> et sont rattachés automatiquement.
                </p>
            </div>
        </x-carte-section>

        <form wire:submit="enregistrerEntreprise">
            <x-carte-section titre="Informations générales">
                <div class="bloc-saisie" style="background:transparent; border:0; padding:0;">
                    <x-champ label="Nom de l'entreprise" model="nom" requis="true" />
                    <x-champ label="Nom du Gérant / Représentant" model="gerantNom" />
                    <x-champ label="Prénom du Gérant" model="gerantPrenom" />
                    <x-champ label="Fonction du Gérant" model="gerantFonction" />
                    <x-champ label="E-mail du gérant" model="gerantEmail" type="email" />
                    <x-champ label="Adresse physique" model="adresse" />
                    <x-champ label="Téléphone" model="telephone" />
                    <x-champ label="E-mail" model="email" type="email" />
                    <x-champ label="RCCM" model="rccm" />
                </div>

                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--th-ligne,#E2E0D8);">
                    <label class="champ-libelle">Logo principal de l'entreprise</label>
                    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                        @if ($this->entreprise?->logoUrl())
                            <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; padding:8px 14px;">
                                <img src="{{ $this->entreprise->logoUrl() }}" alt="Logo actuel" style="height:46px; display:block;">
                            </div>
                            <span style="font-size:12.5px; color:var(--th-gris,#6B6E76);">Logo actuel</span>
                        @endif
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="champ" style="max-width:320px;">
                        <div wire:loading wire:target="logo" style="font-size:13px; color:var(--th-gris,#6B6E76);">Chargement…</div>
                        @if ($logo)
                            <div style="background:#fff; border:1px solid var(--th-accent,#C8102E); border-radius:8px; padding:8px 14px;">
                                <img src="{{ $logo->temporaryUrl() }}" alt="Nouveau logo" style="height:46px; display:block;">
                            </div>
                            <span style="font-size:12.5px; color:var(--th-accent,#C8102E); font-weight:600;">Nouveau — enregistrez pour appliquer</span>
                        @endif
                    </div>
                    @error('logo') <span class="champ-erreur">{{ $message }}</span> @enderror
                </div>
            </x-carte-section>

            <x-carte-section titre="Informations fiscales">
                <div class="bloc-saisie" style="background:transparent; border:0; padding:0;">
                    <x-champ label="NCC — N° Compte Contribuable" model="ncc" />
                    <x-champ label="Régime d'imposition" model="regimeImposition" type="select"
                        :options="\Modules\Noyau\Entreprises\Modeles\Entreprise::REGIMES" />
                    <x-champ label="Centre des impôts" model="centreImpots" />
                    <x-champ label="N° Compte Contribuable (CC)" model="compteContribuable" />
                </div>
            </x-carte-section>

            <x-carte-section titre="DGI & local professionnel">
                <div class="bloc-saisie" style="background:transparent; border:0; padding:0;">
                    <x-champ label="IDU — Identifiant Unique DGI" model="idu" />
                    <x-champ label="Commune" model="commune" />
                    <x-champ label="Quartier" model="quartier" />
                    <x-champ label="Référence cadastrale" model="referenceCadastrale" />
                    <x-champ label="Propriétaire du local professionnel" model="proprietaireLocal" />
                </div>
            </x-carte-section>

            <button type="submit" class="bouton">Enregistrer la fiche entreprise</button>
        </form>
    @endif

    {{-- ------------------------------------------------------- Mon compte --}}
    @if ($onglet === 'compte')
        <form wire:submit="enregistrerMonCompte">
            <x-carte-section titre="Mes informations">
                <div class="bloc-saisie" style="background:transparent; border:0; padding:0;">
                    <x-champ label="Nom et prénoms" model="monNom" requis="true" />
                    <x-champ label="Adresse e-mail" model="monEmail" type="email" requis="true" />
                    <x-champ label="Téléphone" model="monTelephone" />
                </div>
                <div style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <button type="submit" class="bouton">Enregistrer</button>
                    <a href="{{ route('mot-de-passe.modifier') }}" wire:navigate
                        style="font-size:14px; color:var(--th-accent,#C8102E); font-weight:700; text-decoration:none;">
                        Changer mon mot de passe
                    </a>
                </div>
            </x-carte-section>
        </form>
    @endif

    {{-- ----------------------------------------------------------- Villes --}}
    @if ($onglet === 'villes')
        <x-carte-section titre="Création d'une ville">
            <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                Créer une ville crée aussitôt ses deux sites d'activité (Mécanique et Sinistre).
            </p>
            <div class="bloc-saisie">
                <x-champ label="Nom de la ville" model="villeNom" requis="true" placeholder="Ex : Bouaké" />
                <x-champ label="Commune" model="villeCommune" placeholder="Ex : Koko" />
                <x-champ label="Téléphone" model="villeTelephone" placeholder="+225 07 ..." />
                <x-champ label="Adresse" model="villeAdresse" />
                <button type="button" wire:click="ajouterVille" class="bouton bouton-sombre">+ Ajouter la ville</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:16px;">
                <table class="tableau">
                    <thead><tr><th>Code</th><th>Ville</th><th>Commune</th><th>Téléphone</th><th>Responsable</th><th>Sites</th></tr></thead>
                    <tbody>
                        @forelse ($this->villes->forPage($pageVilles, 10) as $ville)
                            <tr>
                                <td style="font-weight:700;">{{ $ville->code }}</td>
                                <td>
                                    <span style="display:inline-block; width:9px; height:9px; border-radius:99px; background:{{ $ville->couleur }}; margin-right:7px;"></span>
                                    {{ $ville->nom }}
                                </td>
                                <td>{{ $ville->commune ?? '—' }}</td>
                                <td>{{ $ville->telephone ?? '—' }}</td>
                                <td>{{ $ville->responsable?->name ?? '— à nommer —' }}</td>
                                <td>
                                    @foreach ($ville->sites as $site)
                                        {{ $site->nom }} ({{ $site->responsable?->name ?? 'hérite de la ville' }}){{ ! $loop->last ? ', ' : '' }}
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucune ville enregistrée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageVilles" :total="$this->villes->count()" prop="pageVilles" />
        </x-carte-section>
    @endif

    {{-- -------------------------------------------------------- Personnel --}}
    @if ($onglet === 'personnel')
        <x-carte-section titre="Personnel de l'entreprise">
            <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                Pour créer un Responsable de site, un Commercial ou un accès Comptabilité, direction l'onglet
                <button type="button" wire:click="$set('onglet', 'acces')" style="background:none; border:0; padding:0; color:var(--th-accent,#C8102E); font-weight:700; cursor:pointer; font-size:13px;">Ajouter un accès</button>.
            </p>

            <div class="tableau-conteneur" style="margin-top:16px;">
                <table class="tableau">
                    <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th></tr></thead>
                    <tbody>
                        @forelse ($this->personnel->forPage($pagePersonnel, 10) as $ligne)
                            <tr>
                                <td style="font-weight:600;">{{ $ligne['utilisateur']->name }}</td>
                                <td>{{ $ligne['utilisateur']->email }}</td>
                                <td>{{ $ligne['role'] }}</td>
                                <td>
                                    <span class="pastille {{ $ligne['utilisateur']->est_actif ? 'pastille-vert' : 'pastille-rouge' }}">
                                        {{ $ligne['utilisateur']->est_actif ? 'Actif' : 'Révoqué' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="4" texte="Aucun membre du personnel." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pagePersonnel" :total="$this->personnel->count()" prop="pagePersonnel" />
        </x-carte-section>

        @if (auth()->user()->hasRole('gerant'))
            <x-carte-section titre="Réaffecter un commercial">
                <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                    Un commercial travaille dans une ville entière, pas sur une seule activité : il peut être
                    déplacé vers une autre ville à tout moment, par exemple en cas de mutation.
                </p>
                <div class="tableau-conteneur">
                    <table class="tableau">
                        <thead><tr><th>Commercial</th><th>Ville actuelle</th><th>Nouvelle ville</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($this->commerciauxReaffectation as $commercial)
                                <tr wire:key="reaffect-{{ $commercial->id }}">
                                    <td style="font-weight:600;">{{ $commercial->numero }} — {{ $commercial->nom }}</td>
                                    <td>{{ $commercial->ville->nom }}</td>
                                    <td>
                                        <select wire:model="reaffectVilleId.{{ $commercial->id }}" class="champ" style="width:auto;">
                                            @foreach ($this->villesActives as $ville)
                                                <option value="{{ $ville->id }}" @selected($ville->id === $commercial->ville_id)>{{ $ville->nom }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" wire:click="reaffecterCommercial({{ $commercial->id }})"
                                            wire:confirm="Réaffecter {{ $commercial->nom }} à cette ville ?"
                                            class="bouton bouton-secondaire bouton-petit">Réaffecter</button>
                                    </td>
                                </tr>
                            @empty
                                <x-table-vide :colspan="4" texte="Aucun commercial à réaffecter." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-carte-section>
        @endif
    @endif

    {{-- ------------------------------------------------- Ajouter un accès --}}
    @if ($onglet === 'acces')
        <livewire:acces-creer />
    @endif

    {{-- -------------------------------------------------------- Exercices --}}
    @if ($onglet === 'exercices')
        <x-carte-section titre="Nouvel exercice" icone="liste" couleur="#2563EB">
            <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                Un exercice couvre une année civile. Il se clôture ville par ville ; tant qu'une
                ville reste ouverte, la saisie y reste possible. La clôture complète de
                l'exercice est toujours une décision volontaire, jamais automatique.
            </p>
            <div class="bloc-saisie">
                <x-champ label="Année" model="exerciceAnnee" type="number" width="140" />
                <button type="button" wire:click="creerExercice" class="bouton bouton-sombre">+ Créer l'exercice</button>
            </div>
        </x-carte-section>

        @foreach ($this->exercices as $exercice)
            @php
                $toutesClosesIci = $exercice->toutesLesVillesSontClosesPour(auth()->user()->entreprise_id);
            @endphp
            <x-carte-section titre="Exercice {{ $exercice->annee }}" icone="liste" couleur="{{ $exercice->statut === 'Clos' ? '#C8102E' : '#0E9F6E' }}">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap;">
                    <span class="pastille {{ $exercice->statut === 'Clos' ? 'pastille-rouge' : 'pastille-vert' }}">
                        {{ $exercice->statut === 'Clos' ? 'Exercice clos' : 'Exercice ouvert' }}
                    </span>
                    @if ($exercice->est_defaut)
                        <span class="pastille pastille-bleu">★ Par défaut — visible par toute l'équipe</span>
                    @else
                        <button type="button" wire:click="definirExerciceParDefaut({{ $exercice->id }})" class="bouton bouton-secondaire bouton-petit">
                            Définir par défaut
                        </button>
                    @endif
                    @if ($exercice->statut === 'Clos')
                        <span style="font-size:12.5px; color:#6B6E76;">Clos le {{ $exercice->cloture_le?->format('d/m/Y') }}</span>
                        <button type="button" wire:click="reouvrirExercice({{ $exercice->id }})" class="bouton bouton-secondaire bouton-petit">Réouvrir l'exercice</button>
                    @elseif ($toutesClosesIci)
                        <span style="font-size:12.5px; color:#D97706; font-weight:600;">Toutes les villes sont closes — l'exercice peut être clôturé.</span>
                        <button type="button" wire:click="cloreExercice({{ $exercice->id }})"
                            wire:confirm="Clôturer définitivement l'exercice {{ $exercice->annee }} ? Plus aucune saisie ne sera possible pour cette année, sur aucune ville."
                            class="bouton bouton-sombre bouton-petit">Clôturer l'exercice</button>
                    @endif
                </div>

                <div class="tableau-conteneur">
                    <table class="tableau">
                        <thead><tr><th>Ville</th><th>Statut</th><th>Clôturé le</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($this->villesActives as $ville)
                                @php
                                    $pivot = $exercice->villes->firstWhere('id', $ville->id)?->pivot;
                                    $statutVille = $pivot?->statut ?? 'Ouvert';
                                @endphp
                                <tr wire:key="exercice-{{ $exercice->id }}-ville-{{ $ville->id }}">
                                    <td style="font-weight:600;">{{ $ville->nom }}</td>
                                    <td>
                                        <span class="pastille {{ $statutVille === 'Clos' ? 'pastille-rouge' : 'pastille-vert' }}">{{ $statutVille }}</span>
                                    </td>
                                    <td>{{ $pivot?->cloture_le ? \Illuminate\Support\Carbon::parse($pivot->cloture_le)->format('d/m/Y') : '—' }}</td>
                                    <td style="text-align:right;">
                                        @if ($statutVille === 'Clos')
                                            <button type="button" wire:click="reouvrirPourVille({{ $exercice->id }}, {{ $ville->id }})" class="bouton bouton-secondaire bouton-petit">Réouvrir</button>
                                        @else
                                            <button type="button" wire:click="clorePourVille({{ $exercice->id }}, {{ $ville->id }})"
                                                wire:confirm="Clôturer l'exercice {{ $exercice->annee }} pour {{ $ville->nom }} ? Plus aucune saisie ne sera possible pour cette ville sur cette année."
                                                class="bouton bouton-secondaire bouton-petit">Clôturer</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-table-vide :colspan="4" texte="Aucune ville active." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-carte-section>
        @endforeach

        @if ($this->exercices->isEmpty())
            <p class="legende-vide">Aucun exercice créé pour le moment.</p>
        @endif
    @endif
</div>
