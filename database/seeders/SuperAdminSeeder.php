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

        $superAdmin = User::firstOrCreate(['email' => 'superadmin@plateforme.local'], [
            'entreprise_id' => null,
            'name' => 'Super Admin',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        // Compte fondateur : toutes les sections, et intouchable par les secondaires.
        if (! $superAdmin->est_fondateur) {
            $superAdmin->update(['est_fondateur' => true]);
        }

        $superAdmin->assignRole('super_admin');
    }
}
