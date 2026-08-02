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

        $superAdmin = User::create([
            'entreprise_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@plateforme.local',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $superAdmin->assignRole('super_admin');
    }
}
