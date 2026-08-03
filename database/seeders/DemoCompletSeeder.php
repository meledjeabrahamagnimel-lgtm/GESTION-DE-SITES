<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration COMPLET, exécutable seul :
 *
 *     php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoCompletSeeder
 *
 * ou, si la base est déjà migrée :
 *
 *     php artisan db:seed --class=DemoCompletSeeder
 *
 * Il enchaîne le Super Admin, l'entreprise pilote « L'Artisan Automobile »
 * (sites, comptes, commerciaux), 60 jours d'écritures d'exploitation, puis
 * les commentaires et relevés d'atelier de la saisie journalière.
 */
class DemoCompletSeeder extends Seeder
{
    private const COMMENTAIRES = [
        'prospects' => [
            'Affluence en baisse, fortes pluies sur la journée.',
            'Campagne de prospection en cours sur la zone industrielle.',
            'Client flotte reçu, négociation d\'un contrat annuel en cours.',
            'Bonne fréquentation, deux prospects transformés dans la journée.',
        ],
        'devis' => [
            'Plusieurs refus liés au prix jugé élevé sur la carrosserie.',
            'Devis en attente de validation assurance.',
            'Délai de pièces annoncé trop long par le fournisseur.',
            'Bon taux de validation sur la mécanique cette semaine.',
        ],
        'ca' => [
            'Facturation retardée : dossier assurance en attente d\'expertise.',
            'Gros dossier livré et facturé ce jour.',
            'Avoir émis sur une facture de la semaine précédente.',
            'Journée conforme aux objectifs de facturation.',
        ],
        'tresorerie' => [
            'Trésorerie insuffisante pour régler le fournisseur de pièces.',
            'Appro reçu de la Direction Générale.',
            'Promesse de règlement du client SIFCA pour la semaine prochaine.',
            'Encaissements conformes aux prévisions.',
        ],
        'charges' => [
            'Achat exceptionnel de matériel d\'atelier.',
            'Hausse du prix des pièces détachées importées.',
            'Transfert de trésorerie vers le site de San Pedro.',
            'Dépense DG : mission de prospection à Yamoussoukro.',
        ],
    ];

    public function run(): void
    {
        // 1. Plateforme et entreprise pilote, 2. écritures d'exploitation.
        $this->call([
            SuperAdminSeeder::class,
            EntrepriseArtisanAutomobileSeeder::class,
            DonneesOperationnellesSeeder::class,
        ]);

        // 3. Saisies journalières : commentaires et véhicules restitués sans facture.
        $this->genererSaisiesJournalieres();

        // 4. Messagerie, notifications, notes et Super Admin secondaire d'exemple.
        $this->call(MessagerieEtNotesSeeder::class);

        $this->command?->info('Jeu de démonstration complet installé.');
        $this->command?->info('Support (Super Admin secondaire) : support@plateforme.local / password');
        $this->command?->info('Gérant      : direction@artisan-automobile.ci / password');
        $this->command?->info('Responsable : david.k@artisan-automobile.ci / password');
        $this->command?->info('Commercial  : k-aya@artisan-automobile.ci / password');
        $this->command?->info('Super Admin : superadmin@plateforme.local / password');
        $this->command?->info('Code entreprise (auto-inscription) : ART-2026CI');
    }

    private function genererSaisiesJournalieres(): void
    {
        $entreprise = Entreprise::where('slug', 'artisan-automobile')->first();

        if (! $entreprise) {
            return;
        }

        $sites = Site::where('entreprise_id', $entreprise->id)->get();
        $lignes = [];

        foreach ($sites as $site) {
            for ($jour = 30; $jour >= 0; $jour--) {
                $date = Carbon::now()->subDays($jour);

                if ($date->isSunday()) {
                    continue;
                }

                // Une anomalie « véhicule restitué sans facture » reste rare (environ 1 jour sur 12).
                $sansFacture = random_int(1, 12) === 1 ? random_int(1, 2) : 0;

                $lignes[] = [
                    'entreprise_id' => $entreprise->id,
                    'site_id' => $site->id,
                    'date' => $date->toDateString(),
                    'vehicules_sans_facture' => $sansFacture,
                    'commentaire_prospects' => $this->commentaire('prospects'),
                    'commentaire_devis' => $this->commentaire('devis'),
                    'commentaire_ca' => $this->commentaire('ca'),
                    'commentaire_tresorerie' => $this->commentaire('tresorerie'),
                    'commentaire_charges' => $this->commentaire('charges'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($lignes, 100) as $lot) {
            SaisieJournaliere::upsert($lot, ['site_id', 'date'], [
                'vehicules_sans_facture', 'commentaire_prospects', 'commentaire_devis',
                'commentaire_ca', 'commentaire_tresorerie', 'commentaire_charges', 'updated_at',
            ]);
        }
    }

    /** Un commentaire sur deux journées environ, pour rester réaliste. */
    private function commentaire(string $rubrique): ?string
    {
        if (random_int(0, 1) === 0) {
            return null;
        }

        $choix = self::COMMENTAIRES[$rubrique];

        return $choix[array_rand($choix)];
    }
}
