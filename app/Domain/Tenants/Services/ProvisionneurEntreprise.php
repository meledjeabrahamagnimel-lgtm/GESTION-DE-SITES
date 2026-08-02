<?php

namespace App\Domain\Tenants\Services;

use App\Domain\Tenants\Models\Entreprise;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Prépare une entreprise fraîchement créée : ses rôles (scopés à son équipe).
 * Rôles disponibles pour toute entreprise : gerant, responsable_site, commercial.
 * Le rôle super_admin, lui, vit hors entreprise sur l'équipe plateforme (id 0).
 */
class ProvisionneurEntreprise
{
    public const ROLES = ['gerant', 'responsable_site', 'commercial'];

    public static function creerRoles(Entreprise $entreprise): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        foreach (self::ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
                'entreprise_id' => $entreprise->id,
            ]);
        }
    }
}
