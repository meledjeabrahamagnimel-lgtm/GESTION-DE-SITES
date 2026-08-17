<?php

namespace Database\Seeders;

use Modules\Noyau\Exploitation\Modeles\SaisieJournaliere;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
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
            'Plusieurs refus liés au prix jugé élevé sur le sinistre.',
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
        // 1. Plateforme et entreprise pilote, 2. écritures d'exploitation d'Abidjan,
        // 3. les deux autres villes avec leur propre historique.
        $this->call([
            SuperAdminSeeder::class,
            EntrepriseArtisanAutomobileSeeder::class,
            DonneesOperationnellesSeeder::class,
            VillesBouakeSanPedroSeeder::class,
        ]);

        // 4. Saisies journalières : commentaires et véhicules restitués sans facture.
        $this->genererSaisiesJournalieres();

        // 5. Messagerie, notifications, notes et Super Admin secondaire d'exemple.
        $this->call(MessagerieEtNotesSeeder::class);

        // 6. Dernier passage sur les comptes : les seeders ci-dessus s'arrêtent dès que
        // l'entreprise existe, celui-ci rattrape ce qui a changé depuis l'installation.
        $this->call(ComptesDeTestSeeder::class);

        $this->command?->info('Jeu de démonstration complet installé.');
        $this->command?->info('La liste des 14 accès est dans ACCES.txt — mot de passe : password');
        $this->command?->info("Code entreprise (auto-inscription) : ART-2026CI");
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
