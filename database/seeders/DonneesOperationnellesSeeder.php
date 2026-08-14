<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Jeu de données opérationnelles réaliste (60 jours) pour la démonstration :
 * prospections, devis, factures, encaissements, charges sur tous les sites.
 */
class DonneesOperationnellesSeeder extends Seeder
{
    private const JOURS_HISTORIQUE = 60;

    /**
     * Nombre de jours récents dont on garantit la complétude sur chaque site.
     * Couvre la journée courante et la veille ouvrée : c'est ce que les écrans
     * « du jour » affichent par défaut à l'ouverture de l'application.
     */
    private const JOURS_RECENTS_COMPLETS = 3;

    /** Poids visé des charges dans le chiffre d'affaires facturé. */
    private const PART_CHARGES = 0.65;

    private const CLIENTS_DEMO = [
        'SODECI', 'CIE', 'Orange CI', 'Nestlé CI', 'Bolloré Transport', 'SIFCA',
        'Groupe Alliances', 'NSIA Assurances', 'Bernabé', 'CFAO Motors',
        'M. Kouassi', 'Mme Traoré', 'M. Diallo', 'M. Ouattara', 'Mme Bamba',
        'M. Koné', "M. N'Dri", 'Mme Gnahoré', 'M. Yao', 'Mme Aka',
    ];

    private const LOCALISATIONS = ['Bouaké centre', 'Air France', 'Koko', 'Zone industrielle', 'Sokoura', "N'Gattakro"];

    private const LIBELLES_CHARGES = [
        'Achats pièces' => ['Fournisseur pièces auto', 'Garage import', 'Pièces détachées CI'],
        'Salaires & personnel' => ['Personnel'],
        'Fonctionnement' => ['Électricité', 'Eau', 'Internet', 'Loyer', 'Carburant véhicules service'],
        'Autres décaissements' => ['Divers', 'Entretien local', 'Fournitures bureau'],
    ];

    public function run(): void
    {
        $entreprise = Entreprise::where('slug', 'artisan-automobile')->first();

        if (! $entreprise) {
            return;
        }

        // Les écritures sont générées une seule fois : sans ce garde-fou, une seconde
        // exécution doublerait tous les montants et fausserait les indicateurs.
        if (Prospection::where('entreprise_id', $entreprise->id)->exists()) {
            $this->command?->warn('Des écritures existent déjà — génération ignorée.');

            return;
        }

        $sites = Site::where('entreprise_id', $entreprise->id)->get();

        $this->genererPourSites($sites, $entreprise->id);
    }

    /**
     * Génère l'historique complet (prospections → devis → factures → encaissements →
     * charges, sur JOURS_HISTORIQUE jours) pour un ensemble de sites donné. Extrait de
     * run() pour pouvoir être rejoué sur un sous-ensemble de sites — une ville ajoutée
     * après coup, par exemple — sans toucher aux écritures déjà générées ailleurs : la
     * calibration des charges (calibrerCharges) est elle-même bornée aux mêmes sites,
     * pour ne jamais recalculer les montants d'une ville qui n'est pas concernée.
     *
     * @param  \Illuminate\Support\Collection<int, Site>  $sites
     */
    public function genererPourSites($sites, int $entrepriseId): void
    {
        $debut = Carbon::now()->subDays(self::JOURS_HISTORIQUE);

        foreach ($sites as $site) {
            $commerciaux = Commercial::where('site_id', $site->id)->where('est_spontane', false)->get();
            $spontane = Commercial::where('site_id', $site->id)->where('est_spontane', true)->first();

            if ($commerciaux->isEmpty()) {
                continue;
            }

            for ($jour = 0; $jour <= self::JOURS_HISTORIQUE; $jour++) {
                $date = $debut->copy()->addDays($jour);

                if ($date->isSunday()) {
                    continue;
                }

                $nbProspections = random_int(1, 4);

                for ($p = 0; $p < $nbProspections; $p++) {
                    $commercial = random_int(1, 10) <= 8 || ! $spontane
                        ? $commerciaux->random()
                        : $spontane;

                    $passage = random_int(1, 100) <= 85;
                    $devisApresPassage = $passage && random_int(1, 100) <= 45;

                    $prospection = Prospection::create([
                        'entreprise_id' => $entrepriseId,
                        'site_id' => $site->id,
                        'commercial_id' => $commercial->id,
                        'numero' => GenerateurNumero::suivant($entrepriseId, 'pro'),
                        'date' => $date->toDateString(),
                        'client' => self::CLIENTS_DEMO[array_rand(self::CLIENTS_DEMO)],
                        'localisation' => self::LOCALISATIONS[array_rand(self::LOCALISATIONS)],
                        'moyen' => ['RDV', 'Téléphone', 'Mail'][array_rand(['RDV', 'Téléphone', 'Mail'])],
                        'activite' => $this->activitePourTransaction($commercial),
                        'passage' => $passage,
                        'devis_apres_passage' => $devisApresPassage,
                        // Circuit de validation : l'essentiel est validé, mais les tout derniers
                        // jours gardent des lignes transmises ou refusées pour illustrer
                        // l'arbitrage du responsable sur les saisies de ses commerciaux.
                        'statut_validation' => $jour >= self::JOURS_HISTORIQUE - 2
                            ? ['Validée', 'Validée', 'Validée', 'Transmise', 'Refusée'][random_int(0, 4)]
                            : 'Validée',
                    ]);

                    if (! $devisApresPassage) {
                        continue;
                    }

                    $montantDevis = random_int(45, 850) * 1000;
                    $tirage = random_int(1, 100);
                    $statut = $tirage <= 55 ? 'Validé' : ($tirage <= 85 ? 'Refusé' : 'En attente');
                    $montantValide = $statut === 'Validé' ? (int) round($montantDevis * (random_int(70, 98) / 100)) : null;

                    $devis = Devis::create([
                        'entreprise_id' => $entrepriseId,
                        'site_id' => $site->id,
                        'commercial_id' => $commercial->id,
                        'prospection_id' => $prospection->id,
                        'numero' => GenerateurNumero::suivant($entrepriseId, 'dev'),
                        'n_fiche_reception' => 'FR-'.$site->code.'-'.random_int(100, 999),
                        'date_reception' => $date->toDateString(),
                        'date_emission' => $date->copy()->addDays(random_int(0, 3))->toDateString(),
                        'client' => $prospection->client,
                        'activite' => $prospection->activite,
                        'statut' => $statut,
                        'montant_devis' => $montantDevis,
                        'montant_valide' => $montantValide,
                    ]);

                    if ($statut !== 'Validé' || random_int(1, 100) > 88) {
                        continue;
                    }

                    $dateFacture = Carbon::parse($devis->date_emission)->addDays(random_int(1, 6));
                    if ($dateFacture->greaterThan(Carbon::now())) {
                        continue;
                    }

                    $facture = Facture::create([
                        'entreprise_id' => $entrepriseId,
                        'site_id' => $site->id,
                        'devis_id' => $devis->id,
                        'commercial_id' => $commercial->id,
                        'numero' => GenerateurNumero::suivant($entrepriseId, 'fac'),
                        'n_facture' => 'FAC-'.$site->code.'-'.random_int(1000, 9999),
                        'date' => $dateFacture->toDateString(),
                        'client' => $devis->client,
                        'type' => random_int(0, 1) ? 'FNE' : 'HT',
                        'activite' => $devis->activite,
                        'montant' => $devis->montant_valide,
                    ]);

                    if (random_int(1, 100) <= 88) {
                        Encaissement::create([
                            'entreprise_id' => $entrepriseId,
                            'site_id' => $site->id,
                            'facture_id' => $facture->id,
                            'date' => $dateFacture->copy()->addDays(random_int(0, 4))->min(Carbon::now())->toDateString(),
                            'type' => 'Client',
                            'moyen' => ['Espèces', 'Mobile Money', 'Chèque', 'Virement'][array_rand(['Espèces', 'Mobile Money', 'Chèque', 'Virement'])],
                            'montant' => $facture->montant,
                            'client' => $facture->client,
                        ]);
                    }
                }

                $this->genererCharges($entrepriseId, $site->id, $date);
            }
        }

        $this->completerJoursRecents($entrepriseId, $sites);
        $this->calibrerCharges($sites->pluck('id')->all());
    }

    /**
     * Garantit que les derniers jours ouvrés comportent, sur CHAQUE site, au moins une
     * écriture de chaque nature.
     *
     * La génération principale procède par tirages successifs : une facture n'existe que
     * si la prospection a donné un devis, que ce devis a été validé, et que sa date de
     * facturation ne dépasse pas aujourd'hui. Sur une journée donnée et un site donné, la
     * probabilité d'obtenir la chaîne complète est faible — d'où des écrans « du jour »
     * vides sur certains sites alors que l'historique est fourni. Or c'est précisément
     * l'écran qu'un responsable ouvre en premier.
     */
    private function completerJoursRecents(int $entrepriseId, $sites): void
    {
        foreach ($sites as $site) {
            $commerciaux = Commercial::where('site_id', $site->id)->where('est_spontane', false)->get();

            if ($commerciaux->isEmpty()) {
                continue;
            }

            for ($recul = 0; $recul < self::JOURS_RECENTS_COMPLETS; $recul++) {
                $date = Carbon::now()->subDays($recul);

                if ($date->isSunday()) {
                    continue;
                }

                $this->completerJournee($entrepriseId, $site, $commerciaux, $date);
            }
        }
    }

    private function completerJournee(int $entrepriseId, Site $site, $commerciaux, Carbon $date): void
    {
        $jour = $date->toDateString();

        // Un devis de chaque statut, pour que la page Devis et l'arbitrage du responsable
        // aient toujours matière à montrer.
        foreach (['En attente', 'Refusé'] as $statut) {
            $existe = Devis::withoutGlobalScopes()
                ->where('site_id', $site->id)->where('statut', $statut)
                ->whereDate('date_emission', $jour)->exists();

            if (! $existe) {
                $this->creerChaineComplete($entrepriseId, $site, $commerciaux->random(), $date, $statut);
            }
        }

        // La facture se vérifie pour elle-même, et non à travers le devis : la génération
        // principale produit des devis validés dont la facture, datée quelques jours plus
        // tard, tombe après aujourd'hui et n'est donc jamais créée. Se fier au devis
        // laisserait ces journées sans aucune facture ni encaissement.
        $aUneFacture = Facture::withoutGlobalScopes()
            ->where('site_id', $site->id)->whereDate('date', $jour)->exists();

        if (! $aUneFacture) {
            $this->creerChaineComplete($entrepriseId, $site, $commerciaux->random(), $date, 'Validé');
        }

        // Une facture n'est encaissée que dans 88 % des cas : l'encaissement se contrôle
        // donc lui aussi pour lui-même, faute de quoi la trésorerie du jour reste vide.
        $aUnEncaissement = Encaissement::withoutGlobalScopes()
            ->where('site_id', $site->id)->whereDate('date', $jour)->exists();

        if (! $aUnEncaissement) {
            $this->creerEncaissementDuJour($entrepriseId, $site, $date);
        }

        // Chaque nature d'opération est contrôlée séparément : une charge tirée au hasard
        // ne garantit ni la présence d'un transfert, ni celle d'un décaissement DG.
        foreach (['Charges', 'Transfert', 'Décaissement DG'] as $typeOperation) {
            $existe = Charge::withoutGlobalScopes()
                ->where('site_id', $site->id)->where('type_operation', $typeOperation)
                ->whereDate('date', $jour)->exists();

            if (! $existe) {
                $this->creerOperationTresorerie($entrepriseId, $site->id, $date, $typeOperation);
            }
        }
    }

    /** Encaisse une facture non réglée du jour, ou à défaut enregistre un appro de la Direction. */
    private function creerEncaissementDuJour(int $entrepriseId, Site $site, Carbon $date): void
    {
        $jour = $date->toDateString();

        $facture = Facture::withoutGlobalScopes()
            ->where('site_id', $site->id)
            ->whereDate('date', $jour)
            ->whereDoesntHave('encaissements')
            ->first();

        if ($facture) {
            Encaissement::create([
                'entreprise_id' => $entrepriseId,
                'site_id' => $site->id,
                'facture_id' => $facture->id,
                'date' => $jour,
                'type' => 'Client',
                'moyen' => ['Espèces', 'Mobile Money', 'Chèque', 'Virement'][random_int(0, 3)],
                'montant' => $facture->montant,
                'client' => $facture->client,
            ]);

            return;
        }

        // Toutes les factures du jour sont déjà réglées : on illustre alors l'autre nature
        // d'encaissement prévue par l'application, l'approvisionnement reçu du siège.
        Encaissement::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $site->id,
            'facture_id' => null,
            'date' => $jour,
            'type' => 'Appro',
            'moyen' => 'Virement',
            'montant' => random_int(150, 600) * 1000,
            'autres_tiers' => 'Direction Générale',
        ]);
    }

    /** Une écriture de trésorerie d'un type d'opération donné, datée du jour demandé. */
    private function creerOperationTresorerie(int $entrepriseId, int $siteId, Carbon $date, string $typeOperation): void
    {
        if ($typeOperation === 'Charges') {
            $nature = array_rand(self::LIBELLES_CHARGES);
            $libelles = self::LIBELLES_CHARGES[$nature];

            Charge::create([
                'entreprise_id' => $entrepriseId,
                'site_id' => $siteId,
                'date' => $date->toDateString(),
                'type_operation' => 'Charges',
                'libelle' => $nature,
                'moyen' => ['Espèces', 'Virement'][random_int(0, 1)],
                'montant' => random_int(30, 260) * 1000,
                'tiers' => $nature === 'Salaires & personnel' ? 'Personnel' : $libelles[array_rand($libelles)],
            ]);

            return;
        }

        Charge::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $siteId,
            'date' => $date->toDateString(),
            'type_operation' => $typeOperation,
            'libelle' => 'Autres décaissements',
            'moyen' => 'Virement',
            'montant' => random_int(20, 90) * 1000,
            'tiers' => $typeOperation === 'Transfert' ? 'Transfert inter-sites' : 'Direction Générale',
        ]);
    }

    /**
     * Crée la chaîne prospection → devis → (facture → encaissement) d'une seule traite,
     * en datant chaque étape du même jour pour qu'elle apparaisse dans la saisie du jour.
     */
    private function creerChaineComplete(int $entrepriseId, Site $site, Commercial $commercial, Carbon $date, string $statut): void
    {
        $client = self::CLIENTS_DEMO[array_rand(self::CLIENTS_DEMO)];
        $activite = $this->activitePourTransaction($commercial);
        $jour = $date->toDateString();

        $prospection = Prospection::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $site->id,
            'commercial_id' => $commercial->id,
            'numero' => GenerateurNumero::suivant($entrepriseId, 'pro'),
            'date' => $jour,
            'client' => $client,
            'localisation' => self::LOCALISATIONS[array_rand(self::LOCALISATIONS)],
            'moyen' => ['RDV', 'Téléphone', 'Mail'][random_int(0, 2)],
            'activite' => $activite,
            'passage' => true,
            'devis_apres_passage' => true,
            'statut_validation' => 'Validée',
        ]);

        $montantDevis = random_int(45, 850) * 1000;
        $montantValide = $statut === 'Validé' ? (int) round($montantDevis * (random_int(70, 98) / 100)) : null;

        $devis = Devis::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $site->id,
            'commercial_id' => $commercial->id,
            'prospection_id' => $prospection->id,
            'numero' => GenerateurNumero::suivant($entrepriseId, 'dev'),
            'n_fiche_reception' => 'FR-'.$site->code.'-'.random_int(100, 999),
            'date_reception' => $jour,
            'date_emission' => $jour,
            'client' => $client,
            'activite' => $activite,
            'statut' => $statut,
            'montant_devis' => $montantDevis,
            'montant_valide' => $montantValide,
        ]);

        // Seul un devis validé se transforme en facture, puis en encaissement.
        if ($statut !== 'Validé') {
            return;
        }

        $facture = Facture::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $site->id,
            'devis_id' => $devis->id,
            'commercial_id' => $commercial->id,
            'numero' => GenerateurNumero::suivant($entrepriseId, 'fac'),
            'n_facture' => 'FAC-'.$site->code.'-'.random_int(1000, 9999),
            'date' => $jour,
            'client' => $client,
            'type' => random_int(0, 1) ? 'FNE' : 'HT',
            'activite' => $activite,
            'montant' => $montantValide,
        ]);

        Encaissement::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $site->id,
            'facture_id' => $facture->id,
            'date' => $jour,
            'type' => 'Client',
            'moyen' => ['Espèces', 'Mobile Money', 'Chèque', 'Virement'][random_int(0, 3)],
            'montant' => $facture->montant,
            'client' => $client,
        ]);
    }

    /**
     * Les montants sont tirés au hasard : sans recalage, le poids des charges peut osciller
     * de 40 % à 90 % du chiffre d'affaires d'une exécution à l'autre. On applique donc un
     * facteur d'échelle unique pour viser une structure de coûts réaliste (~65 % du CA),
     * qui laisse un résultat net positif et lisible dans la démonstration.
     */
    /**
     * Bornée aux sites passés (et non à toute l'entreprise) pour qu'un rejeu partiel —
     * une ville ajoutée après coup — ne recalcule jamais les charges d'une ville déjà
     * calibrée par un précédent passage.
     *
     * @param  array<int, int>  $siteIds
     */
    private function calibrerCharges(array $siteIds): void
    {
        $ca = Facture::withoutGlobalScopes()->whereIn('site_id', $siteIds)->sum('montant');
        $charges = Charge::withoutGlobalScopes()->whereIn('site_id', $siteIds)->sum('montant');

        if ($ca <= 0 || $charges <= 0) {
            return;
        }

        $facteur = ($ca * self::PART_CHARGES) / $charges;

        Charge::withoutGlobalScopes()
            ->whereIn('site_id', $siteIds)
            // ROUND() plutôt que CAST(... AS INTEGER) : ce type n'existe pas en MySQL,
            // qui attend SIGNED. ROUND est comprise à l'identique par MySQL et SQLite.
            ->update(['montant' => DB::raw('ROUND(montant * '.round($facteur, 4).')')]);
    }

    /**
     * Une prospection/un devis/une facture porte toujours une seule activité, jamais
     * « Mécanique/Sinistre » (réservée aux commerciaux polyvalents) : on tire au sort
     * pour ces commerciaux-là, et on reprend l'activité déclarée pour les autres.
     */
    private function activitePourTransaction(Commercial $commercial): string
    {
        return in_array($commercial->activite, ['Mécanique', 'Sinistre'], true)
            ? $commercial->activite
            : (random_int(0, 1) ? 'Mécanique' : 'Sinistre');
    }

    private function genererCharges(int $entrepriseId, int $siteId, Carbon $date): void
    {
        if (random_int(1, 100) > 85) {
            return;
        }

        $nature = array_rand(self::LIBELLES_CHARGES);
        $libelles = self::LIBELLES_CHARGES[$nature];

        // Calibrés pour que le total des charges représente environ 60 à 70 % du CA facturé,
        // afin que le résultat net de la démonstration reste positif et lisible.
        $montants = [
            'Achats pièces' => [55, 330],
            'Salaires & personnel' => [120, 480],
            'Fonctionnement' => [14, 100],
            'Autres décaissements' => [10, 75],
        ];

        [$min, $max] = $montants[$nature];

        Charge::create([
            'entreprise_id' => $entrepriseId,
            'site_id' => $siteId,
            'date' => $date->toDateString(),
            'type_operation' => 'Charges',
            'libelle' => $nature,
            'moyen' => ['Espèces', 'Virement'][array_rand(['Espèces', 'Virement'])],
            'montant' => random_int($min, $max) * 1000,
            'tiers' => $nature === 'Salaires & personnel' ? 'Personnel' : $libelles[array_rand($libelles)],
        ]);
    }
}
