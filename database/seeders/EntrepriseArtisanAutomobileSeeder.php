<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\CompteurDocument;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Exercice;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Entreprise pilote « L'Artisan Automobile » : un jeu de démonstration volontairement
 * minimal — une seule ville (Abidjan) avec ses deux sites d'activité (Mécanique et
 * Sinistre) et un seul compte par rôle — pour permettre des tests rapides de bout en
 * bout sur les quatre interfaces (Gérant, Responsable, Commercial, Caissier).
 */
class EntrepriseArtisanAutomobileSeeder extends Seeder
{
    public function run(): void
    {
        // Réexécution sur une base déjà peuplée : on ne recrée rien.
        if (Entreprise::where('slug', 'artisan-automobile')->exists()) {
            $this->command?->warn("L'entreprise pilote existe déjà — étape ignorée.");

            return;
        }

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
            'gerant_email' => 'gerant@gmail.com',
            'adresse' => 'Zone industrielle de Yopougon, ABIDJAN, CÔTE D\'IVOIRE',
            'telephone' => '+225 27 23 45 67 89',
            'email' => 'gerant@gmail.com',
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
            'name' => 'Jean-Baptiste Kouassi',
            'email' => 'gerant@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $gerant->assignRole('gerant');

        $responsable = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Marie-Claire Aya',
            'email' => 'responsable@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsable->assignRole('responsable_site');

        // La ville Abidjan : le responsable en a la charge entière, donc de ses deux sites.
        $ville = Ville::create([
            'entreprise_id' => $entreprise->id,
            'code' => 'ABJ',
            'nom' => 'Abidjan',
            'commune' => 'Yopougon',
            'telephone' => '+225 27 23 45 67 90',
            'adresse' => 'Zone industrielle, Rue des Artisans',
            'couleur' => '#2563EB',
            'responsable_id' => $responsable->id,
            'est_actif' => true,
        ]);

        $siteMecanique = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'nom' => 'Abidjan — Mécanique',
            'activite' => 'Mécanique',
            'est_actif' => true,
        ]);

        $siteSinistre = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'nom' => 'Abidjan — Sinistre',
            'activite' => 'Sinistre',
            'est_actif' => true,
        ]);

        $commercial = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Koffi Yao',
            'email' => 'commercial@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $commercial->assignRole('commercial');

        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'site_id' => $siteMecanique->id,
            'user_id' => $commercial->id,
            'numero' => 'C-0001',
            'nom' => 'Koffi Yao',
            'activite' => 'Mécanique/Sinistre',
            'objectif_mecanique' => 14_000_000,
            'objectif_sinistre' => 6_000_000,
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);

        // Vente sans commercial nommé : un par site, comme dans le reste de l'application.
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'site_id' => $siteMecanique->id,
            'numero' => 'SP-MEC',
            'nom' => 'Client spontané',
            'activite' => null,
            'objectif_mensuel' => 0,
            'statut' => 'Actif',
            'est_spontane' => true,
        ]);

        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'site_id' => $siteSinistre->id,
            'numero' => 'SP-SIN',
            'nom' => 'Client spontané',
            'activite' => null,
            'objectif_mensuel' => 0,
            'statut' => 'Actif',
            'est_spontane' => true,
        ]);

        // Le caissier est rattaché au site Mécanique précisément (et non à toute la ville),
        // pour tester les deux formes de rattachement prévues (site ou ville entière).
        $caissier = User::create([
            'entreprise_id' => $entreprise->id,
            'site_id' => $siteMecanique->id,
            'name' => 'Fatou Diabaté',
            'email' => 'caissier@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $caissier->assignRole('caissier');

        // Aligne le compteur de numérotation des commerciaux sur celui déjà seedé
        // manuellement, pour que les prochains comptes créés via l'application n'entrent
        // pas en collision.
        CompteurDocument::create([
            'entreprise_id' => $entreprise->id,
            'type' => 'com',
            'dernier_numero' => 1,
        ]);

        // L'exercice de l'année en cours, ouvert : sans lui, le badge d'en-tête reste
        // muet et rien n'illustre le mécanisme de clôture par ville en démonstration.
        Exercice::create([
            'entreprise_id' => $entreprise->id,
            'annee' => now()->year,
            'statut' => 'Ouvert',
            'est_defaut' => true,
        ]);
    }
}
