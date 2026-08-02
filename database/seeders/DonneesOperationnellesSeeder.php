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

/**
 * Jeu de données opérationnelles réaliste (60 jours) pour la démonstration :
 * prospections, devis, factures, encaissements, charges sur tous les sites.
 */
class DonneesOperationnellesSeeder extends Seeder
{
    private const JOURS_HISTORIQUE = 60;

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

        $debut = Carbon::now()->subDays(self::JOURS_HISTORIQUE);
        $sites = Site::where('entreprise_id', $entreprise->id)->get();

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
                        'entreprise_id' => $entreprise->id,
                        'site_id' => $site->id,
                        'commercial_id' => $commercial->id,
                        'numero' => GenerateurNumero::suivant($entreprise->id, 'pro'),
                        'date' => $date->toDateString(),
                        'client' => self::CLIENTS_DEMO[array_rand(self::CLIENTS_DEMO)],
                        'localisation' => self::LOCALISATIONS[array_rand(self::LOCALISATIONS)],
                        'moyen' => ['RDV', 'Téléphone', 'Mail'][array_rand(['RDV', 'Téléphone', 'Mail'])],
                        'activite' => $commercial->activite ?? (random_int(0, 1) ? 'Mécanique' : 'Carrosserie'),
                        'passage' => $passage,
                        'devis_apres_passage' => $devisApresPassage,
                    ]);

                    if (! $devisApresPassage) {
                        continue;
                    }

                    $montantDevis = random_int(45, 850) * 1000;
                    $tirage = random_int(1, 100);
                    $statut = $tirage <= 40 ? 'Validé' : ($tirage <= 85 ? 'Refusé' : 'En attente');
                    $montantValide = $statut === 'Validé' ? (int) round($montantDevis * (random_int(70, 98) / 100)) : null;

                    $devis = Devis::create([
                        'entreprise_id' => $entreprise->id,
                        'site_id' => $site->id,
                        'commercial_id' => $commercial->id,
                        'prospection_id' => $prospection->id,
                        'numero' => GenerateurNumero::suivant($entreprise->id, 'dev'),
                        'n_fiche_reception' => 'FR-'.$site->code.'-'.random_int(100, 999),
                        'date_reception' => $date->toDateString(),
                        'date_emission' => $date->copy()->addDays(random_int(0, 3))->toDateString(),
                        'client' => $prospection->client,
                        'activite' => $prospection->activite,
                        'statut' => $statut,
                        'montant_devis' => $montantDevis,
                        'montant_valide' => $montantValide,
                    ]);

                    if ($statut !== 'Validé' || random_int(1, 100) > 75) {
                        continue;
                    }

                    $dateFacture = Carbon::parse($devis->date_emission)->addDays(random_int(1, 6));
                    if ($dateFacture->greaterThan(Carbon::now())) {
                        continue;
                    }

                    $facture = Facture::create([
                        'entreprise_id' => $entreprise->id,
                        'site_id' => $site->id,
                        'devis_id' => $devis->id,
                        'commercial_id' => $commercial->id,
                        'numero' => GenerateurNumero::suivant($entreprise->id, 'fac'),
                        'n_facture' => 'FAC-'.$site->code.'-'.random_int(1000, 9999),
                        'date' => $dateFacture->toDateString(),
                        'client' => $devis->client,
                        'type' => random_int(0, 1) ? 'FNE' : 'HT',
                        'activite' => $devis->activite,
                        'montant' => $devis->montant_valide,
                    ]);

                    if (random_int(1, 100) <= 88) {
                        Encaissement::create([
                            'entreprise_id' => $entreprise->id,
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

                $this->genererCharges($entreprise->id, $site->id, $date);
            }
        }
    }

    private function genererCharges(int $entrepriseId, int $siteId, Carbon $date): void
    {
        if (random_int(1, 100) > 60) {
            return;
        }

        $nature = array_rand(self::LIBELLES_CHARGES);
        $libelles = self::LIBELLES_CHARGES[$nature];

        $montants = [
            'Achats pièces' => [60, 400],
            'Salaires & personnel' => [150, 700],
            'Fonctionnement' => [15, 120],
            'Autres décaissements' => [10, 90],
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
