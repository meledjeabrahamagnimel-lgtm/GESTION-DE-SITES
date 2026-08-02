<?php

namespace Database\Seeders;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Entreprise pilote « L'Artisan Automobile » : sites, comptes et commerciaux de démonstration,
 * avec la charte de couleurs exacte de la maquette.
 */
class EntrepriseArtisanAutomobileSeeder extends Seeder
{
    public function run(): void
    {
        $cheminLogo = 'logos/artisan-automobile.png';
        Storage::disk('public')->put(
            $cheminLogo,
            file_get_contents(__DIR__.'/assets/artisan-automobile-logo.png')
        );

        $entreprise = Entreprise::create([
            'nom' => "L'Artisan Automobile",
            'slug' => 'artisan-automobile',
            'logo_chemin' => $cheminLogo,
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
            ['code' => 'BKE', 'nom' => 'Bouaké', 'couleur' => '#7C3AED', 'resp_nom' => 'David K.', 'resp_email' => 'david.k@artisan-automobile.ci'],
            ['code' => 'AB1', 'nom' => 'Abidjan — Site 1', 'couleur' => '#2563EB', 'resp_nom' => 'Responsable Site 1', 'resp_email' => 'resp.site1@artisan-automobile.ci'],
            ['code' => 'AB2', 'nom' => 'Abidjan — Site 2', 'couleur' => '#059669', 'resp_nom' => 'Responsable Site 2', 'resp_email' => 'resp.site2@artisan-automobile.ci'],
            ['code' => 'SPD', 'nom' => 'San Pedro', 'couleur' => '#D97706', 'resp_nom' => 'Rama Gaiho', 'resp_email' => 'rama.gaiho@artisan-automobile.ci'],
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
                'doit_changer_mot_de_passe' => true,
            ]);
            $responsable->assignRole('responsable_site');

            $site = Site::create([
                'entreprise_id' => $entreprise->id,
                'code' => $infoSite['code'],
                'nom' => $infoSite['nom'],
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
                    'doit_changer_mot_de_passe' => true,
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
    }
}
