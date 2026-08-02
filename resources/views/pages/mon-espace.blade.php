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
        @foreach (['profil' => 'Mon profil', 'entreprise' => 'Mon entreprise', 'listes' => 'Listes déroulantes'] as $cle => $libelle)
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
