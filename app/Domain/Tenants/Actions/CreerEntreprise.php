<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Inscription publique d'une nouvelle entreprise cliente : crée le tenant,
 * ses rôles, et le premier compte Gérant qui pourra ensuite configurer ses sites et son équipe.
 */
class CreerEntreprise
{
    public function executer(array $donnees): User
    {
        return DB::transaction(function () use ($donnees) {
            $entreprise = Entreprise::create([
                'nom' => $donnees['entreprise_nom'],
            ]);

            ProvisionneurEntreprise::creerRoles($entreprise);
            app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

            Site::create([
                'entreprise_id' => $entreprise->id,
                'code' => 'S1',
                'nom' => $donnees['site_nom'],
                'est_actif' => true,
            ]);

            $gerant = User::create([
                'entreprise_id' => $entreprise->id,
                'name' => $donnees['gerant_nom'],
                'email' => $donnees['gerant_email'],
                'password' => $donnees['gerant_mot_de_passe'],
                'email_verified_at' => now(),
            ]);
            $gerant->assignRole('gerant');

            return $gerant;
        });
    }
}
