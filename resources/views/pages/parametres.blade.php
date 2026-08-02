<?php

use App\Domain\Tenants\Actions\CreerAcces;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Services\EnregistreurLogo;
use App\Models\User;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount, usesFileUploads};

usesFileUploads();

state([
    'onglet' => 'entreprise',
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

    // Nouveau site
    'siteNom' => '', 'siteVille' => '', 'siteCommune' => '', 'siteTelephone' => '', 'siteAdresse' => '',

    // Nouveau personnel
    'persRole' => 'commercial', 'persNom' => '', 'persEmail' => '', 'persMotDePasse' => '',
    'persSiteId' => '', 'persActivite' => 'Mécanique', 'persObjectif' => '',
]);

$entreprise = computed(fn () => Entreprise::find(auth()->user()->entreprise_id));

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)->orderBy('code')->get());

$personnel = computed(function () {
    $utilisateurs = User::where('entreprise_id', auth()->user()->entreprise_id)->orderByDesc('id')->get();
    $roles = User::nomsRolesParUtilisateur($utilisateurs->pluck('id'));

    return $utilisateurs->map(fn ($u) => ['utilisateur' => $u, 'role' => $roles[$u->id] ?? '—']);
});

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

$ajouterSite = function () {
    $donnees = $this->validate([
        'siteNom' => ['required', 'string', 'max:255'],
        'siteVille' => ['required', 'string', 'max:255'],
        'siteCommune' => ['nullable', 'string', 'max:255'],
        'siteTelephone' => ['nullable', 'string', 'max:40'],
        'siteAdresse' => ['nullable', 'string', 'max:255'],
    ], [], ['siteNom' => 'nom du site', 'siteVille' => 'ville']);

    $rang = $this->sites->count() + 1;
    $couleurs = ['#2563EB', '#0E9F6E', '#D97706', '#C8102E', '#7C3AED', '#0891B2'];

    Site::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'code' => 'S'.$rang,
        'nom' => $donnees['siteNom'],
        'ville' => $donnees['siteVille'],
        'commune' => $donnees['siteCommune'] ?: null,
        'telephone' => $donnees['siteTelephone'] ?: null,
        'adresse' => $donnees['siteAdresse'] ?: null,
        'couleur' => $couleurs[($rang - 1) % count($couleurs)],
        'est_actif' => true,
    ]);

    $this->reset(['siteNom', 'siteVille', 'siteCommune', 'siteTelephone', 'siteAdresse']);
    unset($this->sites);
    $this->message = 'Site créé.';
};

$ajouterPersonnel = function (CreerAcces $action) {
    $regles = [
        'persRole' => ['required', 'in:responsable_site,commercial'],
        'persNom' => ['required', 'string', 'max:255'],
        'persEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
        'persMotDePasse' => ['required', 'string', 'min:8'],
        'persSiteId' => ['required', Rule::exists('sites', 'id')->where('entreprise_id', auth()->user()->entreprise_id)],
    ];

    if ($this->persRole === 'commercial') {
        $regles['persActivite'] = ['required', 'in:Mécanique,Carrosserie'];
        $regles['persObjectif'] = ['nullable', 'numeric', 'min:0'];
    }

    $donnees = $this->validate($regles, [], [
        'persNom' => 'nom et prénoms', 'persEmail' => 'adresse e-mail',
        'persMotDePasse' => 'mot de passe', 'persSiteId' => 'site', 'persActivite' => 'activité',
    ]);

    $action->executer($this->entreprise, $donnees['persRole'], [
        'nom' => $donnees['persNom'],
        'email' => $donnees['persEmail'],
        'mot_de_passe' => $donnees['persMotDePasse'],
        'site_id' => $donnees['persSiteId'],
        'activite' => $donnees['persActivite'] ?? null,
        'objectif_mensuel' => $donnees['persObjectif'] ?? 0,
    ]);

    $this->reset(['persNom', 'persEmail', 'persMotDePasse', 'persObjectif']);
    unset($this->personnel);
    $this->message = 'Accès créé — le mot de passe devra être changé à la première connexion.';
};

?>

<div>
    <div style="display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap;">
        @foreach (['entreprise' => 'Fiche entreprise', 'compte' => 'Mon compte', 'sites' => 'Sites', 'personnel' => 'Personnel'] as $cle => $libelle)
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
                        :options="\App\Domain\Tenants\Models\Entreprise::REGIMES" />
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

    {{-- ------------------------------------------------------------ Sites --}}
    @if ($onglet === 'sites')
        <x-carte-section titre="Création d'un site">
            <div class="bloc-saisie">
                <x-champ label="Nom" model="siteNom" requis="true" placeholder="Ex : Agence Nord" />
                <x-champ label="Ville" model="siteVille" requis="true" placeholder="Ex : Abidjan" />
                <x-champ label="Commune" model="siteCommune" placeholder="Ex : Cocody" />
                <x-champ label="Téléphone" model="siteTelephone" placeholder="+225 07 ..." />
                <x-champ label="Adresse" model="siteAdresse" />
                <button type="button" wire:click="ajouterSite" class="bouton bouton-sombre">+ Ajouter le site</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:16px;">
                <table class="tableau">
                    <thead><tr><th>Code</th><th>Nom</th><th>Ville</th><th>Commune</th><th>Téléphone</th><th>Responsable</th></tr></thead>
                    <tbody>
                        @forelse ($this->sites as $site)
                            <tr>
                                <td style="font-weight:700;">{{ $site->code }}</td>
                                <td>
                                    <span style="display:inline-block; width:9px; height:9px; border-radius:99px; background:{{ $site->couleur }}; margin-right:7px;"></span>
                                    {{ $site->nom }}
                                </td>
                                <td>{{ $site->ville ?? '—' }}</td>
                                <td>{{ $site->commune ?? '—' }}</td>
                                <td>{{ $site->telephone ?? '—' }}</td>
                                <td>{{ $site->responsable?->name ?? '— à nommer —' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun site enregistré." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-carte-section>
    @endif

    {{-- -------------------------------------------------------- Personnel --}}
    @if ($onglet === 'personnel')
        <x-carte-section titre="Ajouter un membre du personnel">
            <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                @foreach (['commercial' => 'Commercial', 'responsable_site' => 'Responsable de site'] as $cle => $libelle)
                    <button type="button" wire:click="$set('persRole', '{{ $cle }}')"
                        class="onglet {{ $persRole === $cle ? 'est-actif' : '' }}">{{ $libelle }}</button>
                @endforeach
            </div>

            <div class="bloc-saisie">
                <x-champ label="Nom et prénoms" model="persNom" requis="true" />
                <x-champ label="Adresse e-mail" model="persEmail" type="email" requis="true" />
                <x-champ label="Mot de passe provisoire" model="persMotDePasse" type="password" requis="true" />
                <x-champ label="Site d'affectation" model="persSiteId" type="select"
                    :options="$this->sites->pluck('nom', 'id')" requis="true" width="200" />
                @if ($persRole === 'commercial')
                    <x-champ label="Activité" model="persActivite" type="select"
                        :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="160" />
                    <x-champ label="Objectif mensuel (FCFA)" model="persObjectif" type="number" width="180" />
                @endif
                <button type="button" wire:click="ajouterPersonnel" class="bouton bouton-sombre">+ Ajouter</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:16px;">
                <table class="tableau">
                    <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th></tr></thead>
                    <tbody>
                        @forelse ($this->personnel as $ligne)
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
        </x-carte-section>
    @endif
</div>
