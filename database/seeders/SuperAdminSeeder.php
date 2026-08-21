<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Équipe plateforme : rôle super_admin scopé à l'équipe conventionnelle 0
 * (aucune entreprise réelle ne porte cet id), car le rôle n'appartient à aucun tenant.
 */
class SuperAdminSeeder extends Seeder
{
    public const EQUIPE_PLATEFORME = 0;

    public function run(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(self::EQUIPE_PLATEFORME);

        Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'entreprise_id' => self::EQUIPE_PLATEFORME,
        ]);

        /*
         * L'adresse est publique, le mot de passe ne l'est pas.
         *
         * Celui de la plateforme en service se pose avec `php artisan
         * superadmin:identifiants`, jamais ici : un mot de passe réel écrit dans un
         * fichier suivi par git se retrouve dans l'historique du dépôt, sur chaque poste
         * qui l'a cloné, et le changer plus tard ne l'en retire pas. Le repli ci-dessous
         * ne sert qu'à une installation neuve de développement.
         */
        $superAdmin = User::firstOrCreate(['email' => env('SUPER_ADMIN_EMAIL', 'it.dcknowing@gmail.com')], [
            'entreprise_id' => null,
            'name' => 'Super Admin DC-KNOWING',
            'password' => env('SUPER_ADMIN_MOT_DE_PASSE', 'password'),
            'email_verified_at' => now(),
        ]);

        // Compte fondateur : toutes les sections, et intouchable par les secondaires.
        if (! $superAdmin->est_fondateur) {
            $superAdmin->update(['est_fondateur' => true]);
        }

        $superAdmin->assignRole('super_admin');
    }
}
