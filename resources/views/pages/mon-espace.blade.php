<?php

use App\Domain\Shared\Models\Referentiel;
use App\Domain\Shared\Services\EnregistreurPhoto;
use App\Domain\Tenants\Models\Site;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount, usesFileUploads};

usesFileUploads();

state([
    'onglet' => 'profil',
    'message' => null,

    'nom' => '', 'email' => '', 'telephone' => '', 'photo' => null,

    // Référentiels
    'refType' => Referentiel::ACTIVITE,
    'refValeur' => '',

    // Sites (responsable de site uniquement)
    'siteId' => null,
    'siteNom' => '', 'siteVille' => '', 'siteCommune' => '', 'siteTelephone' => '', 'siteAdresse' => '',
]);

mount(function () {
    $this->nom = auth()->user()->name;
    $this->email = auth()->user()->email;
    $this->telephone = auth()->user()->telephone ?? '';
});

$utilisateur = computed(fn () => auth()->user());

$entreprise = computed(fn () => auth()->user()->entreprise);

$monRole = computed(fn () => auth()->user()->hasRole('responsable_site') ? 'Responsable de site' : 'Commercial');

$monSite = computed(function () {
    if (auth()->user()->hasRole('responsable_site')) {
        return Site::where('responsable_id', auth()->id())->first();
    }

    return \App\Domain\Operations\Models\Commercial::where('user_id', auth()->id())->first()?->site;
});

$maFicheCommerciale = computed(fn () => \App\Domain\Operations\Models\Commercial::where('user_id', auth()->id())->first());

/**
 * Le responsable de site tient les sites à jour depuis son poste, sans dépendre
 * de la disponibilité du gérant. Le commercial, lui, n'y a pas accès.
 */
$estResponsable = computed(fn () => auth()->user()->hasRole('responsable_site'));

$sites = computed(fn () => Site::where('entreprise_id', auth()->user()->entreprise_id)
    ->withCount('commerciaux')->orderBy('code')->get());

$viderFormulaireSite = function () {
    $this->siteId = null;
    $this->siteNom = '';
    $this->siteVille = '';
    $this->siteCommune = '';
    $this->siteTelephone = '';
    $this->siteAdresse = '';
};

$editerSite = function (int $id) {
    abort_unless($this->estResponsable, 403);

    $site = Site::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($id);

    $this->siteId = $site->id;
    $this->siteNom = $site->nom;
    $this->siteVille = $site->ville ?? '';
    $this->siteCommune = $site->commune ?? '';
    $this->siteTelephone = $site->telephone ?? '';
    $this->siteAdresse = $site->adresse ?? '';
};

$enregistrerSite = function () {
    abort_unless($this->estResponsable, 403);

    $donnees = $this->validate([
        'siteNom' => ['required', 'string', 'max:255'],
        'siteVille' => ['required', 'string', 'max:255'],
        'siteCommune' => ['nullable', 'string', 'max:255'],
        'siteTelephone' => ['nullable', 'string', 'max:40'],
        'siteAdresse' => ['nullable', 'string', 'max:255'],
    ], [], [
        'siteNom' => 'nom du site', 'siteVille' => 'ville', 'siteCommune' => 'commune',
        'siteTelephone' => 'téléphone', 'siteAdresse' => 'adresse',
    ]);

    $valeurs = [
        'nom' => $donnees['siteNom'],
        'ville' => $donnees['siteVille'],
        'commune' => $donnees['siteCommune'] ?: null,
        'telephone' => $donnees['siteTelephone'] ?: null,
        'adresse' => $donnees['siteAdresse'] ?: null,
    ];

    if ($this->siteId) {
        Site::where('entreprise_id', auth()->user()->entreprise_id)
            ->findOrFail($this->siteId)->update($valeurs);

        $this->message = 'Site mis à jour.';
    } else {
        // Le code sert de préfixe aux numéros de documents : il doit rester unique
        // dans l'entreprise, y compris après la suppression d'un site.
        $rang = Site::where('entreprise_id', auth()->user()->entreprise_id)->max('id') + 1;

        Site::create([
            ...$valeurs,
            'entreprise_id' => auth()->user()->entreprise_id,
            'code' => 'S'.str_pad((string) $rang, 2, '0', STR_PAD_LEFT),
            'est_actif' => true,
        ]);

        $this->message = 'Site créé : il est désormais proposé dans les listes déroulantes.';
    }

    $this->viderFormulaireSite();
    unset($this->sites, $this->monSite);
};

$basculerSite = function (int $id) {
    abort_unless($this->estResponsable, 403);

    $site = Site::where('entreprise_id', auth()->user()->entreprise_id)->findOrFail($id);
    $site->update(['est_actif' => ! $site->est_actif]);

    unset($this->sites);
    $this->message = $site->est_actif ? 'Site réactivé.' : 'Site désactivé.';
};

$referentiels = computed(fn () => Referentiel::where('type', $this->refType)->orderBy('valeur')->get());

$optionsDuType = computed(fn () => Referentiel::options($this->refType));

$enregistrerProfil = function (EnregistreurPhoto $enregistreur) {
    $donnees = $this->validate([
        'nom' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
        'telephone' => ['nullable', 'string', 'max:40'],
        'photo' => EnregistreurPhoto::REGLES,
    ], [], ['nom' => 'nom', 'email' => 'adresse e-mail', 'telephone' => 'téléphone']);

    $utilisateur = auth()->user();

    $utilisateur->update([
        'name' => $donnees['nom'],
        'email' => $donnees['email'],
        'telephone' => $donnees['telephone'] ?: null,
    ]);

    if ($this->photo) {
        $utilisateur->update(['photo_chemin' => $enregistreur->enregistrer($this->photo, $utilisateur)]);
        $this->reset('photo');
    }

    $this->message = 'Vos informations ont été mises à jour.';
};

$ajouterReferentiel = function () {
    $donnees = $this->validate([
        'refType' => ['required', Rule::in(array_keys(Referentiel::LIBELLES))],
        'refValeur' => ['required', 'string', 'max:120'],
    ], [], ['refValeur' => 'valeur']);

    $valeur = trim($donnees['refValeur']);

    if (array_key_exists($valeur, $this->optionsDuType)) {
        $this->addError('refValeur', 'Cette valeur existe déjà dans la liste.');

        return;
    }

    Referentiel::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'type' => $donnees['refType'],
        'valeur' => $valeur,
        'est_actif' => true,
    ]);

    $this->reset('refValeur');
    unset($this->referentiels, $this->optionsDuType);
    $this->message = 'Valeur ajoutée : elle est désormais proposée dans les listes déroulantes.';
};

$basculerReferentiel = function (int $id) {
    $ref = Referentiel::findOrFail($id);
    $ref->update(['est_actif' => ! $ref->est_actif]);
    unset($this->referentiels, $this->optionsDuType);
};

$supprimerReferentiel = function (int $id) {
    Referentiel::where('id', $id)->delete();
    unset($this->referentiels, $this->optionsDuType);
    $this->message = 'Valeur supprimée.';
};

?>

<div>
    <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
        @php
            $onglets = ['profil' => 'Mon profil', 'entreprise' => 'Mon entreprise'];
            if ($this->estResponsable) {
                $onglets['sites'] = 'Sites';
            }
            $onglets['listes'] = 'Listes déroulantes';
        @endphp
        @foreach ($onglets as $cle => $libelle)
            <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                class="onglet {{ $onglet === $cle ? 'est-actif' : '' }}">{{ $libelle }}</button>
        @endforeach
    </div>

    @if ($message)
        <div class="encart encart-succes">{{ $message }}</div>
    @endif

    {{-- ------------------------------------------------------ Mon profil --}}
    @if ($onglet === 'profil')
        <form wire:submit="enregistrerProfil">
            <x-carte-section titre="Mon profil">
                <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
                    <div style="text-align:center;">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu"
                                style="width:84px; height:84px; border-radius:99px; object-fit:cover; border:2px solid var(--th-accent,#C8102E);">
                            <div style="font-size:11.5px; color:var(--th-accent,#C8102E); font-weight:600; margin-top:5px;">Enregistrez pour appliquer</div>
                        @else
                            <x-avatar :utilisateur="$this->utilisateur" :taille="84" />
                        @endif
                    </div>
                    <div style="flex:1; min-width:240px;">
                        <label class="champ-libelle">Photo de profil</label>
                        <input type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="champ" style="max-width:320px;">
                        <div wire:loading wire:target="photo" style="font-size:12.5px; color:var(--th-gris,#6B6E76); margin-top:4px;">Chargement…</div>
                        @error('photo') <span class="champ-erreur">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="bloc-saisie" style="background:transparent; border:0; padding:0;">
                    <x-champ label="Nom et prénoms" model="nom" requis="true" />
                    <x-champ label="Adresse e-mail" model="email" type="email" requis="true" />
                    <x-champ label="Téléphone" model="telephone" />
                </div>

                <div style="margin-top:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <button type="submit" class="bouton">Enregistrer</button>
                    <a href="{{ route('mot-de-passe.modifier') }}" wire:navigate
                        style="font-size:14px; color:var(--th-accent,#C8102E); font-weight:700; text-decoration:none;">Changer mon mot de passe</a>
                </div>
            </x-carte-section>
        </form>
    @endif

    {{-- -------------------------------------------------- Mon entreprise --}}
    @if ($onglet === 'entreprise')
        <x-carte-section titre="Mon rattachement">
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead><tr><th>Information</th><th>Valeur</th></tr></thead>
                    <tbody>
                        <tr><td style="font-weight:600;">Entreprise</td><td>{{ $this->entreprise?->nom ?? '—' }}</td></tr>
                        <tr><td style="font-weight:600;">Mon rôle</td><td>{{ $this->monRole }}</td></tr>
                        <tr><td style="font-weight:600;">Mon site</td><td>{{ $this->monSite?->nom ?? '—' }}</td></tr>
                        @if ($this->maFicheCommerciale)
                            <tr><td style="font-weight:600;">N° commercial</td><td>{{ $this->maFicheCommerciale->numero }}</td></tr>
                            <tr><td style="font-weight:600;">Activité</td><td>{{ $this->maFicheCommerciale->activite ?? '—' }}</td></tr>
                            <tr><td style="font-weight:600;">Objectif mensuel</td><td>{{ ae($this->maFicheCommerciale->objectif_mensuel) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--th-ligne,#E2E0D8);">
                <label class="champ-libelle">Code entreprise</label>
                <p style="font-size:12.5px; color:var(--th-gris,#6B6E76); margin:0 0 8px;">
                    Lecture seule. Seul le gérant peut le modifier.
                </p>
                <div style="font-family:'Barlow Condensed',sans-serif; font-size:30px; font-weight:700; letter-spacing:3px; color:var(--th-accent,#C8102E);">
                    {{ $this->entreprise?->code_entreprise ?? '— non généré —' }}
                </div>
            </div>
        </x-carte-section>
    @endif

    {{-- -------------------------------------------------------------- Sites --}}
    @if ($onglet === 'sites' && $this->estResponsable)
        <x-carte-section titre="{{ $siteId ? 'Modifier le site' : 'Nouveau site' }}" icone="atelier" couleur="#2563EB">
            <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                Les sites créés ici alimentent immédiatement les listes déroulantes de saisie
                et le filtre du tableau de bord. Le code est attribué automatiquement.
            </p>

            <div class="bloc-saisie">
                <x-champ label="Nom du site" model="siteNom" requis="true" placeholder="Ex : Abidjan — Site 2" />
                <x-champ label="Ville" model="siteVille" requis="true" placeholder="Ex : Abidjan" />
                <x-champ label="Commune" model="siteCommune" placeholder="Ex : Cocody" />
            </div>

            <div class="bloc-saisie" style="margin-top:10px;">
                <x-champ label="Téléphone" model="siteTelephone" placeholder="+225 …" />
                <x-champ label="Adresse" model="siteAdresse" placeholder="Ex : Boulevard Latrille" />
            </div>

            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" wire:click="enregistrerSite" class="bouton">
                    {{ $siteId ? 'Enregistrer les modifications' : '+ Créer le site' }}
                </button>
                @if ($siteId)
                    <button type="button" wire:click="viderFormulaireSite" class="bouton bouton-secondaire">Annuler</button>
                @endif
            </div>
        </x-carte-section>

        <x-carte-section titre="Sites de l'entreprise" icone="liste" couleur="#2A2E35">
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Code</th><th>Nom</th><th>Ville</th><th>Commune</th>
                            <th>Téléphone</th><th>Responsable</th><th>Commerciaux</th><th>Statut</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->sites as $s)
                            <tr wire:key="site-{{ $s->id }}">
                                <td style="font-weight:700;">{{ $s->code }}</td>
                                <td style="font-weight:600;">{{ $s->nom }}</td>
                                <td>{{ $s->ville ?? '—' }}</td>
                                <td>{{ $s->commune ?? '—' }}</td>
                                <td>{{ $s->telephone ?? '—' }}</td>
                                <td>{{ $s->responsable?->name ?? '—' }}</td>
                                <td>{{ $s->commerciaux_count }}</td>
                                <td>
                                    <span class="pastille {{ $s->est_actif ? 'pastille-vert' : 'pastille-ambre' }}">
                                        {{ $s->est_actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" wire:click="editerSite({{ $s->id }})"
                                        class="bouton bouton-secondaire bouton-petit">Modifier</button>
                                    <button type="button" wire:click="basculerSite({{ $s->id }})"
                                        class="bouton bouton-secondaire bouton-petit">
                                        {{ $s->est_actif ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->sites->isEmpty())
                <p class="legende-vide">Aucun site pour le moment.</p>
            @endif
        </x-carte-section>
    @endif

    {{-- ------------------------------------------------ Listes déroulantes --}}
    @if ($onglet === 'listes')
        <x-carte-section titre="Listes déroulantes">
            <p style="font-size:13px; color:var(--th-gris,#6B6E76); margin:0 0 14px;">
                Ajoutez ici les valeurs qui manquent dans les listes de saisie. Elles deviennent
                immédiatement disponibles pour toute l'entreprise. Les valeurs livrées avec
                l'application ne peuvent pas être supprimées.
            </p>

            <div class="bloc-saisie">
                <x-champ label="Liste" model="refType" type="select" live="true" width="220"
                    :options="\App\Domain\Shared\Models\Referentiel::LIBELLES" />
                <x-champ label="Nouvelle valeur" model="refValeur" placeholder="Ex : Peinture" />
                <button type="button" wire:click="ajouterReferentiel" class="bouton bouton-sombre">+ Ajouter</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:14px;">
                <table class="tableau">
                    <thead><tr><th>Valeur</th><th>Origine</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($this->optionsDuType as $valeur => $libelle)
                            @php
                                $parDefaut = \App\Domain\Shared\Models\Referentiel::estValeurParDefaut($refType, $valeur);
                                $ligne = $this->referentiels->firstWhere('valeur', $valeur);
                            @endphp
                            <tr wire:key="ref-{{ $refType }}-{{ $loop->index }}">
                                <td style="font-weight:600;">{{ $libelle }}</td>
                                <td>
                                    <span class="pastille {{ $parDefaut ? 'pastille-bleu' : 'pastille-vert' }}">
                                        {{ $parDefaut ? 'Livrée' : 'Ajoutée' }}
                                    </span>
                                </td>
                                <td>{{ $ligne && ! $ligne->est_actif ? 'Masquée' : 'Active' }}</td>
                                <td style="text-align:right;">
                                    @if ($ligne)
                                        <button type="button" wire:click="basculerReferentiel({{ $ligne->id }})"
                                            class="bouton bouton-secondaire bouton-petit" style="margin-right:5px;">
                                            {{ $ligne->est_actif ? 'Masquer' : 'Réactiver' }}
                                        </button>
                                        <button type="button" wire:click="supprimerReferentiel({{ $ligne->id }})"
                                            wire:confirm="Supprimer définitivement cette valeur ?"
                                            class="bouton bouton-secondaire bouton-petit">Supprimer</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-carte-section>
    @endif
</div>
