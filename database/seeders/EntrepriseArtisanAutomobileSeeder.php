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
 * Entreprise pilote « L'Artisan Automobile », volet Abidjan : la seule ville à compter
 * deux lieux distincts, ce qui en fait le terrain de test complet — un superviseur de
 * ville qui couvre les deux, un responsable nommé sur chacun d'eux, une comptabilité
 * pour toute la ville, un commercial, et le gérant de l'entreprise.
 *
 * Bouaké et San Pedro, à un seul lieu chacune, sont installées ensuite par
 * VillesBouakeSanPedroSeeder.
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
            'email' => 'superviseurabidjan@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsable->assignRole('responsable_ville');

        // Abidjan comptant deux lieux, chacun a son propre responsable, qui ne répond que
        // du sien — le superviseur de ville, lui, couvre les deux.
        $responsableSiteUn = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Sylvain Kouassi',
            'email' => 'responsableabidjansite1@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsableSiteUn->assignRole('responsable_site');

        $responsableSiteDeux = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Estelle Kacou',
            'email' => 'responsableabidjansite2@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $responsableSiteDeux->assignRole('responsable_site');

        // La ville Abidjan : le responsable de ville en a la charge entière, donc de ses deux lieux.
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

        // Abidjan est la seule ville à compter deux lieux distincts. Chacun accueille les
        // deux activités : c'est l'opération, jamais le lieu, qui porte Mécanique ou Sinistre.
        $siteUn = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'code' => 'ABJ-1',
            'nom' => 'Abidjan — Site 1',
            'responsable_id' => $responsableSiteUn->id,
            'est_actif' => true,
        ]);

        $siteDeux = Site::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'code' => 'ABJ-2',
            'nom' => 'Abidjan — Site 2',
            'responsable_id' => $responsableSiteDeux->id,
            'est_actif' => true,
        ]);

        $commercial = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Koffi Yao',
            'email' => 'commercialabidjan@gmail.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $commercial->assignRole('commercial');

        // Le rattachement est porté par le compte lui-même, pas seulement par la fiche :
        // c'est ce qui permet de le retrouver après une purge des données.
        $commercial->update(['ville_id' => $ville->id]);
        $responsable->update(['ville_id' => $ville->id]);
        $responsableSiteUn->update(['ville_id' => $ville->id, 'site_id' => $siteUn->id]);
        $responsableSiteDeux->update(['ville_id' => $ville->id, 'site_id' => $siteDeux->id]);

        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $commercial->id,
            'numero' => 'C-0001',
            'nom' => 'Koffi Yao',
            'objectif_mecanique' => 14_000_000,
            'objectif_sinistre' => 6_000_000,
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);

        // Les responsables prospectent eux aussi : ils doivent donc figurer parmi les
        // commerciaux, sans quoi ni leurs prospections ni leur chiffre d'affaires ne
        // seraient rattachables à quiconque.
        foreach ([
            ['C-0002', $responsable, 9_000_000, 4_000_000],
            ['C-0003', $responsableSiteUn, 7_000_000, 3_000_000],
            ['C-0004', $responsableSiteDeux, 7_000_000, 3_000_000],
        ] as [$numero, $encadrant, $mecanique, $sinistre]) {
            Commercial::create([
                'entreprise_id' => $entreprise->id,
                'ville_id' => $ville->id,
                'user_id' => $encadrant->id,
                'numero' => $numero,
                'nom' => $encadrant->name,
                'objectif_mecanique' => $mecanique,
                'objectif_sinistre' => $sinistre,
                'statut' => 'Actif',
                'est_spontane' => false,
            ]);
        }

        // Vente sans commercial nommé : un seul par ville, celui-ci couvrant les deux
        // activités comme tout commercial.
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'numero' => 'SP-ABJ',
            'nom' => 'Client spontané',
            'objectif_mensuel' => 0,
            'statut' => 'Actif',
            'est_spontane' => true,
        ]);

        // La comptabilité couvre une ville entière, jamais un lieu : une seule caisse
        // par ville, quel que soit le nombre de sites qui s'y trouvent.
        $caissier = User::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'name' => 'Fatou Diabaté',
            'email' => 'comptabiliteabidjansite2@gmail.com',
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
            'dernier_numero' => 4,
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
