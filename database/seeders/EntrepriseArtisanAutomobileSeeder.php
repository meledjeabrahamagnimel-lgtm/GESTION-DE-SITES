<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\CompteurDocument;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Entreprise pilote « L'Artisan Automobile » : sites, comptes et commerciaux de démonstration,
 * avec la charte de couleurs exacte de la maquette.
 */
class EntrepriseArtisanAutomobileSeeder extends Seeder
{
    public function run(): void
    {
        // Logo servi depuis public/logos : versionné, donc disponible en production
        // sans dépendre du lien symbolique public/storage.
        $cheminLogo = 'public:logos/artisan-automobile.png';

        $entreprise = Entreprise::create([
            'nom' => "L'Artisan Automobile",
            'slug' => 'artisan-automobile',
            'code_entreprise' => 'ART-2026CI',
            'logo_chemin' => $cheminLogo,
            // Identification légale et fiscale (Côte d'Ivoire).
            'gerant_nom' => 'KOUASSI',
            'gerant_prenom' => 'Jean-Baptiste',
            'gerant_fonction' => 'Gérant',
            'gerant_email' => 'direction@artisan-automobile.ci',
            'adresse' => 'Zone industrielle de Yopougon, ABIDJAN, CÔTE D\'IVOIRE',
            'telephone' => '+225 27 23 45 67 89',
            'email' => 'contact@artisan-automobile.ci',
            'rccm' => 'CI-ABJ-2018-B-14520',
            'ncc' => '1745820 K',
            'regime_imposition' => "RNI — Régime Normal d'Imposition",
            'centre_impots' => 'YOPOUGON INDUSTRIEL',
            'compte_contribuable' => '1745820 K',
            'idu' => 'CI-001-2026-A874512',
            'commune' => 'YOPOUGON',
            'quartier' => 'Zone Industrielle',
            'reference_cadastrale' => 'Section D, Parcelle 118',
            'proprietaire_local' => 'SCI LES ATELIERS DU LAGON',
            'couleur_ink' => '#191B20',
            'couleur_paper' => '#F4F3EF',
            'couleur_ligne' => '#E2E0D8',
            'couleur_accent' => '#C8102E',
            'couleur_succes' => '#0E9F6E',
            'couleur_alerte' => '#D97706',
            'couleur_info' => '#2563EB',
            'plan' => 'entreprise',
            'est_active' => true,
        ]);

        ProvisionneurEntreprise::creerRoles($entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        $gerant = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Direction Générale',
            'email' => 'direction@artisan-automobile.ci',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $gerant->assignRole('gerant');

        $sites = [
            ['code' => 'BKE', 'nom' => 'Bouaké', 'couleur' => '#7C3AED', 'ville' => 'Bouaké', 'commune' => 'Koko', 'telephone' => '+225 31 63 22 11', 'adresse' => 'Route de Katiola, face station Total', 'resp_nom' => 'David K.', 'resp_email' => 'david.k@artisan-automobile.ci'],
            ['code' => 'AB1', 'nom' => 'Abidjan — Site 1', 'couleur' => '#2563EB', 'ville' => 'Abidjan', 'commune' => 'Yopougon', 'telephone' => '+225 27 23 45 67 90', 'adresse' => 'Zone industrielle, Rue des Artisans', 'resp_nom' => 'Responsable Site 1', 'resp_email' => 'resp.site1@artisan-automobile.ci'],
            ['code' => 'AB2', 'nom' => 'Abidjan — Site 2', 'couleur' => '#059669', 'ville' => 'Abidjan', 'commune' => 'Marcory', 'telephone' => '+225 27 21 34 55 08', 'adresse' => 'Boulevard du Gabon, Zone 4', 'resp_nom' => 'Responsable Site 2', 'resp_email' => 'resp.site2@artisan-automobile.ci'],
            ['code' => 'SPD', 'nom' => 'San Pedro', 'couleur' => '#D97706', 'ville' => 'San Pedro', 'commune' => 'Bardot', 'telephone' => '+225 34 71 18 42', 'adresse' => 'Avenue du Port, quartier Bardot', 'resp_nom' => 'Rama Gaiho', 'resp_email' => 'rama.gaiho@artisan-automobile.ci'],
        ];

        $commerciauxParSite = [
            'BKE' => [
                ['nom' => 'Commercial 1 — Bouaké (à nommer)', 'activite' => 'Mécanique', 'objectif_mensuel' => 3_500_000],
                ['nom' => 'Commercial 2 — Bouaké (à nommer)', 'activite' => 'Carrosserie', 'objectif_mensuel' => 2_500_000],
            ],
            'AB1' => [
                ['nom' => 'K. Aya', 'activite' => 'Mécanique', 'objectif_mensuel' => 6_000_000],
                ['nom' => 'M. Koffi', 'activite' => 'Carrosserie', 'objectif_mensuel' => 5_000_000],
            ],
            'AB2' => [
                ['nom' => "R. N'Guessan", 'activite' => 'Mécanique', 'objectif_mensuel' => 6_000_000],
                ['nom' => 'F. Touré', 'activite' => 'Carrosserie', 'objectif_mensuel' => 4_500_000],
            ],
            'SPD' => [
                ['nom' => 'Y. Kouamé', 'activite' => 'Mécanique', 'objectif_mensuel' => 5_000_000],
                ['nom' => 'A. Gnaoré', 'activite' => 'Carrosserie', 'objectif_mensuel' => 4_000_000],
            ],
        ];

        $numeroCommercial = 0;

        foreach ($sites as $infoSite) {
            $responsable = User::create([
                'entreprise_id' => $entreprise->id,
                'name' => $infoSite['resp_nom'],
                'email' => $infoSite['resp_email'],
                'password' => 'password',
                'email_verified_at' => now(),
            ]);
            $responsable->assignRole('responsable_site');

            $site = Site::create([
                'entreprise_id' => $entreprise->id,
                'code' => $infoSite['code'],
                'nom' => $infoSite['nom'],
                'ville' => $infoSite['ville'],
                'commune' => $infoSite['commune'],
                'telephone' => $infoSite['telephone'],
                'adresse' => $infoSite['adresse'],
                'couleur' => $infoSite['couleur'],
                'responsable_id' => $responsable->id,
                'est_actif' => true,
            ]);

            foreach ($commerciauxParSite[$infoSite['code']] as $infoCommercial) {
                $numeroCommercial++;
                $slug = str($infoCommercial['nom'])->before(' (')->slug();

                $utilisateurCommercial = User::create([
                    'entreprise_id' => $entreprise->id,
                    'name' => $infoCommercial['nom'],
                    'email' => $slug.'@artisan-automobile.ci',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]);
                $utilisateurCommercial->assignRole('commercial');

                Commercial::create([
                    'entreprise_id' => $entreprise->id,
                    'site_id' => $site->id,
                    'user_id' => $utilisateurCommercial->id,
                    'numero' => 'C-'.str_pad((string) $numeroCommercial, 4, '0', STR_PAD_LEFT),
                    'nom' => $infoCommercial['nom'],
                    'activite' => $infoCommercial['activite'],
                    'objectif_mensuel' => $infoCommercial['objectif_mensuel'],
                    'statut' => 'Actif',
                    'est_spontane' => false,
                ]);
            }

            Commercial::create([
                'entreprise_id' => $entreprise->id,
                'site_id' => $site->id,
                'numero' => 'SP-'.$site->code,
                'nom' => 'Client spontané',
                'activite' => null,
                'objectif_mensuel' => 0,
                'statut' => 'Actif',
                'est_spontane' => true,
            ]);
        }

        // Aligne le compteur de numérotation des commerciaux sur ceux déjà seedés manuellement,
        // pour que les prochains comptes créés via l'application n'entrent pas en collision.
        CompteurDocument::create([
            'entreprise_id' => $entreprise->id,
            'type' => 'com',
            'dernier_numero' => $numeroCommercial,
        ]);
    }
}
