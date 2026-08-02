<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Site;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount};

state([
    'date' => null,

    'comNom' => '', 'comActivite' => 'Mécanique', 'comObjectif' => '',
    'editionCommercialId' => null, 'editionNom' => '',

    'prosClient' => '', 'prosLocalisation' => '', 'prosMoyen' => 'RDV', 'prosCommercialId' => '',
    'prosActivite' => 'Mécanique', 'prosPassage' => false, 'prosDevisApres' => false, 'prosObs' => '',

    'devisSelection' => [], 'devisBrouillon' => [],
    'factureSelection' => [], 'factureBrouillon' => [],

    'encType' => 'Client', 'encMoyen' => 'Espèces', 'encMontant' => '', 'encClient' => '', 'encTiers' => '',

    'chgDate' => null, 'chgTypeOp' => 'Charges', 'chgLibelle' => 'Achats pièces',
    'chgMoyen' => 'Espèces', 'chgMontant' => '', 'chgTiers' => '', 'chgObs' => '',

    'vehiculesSansFacture' => 0,
    'commentaireProspects' => '', 'commentaireDevis' => '', 'commentaireCA' => '',
    'commentaireTresorerie' => '', 'commentaireCharges' => '',
]);

$site = computed(fn () => Site::where('responsable_id', auth()->id())->first());

mount(function () {
    $this->date = now()->toDateString();
    $this->chgDate = now()->toDateString();
    $this->prosCommercialId = $this->commerciauxSelectables->keys()->first() ?? '';
    $this->chargerSaisieJournaliere();
});

$chargerSaisieJournaliere = function () {
    if (! $this->site) {
        return;
    }

    $saisie = SaisieJournaliere::where('site_id', $this->site->id)->where('date', $this->date)->first();

    $this->vehiculesSansFacture = $saisie?->vehicules_sans_facture ?? 0;
    $this->commentaireProspects = $saisie?->commentaire_prospects ?? '';
    $this->commentaireDevis = $saisie?->commentaire_devis ?? '';
    $this->commentaireCA = $saisie?->commentaire_ca ?? '';
    $this->commentaireTresorerie = $saisie?->commentaire_tresorerie ?? '';
    $this->commentaireCharges = $saisie?->commentaire_charges ?? '';
};

$enregistrerSaisieJournaliere = function () {
    if (! $this->site) {
        return;
    }

    // upsert() atomique : évite la violation de contrainte unique (site_id, date) quand deux
    // champs de la saisie (ex. commentaire + véhicules sans facture) sont modifiés coup sur coup.
    SaisieJournaliere::upsert([[
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->site->id,
        'date' => $this->date,
        'vehicules_sans_facture' => (int) $this->vehiculesSansFacture,
        'commentaire_prospects' => $this->commentaireProspects ?: null,
        'commentaire_devis' => $this->commentaireDevis ?: null,
        'commentaire_ca' => $this->commentaireCA ?: null,
        'commentaire_tresorerie' => $this->commentaireTresorerie ?: null,
        'commentaire_charges' => $this->commentaireCharges ?: null,
        'created_at' => now(),
        'updated_at' => now(),
    ]], ['site_id', 'date'], [
        'vehicules_sans_facture', 'commentaire_prospects', 'commentaire_devis',
        'commentaire_ca', 'commentaire_tresorerie', 'commentaire_charges', 'updated_at',
    ]);
};

$updatedVehiculesSansFacture = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedCommentaireProspects = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedCommentaireDevis = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedCommentaireCA = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedCommentaireTresorerie = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedCommentaireCharges = function () {
    $this->enregistrerSaisieJournaliere();
};

$updatedDate = function () {
    $this->devisSelection = [];
    $this->devisBrouillon = [];
    $this->factureSelection = [];
    $this->factureBrouillon = [];
    $this->chgDate = $this->date;
    $this->chargerSaisieJournaliere();
};

$dateLabel = computed(function () {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $d = \Illuminate\Support\Carbon::parse($this->date);

    return $jours[$d->dayOfWeek].' '.$d->format('d').' '.$mois[$d->month - 1].' '.$d->format('Y');
});

$commerciaux = computed(fn () => Commercial::where('site_id', $this->site?->id ?? 0)->orderBy('numero')->get());

$commerciauxSelectables = computed(fn () => Commercial::where('site_id', $this->site?->id ?? 0)->where('statut', 'Actif')->orderBy('numero')->pluck('nom', 'id'));

$prospectionsDuJour = computed(fn () => Prospection::where('site_id', $this->site?->id ?? 0)->where('date', $this->date)->with('commercial')->orderByDesc('id')->get());

$prospectionsAttenteDevis = computed(fn () => Prospection::where('site_id', $this->site?->id ?? 0)->where('devis_apres_passage', true)->doesntHave('devis')->with('commercial')->orderBy('date')->limit(12)->get());

$devisDuJour = computed(fn () => Devis::where('site_id', $this->site?->id ?? 0)->where('date_emission', $this->date)->with('commercial')->orderByDesc('id')->get());

$devisEnAttente = computed(fn () => Devis::where('site_id', $this->site?->id ?? 0)->where('statut', 'En attente')->with('commercial')->orderBy('date_emission')->get());

$devisValidesNonFactures = computed(fn () => Devis::where('site_id', $this->site?->id ?? 0)->where('statut', 'Validé')->doesntHave('facture')->with('commercial')->orderBy('date_emission')->limit(12)->get());

$facturesDuJour = computed(fn () => Facture::where('site_id', $this->site?->id ?? 0)->where('date', $this->date)->with('commercial')->orderByDesc('id')->get());

$encaissementsDuJour = computed(fn () => Encaissement::where('site_id', $this->site?->id ?? 0)->where('date', $this->date)->orderByDesc('id')->get());

$chargesDuJour = computed(fn () => Charge::where('site_id', $this->site?->id ?? 0)->where('date', $this->date)->orderByDesc('id')->get());

$statistiquesCommerciaux = computed(fn () => Commercial::where('site_id', $this->site?->id ?? 0)->where('est_spontane', false)->with('site')->orderBy('numero')->get());

$clientsConnus = computed(function () {
    return Prospection::where('site_id', $this->site?->id ?? 0)->latest('id')->limit(200)->pluck('client')
        ->merge(Devis::where('site_id', $this->site?->id ?? 0)->latest('id')->limit(200)->pluck('client'))
        ->unique()->values()->take(40);
});

$libellesOperation = computed(function () {
    $charges = ['Achats pièces', 'Salaires & personnel', 'Fonctionnement', 'Autres décaissements'];
    $decaissements = ['Transfert de trésorerie vers un autre site', 'Décaissements DG'];

    return $this->chgTypeOp === 'Charges' ? $charges : $decaissements;
});

$updatedChgTypeOp = function () {
    $this->chgLibelle = $this->chgTypeOp === 'Charges' ? 'Achats pièces' : 'Transfert de trésorerie vers un autre site';
};

// 1. Liste des commerciaux

$ajouterCommercial = function () {
    $donnees = $this->validate([
        'comNom' => ['required', 'string', 'max:255'],
        'comActivite' => ['required', 'in:Mécanique,Carrosserie'],
        'comObjectif' => ['nullable', 'numeric', 'min:0'],
    ], [], ['comNom' => 'nom et prénoms', 'comActivite' => 'activité', 'comObjectif' => 'objectif mensuel']);

    Commercial::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->site->id,
        'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'com'),
        'nom' => $donnees['comNom'],
        'activite' => $donnees['comActivite'],
        'objectif_mensuel' => $donnees['comObjectif'] ?: 0,
        'statut' => 'Actif',
    ]);

    $this->reset(['comNom', 'comObjectif']);
    $this->comActivite = 'Mécanique';
};

$basculerEditionCommercial = function (int $id) {
    if ($this->editionCommercialId === $id) {
        $this->editionCommercialId = null;

        return;
    }

    $this->editionCommercialId = $id;
    $this->editionNom = Commercial::where('site_id', $this->site->id)->findOrFail($id)->nom;
};

$enregistrerEditionCommercial = function (int $id) {
    $donnees = $this->validate(['editionNom' => ['required', 'string', 'max:255']], [], ['editionNom' => 'nom et prénoms']);
    Commercial::where('site_id', $this->site->id)->where('id', $id)->update(['nom' => $donnees['editionNom']]);
    $this->editionCommercialId = null;
};

$basculerStatutCommercial = function (int $id) {
    $commercial = Commercial::where('site_id', $this->site->id)->findOrFail($id);
    $commercial->update(['statut' => $commercial->statut === 'Actif' ? 'Inactif' : 'Actif']);
};

// 2. Prospections

$ajouterProspection = function () {
    $donnees = $this->validate([
        'prosClient' => ['required', 'string', 'max:255'],
        'prosLocalisation' => ['nullable', 'string', 'max:255'],
        'prosMoyen' => ['required', 'in:RDV,Téléphone,Mail'],
        'prosCommercialId' => ['required', Rule::exists('commerciaux', 'id')->where('site_id', $this->site->id)],
        'prosActivite' => ['required', 'in:Mécanique,Carrosserie'],
        'prosObs' => ['nullable', 'string'],
    ], [], ['prosClient' => 'clients visités', 'prosCommercialId' => 'commercial', 'prosActivite' => 'activité']);

    Prospection::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->site->id,
        'commercial_id' => $donnees['prosCommercialId'],
        'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'pro'),
        'date' => $this->date,
        'client' => $donnees['prosClient'],
        'localisation' => $donnees['prosLocalisation'] ?: null,
        'moyen' => $donnees['prosMoyen'],
        'activite' => $donnees['prosActivite'],
        'passage' => $this->prosPassage,
        'devis_apres_passage' => $this->prosDevisApres,
        'observations' => $donnees['prosObs'] ?: null,
        'cree_par' => auth()->id(),
    ]);

    $this->reset(['prosClient', 'prosLocalisation', 'prosObs', 'prosPassage', 'prosDevisApres']);
    $this->prosMoyen = 'RDV';
    $this->prosActivite = 'Mécanique';
};

// 3. Devis

$genererBrouillonsDevis = function () {
    $ids = collect($this->devisSelection)->filter()->keys()->all();
    if (empty($ids)) {
        return;
    }

    $this->devisBrouillon = Prospection::where('site_id', $this->site->id)->whereIn('id', $ids)->get()->map(fn ($p) => [
        'prospection_id' => $p->id,
        'date_reception' => now()->toDateString(),
        'n_fiche_reception' => '',
        'client' => $p->client,
        'date_emission' => now()->toDateString(),
        'commercial_id' => $p->commercial_id,
        'statut' => 'En attente',
        'montant_devis' => '',
        'montant_valide' => '',
        'activite' => $p->activite,
        'observations' => '',
    ])->values()->all();

    $this->devisSelection = [];
};

$annulerBrouillonsDevis = function () {
    $this->devisBrouillon = [];
};

$validerDevis = function () {
    // Défense en profondeur : le brouillon est un tableau lié côté client, on revérifie
    // que chaque commercial/prospection référencé appartient bien au site du Responsable.
    $commerciauxDuSite = Commercial::where('site_id', $this->site->id)->pluck('id');
    $prospectionsDuSite = Prospection::where('site_id', $this->site->id)->pluck('id');

    foreach ($this->devisBrouillon as $ligne) {
        if (empty($ligne['montant_devis']) || ! is_numeric($ligne['montant_devis'])) {
            continue;
        }

        if (! $commerciauxDuSite->contains($ligne['commercial_id']) || ! $prospectionsDuSite->contains($ligne['prospection_id'])) {
            continue;
        }

        Devis::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'site_id' => $this->site->id,
            'commercial_id' => $ligne['commercial_id'],
            'prospection_id' => $ligne['prospection_id'],
            'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'dev'),
            'n_fiche_reception' => $ligne['n_fiche_reception'] ?: null,
            'date_reception' => $ligne['date_reception'] ?: null,
            'date_emission' => $ligne['date_emission'],
            'client' => $ligne['client'],
            'activite' => $ligne['activite'],
            'statut' => $ligne['statut'],
            'montant_devis' => (int) $ligne['montant_devis'],
            'montant_valide' => $ligne['statut'] === 'Validé' && $ligne['montant_valide'] !== '' ? (int) $ligne['montant_valide'] : null,
            'observations' => $ligne['observations'] ?: null,
            'cree_par' => auth()->id(),
        ]);
    }

    $this->devisBrouillon = [];
};

$changerStatutDevis = function (int $id, string $statut) {
    if (! in_array($statut, ['En attente', 'Validé', 'Refusé'], true)) {
        return;
    }

    $devis = Devis::where('site_id', $this->site->id)->findOrFail($id);
    $devis->update([
        'statut' => $statut,
        'montant_valide' => $statut === 'Validé' ? $devis->montant_valide : null,
    ]);
};

$changerMontantValide = function (int $id, $valeur) {
    $devis = Devis::where('site_id', $this->site->id)->findOrFail($id);
    if ($devis->statut !== 'Validé') {
        return;
    }

    $devis->update(['montant_valide' => is_numeric($valeur) ? (int) $valeur : null]);
};

// Chiffre d'affaires facturé

$genererBrouillonsFactures = function () {
    $ids = collect($this->factureSelection)->filter()->keys()->all();
    if (empty($ids)) {
        return;
    }

    $this->factureBrouillon = Devis::where('site_id', $this->site->id)->whereIn('id', $ids)->get()->map(fn ($d) => [
        'devis_id' => $d->id,
        'commercial_id' => $d->commercial_id,
        'client' => $d->client,
        'type' => 'FNE',
        'n_facture' => '',
        'activite' => $d->activite,
        'montant' => $d->montant_valide ?? $d->montant_devis,
        'observations' => '',
    ])->values()->all();

    $this->factureSelection = [];
};

$annulerBrouillonsFactures = function () {
    $this->factureBrouillon = [];
};

$validerFactures = function () {
    // Défense en profondeur : le brouillon est un tableau lié côté client, on revérifie
    // que chaque commercial/devis référencé appartient bien au site du Responsable.
    $commerciauxDuSite = Commercial::where('site_id', $this->site->id)->pluck('id');
    $devisDuSite = Devis::where('site_id', $this->site->id)->pluck('id');

    foreach ($this->factureBrouillon as $ligne) {
        if (empty($ligne['n_facture']) || empty($ligne['montant']) || ! is_numeric($ligne['montant'])) {
            continue;
        }

        if (! $commerciauxDuSite->contains($ligne['commercial_id']) || ! $devisDuSite->contains($ligne['devis_id'])) {
            continue;
        }

        Facture::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'site_id' => $this->site->id,
            'devis_id' => $ligne['devis_id'],
            'commercial_id' => $ligne['commercial_id'],
            'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'fac'),
            'n_facture' => $ligne['n_facture'],
            'date' => $this->date,
            'client' => $ligne['client'],
            'type' => $ligne['type'],
            'activite' => $ligne['activite'],
            'montant' => (int) $ligne['montant'],
            'observations' => $ligne['observations'] ?: null,
            'cree_par' => auth()->id(),
        ]);
    }

    $this->factureBrouillon = [];
};

// Encaissements du jour

$ajouterEncaissement = function () {
    $donnees = $this->validate([
        'encType' => ['required', 'in:Client,Appro,Autres'],
        'encMoyen' => ['required', 'in:Espèces,Mobile Money,Chèque,Virement,Autres'],
        'encMontant' => ['required', 'numeric', 'min:1'],
        'encClient' => ['nullable', 'string', 'max:255'],
        'encTiers' => ['nullable', 'string', 'max:255'],
    ], [], ['encMontant' => 'montant']);

    Encaissement::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->site->id,
        'date' => $this->date,
        'type' => $donnees['encType'],
        'moyen' => $donnees['encMoyen'],
        'montant' => (int) $donnees['encMontant'],
        'client' => $donnees['encClient'] ?: null,
        'autres_tiers' => $donnees['encTiers'] ?: null,
        'cree_par' => auth()->id(),
    ]);

    $this->reset(['encMontant', 'encClient', 'encTiers']);
    $this->encType = 'Client';
    $this->encMoyen = 'Espèces';
};

// Charges & décaissements du jour

$ajouterCharge = function () {
    $donnees = $this->validate([
        'chgDate' => ['required', 'date'],
        'chgTypeOp' => ['required', 'in:Charges,Décaissements'],
        'chgLibelle' => ['required', 'string'],
        'chgMoyen' => ['required', 'in:Espèces,Mobile Money,Chèque,Virement,Autres'],
        'chgMontant' => ['required', 'numeric', 'min:1'],
        'chgTiers' => ['nullable', 'string', 'max:255'],
        'chgObs' => ['nullable', 'string'],
    ], [], ['chgMontant' => 'montant', 'chgLibelle' => "libellé d'opération"]);

    $typeOperation = 'Charges';
    if ($donnees['chgTypeOp'] === 'Décaissements') {
        $typeOperation = str_starts_with($donnees['chgLibelle'], 'Transfert') ? 'Transfert' : 'Décaissement DG';
    }

    Charge::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->site->id,
        'date' => $donnees['chgDate'],
        'type_operation' => $typeOperation,
        'libelle' => $donnees['chgLibelle'],
        'moyen' => $donnees['chgMoyen'],
        'montant' => (int) $donnees['chgMontant'],
        'tiers' => $donnees['chgTiers'] ?: null,
        'observations' => $donnees['chgObs'] ?: null,
        'cree_par' => auth()->id(),
    ]);

    $this->reset(['chgMontant', 'chgTiers', 'chgObs']);
    $this->chgDate = $this->date;
    $this->chgTypeOp = 'Charges';
    $this->chgLibelle = 'Achats pièces';
    $this->chgMoyen = 'Espèces';
};

?>

<div>
    @if (! $this->site)
        <x-a-venir titre="Aucun site assigné" description="Ce compte Responsable n'est rattaché à aucun site pour le moment. Contactez votre Gérant." />
    @else
        <div style="background:#fff; border:1px solid var(--th-ligne,#E2E0D8); border-radius:10px; padding:20px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; justify-content:space-between;">
            <div>
                <h1 style="font-size:19px; font-weight:800; margin:0 0 4px;">Saisie du jour — {{ $this->site->nom }}</h1>
                <p style="color:#6B6E76; font-size:14px; margin:0;">Chaque ligne est enregistrée immédiatement à l'ajout et alimente les tableaux de bord en temps réel.</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px;">
                <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">Date de la journée (calendrier)</label>
                <input type="date" wire:model.live="date"
                    style="padding:8px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14.5px;">
                <span style="font-size:12.5px; color:#6B6E76;">Rattachement automatique : <b>{{ $this->dateLabel }}</b></span>
            </div>
        </div>

        <x-carte-section titre="Flux commercial">
            <x-sous-titre n="1" t="Liste des commerciaux" />
            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse; width:100%; font-size:14px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:7px 10px;">N°</th>
                            <th style="padding:7px 10px;">Nom et Prénoms</th>
                            <th style="padding:7px 10px;">Activité</th>
                            <th style="padding:7px 10px;">Objectif mensuel</th>
                            <th style="padding:7px 10px;">Statut</th>
                            <th style="padding:7px 10px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->commerciaux as $commercial)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8); opacity:{{ $commercial->statut === 'Inactif' ? '.55' : '1' }};" wire:key="commercial-{{ $commercial->id }}">
                                <td style="padding:6px 10px; font-weight:700;">{{ $commercial->numero }}</td>
                                <td style="padding:6px 10px;">
                                    @if ($editionCommercialId === $commercial->id)
                                        <input type="text" wire:model="editionNom" style="padding:5px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13.5px; width:180px;">
                                    @else
                                        <b>{{ $commercial->nom }}</b>
                                    @endif
                                </td>
                                <td style="padding:6px 10px;">{{ $commercial->activite ?? '—' }}</td>
                                <td style="padding:6px 10px;">{{ $commercial->est_spontane ? '—' : ae($commercial->objectif_mensuel) }}</td>
                                <td style="padding:6px 10px;">
                                    @if ($commercial->est_spontane)
                                        <span style="color:#6B6E76;">Actif (fixe)</span>
                                    @else
                                        <button type="button" wire:click="basculerStatutCommercial({{ $commercial->id }})"
                                            style="padding:4px 10px; border-radius:99px; font-size:12.5px; font-weight:700; border:1px solid {{ $commercial->statut === 'Inactif' ? '#C8102E55' : '#0E9F6E55' }}; background:{{ $commercial->statut === 'Inactif' ? '#FDF2F4' : '#EAF9F3' }}; color:{{ $commercial->statut === 'Inactif' ? '#C8102E' : '#0E9F6E' }}; cursor:pointer;">
                                            {{ $commercial->statut }}
                                        </button>
                                    @endif
                                </td>
                                <td style="padding:6px 10px; text-align:right;">
                                    @unless ($commercial->est_spontane)
                                        @if ($editionCommercialId === $commercial->id)
                                            <button type="button" wire:click="enregistrerEditionCommercial({{ $commercial->id }})"
                                                style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:4px 10px; font-size:12px; cursor:pointer;">Terminer</button>
                                        @else
                                            <button type="button" wire:click="basculerEditionCommercial({{ $commercial->id }})"
                                                style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; padding:4px 10px; font-size:12px; cursor:pointer;">Modifier</button>
                                        @endif
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun commercial rattaché à ce site." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                <x-champ label="Nom et prénoms" model="comNom" />
                <x-champ label="Activité" model="comActivite" type="select" :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="150" />
                <x-champ label="Objectif mensuel (FCFA)" model="comObjectif" type="number" width="150" />
                <button type="button" wire:click="ajouterCommercial"
                    style="background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer; white-space:nowrap;">
                    + Ajouter un commercial
                </button>
                <span style="font-size:11.5px; color:#9A9DA5; flex-basis:100%;">
                    « Client spontané » recense les clients venus sans démarchage. Un commercial « Inactif » disparaît des listes déroulantes dès le jour de désactivation, jusqu'à réactivation.
                </span>
            </div>

            <x-sous-titre n="2" t="Prospections" />
            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse; width:100%; font-size:14px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:7px 10px;">N°</th>
                            <th style="padding:7px 10px;">Clients visités</th>
                            <th style="padding:7px 10px;">Localisation</th>
                            <th style="padding:7px 10px;">Moyens</th>
                            <th style="padding:7px 10px;">Commercial</th>
                            <th style="padding:7px 10px;">Activité</th>
                            <th style="padding:7px 10px;">Passage</th>
                            <th style="padding:7px 10px;">Devis après passage</th>
                            <th style="padding:7px 10px;">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->prospectionsDuJour as $p)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:6px 10px; font-weight:700;">{{ $p->numero }}</td>
                                <td style="padding:6px 10px;">{{ $p->client }}</td>
                                <td style="padding:6px 10px; color:#6B6E76;">{{ $p->localisation ?? '—' }}</td>
                                <td style="padding:6px 10px;">{{ $p->moyen }}</td>
                                <td style="padding:6px 10px;">{{ $p->commercial->nom }}</td>
                                <td style="padding:6px 10px;">{{ $p->activite }}</td>
                                <td style="padding:6px 10px;">{{ $p->passage ? '☑' : '☐' }}</td>
                                <td style="padding:6px 10px;">{{ $p->devis_apres_passage ? '☑' : '☐' }}</td>
                                <td style="padding:6px 10px; color:#6B6E76;">{{ $p->observations ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="9" texte="Aucune prospection saisie pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                <x-champ label="Clients visités" model="prosClient" />
                <x-champ label="Localisation" model="prosLocalisation" width="130" />
                <x-champ label="Moyens" model="prosMoyen" type="select" :options="['RDV' => 'RDV', 'Téléphone' => 'Téléphone', 'Mail' => 'Mail']" width="130" />
                <x-champ label="Commercial" model="prosCommercialId" type="select" :options="$this->commerciauxSelectables" width="170" />
                <x-champ label="Activité" model="prosActivite" type="select" :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="140" />
                <x-champ label="Passage" model="prosPassage" type="checkbox" />
                <x-champ label="Devis après passage" model="prosDevisApres" type="checkbox" />
                <x-champ label="Observations" model="prosObs" />
                <button type="button" wire:click="ajouterProspection"
                    style="background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer; white-space:nowrap;">
                    + Ajouter
                </button>
            </div>

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Prospects</label>
                <textarea wire:model.live.debounce.700ms="commentaireProspects" rows="2"
                    placeholder="Ex. : affluence en baisse (pluies, jour férié...), campagne de prospection en cours, client flotte reçu..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>

            <x-sous-titre n="3" t="Devis" />

            @if ($this->prospectionsAttenteDevis->isNotEmpty())
                <div style="width:100%;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Devis à effectuer, issus de la prospection ({{ $this->prospectionsAttenteDevis->count() }}) — cocher puis « Ajouter devis » :
                    </p>
                    <div style="overflow-x:auto;">
                        <table style="border-collapse:collapse; width:100%; font-size:13.5px;">
                            <thead>
                                <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                                    <th style="padding:6px 8px;">✓</th>
                                    <th style="padding:6px 8px;">N° prospection</th>
                                    <th style="padding:6px 8px;">Client</th>
                                    <th style="padding:6px 8px;">Date</th>
                                    <th style="padding:6px 8px;">Commercial</th>
                                    <th style="padding:6px 8px;">Activité</th>
                                    <th style="padding:6px 8px;">Observations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->prospectionsAttenteDevis as $p)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="attente-devis-{{ $p->id }}">
                                        <td style="padding:5px 8px;"><input type="checkbox" wire:model="devisSelection.{{ $p->id }}"></td>
                                        <td style="padding:5px 8px; font-weight:700;">{{ $p->numero }}</td>
                                        <td style="padding:5px 8px;">{{ $p->client }}</td>
                                        <td style="padding:5px 8px;">{{ $p->date->format('d/m/Y') }}</td>
                                        <td style="padding:5px 8px;">{{ $p->commercial->nom }}</td>
                                        <td style="padding:5px 8px;">{{ $p->activite }}</td>
                                        <td style="padding:5px 8px; color:#6B6E76;">{{ $p->observations ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" wire:click="genererBrouillonsDevis"
                        style="margin-top:8px; background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer;">
                        + Ajouter devis
                    </button>
                </div>
            @endif

            @if (! empty($devisBrouillon))
                <div style="width:100%; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                    <p style="font-size:12.5px; font-weight:700; margin:0 0 8px;">Renseigner les informations du tableau devis puis valider :</p>
                    @foreach ($devisBrouillon as $i => $ligne)
                        <div wire:key="devis-brouillon-{{ $i }}" style="display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <x-champ label="Date de réception" model="devisBrouillon.{{ $i }}.date_reception" type="date" width="140" />
                            <x-champ label="N° fiche de réception" model="devisBrouillon.{{ $i }}.n_fiche_reception" width="130" />
                            <x-champ label="Client" model="devisBrouillon.{{ $i }}.client" />
                            <x-champ label="Date d'émission" model="devisBrouillon.{{ $i }}.date_emission" type="date" width="140" />
                            <x-champ label="Commercial" model="devisBrouillon.{{ $i }}.commercial_id" type="select" :options="$this->commerciauxSelectables" width="160" />
                            <x-champ label="Statut du devis" model="devisBrouillon.{{ $i }}.statut" type="select" :options="['En attente' => 'En attente', 'Validé' => 'Validé', 'Refusé' => 'Refusé']" width="130" live="true" />
                            <x-champ label="Montant du devis" model="devisBrouillon.{{ $i }}.montant_devis" type="number" width="130" />
                            @if (($ligne['statut'] ?? '') === 'Validé')
                                <x-champ label="Montant validé" model="devisBrouillon.{{ $i }}.montant_valide" type="number" width="130" />
                            @endif
                            <x-champ label="Activité" model="devisBrouillon.{{ $i }}.activite" type="select" :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="140" />
                            <x-champ label="Observations" model="devisBrouillon.{{ $i }}.observations" />
                        </div>
                    @endforeach
                    <div style="display:flex; gap:8px;">
                        <button type="button" wire:click="validerDevis"
                            style="background:var(--th-accent,#C8102E); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            ✓ Valider — rejoint la liste des devis
                        </button>
                        <button type="button" wire:click="annulerBrouillonsDevis"
                            style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            Annuler
                        </button>
                    </div>
                </div>
            @endif

            <div style="overflow-x:auto; margin-top:14px;">
                <table style="border-collapse:collapse; width:100%; font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:6px 8px;">N°</th>
                            <th style="padding:6px 8px;">Réception</th>
                            <th style="padding:6px 8px;">Fiche</th>
                            <th style="padding:6px 8px;">Client</th>
                            <th style="padding:6px 8px;">Émission</th>
                            <th style="padding:6px 8px;">Commercial</th>
                            <th style="padding:6px 8px;">Statut</th>
                            <th style="padding:6px 8px;">Montant devis</th>
                            <th style="padding:6px 8px;">Montant validé</th>
                            <th style="padding:6px 8px;">Activité</th>
                            <th style="padding:6px 8px;">Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->devisDuJour as $d)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:5px 8px; font-weight:700;">{{ $d->numero }}</td>
                                <td style="padding:5px 8px;">{{ $d->date_reception?->format('d/m/Y') ?? '—' }}</td>
                                <td style="padding:5px 8px;">{{ $d->n_fiche_reception ?? '—' }}</td>
                                <td style="padding:5px 8px;">{{ $d->client }}</td>
                                <td style="padding:5px 8px;">{{ $d->date_emission->format('d/m/Y') }}</td>
                                <td style="padding:5px 8px;">{{ $d->commercial->nom }}</td>
                                <td style="padding:5px 8px; font-weight:700; color:{{ $d->statut === 'Validé' ? '#0E9F6E' : ($d->statut === 'Refusé' ? '#C8102E' : '#D97706') }};">{{ $d->statut }}</td>
                                <td style="padding:5px 8px;">{{ ae($d->montant_devis) }}</td>
                                <td style="padding:5px 8px;">{{ $d->statut === 'Validé' ? ae($d->montant_valide) : '—' }}</td>
                                <td style="padding:5px 8px;">{{ $d->activite }}</td>
                                <td style="padding:5px 8px; color:#6B6E76;">{{ $d->observations ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="11" texte="Aucun devis émis pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->devisEnAttente->isNotEmpty())
                <div style="width:100%; margin-top:14px;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Devis en attente ({{ $this->devisEnAttente->count() }}) — changer le statut :
                    </p>
                    <div style="overflow-x:auto;">
                        <table style="border-collapse:collapse; width:100%; font-size:13.5px;">
                            <thead>
                                <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                                    <th style="padding:6px 8px;">N°</th>
                                    <th style="padding:6px 8px;">Client</th>
                                    <th style="padding:6px 8px;">Émission</th>
                                    <th style="padding:6px 8px;">Montant</th>
                                    <th style="padding:6px 8px;">Statut</th>
                                    <th style="padding:6px 8px;">Montant validé</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->devisEnAttente as $d)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="devis-attente-{{ $d->id }}">
                                        <td style="padding:5px 8px; font-weight:700;">{{ $d->numero }}</td>
                                        <td style="padding:5px 8px;">{{ $d->client }}</td>
                                        <td style="padding:5px 8px;">{{ $d->date_emission->format('d/m/Y') }}</td>
                                        <td style="padding:5px 8px;">{{ ae($d->montant_devis) }}</td>
                                        <td style="padding:5px 8px;">
                                            <select wire:change="changerStatutDevis({{ $d->id }}, $event.target.value)"
                                                style="padding:5px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13px;">
                                                <option {{ $d->statut === 'En attente' ? 'selected' : '' }}>En attente</option>
                                                <option {{ $d->statut === 'Validé' ? 'selected' : '' }}>Validé</option>
                                                <option {{ $d->statut === 'Refusé' ? 'selected' : '' }}>Refusé</option>
                                            </select>
                                        </td>
                                        <td style="padding:5px 8px;">
                                            @if ($d->statut === 'Validé')
                                                <input type="number" value="{{ $d->montant_valide }}" wire:change="changerMontantValide({{ $d->id }}, $event.target.value)"
                                                    style="padding:5px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13px; width:110px;">
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Devis</label>
                <textarea wire:model.live.debounce.700ms="commentaireDevis" rows="2"
                    placeholder="Ex. : baisse des devis liée à..., motifs des refus (prix jugé élevé, délai, pièces indisponibles)..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>

            <x-sous-titre n="4" t="Statistiques des commerciaux" />
            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse; width:100%; font-size:14px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:6px 10px;">N°</th>
                            <th style="padding:6px 10px;">Commercial</th>
                            <th style="padding:6px 10px;">Site</th>
                            <th style="padding:6px 10px;">Activité</th>
                            <th style="padding:6px 10px;">Objectif mensuel</th>
                            <th style="padding:6px 10px;">Objectif journalier (mensuel/30)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->statistiquesCommerciaux as $s)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:6px 10px; font-weight:700;">{{ $s->numero }}</td>
                                <td style="padding:6px 10px;">{{ $s->nom }}</td>
                                <td style="padding:6px 10px;">{{ $s->site->nom }}</td>
                                <td style="padding:6px 10px;">{{ $s->activite }}</td>
                                <td style="padding:6px 10px;">{{ ae($s->objectif_mensuel) }}</td>
                                <td style="padding:6px 10px;">{{ ae($s->objectifJournalier()) }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun commercial actif sur ce site." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-carte-section>

        <x-carte-section titre="Chiffre d'affaires facturé">
            @if ($this->devisValidesNonFactures->isNotEmpty())
                <div style="width:100%;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Listing des devis émis validés, non facturés ({{ $this->devisValidesNonFactures->count() }}) — cocher les devis à facturer :
                    </p>
                    <div style="overflow-x:auto;">
                        <table style="border-collapse:collapse; width:100%; font-size:13.5px;">
                            <thead>
                                <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                                    <th style="padding:6px 8px;">✓</th>
                                    <th style="padding:6px 8px;">N° devis</th>
                                    <th style="padding:6px 8px;">Client</th>
                                    <th style="padding:6px 8px;">Montant validé</th>
                                    <th style="padding:6px 8px;">Commercial</th>
                                    <th style="padding:6px 8px;">Activité</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->devisValidesNonFactures as $d)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="valide-non-facture-{{ $d->id }}">
                                        <td style="padding:5px 8px;"><input type="checkbox" wire:model="factureSelection.{{ $d->id }}"></td>
                                        <td style="padding:5px 8px; font-weight:700;">{{ $d->numero }}</td>
                                        <td style="padding:5px 8px;">{{ $d->client }}</td>
                                        <td style="padding:5px 8px;">{{ ae($d->montant_valide ?? $d->montant_devis) }}</td>
                                        <td style="padding:5px 8px;">{{ $d->commercial->nom }}</td>
                                        <td style="padding:5px 8px;">{{ $d->activite }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" wire:click="genererBrouillonsFactures"
                        style="margin-top:8px; background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer;">
                        + Facturer la sélection
                    </button>
                </div>
            @endif

            @if (! empty($factureBrouillon))
                <div style="width:100%; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                    <p style="font-size:12.5px; font-weight:700; margin:0 0 8px;">Tableau de facturation — compléter puis valider :</p>
                    @foreach ($factureBrouillon as $i => $ligne)
                        <div wire:key="facture-brouillon-{{ $i }}" style="display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                            <x-champ label="Commercial" model="factureBrouillon.{{ $i }}.commercial_id" type="select" :options="$this->commerciauxSelectables" width="160" />
                            <x-champ label="Clients" model="factureBrouillon.{{ $i }}.client" />
                            <x-champ label="Type" model="factureBrouillon.{{ $i }}.type" type="select" :options="['FNE' => 'FNE', 'HT' => 'HT']" width="90" />
                            <x-champ label="N° de facture" model="factureBrouillon.{{ $i }}.n_facture" width="130" />
                            <x-champ label="Activité" model="factureBrouillon.{{ $i }}.activite" type="select" :options="['Mécanique' => 'Mécanique', 'Carrosserie' => 'Carrosserie']" width="140" />
                            <x-champ label="Montant de la facture" model="factureBrouillon.{{ $i }}.montant" type="number" width="140" />
                            <x-champ label="Observations" model="factureBrouillon.{{ $i }}.observations" />
                        </div>
                    @endforeach
                    <div style="display:flex; gap:8px;">
                        <button type="button" wire:click="validerFactures"
                            style="background:var(--th-accent,#C8102E); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            ✓ Valider la facturation
                        </button>
                        <button type="button" wire:click="annulerBrouillonsFactures"
                            style="background:transparent; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer;">
                            Annuler
                        </button>
                    </div>
                </div>
            @endif

            <div style="overflow-x:auto; margin-top:14px;">
                <table style="border-collapse:collapse; width:100%; font-size:13.5px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:6px 8px;">N°</th>
                            <th style="padding:6px 8px;">Commercial</th>
                            <th style="padding:6px 8px;">Clients</th>
                            <th style="padding:6px 8px;">Type</th>
                            <th style="padding:6px 8px;">N° de facture</th>
                            <th style="padding:6px 8px;">Activité</th>
                            <th style="padding:6px 8px;">Montant de la facture</th>
                            <th style="padding:6px 8px;">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->facturesDuJour as $f)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:5px 8px; font-weight:700;">{{ $f->numero }}</td>
                                <td style="padding:5px 8px;">{{ $f->commercial->nom }}</td>
                                <td style="padding:5px 8px;">{{ $f->client }}</td>
                                <td style="padding:5px 8px;">{{ $f->type }}</td>
                                <td style="padding:5px 8px;">{{ $f->n_facture }}</td>
                                <td style="padding:5px 8px;">{{ $f->activite }}</td>
                                <td style="padding:5px 8px; font-weight:700;">{{ ae($f->montant) }}</td>
                                <td style="padding:5px 8px; color:#6B6E76;">{{ $f->observations ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="8" texte="Aucune facture émise pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Chiffre d'affaires</label>
                <textarea wire:model.live.debounce.700ms="commentaireCA" rows="2"
                    placeholder="Ex. : facturation retardée (dossier assurance en attente d'expertise), gros dossier livré et facturé, avoir émis..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Encaissements du jour">
            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse; width:100%; font-size:14px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:6px 10px;">Type d'encaissement</th>
                            <th style="padding:6px 10px;">Moyens</th>
                            <th style="padding:6px 10px;">Montant</th>
                            <th style="padding:6px 10px;">Clients</th>
                            <th style="padding:6px 10px;">Autres tiers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->encaissementsDuJour as $e)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:6px 10px;">{{ $e->type }}</td>
                                <td style="padding:6px 10px;">{{ $e->moyen }}</td>
                                <td style="padding:6px 10px; font-weight:700; color:#0E9F6E;">{{ ae($e->montant) }}</td>
                                <td style="padding:6px 10px;">{{ $e->client ?? '—' }}</td>
                                <td style="padding:6px 10px;">{{ $e->autres_tiers ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="5" texte="Aucun encaissement saisi pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                <x-champ label="Type d'encaissement" model="encType" type="select" :options="['Client' => 'Client', 'Appro' => 'Appro', 'Autres' => 'Autres']" width="140" />
                <x-champ label="Moyens" model="encMoyen" type="select" :options="['Espèces' => 'Espèces', 'Mobile Money' => 'Mobile Money', 'Chèque' => 'Chèque', 'Virement' => 'Virement', 'Autres' => 'Autres']" width="150" />
                <x-champ label="Montant (FCFA)" model="encMontant" type="number" width="140" />
                <div style="display:flex; flex-direction:column; gap:4px; flex:1; min-width:160px;">
                    <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">Clients</label>
                    <input type="text" wire:model="encClient" list="clients-connus"
                        style="padding:8px 10px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px;">
                    <datalist id="clients-connus">
                        @foreach ($this->clientsConnus as $client)
                            <option value="{{ $client }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <x-champ label="Autres tiers à préciser" model="encTiers" />
                <button type="button" wire:click="ajouterEncaissement"
                    style="background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer; white-space:nowrap;">
                    + Ajouter
                </button>
            </div>

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Trésorerie</label>
                <textarea wire:model.live.debounce.700ms="commentaireTresorerie" rows="2"
                    placeholder="Ex. : trésorerie insuffisante pour..., appro reçu de la DG, promesse de règlement client X..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Charges & décaissements du jour">
            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse; width:100%; font-size:14px;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--th-ligne,#E2E0D8); color:#6B6E76;">
                            <th style="padding:6px 10px;">Date</th>
                            <th style="padding:6px 10px;">Type d'opération</th>
                            <th style="padding:6px 10px;">Libellé d'opération</th>
                            <th style="padding:6px 10px;">Moyens</th>
                            <th style="padding:6px 10px;">Montant</th>
                            <th style="padding:6px 10px;">Tiers</th>
                            <th style="padding:6px 10px;">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->chargesDuJour as $c)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="padding:6px 10px;">{{ $c->date->format('d/m/Y') }}</td>
                                <td style="padding:6px 10px;">{{ $c->type_operation === 'Charges' ? 'Charges' : 'Décaissements' }}</td>
                                <td style="padding:6px 10px;">{{ $c->libelle }}</td>
                                <td style="padding:6px 10px;">{{ $c->moyen }}</td>
                                <td style="padding:6px 10px; font-weight:700; color:#C8102E;">{{ ae($c->montant) }}</td>
                                <td style="padding:6px 10px;">{{ $c->tiers ?? '—' }}</td>
                                <td style="padding:6px 10px; color:#6B6E76;">{{ $c->observations ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="7" texte="Aucune charge ni décaissement saisi pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:end; background:#FAF9F5; border:1px dashed var(--th-ligne,#E2E0D8); border-radius:8px; padding:12px; margin-top:12px;">
                <x-champ label="Date" model="chgDate" type="date" width="140" />
                <x-champ label="Type d'opération" model="chgTypeOp" type="select" :options="['Charges' => 'Charges', 'Décaissements' => 'Décaissements']" width="150" live="true" />
                <x-champ label="Libellé d'opération" model="chgLibelle" type="select" :options="array_combine($this->libellesOperation, $this->libellesOperation)" width="220" />
                <x-champ label="Moyens" model="chgMoyen" type="select" :options="['Espèces' => 'Espèces', 'Mobile Money' => 'Mobile Money', 'Chèque' => 'Chèque', 'Virement' => 'Virement', 'Autres' => 'Autres']" width="150" />
                <x-champ label="Montant (FCFA)" model="chgMontant" type="number" width="140" />
                <x-champ label="Tiers" model="chgTiers" width="160" />
                <x-champ label="Observations" model="chgObs" />
                <button type="button" wire:click="ajouterCharge"
                    style="background:var(--th-ink,#191B20); color:#fff; border:0; border-radius:8px; padding:9px 16px; font-weight:700; font-size:13.5px; cursor:pointer; white-space:nowrap;">
                    + Ajouter
                </button>
            </div>

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Charges</label>
                <textarea wire:model.live.debounce.700ms="commentaireCharges" rows="2"
                    placeholder="Ex. : achat exceptionnel de matériel, hausse du prix des pièces, transfert vers le site de..., dépense DG (préciser l'objet)..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Atelier">
            <x-champ label="Véhicules restitués SANS facture (nb)" model="vehiculesSansFacture" type="number" width="260" live="true" />
            @if ((int) $vehiculesSansFacture > 0)
                <div style="margin-top:10px; background:#FDF2F4; border:1px solid #C8102E; color:#C8102E; border-radius:8px; padding:10px 14px; font-size:13.5px; display:flex; gap:8px; align-items:center;">
                    ⚠ Anomalie critique : signalement immédiat à la Direction.
                </div>
            @endif
            <p style="font-size:12px; color:#9A9DA5; margin-top:14px;">
                Chaque ligne (prospection, devis, facture, encaissement, opération) est enregistrée immédiatement à l'ajout et alimente les onglets en temps réel.
            </p>
        </x-carte-section>
    @endif
</div>
