<?php

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Shared\Concerns\GereLesDonneesLibres;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Shared\Models\Referentiel;
use App\Domain\Shared\Services\Notificateur;
use App\Domain\Tenants\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, computed, mount, uses};

uses([GereLesDonneesLibres::class]);

state([
    'date' => null,
    'siteActifId' => null,

    'prosClient' => '', 'prosLocalisation' => '', 'prosMoyen' => 'RDV', 'prosCommercialId' => '',
    'prosActivite' => 'Mécanique', 'prosPassage' => false, 'prosDatePassage' => null,
    'prosDevisApres' => false, 'prosDateDevis' => null, 'prosObs' => '',

    'editionProspectionId' => null,
    'editionProsClient' => '', 'editionProsLocalisation' => '', 'editionProsMoyen' => 'RDV',
    'editionProsCommercialId' => '', 'editionProsActivite' => 'Mécanique',
    'editionProsPassage' => false, 'editionProsDatePassage' => null,
    'editionProsDevisApres' => false, 'editionProsDateDevis' => null,
    'editionProsObs' => '',

    'devisSelection' => [], 'devisBrouillon' => [],
    'factureSelection' => [], 'factureBrouillon' => [],

    'encType' => 'Client', 'encFactureId' => '', 'encMoyen' => 'Espèces', 'encMontant' => '', 'encClient' => '', 'encTiers' => '',

    'chgDate' => null, 'chgTypeOp' => 'Charges', 'chgLibelle' => 'Achats pièces',
    'chgMoyen' => 'Espèces', 'chgMontant' => '', 'chgTiers' => '', 'chgObs' => '',

    'motifRefus' => [],

    'vehiculesSansFacture' => 0,
    'commentaireProspects' => '', 'commentaireDevis' => '', 'commentaireCA' => '',
    'commentaireTresorerie' => '', 'commentaireCharges' => '',

    'pageProsATraiter' => 1, 'pageProsDuJour' => 1, 'pageAttenteDevis' => 1,
    'pageDevisDuJour' => 1, 'pageDevisEnAttente' => 1, 'pageValidesNonFactures' => 1,
    'pageFacturesDuJour' => 1, 'pageEncaissementsDuJour' => 1, 'pageChargesDuJour' => 1,
]);

/** Sites dont le responsable a la charge, directement ou via sa ville — un ou deux. */
$mesSites = computed(fn () => Site::visiblesPour(auth()->user()));

/**
 * Sites réellement pris en compte par l'écran : un seul (celui choisi), ou les deux
 * du périmètre si « Les deux » est sélectionné — auquel cas les tableaux consolident
 * les deux sites et l'Activité redevient libre dans chaque saisie.
 */
$sitesActifs = computed(function () {
    if ($this->siteActifId === 'les-deux') {
        return $this->mesSites;
    }

    $s = $this->siteActifId ? $this->mesSites->firstWhere('id', (int) $this->siteActifId) : $this->mesSites->first();

    return $s ? collect([$s]) : collect();
});

$siteIdsActifs = computed(fn () => $this->sitesActifs->pluck('id')->all());

/** Le site précis choisi, ou null en mode « Les deux » — sert à verrouiller l'Activité dans les saisies. */
$siteUnique = computed(fn () => $this->sitesActifs->count() === 1 ? $this->sitesActifs->first() : null);

/** Activité imposée dans chaque saisie quand un site précis est choisi ; libre en mode « Les deux ». */
$activiteVerrouillee = computed(fn () => $this->siteUnique?->activite);

/**
 * Site d'ancrage pour les sous-systèmes sans notion d'activité propre (charges,
 * décaissements, encaissements, commentaires du jour) : le site précis choisi, ou le
 * premier du périmètre en mode « Les deux » — ces saisies restent rattachées à un
 * site physique unique, la consolidation ne concernant que la lecture.
 */
$site = computed(fn () => $this->sitesActifs->first());

/** Vrai si l'exercice est clos pour la ville de ce site à la date sélectionnée : toute saisie y est bloquée. */
$exerciceFerme = computed(fn () => $this->site
    && \App\Domain\Tenants\Models\Exercice::estFerme(auth()->user()->entreprise_id, $this->site->ville_id, $this->date));

/** Résout le site physique d'une nouvelle ligne prospection/devis/facture à partir de son activité. */
$resoudreSiteId = function (string $activite) {
    return $this->mesSites->firstWhere('activite', $activite)?->id;
};

mount(function () {
    $this->date = now()->toDateString();
    $this->chgDate = now()->toDateString();
    $this->siteActifId = $this->mesSites->first()?->id;
    $this->prosActivite = $this->activiteVerrouillee ?? $this->prosActivite;
    $this->prosCommercialId = $this->commerciauxSelectables->keys()->first() ?? '';
    $this->chargerSaisieJournaliere();
});

$reinitialiserPagination = function () {
    $this->pageProsATraiter = $this->pageProsDuJour = $this->pageAttenteDevis = 1;
    $this->pageDevisDuJour = $this->pageDevisEnAttente = $this->pageValidesNonFactures = 1;
    $this->pageFacturesDuJour = $this->pageEncaissementsDuJour = $this->pageChargesDuJour = 1;
};

$updatedSiteActifId = function () {
    $this->devisSelection = [];
    $this->devisBrouillon = [];
    $this->factureSelection = [];
    $this->factureBrouillon = [];
    $this->prosCommercialId = $this->commerciauxSelectables->keys()->first() ?? '';
    if ($this->activiteVerrouillee) {
        $this->prosActivite = $this->activiteVerrouillee;
    }
    $this->reinitialiserPagination();
    $this->chargerSaisieJournaliere();
};

$chargerSaisieJournaliere = function () {
    if (! $this->site) {
        return;
    }

    $saisie = SaisieJournaliere::where('site_id', $this->site->id)->whereDate('date', $this->date)->first();

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
    $this->reinitialiserPagination();
    $this->chargerSaisieJournaliere();
};

$dateLabel = computed(function () {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $d = \Illuminate\Support\Carbon::parse($this->date);

    return $jours[$d->dayOfWeek].' '.$d->format('d').' '.$mois[$d->month - 1].' '.$d->format('Y');
});

$commerciauxSelectables = computed(fn () => Commercial::whereIn('site_id', $this->siteIdsActifs)->where('statut', 'Actif')->orderBy('numero')->pluck('nom', 'id'));

$prospectionsATraiter = computed(fn () => Prospection::whereIn('site_id', $this->siteIdsActifs)
    ->aTraiter()->with('commercial')->orderBy('date')->get());

/**
 * Prévient les commerciaux concernés du sort réservé à leurs transmissions.
 * Appelé AVANT la mise à jour, tant que les lignes portent encore le statut « Transmise ».
 *
 * @param  array<int, int>  $ids
 */
$avertirDuRetour = function (array $ids, string $statut, ?string $motif = null) {
    if ($ids === []) {
        return;
    }

    $destinataires = Prospection::whereIn('site_id', $this->siteIdsActifs)
        ->whereIn('id', $ids)
        ->with('commercial:id,user_id')
        ->get()
        ->pluck('commercial.user_id')
        ->filter()
        ->unique();

    if ($destinataires->isEmpty()) {
        return;
    }

    $refusee = $statut === 'Refusée';

    Notificateur::pourPlusieurs(
        destinataires: $destinataires,
        titre: $refusee ? 'Prospection refusée' : 'Prospection validée',
        corps: $refusee
            ? 'Votre responsable a refusé une prospection.'.($motif ? ' Motif : '.$motif : '')
            : 'Votre responsable a validé '.count($ids).' prospection(s).',
        canal: NotificationApp::CANAL_GESTION,
        niveau: $refusee ? NotificationApp::NIVEAU_CRITIQUE : NotificationApp::NIVEAU_SUCCES,
        lien: route('mes-prospections'),
    );
};

$validerProspection = function (int $id) {
    $this->avertirDuRetour([$id], 'Validée');

    Prospection::whereIn('site_id', $this->siteIdsActifs)->aTraiter()->where('id', $id)
        ->update(['statut_validation' => 'Validée', 'motif_refus' => null]);
    unset($this->prospectionsATraiter, $this->prospectionsDuJour);
};

$refuserProspection = function (int $id) {
    $motif = $this->motifRefus[$id] ?? null;
    $this->avertirDuRetour([$id], 'Refusée', $motif);

    Prospection::whereIn('site_id', $this->siteIdsActifs)->aTraiter()->where('id', $id)
        ->update(['statut_validation' => 'Refusée', 'motif_refus' => $motif]);
    unset($this->prospectionsATraiter, $this->prospectionsDuJour);
};

$validerToutesProspections = function () {
    $ids = Prospection::whereIn('site_id', $this->siteIdsActifs)->aTraiter()->pluck('id')->all();
    $this->avertirDuRetour($ids, 'Validée');

    Prospection::whereIn('site_id', $this->siteIdsActifs)->aTraiter()
        ->update(['statut_validation' => 'Validée', 'motif_refus' => null]);
    unset($this->prospectionsATraiter, $this->prospectionsDuJour);
};

$prospectionsDuJour = computed(fn () => Prospection::whereIn('site_id', $this->siteIdsActifs)->visibles()->whereDate('date', $this->date)->with(['commercial', 'donneesLibres'])->orderByDesc('id')->get());

$prospectionsAttenteDevis = computed(fn () => Prospection::whereIn('site_id', $this->siteIdsActifs)->where('statut_validation', 'Validée')->where('devis_apres_passage', true)->doesntHave('devis')->with('commercial')->orderBy('date')->get());

$devisDuJour = computed(fn () => Devis::whereIn('site_id', $this->siteIdsActifs)->whereDate('date_emission', $this->date)->with('commercial')->orderByDesc('id')->get());

$devisEnAttente = computed(fn () => Devis::whereIn('site_id', $this->siteIdsActifs)->where('statut', 'En attente')->with('commercial')->orderBy('date_emission')->get());

$devisValidesNonFactures = computed(fn () => Devis::whereIn('site_id', $this->siteIdsActifs)->where('statut', 'Validé')->doesntHave('facture')->with('commercial')->orderBy('date_emission')->get());

$facturesDuJour = computed(fn () => Facture::whereIn('site_id', $this->siteIdsActifs)->whereDate('date', $this->date)->with(['commercial', 'donneesLibres'])->orderByDesc('id')->get());

$encaissementsDuJour = computed(fn () => Encaissement::whereIn('site_id', $this->siteIdsActifs)->whereDate('date', $this->date)->with('donneesLibres')->orderByDesc('id')->get());

/** Factures encore soldables sur les sites actifs : c'est la garde-fou anti-double-saisie avec le caissier. */
$facturesAvecReste = computed(fn () => Facture::whereIn('site_id', $this->siteIdsActifs)->avecResteAEncaisser()->orderByDesc('date')->get());

$chargesDuJour = computed(fn () => Charge::whereIn('site_id', $this->siteIdsActifs)->whereDate('date', $this->date)->with('donneesLibres')->orderByDesc('id')->get());

$clientsConnus = computed(function () {
    return Prospection::whereIn('site_id', $this->siteIdsActifs)->latest('id')->limit(200)->pluck('client')
        ->merge(Devis::whereIn('site_id', $this->siteIdsActifs)->latest('id')->limit(200)->pluck('client'))
        ->unique()->values()->take(40);
});

$optionsActivite = computed(fn () => Referentiel::options(Referentiel::ACTIVITE));

$optionsMoyenProspection = computed(fn () => Referentiel::options(Referentiel::MOYEN_PROSPECTION));

$optionsMoyenPaiement = computed(fn () => Referentiel::options(Referentiel::MOYEN_PAIEMENT));

$optionsTypeEncaissement = computed(fn () => Referentiel::options(Referentiel::TYPE_ENCAISSEMENT));

$libellesOperation = computed(function () {
    // Les libellés de charge sont enrichissables par l'entreprise ; les deux libellés de
    // décaissement restent figés car ils pilotent le calcul du résultat.
    $decaissements = ['Transfert de trésorerie vers un autre site', 'Décaissements DG'];

    return $this->chgTypeOp === 'Charges'
        ? array_values(Referentiel::options(Referentiel::LIBELLE_CHARGE))
        : $decaissements;
});

$updatedChgTypeOp = function () {
    $this->chgLibelle = $this->chgTypeOp === 'Charges' ? 'Achats pièces' : 'Transfert de trésorerie vers un autre site';
};

// 1. Prospections

/** Impossible de faire un devis sans passage : cocher l'un coche et date l'autre, à la même date. */
$updatedProsPassage = function ($valeur) {
    if (! $valeur) {
        $this->prosDevisApres = false;
        $this->prosDateDevis = null;
        $this->prosDatePassage = null;

        return;
    }

    $this->prosDatePassage ??= $this->date;
};

$updatedProsDevisApres = function ($valeur) {
    if (! $valeur) {
        $this->prosDateDevis = null;

        return;
    }

    $this->prosDateDevis ??= $this->date;
    $this->prosPassage = true;
    $this->prosDatePassage = $this->prosDateDevis;
};

/** Le devis n'a pas de date propre : elle vaut toujours la date de passage. */
$updatedProsDateDevis = function ($valeur) {
    if ($this->prosDevisApres) {
        $this->prosDatePassage = $valeur;
    }
};

$ajouterProspection = function () {
    if ($this->exerciceFerme) {
        $this->addError('exercice', "L'exercice est clos pour ce site à cette date — saisie impossible.");

        return;
    }

    $donnees = $this->validate([
        'prosClient' => ['required', 'string', 'max:255'],
        'prosLocalisation' => ['nullable', 'string', 'max:255'],
        'prosMoyen' => ['required', Rule::in(array_keys($this->optionsMoyenProspection))],
        'prosCommercialId' => ['required', Rule::exists('commerciaux', 'id')->whereIn('site_id', $this->siteIdsActifs)],
        'prosActivite' => ['required', Rule::in(array_keys($this->optionsActivite))],
        'prosDatePassage' => ['nullable', 'date'],
        'prosDateDevis' => ['nullable', 'date'],
        'prosObs' => ['nullable', 'string'],
    ], [], ['prosClient' => 'clients visités', 'prosCommercialId' => 'commercial', 'prosActivite' => 'activité']);

    $coherence = Prospection::normaliserPassage(
        (bool) $this->prosPassage, $donnees['prosDatePassage'],
        (bool) $this->prosDevisApres, $donnees['prosDateDevis'],
    );

    Prospection::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $this->resoudreSiteId($donnees['prosActivite']) ?? $this->site->id,
        'commercial_id' => $donnees['prosCommercialId'],
        'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'pro'),
        'date' => $this->date,
        'client' => $donnees['prosClient'],
        'localisation' => $donnees['prosLocalisation'] ?: null,
        'moyen' => $donnees['prosMoyen'],
        'activite' => $donnees['prosActivite'],
        ...$coherence,
        'observations' => $donnees['prosObs'] ?: null,
        'cree_par' => auth()->id(),
    ]);

    $this->reset(['prosClient', 'prosLocalisation', 'prosObs', 'prosPassage', 'prosDatePassage', 'prosDevisApres', 'prosDateDevis']);
    $this->prosMoyen = 'RDV';
    $this->prosActivite = $this->activiteVerrouillee ?? 'Mécanique';
};

$modifierProspection = function (int $id) {
    $p = Prospection::whereIn('site_id', $this->siteIdsActifs)->findOrFail($id);

    $this->editionProspectionId = $p->id;
    $this->editionProsClient = $p->client;
    $this->editionProsLocalisation = $p->localisation ?? '';
    $this->editionProsMoyen = $p->moyen;
    $this->editionProsCommercialId = $p->commercial_id;
    $this->editionProsActivite = $p->activite;
    $this->editionProsPassage = $p->passage;
    $this->editionProsDatePassage = $p->date_passage?->toDateString();
    $this->editionProsDevisApres = $p->devis_apres_passage;
    $this->editionProsDateDevis = $p->date_devis?->toDateString();
    $this->editionProsObs = $p->observations ?? '';
};

$annulerEditionProspection = function () {
    $this->editionProspectionId = null;
};

/** Miroir des règles de cohérence de la création, appliquées cette fois à l'édition en ligne. */
$updatedEditionProsPassage = function ($valeur) {
    if (! $valeur) {
        $this->editionProsDevisApres = false;
        $this->editionProsDateDevis = null;
        $this->editionProsDatePassage = null;

        return;
    }

    $this->editionProsDatePassage ??= $this->date;
};

$updatedEditionProsDevisApres = function ($valeur) {
    if (! $valeur) {
        $this->editionProsDateDevis = null;

        return;
    }

    $this->editionProsDateDevis ??= $this->date;
    $this->editionProsPassage = true;
    $this->editionProsDatePassage = $this->editionProsDateDevis;
};

$updatedEditionProsDateDevis = function ($valeur) {
    if ($this->editionProsDevisApres) {
        $this->editionProsDatePassage = $valeur;
    }
};

$enregistrerEditionProspection = function () {
    $donnees = $this->validate([
        'editionProsClient' => ['required', 'string', 'max:255'],
        'editionProsLocalisation' => ['nullable', 'string', 'max:255'],
        'editionProsMoyen' => ['required', Rule::in(array_keys($this->optionsMoyenProspection))],
        'editionProsCommercialId' => ['required', Rule::exists('commerciaux', 'id')->whereIn('site_id', $this->siteIdsActifs)],
        'editionProsActivite' => ['required', Rule::in(array_keys($this->optionsActivite))],
        'editionProsDatePassage' => ['nullable', 'date'],
        'editionProsDateDevis' => ['nullable', 'date'],
        'editionProsObs' => ['nullable', 'string'],
    ], [], ['editionProsClient' => 'clients visités', 'editionProsCommercialId' => 'commercial', 'editionProsActivite' => 'activité']);

    $coherence = Prospection::normaliserPassage(
        (bool) $this->editionProsPassage, $donnees['editionProsDatePassage'],
        (bool) $this->editionProsDevisApres, $donnees['editionProsDateDevis'],
    );

    Prospection::whereIn('site_id', $this->siteIdsActifs)->findOrFail($this->editionProspectionId)->update([
        'client' => $donnees['editionProsClient'],
        'localisation' => $donnees['editionProsLocalisation'] ?: null,
        'moyen' => $donnees['editionProsMoyen'],
        'commercial_id' => $donnees['editionProsCommercialId'],
        'activite' => $donnees['editionProsActivite'],
        ...$coherence,
        'observations' => $donnees['editionProsObs'] ?: null,
    ]);

    $this->editionProspectionId = null;
    unset($this->prospectionsDuJour);
};

// 2. Devis

$genererBrouillonsDevis = function () {
    $ids = collect($this->devisSelection)->filter()->keys()->all();
    if (empty($ids)) {
        return;
    }

    $this->devisBrouillon = Prospection::whereIn('site_id', $this->siteIdsActifs)->whereIn('id', $ids)->get()->map(fn ($p) => [
        'prospection_id' => $p->id,
        'prospection_numero' => $p->numero,
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
    if ($this->exerciceFerme) {
        $this->addError('exercice', "L'exercice est clos pour ce site à cette date — saisie impossible.");

        return;
    }

    // Défense en profondeur : le brouillon est un tableau lié côté client, on revérifie
    // que chaque commercial/prospection référencé appartient bien à un site du Responsable.
    $commerciauxDuSite = Commercial::whereIn('site_id', $this->siteIdsActifs)->pluck('id');
    $prospectionsDuSite = Prospection::whereIn('site_id', $this->siteIdsActifs)->pluck('id');

    foreach ($this->devisBrouillon as $ligne) {
        if (empty($ligne['montant_devis']) || ! is_numeric($ligne['montant_devis'])) {
            continue;
        }

        if (! $commerciauxDuSite->contains($ligne['commercial_id']) || ! $prospectionsDuSite->contains($ligne['prospection_id'])) {
            continue;
        }

        Devis::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'site_id' => $this->resoudreSiteId($ligne['activite']) ?? $this->site->id,
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

    $devis = Devis::whereIn('site_id', $this->siteIdsActifs)->findOrFail($id);
    $devis->update([
        'statut' => $statut,
        // Le montant validé démarre égal au montant du devis — modifiable ensuite via le
        // champ dédié — plutôt que de laisser un montant vide à chaque première validation.
        'montant_valide' => $statut === 'Validé' ? ($devis->montant_valide ?? $devis->montant_devis) : null,
    ]);
};

$changerMontantValide = function (int $id, $valeur) {
    $devis = Devis::whereIn('site_id', $this->siteIdsActifs)->findOrFail($id);
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

    $this->factureBrouillon = Devis::whereIn('site_id', $this->siteIdsActifs)->whereIn('id', $ids)->get()->map(fn ($d) => [
        'devis_id' => $d->id,
        'devis_numero' => $d->numero,
        'commercial_id' => $d->commercial_id,
        'client' => $d->client,
        'type' => 'FNE',
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
    if ($this->exerciceFerme) {
        $this->addError('exercice', "L'exercice est clos pour ce site à cette date — saisie impossible.");

        return;
    }

    // Défense en profondeur : le brouillon est un tableau lié côté client, on revérifie
    // que chaque commercial/devis référencé appartient bien à un site du Responsable.
    $commerciauxDuSite = Commercial::whereIn('site_id', $this->siteIdsActifs)->pluck('id');
    $devisDuSite = Devis::whereIn('site_id', $this->siteIdsActifs)->pluck('id');

    foreach ($this->factureBrouillon as $ligne) {
        if (empty($ligne['montant']) || ! is_numeric($ligne['montant'])) {
            continue;
        }

        if (! $commerciauxDuSite->contains($ligne['commercial_id']) || ! $devisDuSite->contains($ligne['devis_id'])) {
            continue;
        }

        Facture::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'site_id' => $this->resoudreSiteId($ligne['activite']) ?? $this->site->id,
            'devis_id' => $ligne['devis_id'],
            'commercial_id' => $ligne['commercial_id'],
            'numero' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'fac'),
            // Le N° de facture est généré automatiquement, jamais saisi à la main — un champ
            // manuel oublié faisait silencieusement disparaître la ligne à la validation.
            'n_facture' => GenerateurNumero::suivant(auth()->user()->entreprise_id, 'nfa'),
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
    if ($this->exerciceFerme) {
        $this->addError('exercice', "L'exercice est clos pour ce site à cette date — saisie impossible.");

        return;
    }

    $donnees = $this->validate([
        'encType' => ['required', Rule::in(array_keys($this->optionsTypeEncaissement))],
        'encFactureId' => [$this->encType === 'Client' ? 'required' : 'nullable', 'nullable', 'integer'],
        'encMoyen' => ['required', Rule::in(array_keys($this->optionsMoyenPaiement))],
        'encMontant' => ['required', 'numeric', 'min:1'],
        'encClient' => ['nullable', 'string', 'max:255'],
        'encTiers' => ['nullable', 'string', 'max:255'],
    ], [], ['encMontant' => 'montant', 'encFactureId' => 'facture']);

    $montant = (int) $donnees['encMontant'];
    $factureId = null;
    $client = $donnees['encClient'] ?: null;
    // La facture peut appartenir à l'un ou l'autre site en mode « Les deux » : l'encaissement
    // est rattaché au site réel de la facture, jamais au seul site d'ancrage.
    $siteIdResolu = $this->site->id;

    if ($donnees['encFactureId']) {
        // Verrou en base : empêche qu'un encaissement du responsable et du caissier,
        // saisis au même instant sur la même facture, dépassent ensemble son montant.
        $depassement = DB::transaction(function () use ($donnees, $montant, &$factureId, &$client, &$siteIdResolu) {
            $facture = Facture::whereIn('site_id', $this->siteIdsActifs)->lockForUpdate()->find($donnees['encFactureId']);

            if (! $facture || $montant > $facture->resteAEncaisser()) {
                return true;
            }

            $factureId = $facture->id;
            $client = $facture->client;
            $siteIdResolu = $facture->site_id;

            return false;
        });

        if ($depassement) {
            $this->addError('encMontant', 'Cette facture est déjà soldée, ou le montant dépasse son reste à encaisser.');
            unset($this->facturesAvecReste);

            return;
        }
    }

    Encaissement::create([
        'entreprise_id' => auth()->user()->entreprise_id,
        'site_id' => $siteIdResolu,
        'facture_id' => $factureId,
        'date' => $this->date,
        'type' => $donnees['encType'],
        'moyen' => $donnees['encMoyen'],
        'montant' => $montant,
        'client' => $client,
        'autres_tiers' => $donnees['encTiers'] ?: null,
        'cree_par' => auth()->id(),
    ]);

    $this->reset(['encMontant', 'encClient', 'encTiers', 'encFactureId']);
    $this->encType = 'Client';
    $this->encMoyen = 'Espèces';
    unset($this->facturesAvecReste);
};

// Charges & décaissements du jour

$ajouterCharge = function () {
    if ($this->site && \App\Domain\Tenants\Models\Exercice::estFerme(auth()->user()->entreprise_id, $this->site->ville_id, $this->chgDate)) {
        $this->addError('exercice', "L'exercice est clos pour ce site à cette date — saisie impossible.");

        return;
    }

    $donnees = $this->validate([
        'chgDate' => ['required', 'date'],
        'chgTypeOp' => ['required', 'in:Charges,Décaissements'],
        'chgLibelle' => ['required', 'string'],
        'chgMoyen' => ['required', Rule::in(array_keys($this->optionsMoyenPaiement))],
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
    @if ($this->mesSites->isEmpty())
        <x-a-venir titre="Aucun site assigné" description="Ce compte Responsable n'est rattaché à aucun site pour le moment. Contactez votre Gérant." />
    @else
        <div class="carte" style="display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap;">
            <div style="flex:1; min-width:220px;">
                <h1 style="font-size:19px; font-weight:800; margin:0 0 4px;">
                    Saisie du jour — {{ $this->siteUnique?->nom ?? ($this->mesSites->first()->ville->nom.' — Mécanique + Sinistre') }}
                </h1>
                <p style="color:#6B6E76; font-size:14px; margin:0;">Chaque ligne est enregistrée immédiatement à l'ajout et alimente les tableaux de bord en temps réel.</p>
            </div>
            <div style="display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap;">
                @if ($this->mesSites->count() > 1)
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <label class="champ-libelle">Site</label>
                        <select wire:model.live="siteActifId" class="champ" style="width:220px;">
                            @foreach ($this->mesSites as $s)
                                <option value="{{ $s->id }}">{{ $s->nom }} — {{ $s->activite }}</option>
                            @endforeach
                            <option value="les-deux">Les deux (Mécanique + Sinistre)</option>
                        </select>
                    </div>
                @endif
                <div style="display:flex; flex-direction:column; gap:4px;">
                    <label class="champ-libelle">Date de la journée (calendrier)</label>
                    <input type="date" wire:model.live="date" class="champ" style="width:158px;">
                    <span style="font-size:11.5px; color:var(--th-gris,#6B6E76);">Rattachement : <b>{{ $this->dateLabel }}</b></span>
                </div>
            </div>
        </div>

        @if ($this->exerciceFerme)
            <div class="encart encart-alerte" style="margin-bottom:16px;">
                L'exercice est clos pour <b>{{ $this->site->ville->nom }}</b> à cette date : la saisie est bloquée. Un gérant peut réouvrir l'exercice depuis les Paramètres.
            </div>
        @endif
        @error('exercice') <div class="encart encart-alerte" style="margin-bottom:16px;">{{ $message }}</div> @enderror

        <x-carte-section titre="Flux commercial" icone="commercial" couleur="var(--th-ink,#191B20)">
            @if ($this->prospectionsATraiter->isNotEmpty())
                <x-sous-titre n="1" t="Prospections transmises par vos commerciaux" />
                <p style="font-size:12.5px; font-weight:700; color:var(--th-bleu,#2563EB); margin:0 0 8px;">
                    {{ $this->prospectionsATraiter->count() }} prospection(s) en attente de votre validation.
                    <button type="button" wire:click="validerToutesProspections"
                        wire:confirm="Valider toutes les prospections transmises ?"
                        class="bouton bouton-petit" style="margin-left:10px;">Tout valider</button>
                </p>
                <div class="tableau-conteneur">
                    <table class="tableau">
                        <thead>
                            <tr>
                                <th>N°</th><th>Date</th><th>Commercial</th><th>Clients visités</th>
                                <th>Localisation</th><th>Moyens</th><th>Activité</th>
                                <th>Passage</th><th>Devis après passage</th><th>Observations</th>
                                <th>Motif si refus</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->prospectionsATraiter->forPage($pageProsATraiter, 10) as $p)
                                <tr wire:key="a-traiter-{{ $p->id }}">
                                    <td style="font-weight:700;">{{ $p->numero }}</td>
                                    <td>{{ $p->date->format('d/m/Y') }}</td>
                                    <td>{{ $p->commercial->nom }}</td>
                                    <td>{{ $p->client }}</td>
                                    <td style="color:var(--th-gris,#6B6E76);">{{ $p->localisation ?? '—' }}</td>
                                    <td>{{ $p->moyen }}</td>
                                    <td>{{ $p->activite }}</td>
                                    <td>{{ $p->passage ? '☑' : '☐' }}</td>
                                    <td>{{ $p->devis_apres_passage ? '☑' : '☐' }}</td>
                                    <td style="color:var(--th-gris,#6B6E76);">{{ $p->observations ?? '—' }}</td>
                                    <td><input type="text" wire:model="motifRefus.{{ $p->id }}" class="champ" style="width:150px;" placeholder="Facultatif"></td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button type="button" wire:click="validerProspection({{ $p->id }})"
                                            class="bouton bouton-petit bouton-vert" style="margin-right:5px;">Valider</button>
                                        <button type="button" wire:click="refuserProspection({{ $p->id }})"
                                            class="bouton bouton-petit bouton-secondaire">Refuser</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-pagination :page="$pageProsATraiter" :total="$this->prospectionsATraiter->count()" prop="pageProsATraiter" />
            @endif

            <x-sous-titre n="1" t="Prospections" />
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Clients visités</th>
                            <th>Localisation</th>
                            <th>Moyens</th>
                            <th>Commercial</th>
                            <th>Activité</th>
                            <th>Passage</th>
                            <th>Devis après passage</th>
                            <th>Informations libres</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->prospectionsDuJour->forPage($pageProsDuJour, 10) as $p)
                            @if ($editionProspectionId === $p->id)
                                <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8); background:#FDF2F4;" wire:key="pros-edit-{{ $p->id }}">
                                    <td style="font-weight:700;">{{ $p->numero }}</td>
                                    <td><input type="text" wire:model="editionProsClient" class="champ" style="min-width:130px;"></td>
                                    <td><input type="text" wire:model="editionProsLocalisation" class="champ" style="min-width:110px;"></td>
                                    <td>
                                        <select wire:model="editionProsMoyen" class="champ">
                                            @foreach ($this->optionsMoyenProspection as $valeur => $libelle)
                                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select wire:model="editionProsCommercialId" class="champ">
                                            @foreach ($this->commerciauxSelectables as $id => $nom)
                                                <option value="{{ $id }}">{{ $nom }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select wire:model="editionProsActivite" class="champ">
                                            @foreach ($this->optionsActivite as $valeur => $libelle)
                                                <option value="{{ $valeur }}">{{ $libelle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="white-space:normal; min-width:150px;">
                                        <label style="display:flex; align-items:center; gap:5px; font-size:13px;">
                                            <input type="checkbox" wire:model.live="editionProsPassage"> Passage
                                        </label>
                                        @if ($editionProsPassage && ! $editionProsDevisApres)
                                            <input type="date" wire:model="editionProsDatePassage" class="champ" style="margin-top:4px;">
                                        @endif
                                    </td>
                                    <td style="white-space:normal; min-width:180px;">
                                        <label style="display:flex; align-items:center; gap:5px; font-size:13px;">
                                            <input type="checkbox" wire:model.live="editionProsDevisApres"> Devis après passage
                                        </label>
                                        @if ($editionProsDevisApres)
                                            <input type="date" wire:model.live="editionProsDateDevis" class="champ" style="margin-top:4px;">
                                            <span style="font-size:10.5px; color:#9A9DA5;">= date de passage</span>
                                        @endif
                                    </td>
                                    <td style="white-space:normal; min-width:220px;">
                                        <textarea wire:model="editionProsObs" rows="2" placeholder="Observations"
                                            style="width:100%; box-sizing:border-box; padding:6px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13px;"></textarea>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <button type="button" wire:click="enregistrerEditionProspection" class="bouton bouton-petit bouton-vert" style="margin-right:5px;">Enregistrer</button>
                                        <button type="button" wire:click="annulerEditionProspection" class="bouton bouton-petit bouton-secondaire">Annuler</button>
                                    </td>
                                </tr>
                            @else
                                <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="pros-{{ $p->id }}">
                                    <td style="font-weight:700;">{{ $p->numero }}</td>
                                    <td>{{ $p->client }}</td>
                                    <td style="color:#6B6E76;">{{ $p->localisation ?? '—' }}</td>
                                    <td>{{ $p->moyen }}</td>
                                    <td>{{ $p->commercial->nom }}</td>
                                    <td>{{ $p->activite }}</td>
                                    <td>{{ $p->passage ? '☑' : '☐' }} {{ $p->date_passage?->format('d/m/Y') }}</td>
                                    <td>{{ $p->devis_apres_passage ? '☑' : '☐' }} {{ $p->date_devis?->format('d/m/Y') }}</td>
                                    <td style="white-space:normal; min-width:220px;">
                                        <x-saisie-libre :sujet="$p"
                                            :ouvert="$libreSujetId === $p->id && $libreSujetType === get_class($p)" />
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <a href="{{ route('prospection.voir', $p) }}" wire:navigate class="bouton bouton-petit bouton-secondaire" style="margin-right:5px;">Voir</a>
                                        <button type="button" wire:click="modifierProspection({{ $p->id }})" class="bouton bouton-petit bouton-secondaire">Modifier</button>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-table-vide :colspan="10" texte="Aucune prospection saisie pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageProsDuJour" :total="$this->prospectionsDuJour->count()" prop="pageProsDuJour" />

            <div class="bloc-saisie">
                <x-champ label="Clients visités" model="prosClient" />
                <x-champ label="Localisation" model="prosLocalisation" width="130" />
                <x-champ label="Moyens" model="prosMoyen" type="select" :options="$this->optionsMoyenProspection" width="130" />
                <x-champ label="Commercial" model="prosCommercialId" type="select" :options="$this->commerciauxSelectables" width="170" />
                <x-champ label="Activité" model="prosActivite" type="select" :options="$this->optionsActivite" width="140" :disabled="$this->activiteVerrouillee !== null" />
                <x-champ label="Passage" model="prosPassage" type="checkbox" live="true" />
                @if ($prosPassage && ! $prosDevisApres)
                    <x-champ label="Date de passage" model="prosDatePassage" type="date" width="150" />
                @endif
                <x-champ label="Devis après passage" model="prosDevisApres" type="checkbox" live="true" />
                @if ($prosDevisApres)
                    <x-champ label="Date du devis (= date de passage)" model="prosDateDevis" type="date" live="true" width="180" />
                @endif
                <x-champ label="Observations" model="prosObs" />
                <button type="button" wire:click="ajouterProspection"
                    class="bouton bouton-sombre">
                    + Ajouter
                </button>
            </div>

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Prospects</label>
                <textarea wire:model.live.debounce.700ms="commentaireProspects" rows="2"
                    placeholder="Ex. : affluence en baisse (pluies, jour férié...), campagne de prospection en cours, client flotte reçu..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>

            <x-sous-titre n="2" t="Devis" />

            @if ($this->prospectionsAttenteDevis->isNotEmpty())
                <div style="width:100%;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Devis à effectuer, issus de la prospection ({{ $this->prospectionsAttenteDevis->count() }}) — cocher puis « Ajouter devis » :
                    </p>
                    <div class="tableau-conteneur">
                        <table class="tableau">
                            <thead>
                                <tr>
                                    <th>✓</th>
                                    <th>N° prospection</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Commercial</th>
                                    <th>Activité</th>
                                    <th>Observations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->prospectionsAttenteDevis->forPage($pageAttenteDevis, 10) as $p)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="attente-devis-{{ $p->id }}">
                                        <td><input type="checkbox" wire:model="devisSelection.{{ $p->id }}"></td>
                                        <td style="font-weight:700;">{{ $p->numero }}</td>
                                        <td>{{ $p->client }}</td>
                                        <td>{{ $p->date->format('d/m/Y') }}</td>
                                        <td>{{ $p->commercial->nom }}</td>
                                        <td>{{ $p->activite }}</td>
                                        <td style="color:#6B6E76;">{{ $p->observations ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-pagination :page="$pageAttenteDevis" :total="$this->prospectionsAttenteDevis->count()" prop="pageAttenteDevis" />
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
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">N° prospection d'origine</label>
                                <span style="padding:8px 10px; font-weight:700;">{{ $ligne['prospection_numero'] }}</span>
                            </div>
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
                            <x-champ label="Activité" model="devisBrouillon.{{ $i }}.activite" type="select" :options="['Mécanique' => 'Mécanique', 'Sinistre' => 'Sinistre']" width="140" :disabled="$this->activiteVerrouillee !== null" />
                            <x-champ label="Observations" model="devisBrouillon.{{ $i }}.observations" />
                        </div>
                    @endforeach
                    <div style="display:flex; gap:8px;">
                        <button type="button" wire:click="validerDevis"
                            class="bouton">
                            ✓ Valider — rejoint la liste des devis
                        </button>
                        <button type="button" wire:click="annulerBrouillonsDevis"
                            class="bouton bouton-secondaire">
                            Annuler
                        </button>
                    </div>
                </div>
            @endif

            <div style="overflow-x:auto; margin-top:14px;">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Réception</th>
                            <th>Fiche</th>
                            <th>Client</th>
                            <th>Émission</th>
                            <th>Commercial</th>
                            <th>Statut</th>
                            <th>Montant devis</th>
                            <th>Montant validé</th>
                            <th>Activité</th>
                            <th>Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->devisDuJour->forPage($pageDevisDuJour, 10) as $d)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="font-weight:700;">{{ $d->numero }}</td>
                                <td>{{ $d->date_reception?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $d->n_fiche_reception ?? '—' }}</td>
                                <td>{{ $d->client }}</td>
                                <td>{{ $d->date_emission->format('d/m/Y') }}</td>
                                <td>{{ $d->commercial->nom }}</td>
                                <td style="font-weight:700; color:{{ $d->statut === 'Validé' ? '#0E9F6E' : ($d->statut === 'Refusé' ? '#C8102E' : '#D97706') }};">{{ $d->statut }}</td>
                                <td>{{ ae($d->montant_devis) }}</td>
                                <td>{{ $d->statut === 'Validé' ? ae($d->montant_valide) : '—' }}</td>
                                <td>{{ $d->activite }}</td>
                                <td style="color:#6B6E76;">{{ $d->observations ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="11" texte="Aucun devis émis pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageDevisDuJour" :total="$this->devisDuJour->count()" prop="pageDevisDuJour" />

            @if ($this->devisEnAttente->isNotEmpty())
                <div style="width:100%; margin-top:14px;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Devis en attente ({{ $this->devisEnAttente->count() }}) — changer le statut :
                    </p>
                    <div class="tableau-conteneur">
                        <table class="tableau">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Client</th>
                                    <th>Émission</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Montant validé</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->devisEnAttente->forPage($pageDevisEnAttente, 10) as $d)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="devis-attente-{{ $d->id }}">
                                        <td style="font-weight:700;">{{ $d->numero }}</td>
                                        <td>{{ $d->client }}</td>
                                        <td>{{ $d->date_emission->format('d/m/Y') }}</td>
                                        <td>{{ ae($d->montant_devis) }}</td>
                                        <td>
                                            <select wire:change="changerStatutDevis({{ $d->id }}, $event.target.value)"
                                                style="padding:5px 8px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:6px; font-size:13px;">
                                                <option {{ $d->statut === 'En attente' ? 'selected' : '' }}>En attente</option>
                                                <option {{ $d->statut === 'Validé' ? 'selected' : '' }}>Validé</option>
                                                <option {{ $d->statut === 'Refusé' ? 'selected' : '' }}>Refusé</option>
                                            </select>
                                        </td>
                                        <td>
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
                    <x-pagination :page="$pageDevisEnAttente" :total="$this->devisEnAttente->count()" prop="pageDevisEnAttente" />
                </div>
            @endif

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Devis</label>
                <textarea wire:model.live.debounce.700ms="commentaireDevis" rows="2"
                    placeholder="Ex. : baisse des devis liée à..., motifs des refus (prix jugé élevé, délai, pièces indisponibles)..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>

        </x-carte-section>

        <x-carte-section titre="Chiffre d'affaires facturé" icone="facture" couleur="var(--th-vert,#0E9F6E)">
            @if ($this->devisValidesNonFactures->isNotEmpty())
                <div style="width:100%;">
                    <p style="font-size:12.5px; font-weight:700; color:#D97706; margin:0 0 6px;">
                        Listing des devis émis validés, non facturés ({{ $this->devisValidesNonFactures->count() }}) — cocher les devis à facturer :
                    </p>
                    <div class="tableau-conteneur">
                        <table class="tableau">
                            <thead>
                                <tr>
                                    <th>✓</th>
                                    <th>N° devis</th>
                                    <th>Client</th>
                                    <th>Montant validé</th>
                                    <th>Commercial</th>
                                    <th>Activité</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->devisValidesNonFactures->forPage($pageValidesNonFactures, 10) as $d)
                                    <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);" wire:key="valide-non-facture-{{ $d->id }}">
                                        <td><input type="checkbox" wire:model="factureSelection.{{ $d->id }}"></td>
                                        <td style="font-weight:700;">{{ $d->numero }}</td>
                                        <td>{{ $d->client }}</td>
                                        <td>{{ ae($d->montant_valide ?? $d->montant_devis) }}</td>
                                        <td>{{ $d->commercial->nom }}</td>
                                        <td>{{ $d->activite }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-pagination :page="$pageValidesNonFactures" :total="$this->devisValidesNonFactures->count()" prop="pageValidesNonFactures" />
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
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">N° devis d'origine</label>
                                <span style="padding:8px 10px; font-weight:700;">{{ $ligne['devis_numero'] }}</span>
                            </div>
                            <x-champ label="Commercial" model="factureBrouillon.{{ $i }}.commercial_id" type="select" :options="$this->commerciauxSelectables" width="160" />
                            <x-champ label="Clients" model="factureBrouillon.{{ $i }}.client" />
                            <x-champ label="Type" model="factureBrouillon.{{ $i }}.type" type="select" :options="['FNE' => 'FNE', 'HT' => 'HT']" width="90" />
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">N° de facture</label>
                                <span style="padding:8px 10px; color:#9A9DA5; font-style:italic;">généré automatiquement</span>
                            </div>
                            <x-champ label="Activité" model="factureBrouillon.{{ $i }}.activite" type="select" :options="['Mécanique' => 'Mécanique', 'Sinistre' => 'Sinistre']" width="140" :disabled="$this->activiteVerrouillee !== null" />
                            <x-champ label="Montant de la facture" model="factureBrouillon.{{ $i }}.montant" type="number" width="140" />
                            <x-champ label="Observations" model="factureBrouillon.{{ $i }}.observations" />
                        </div>
                    @endforeach
                    <div style="display:flex; gap:8px;">
                        <button type="button" wire:click="validerFactures"
                            class="bouton">
                            ✓ Valider la facturation
                        </button>
                        <button type="button" wire:click="annulerBrouillonsFactures"
                            class="bouton bouton-secondaire">
                            Annuler
                        </button>
                    </div>
                </div>
            @endif

            <div style="overflow-x:auto; margin-top:14px;">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Commercial</th>
                            <th>Clients</th>
                            <th>Type</th>
                            <th>N° de facture</th>
                            <th>Activité</th>
                            <th>Montant de la facture</th>
                            <th>Observations</th>
                            <th>Informations libres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->facturesDuJour->forPage($pageFacturesDuJour, 10) as $f)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td style="font-weight:700;">{{ $f->numero }}</td>
                                <td>{{ $f->commercial->nom }}</td>
                                <td>{{ $f->client }}</td>
                                <td>{{ $f->type }}</td>
                                <td>{{ $f->n_facture }}</td>
                                <td>{{ $f->activite }}</td>
                                <td style="font-weight:700;">{{ ae($f->montant) }}</td>
                                <td style="color:#6B6E76;">{{ $f->observations ?? '—' }}</td>
                                <td style="white-space:normal; min-width:220px;">
                                    <x-saisie-libre :sujet="$f"
                                        :ouvert="$libreSujetId === $f->id && $libreSujetType === get_class($f)" />
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="9" texte="Aucune facture émise pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageFacturesDuJour" :total="$this->facturesDuJour->count()" prop="pageFacturesDuJour" />

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Chiffre d'affaires</label>
                <textarea wire:model.live.debounce.700ms="commentaireCA" rows="2"
                    placeholder="Ex. : facturation retardée (dossier assurance en attente d'expertise), gros dossier livré et facturé, avoir émis..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Encaissements du jour" icone="encaissement" couleur="var(--th-bleu,#2563EB)">
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Type d'encaissement</th>
                            <th>Moyens</th>
                            <th>Montant</th>
                            <th>Clients</th>
                            <th>Autres tiers</th>
                            <th>Informations libres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->encaissementsDuJour->forPage($pageEncaissementsDuJour, 10) as $e)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td>{{ $e->type }}</td>
                                <td>{{ $e->moyen }}</td>
                                <td style="font-weight:700; color:#0E9F6E;">{{ ae($e->montant) }}</td>
                                <td>{{ $e->client ?? '—' }}</td>
                                <td>{{ $e->autres_tiers ?? '—' }}</td>
                                <td style="white-space:normal; min-width:220px;">
                                    <x-saisie-libre :sujet="$e"
                                        :ouvert="$libreSujetId === $e->id && $libreSujetType === get_class($e)" />
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="6" texte="Aucun encaissement saisi pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageEncaissementsDuJour" :total="$this->encaissementsDuJour->count()" prop="pageEncaissementsDuJour" />

            <div class="bloc-saisie">
                <x-champ label="Type d'encaissement" model="encType" type="select" live="true" :options="$this->optionsTypeEncaissement" width="140" />
                @if ($encType === 'Client')
                    <x-champ label="Facture à encaisser" model="encFactureId" type="select" width="260"
                        :options="$this->facturesAvecReste->mapWithKeys(fn ($f) => [$f->id => $f->n_facture.' — '.$f->client.' — reste '.ae($f->resteAEncaisser())])" />
                @endif
                <x-champ label="Moyens" model="encMoyen" type="select" :options="$this->optionsMoyenPaiement" width="150" />
                <x-champ label="Montant (FCFA)" model="encMontant" type="number" width="140" />
                @if ($encType !== 'Client')
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
                @endif
                <button type="button" wire:click="ajouterEncaissement"
                    class="bouton bouton-sombre">
                    + Ajouter
                </button>
            </div>
            @error('encMontant') <div style="color:#C8102E; font-size:13.5px; margin-top:8px;">{{ $message }}</div> @enderror

            <div style="margin-top:14px;">
                <label style="display:block; font-size:12.5px; font-weight:600; color:#4B4E55; margin-bottom:5px;">Commentaire — Trésorerie</label>
                <textarea wire:model.live.debounce.700ms="commentaireTresorerie" rows="2"
                    placeholder="Ex. : trésorerie insuffisante pour..., appro reçu de la DG, promesse de règlement client X..."
                    style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:13.5px; resize:vertical;"></textarea>
            </div>
        </x-carte-section>

        <x-carte-section titre="Charges &amp; décaissements du jour" icone="charge" couleur="var(--th-accent,#C8102E)">
            <div class="tableau-conteneur">
                <table class="tableau">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type d'opération</th>
                            <th>Libellé d'opération</th>
                            <th>Moyens</th>
                            <th>Montant</th>
                            <th>Tiers</th>
                            <th>Observations</th>
                            <th>Informations libres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->chargesDuJour->forPage($pageChargesDuJour, 10) as $c)
                            <tr style="border-bottom:1px solid var(--th-ligne,#E2E0D8);">
                                <td>{{ $c->date->format('d/m/Y') }}</td>
                                <td>{{ $c->type_operation === 'Charges' ? 'Charges' : 'Décaissements' }}</td>
                                <td>{{ $c->libelle }}</td>
                                <td>{{ $c->moyen }}</td>
                                <td style="font-weight:700; color:#C8102E;">{{ ae($c->montant) }}</td>
                                <td>{{ $c->tiers ?? '—' }}</td>
                                <td style="color:#6B6E76;">{{ $c->observations ?? '—' }}</td>
                                <td style="white-space:normal; min-width:220px;">
                                    <x-saisie-libre :sujet="$c"
                                        :ouvert="$libreSujetId === $c->id && $libreSujetType === get_class($c)" />
                                </td>
                            </tr>
                        @empty
                            <x-table-vide :colspan="8" texte="Aucune charge ni décaissement saisi pour cette journée." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :page="$pageChargesDuJour" :total="$this->chargesDuJour->count()" prop="pageChargesDuJour" />

            <div class="bloc-saisie">
                <x-champ label="Date" model="chgDate" type="date" width="140" />
                <x-champ label="Type d'opération" model="chgTypeOp" type="select" :options="['Charges' => 'Charges', 'Décaissements' => 'Décaissements']" width="150" live="true" />
                <x-champ label="Libellé d'opération" model="chgLibelle" type="select" :options="array_combine($this->libellesOperation, $this->libellesOperation)" width="220" />
                <x-champ label="Moyens" model="chgMoyen" type="select" :options="$this->optionsMoyenPaiement" width="150" />
                <x-champ label="Montant (FCFA)" model="chgMontant" type="number" width="140" />
                <x-champ label="Tiers" model="chgTiers" width="160" />
                <x-champ label="Observations" model="chgObs" />
                <button type="button" wire:click="ajouterCharge"
                    class="bouton bouton-sombre">
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

        <x-carte-section titre="Atelier" icone="atelier" couleur="var(--th-ambre,#D97706)">
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
