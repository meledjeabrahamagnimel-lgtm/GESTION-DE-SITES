<?php

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Modules\Noyau\Exploitation\Services\GenerateurNumero;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Commun\Concerns\GereLesDonneesLibres;
use Modules\Noyau\Commun\Modeles\NotificationApp;
use Modules\Noyau\Commun\Modeles\Referentiel;
use Modules\Noyau\Commun\Services\Notificateur;
use Livewire\WithPagination;
use function Livewire\Volt\{state, computed, mount, uses, usesPagination};

uses([GereLesDonneesLibres::class, WithPagination::class]);
usesPagination();

state([
    'date' => null,
    'client' => '', 'localisation' => '', 'moyen' => 'RDV',
    'activite' => '', 'passage' => false, 'datePassage' => null,
    'devisApres' => false, 'dateDevis' => null, 'observations' => '',
    'commentaire' => '',
    'message' => null,
    'selection' => [],

    // Édition en ligne d'un brouillon, tant qu'il n'est pas parti chez le responsable.
    'editionId' => null,
    'eClient' => '', 'eLocalisation' => '', 'eMoyen' => 'RDV', 'eActivite' => '',
    'ePassage' => false, 'eDatePassage' => null,
    'eDevisApres' => false, 'eDateDevis' => null, 'eObservations' => '',
]);

// Les filtres sont portés par l'adresse de la page : ils survivent au rechargement,
// au retour arrière et au partage du lien.
state([
    'fNumero' => '', 'fDate' => '', 'fActivite' => '',
    'fClient' => '', 'fStatut' => '',
])->url(except: '');

mount(function () {
    $this->date = now()->toDateString();
    // Un commercial n'est pas cantonné à une activité : il travaille dans une ville et
    // choisit l'activité concernée à chaque prospection. « Mécanique/Sinistre » (valeur
    // possible sur la fiche commercial) n'est pas une activité de prospection valide.
    $this->activite = array_key_first($this->optionsActivite);
});

$commercial = computed(fn () => Commercial::where('user_id', auth()->id())->with('ville')->first());

$optionsActivite = computed(fn () => Referentiel::options(Referentiel::ACTIVITE));

$optionsMoyen = computed(fn () => Referentiel::options(Referentiel::MOYEN_PROSPECTION));

/** Requête filtrée : chaque filtre est appliqué dès la frappe. */
$requete = computed(function () {
    $q = Prospection::where('commercial_id', $this->commercial?->id ?? 0)->with('donneesLibres');

    if ($this->fNumero !== '') {
        $q->where('numero', 'like', '%'.$this->fNumero.'%');
    }
    if ($this->fDate !== '') {
        $q->whereDate('date', $this->fDate);
    }
    if ($this->fActivite !== '') {
        $q->where('activite', $this->fActivite);
    }
    if ($this->fClient !== '') {
        $q->where('client', 'like', '%'.$this->fClient.'%');
    }
    if ($this->fStatut !== '') {
        $q->where('statut_validation', $this->fStatut);
    }

    return $q->latest('date')->latest('id');
});

$lignes = computed(fn () => $this->requete->paginate(20));

$compteurs = computed(function () {
    $base = Prospection::where('commercial_id', $this->commercial?->id ?? 0);

    return [
        'brouillon' => (clone $base)->where('statut_validation', 'Brouillon')->count(),
        'transmise' => (clone $base)->where('statut_validation', 'Transmise')->count(),
        'validee' => (clone $base)->where('statut_validation', 'Validée')->count(),
        'refusee' => (clone $base)->where('statut_validation', 'Refusée')->count(),
    ];
});

/** Identifiants des brouillons de la page courante, pour le « tout sélectionner ». */
$brouillonsAffiches = computed(fn () => $this->lignes->getCollection()
    ->where('statut_validation', 'Brouillon')->pluck('id')->all());

$selectionnes = computed(fn () => collect($this->selection)->filter()->keys()->map(fn ($i) => (int) $i)->all());

// Tout filtre modifié ramène à la première page, sinon on peut se retrouver sur une page vide.
$updatedFNumero = fn () => $this->resetPage();
$updatedFDate = fn () => $this->resetPage();
$updatedFActivite = fn () => $this->resetPage();
$updatedFClient = fn () => $this->resetPage();
$updatedFStatut = fn () => $this->resetPage();

$reinitialiserFiltres = function () {
    $this->reset(['fNumero', 'fDate', 'fActivite', 'fClient', 'fStatut']);
    $this->resetPage();
};

$toutSelectionner = function () {
    foreach ($this->brouillonsAffiches as $id) {
        $this->selection[$id] = true;
    }
};

$toutDeselectionner = function () {
    $this->selection = [];
};

/** Impossible de faire un devis sans passage : cocher l'un coche et date l'autre, à la même date. */
$updatedPassage = function ($valeur) {
    if (! $valeur) {
        $this->devisApres = false;
        $this->dateDevis = null;
        $this->datePassage = null;

        return;
    }

    $this->datePassage ??= $this->date;
};

$updatedDevisApres = function ($valeur) {
    if (! $valeur) {
        $this->dateDevis = null;

        return;
    }

    $this->dateDevis ??= $this->date;
    $this->passage = true;
    $this->datePassage = $this->dateDevis;
};

/** Le devis n'a pas de date propre : elle vaut toujours la date de passage. */
$updatedDateDevis = function ($valeur) {
    if ($this->devisApres) {
        $this->datePassage = $valeur;
    }
};

/*
|--------------------------------------------------------------------------
| Enregistrer une prospection
|--------------------------------------------------------------------------
| Deux gestes, pas un : « Brouillon » met de côté une visite dont on n'est pas
| encore sûr, « Ajouter » la transmet aussitôt au responsable. Obliger à passer
| par le brouillon puis à cocher pour transmettre faisait trois clics là où la
| plupart des saisies n'en demandent qu'un.
*/
$ajouterEnBrouillon = function () {
    $this->enregistrerProspection('Brouillon');
};

$ajouterEtTransmettre = function () {
    $this->enregistrerProspection('Transmise');
};

$enregistrerProspection = function (string $statut) {
    if (! $this->commercial) {
        return;
    }

    $donnees = $this->validate([
        'date' => ['required', 'date'],
        'client' => ['required', 'string', 'max:255'],
        'localisation' => ['nullable', 'string', 'max:255'],
        'moyen' => ['required', 'string', 'max:60'],
        'activite' => ['required', 'string', 'max:60'],
        'datePassage' => ['nullable', 'date'],
        'dateDevis' => ['nullable', 'date'],
        'observations' => ['nullable', 'string'],
    ], [], ['client' => 'clients visités', 'activite' => 'activité']);

    $coherence = Prospection::normaliserPassage(
        (bool) $this->passage, $donnees['datePassage'],
        (bool) $this->devisApres, $donnees['dateDevis'],
    );

    // Le commercial est rattaché à une ville, pas à un lieu : sa prospection est
    // enregistrée sur le premier lieu de sa ville, qui accueille les deux activités.
    // C'est la prospection elle-même qui dit laquelle est concernée.
    $siteId = Site::where('ville_id', $this->commercial->ville_id)->orderBy('id')->value('id');

    Prospection::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $siteId,
        'commercial_id' => $this->commercial->id,
        'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'pro'),
        'date' => $donnees['date'],
        'client' => $donnees['client'],
        'localisation' => $donnees['localisation'] ?: null,
        'moyen' => $donnees['moyen'],
        'activite' => $donnees['activite'],
        ...$coherence,
        'observations' => $donnees['observations'] ?: null,
        'cree_par' => auth()->id(),
        'statut_validation' => $statut,
        'transmise_le' => $statut === 'Transmise' ? now() : null,
    ]);

    if ($statut === 'Transmise') {
        $this->prevenirLeResponsable(1);
    }

    $this->reset(['client', 'localisation', 'observations', 'passage', 'datePassage', 'devisApres', 'dateDevis']);
    $this->resetPage();
    $this->message = $statut === 'Transmise'
        ? 'Prospection transmise à votre responsable.'
        : "Prospection enregistrée en brouillon — modifiable tant qu'elle n'est pas transmise.";
};

/** Le responsable est prévenu tout de suite : sans cela, une transmission peut dormir des jours. */
$prevenirLeResponsable = function (int $nombre) {
    Notificateur::pourPlusieurs(
        destinataires: Notificateur::encadrementDeVille($this->commercial?->ville_id, auth()->user()->entreprise_id),
        titre: $nombre.' prospection(s) à valider',
        corps: auth()->user()->name.' vient de transmettre '.$nombre.' prospection(s).',
        canal: NotificationApp::CANAL_GESTION,
        niveau: NotificationApp::NIVEAU_ALERTE,
        lien: route('saisie-du-jour'),
    );
};

/*
|--------------------------------------------------------------------------
| Modifier un brouillon
|--------------------------------------------------------------------------
| Tant qu'une prospection n'est pas partie, elle appartient encore au commercial :
| il doit pouvoir corriger un nom mal orthographié ou une case oubliée. Une fois
| transmise, elle ne lui appartient plus — c'est le responsable qui la corrige,
| sans quoi une ligne pourrait changer sous les yeux de celui qui l'arbitre.
*/
$modifier = function (int $id) {
    $p = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')->findOrFail($id);

    $this->editionId = $p->id;
    $this->eClient = $p->client;
    $this->eLocalisation = $p->localisation ?? '';
    $this->eMoyen = $p->moyen;
    $this->eActivite = $p->activite;
    $this->ePassage = (bool) $p->passage;
    $this->eDatePassage = $p->date_passage?->toDateString();
    $this->eDevisApres = (bool) $p->devis_apres_passage;
    $this->eDateDevis = $p->date_devis?->toDateString();
    $this->eObservations = $p->observations ?? '';
};

$annulerEdition = function () {
    $this->editionId = null;
    $this->resetValidation();
};

/** Mêmes règles de cohérence qu'à la saisie : un devis suppose un passage. */
$updatedEPassage = function ($valeur) {
    if (! $valeur) {
        $this->eDevisApres = false;
        $this->eDateDevis = null;
        $this->eDatePassage = null;

        return;
    }

    $this->eDatePassage ??= now()->toDateString();
};

$updatedEDevisApres = function ($valeur) {
    if (! $valeur) {
        $this->eDateDevis = null;

        return;
    }

    $this->eDateDevis ??= now()->toDateString();
    $this->ePassage = true;
    $this->eDatePassage = $this->eDateDevis;
};

$updatedEDateDevis = function ($valeur) {
    if ($this->eDevisApres) {
        $this->eDatePassage = $valeur;
    }
};

$enregistrerEdition = function () {
    // Le formulaire n'apparaît qu'une ligne ouverte : un appel sans ligne ne peut venir
    // que d'une requête forgée, on l'ignore sans rien changer.
    $p = $this->editionId
        ? Prospection::where('commercial_id', $this->commercial?->id ?? 0)
            ->where('statut_validation', 'Brouillon')->find($this->editionId)
        : null;

    if (! $p) {
        $this->annulerEdition();

        return;
    }

    $donnees = $this->validate([
        'eClient' => ['required', 'string', 'max:255'],
        'eLocalisation' => ['nullable', 'string', 'max:255'],
        'eMoyen' => ['required', 'string', 'max:60'],
        'eActivite' => ['required', 'string', 'max:60'],
        'eDatePassage' => ['nullable', 'date'],
        'eDateDevis' => ['nullable', 'date'],
        'eObservations' => ['nullable', 'string'],
    ], [], ['eClient' => 'clients visités', 'eActivite' => 'activité']);

    $coherence = Prospection::normaliserPassage(
        (bool) $this->ePassage, $donnees['eDatePassage'],
        (bool) $this->eDevisApres, $donnees['eDateDevis'],
    );

    $p->update([
        'client' => $donnees['eClient'],
        'localisation' => $donnees['eLocalisation'] ?: null,
        'moyen' => $donnees['eMoyen'],
        'activite' => $donnees['eActivite'],
        ...$coherence,
        'observations' => $donnees['eObservations'] ?: null,
    ]);

    $this->editionId = null;
    $this->message = 'Brouillon modifié.';
};

/** Cocher directement dans la liste, sans ouvrir le formulaire pour une seule case. */
$basculerPassage = function (int $id) {
    $p = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')->findOrFail($id);

    $passage = ! $p->passage;

    $p->update([
        'passage' => $passage,
        'date_passage' => $passage ? ($p->date_passage ?? $p->date) : null,
        'devis_apres_passage' => $passage ? $p->devis_apres_passage : false,
        'date_devis' => $passage ? $p->date_devis : null,
    ]);
};

$basculerDevisApres = function (int $id) {
    $p = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')->findOrFail($id);

    $devisApres = ! $p->devis_apres_passage;
    $dateDevis = $devisApres ? ($p->date_devis ?? $p->date_passage ?? $p->date) : null;

    $p->update([
        'devis_apres_passage' => $devisApres,
        'date_devis' => $dateDevis,
        'passage' => $devisApres ? true : $p->passage,
        'date_passage' => $devisApres ? $dateDevis : $p->date_passage,
    ]);
};

$supprimer = function (int $id) {
    Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')->where('id', $id)->delete();

    unset($this->selection[$id]);
    $this->message = 'Brouillon supprimé.';
};

/**
 * Transmettre une seule ligne, depuis sa propre colonne Actions.
 *
 * Le commercial ne « valide » pas : il transmet. La validation appartient au
 * responsable, qui arbitre. C'est pour lui le geste équivalent — la ligne quitte
 * son brouillon et part à l'arbitrage.
 */
$transmettre = function (int $id) {
    $nombre = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')
        ->where('id', $id)
        ->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

    if ($nombre === 0) {
        // Déjà transmise, ou pas à lui : rien à faire, et surtout aucune notification
        // à envoyer pour un geste qui n'a rien changé.
        return;
    }

    $this->prevenirLeResponsable(1);

    unset($this->selection[$id]);
    $this->message = 'Prospection transmise à votre responsable.';
};

$transmettreSelection = function () {
    $ids = $this->selectionnes;

    if (empty($ids)) {
        $this->message = 'Sélectionnez au moins un brouillon à transmettre.';

        return;
    }

    // On ne transmet que ses propres brouillons, jamais une ligne déjà arbitrée.
    $nombre = Prospection::where('commercial_id', $this->commercial?->id ?? 0)
        ->where('statut_validation', 'Brouillon')
        ->whereIn('id', $ids)
        ->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

    // Le responsable est prévenu tout de suite : sans cela, une transmission peut
    // dormir plusieurs jours avant d'être arbitrée.
    if ($nombre > 0) {
        $this->prevenirLeResponsable($nombre);
    }

    $this->selection = [];
    $this->message = $nombre > 0
        ? "$nombre prospection(s) transmise(s) à votre responsable de site."
        : 'Aucun brouillon transmis.';
};

?>

<div>
    @if (! $this->commercial)
        <x-a-venir titre="Aucune fiche commerciale associée"
            description="Votre compte n'est rattaché à aucune fiche commerciale. Contactez votre responsable de site." />
    @else
        <div class="carte" style="margin-bottom:14px; display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:12px;">
                <x-avatar :utilisateur="auth()->user()" :taille="44" />
                <div>
                    <h1 style="font-family:'Barlow Condensed',sans-serif; font-size:23px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin:0;">
                        {{ $this->commercial->nom }}
                    </h1>
                    <p style="color:var(--th-gris,#6B6E76); font-size:12.5px; margin:2px 0 0;">
                        {{ $this->commercial->ville->nom }} · N° {{ $this->commercial->numero }}
                    </p>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" wire:click="toutSelectionner" class="bouton bouton-secondaire bouton-petit">
                    Tout sélectionner ({{ count($this->brouillonsAffiches) }})
                </button>
                <button type="button" wire:click="toutDeselectionner" class="bouton bouton-secondaire bouton-petit">Aucun</button>
                <button type="button" wire:click="transmettreSelection"
                    wire:confirm="Transmettre les prospections sélectionnées à votre responsable ?"
                    class="bouton" @disabled(count($this->selectionnes) === 0)>
                    Transmettre la sélection ({{ count($this->selectionnes) }})
                </button>
            </div>
        </div>

        @if ($message)
            <div class="encart encart-succes">{{ $message }}</div>
        @endif

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; margin-bottom:16px;">
            <x-kpi-card label="Brouillons" :value="$this->compteurs['brouillon']" sub="À transmettre" />
            <x-kpi-card label="Transmises" :value="$this->compteurs['transmise']" sub="En attente du responsable" />
            <x-kpi-card label="Validées" :value="$this->compteurs['validee']" :bon="true" />
            <x-kpi-card label="Refusées" :value="$this->compteurs['refusee']" :accent="$this->compteurs['refusee'] > 0" />
        </div>

        <x-carte-section titre="Nouvelle prospection">
            <div class="bloc-saisie">
                <x-champ label="Date" model="date" type="date" width="140" />
                <x-champ label="Clients visités" model="client" requis="true" />
                <x-champ label="Localisation" model="localisation" width="150" />
                <x-champ label="Moyens" model="moyen" type="select" :options="$this->optionsMoyen" width="140" />
                <x-champ label="Activité" model="activite" type="select" :options="$this->optionsActivite" width="150" />
                <x-champ label="Passage" model="passage" type="checkbox" live="true" />
                @if ($passage && ! $devisApres)
                    <x-champ label="Date de passage" model="datePassage" type="date" width="150" />
                @endif
                <x-champ label="Devis après passage" model="devisApres" type="checkbox" live="true" />
                @if ($devisApres)
                    <x-champ label="Date du devis (= date de passage)" model="dateDevis" type="date" live="true" width="180" />
                @endif
                <x-champ label="Observations" model="observations" />
                {{-- Deux gestes distincts : mettre de côté, ou transmettre tout de suite.
                     La plupart des visites se saisissent une fois rentré, sûr de soi :
                     les faire passer par le brouillon coûtait deux clics de plus. --}}
                <button type="button" wire:click="ajouterEtTransmettre" class="bouton bouton-sombre">+ Ajouter et transmettre</button>
                <button type="button" wire:click="ajouterEnBrouillon" class="bouton bouton-secondaire">Enregistrer en brouillon</button>
            </div>
            {{-- Les listes déroulantes sont fixées par la direction, dans Paramètres →
                 Listes déroulantes. Inviter chaque poste à créer sa propre valeur
                 rendrait les indicateurs incomparables d'une ville à l'autre. --}}
            <p style="font-size:11.5px; color:#9A9DA5; margin:8px 0 0;">
                Une valeur manque dans « Moyens » ou « Activité » ? Signalez-le à votre responsable :
                ces listes sont définies pour toute l'entreprise.
            </p>

            <div style="margin-top:12px;">
                <label class="champ-libelle">Commentaire à l'attention de votre responsable</label>
                <textarea wire:model="commentaire" rows="2" class="champ" style="resize:vertical;"
                    placeholder="Ex. : affluence en baisse (pluies), campagne en cours sur la zone industrielle..."></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Mes prospections">
            {{-- Filtres : appliqués à la frappe et conservés dans l'adresse de la page. --}}
            <div class="bloc-saisie" style="background:#fff; border-style:solid;">
                <x-champ label="N°" model="fNumero" live="true" width="120" placeholder="P-00…" />
                <x-champ label="Date" model="fDate" type="date" live="true" width="150" />
                <x-champ label="Activité" model="fActivite" type="select" live="true" width="150"
                    :options="collect(['' => 'Toutes'])->union($this->optionsActivite)" />
                <x-champ label="Client" model="fClient" live="true" placeholder="Nom du client…" />
                <x-champ label="Statut" model="fStatut" type="select" live="true" width="150"
                    :options="['' => 'Tous', 'Brouillon' => 'Brouillon', 'Transmise' => 'Transmise', 'Validée' => 'Validée', 'Refusée' => 'Refusée']" />
                <button type="button" wire:click="reinitialiserFiltres" class="bouton bouton-secondaire bouton-petit">Réinitialiser</button>
            </div>

            <div class="tableau-conteneur" style="margin-top:12px;">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>✓</th><th>N°</th><th>Date</th><th>Clients visités</th><th>Localisation</th>
                            <th>Moyens</th><th>Activité</th><th>Passage</th><th>Devis après passage</th>
                            <th>Observations</th><th>Informations libres</th><th>Statut</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->lignes as $ligne)
                            @php
                                $pastille = [
                                    'Brouillon' => 'pastille-ambre', 'Transmise' => 'pastille-bleu',
                                    'Validée' => 'pastille-vert', 'Refusée' => 'pastille-rouge',
                                ][$ligne->statut_validation] ?? 'pastille-ambre';
                            @endphp
                            @php $brouillon = $ligne->statut_validation === 'Brouillon'; @endphp

                            @if ($editionId === $ligne->id)
                                {{-- Un brouillon n'est pas encore parti : il appartient toujours
                                     au commercial, qui doit pouvoir le corriger. --}}
                                <tr wire:key="pros-edit-{{ $ligne->id }}" style="background:#FDF2F4;">
                                    <td>—</td>
                                    <td style="font-weight:700;">{{ $ligne->numero }}</td>
                                    <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                    <td><input type="text" wire:model="eClient" class="champ" style="min-width:130px;"></td>
                                    <td><input type="text" wire:model="eLocalisation" class="champ" style="min-width:110px;"></td>
                                    <td>
                                        <select wire:model="eMoyen" class="champ">
                                            @foreach ($this->optionsMoyen as $valeur => $libelle)
                                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select wire:model="eActivite" class="champ">
                                            @foreach ($this->optionsActivite as $valeur => $libelle)
                                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="white-space:normal; min-width:140px;">
                                        <label style="display:flex; align-items:center; gap:5px; font-size:13px;">
                                            <input type="checkbox" wire:model.live="ePassage"> Passage
                                        </label>
                                        @if ($ePassage && ! $eDevisApres)
                                            <input type="date" wire:model="eDatePassage" class="champ" style="margin-top:4px;">
                                        @endif
                                    </td>
                                    <td style="white-space:normal; min-width:170px;">
                                        <label style="display:flex; align-items:center; gap:5px; font-size:13px;">
                                            <input type="checkbox" wire:model.live="eDevisApres"> Devis après passage
                                        </label>
                                        @if ($eDevisApres)
                                            <input type="date" wire:model.live="eDateDevis" class="champ" style="margin-top:4px;">
                                        @endif
                                    </td>
                                    <td style="white-space:normal; min-width:180px;">
                                        <textarea wire:model="eObservations" rows="2" placeholder="Observations"
                                            style="width:100%; box-sizing:border-box; padding:6px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13px;"></textarea>
                                    </td>
                                    <td>—</td>
                                    <td><span class="pastille pastille-ambre">Brouillon</span></td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button type="button" wire:click="enregistrerEdition" class="bouton bouton-petit bouton-vert" style="margin-right:5px;">Enregistrer</button>
                                        <button type="button" wire:click="annulerEdition" class="bouton bouton-petit bouton-secondaire">Annuler</button>
                                    </td>
                                </tr>
                            @else
                            <tr wire:key="pros-{{ $ligne->id }}">
                                <td>
                                    @if ($brouillon)
                                        <input type="checkbox" wire:model.live="selection.{{ $ligne->id }}">
                                    @endif
                                </td>
                                <td style="font-weight:700;">{{ $ligne->numero }}</td>
                                <td>{{ $ligne->date->format('d/m/Y') }}</td>
                                <td>{{ $ligne->client }}</td>
                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->localisation ?? '—' }}</td>
                                <td>{{ $ligne->moyen }}</td>
                                <td>{{ $ligne->activite }}</td>

                                {{-- Sur un brouillon, la case se coche d'un clic. Une fois transmise,
                                     la ligne se fige : elle ne doit pas changer sous les yeux du
                                     responsable en train de l'arbitrer. Dans les deux cas la date
                                     reste affichée sous la case : une coche sans date laisse croire
                                     que la date n'a pas été enregistrée. --}}
                                <td style="text-align:center;">
                                    @if ($brouillon)
                                        <input type="checkbox" wire:click="basculerPassage({{ $ligne->id }})"
                                            @checked($ligne->passage) style="width:16px; height:16px; cursor:pointer;"
                                            title="Êtes-vous passé sur site ?">
                                    @else
                                        {{ $ligne->passage ? '☑' : '☐' }}
                                    @endif
                                    <x-date-sous-case :date="$ligne->date_passage" />
                                </td>
                                <td style="text-align:center;">
                                    @if ($brouillon)
                                        <input type="checkbox" wire:click="basculerDevisApres({{ $ligne->id }})"
                                            @checked($ligne->devis_apres_passage) style="width:16px; height:16px; cursor:pointer;"
                                            title="Un devis doit-il suivre ? Cocher marque aussi le passage.">
                                    @else
                                        {{ $ligne->devis_apres_passage ? '☑' : '☐' }}
                                    @endif
                                    <x-date-sous-case :date="$ligne->date_devis" />
                                </td>

                                <td style="color:var(--th-gris,#6B6E76);">{{ $ligne->observations ?? '—' }}</td>
                                <td style="white-space:normal; min-width:230px;">
                                    <x-saisie-libre :sujet="$ligne"
                                        :ouvert="$libreSujetId === $ligne->id && $libreSujetType === get_class($ligne)" />
                                </td>
                                <td>
                                    <span class="pastille {{ $pastille }}">{{ $ligne->statut_validation }}</span>
                                    @if ($ligne->statut_validation === 'Refusée' && $ligne->motif_refus)
                                        <div style="font-size:11px; color:var(--th-accent,#C8102E); margin-top:3px; white-space:normal;">{{ $ligne->motif_refus }}</div>
                                    @endif
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    @if ($brouillon)
                                        {{-- Transmettre une seule ligne sans passer par la sélection :
                                             cocher puis remonter au bouton du haut faisait trois gestes
                                             pour un brouillon isolé, ce qui est le cas courant. --}}
                                        <button type="button" wire:click="transmettre({{ $ligne->id }})"
                                            wire:confirm="Transmettre cette prospection à votre responsable ?"
                                            class="bouton bouton-petit bouton-vert" style="margin-right:5px;">Transmettre</button>
                                        <button type="button" wire:click="modifier({{ $ligne->id }})"
                                            class="bouton bouton-secondaire bouton-petit" style="margin-right:5px;">Modifier</button>
                                        <button type="button" wire:click="supprimer({{ $ligne->id }})"
                                            wire:confirm="Supprimer ce brouillon ?"
                                            class="bouton bouton-secondaire bouton-petit">Supprimer</button>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @empty
                            <x-table-vide :colspan="13" texte="Aucune prospection ne correspond à ces filtres." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:12px;">{{ $this->lignes->links() }}</div>
        </x-carte-section>
    @endif
</div>
