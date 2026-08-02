<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Création d'un accès (Gérant, Responsable de site ou Commercial) au sein d'une entreprise.
 * Utilisée par le Gérant (crée Responsables/Commerciaux), le Responsable (crée des Commerciaux)
 * et le Super Admin (crée n'importe quel rôle, dans n'importe quelle entreprise).
 */
class CreerAcces
{
    public function executer(Entreprise $entreprise, string $role, array $donnees): User
    {
        return DB::transaction(function () use ($entreprise, $role, $donnees) {
            $utilisateur = User::create([
                'entreprise_id' => $entreprise->id,
                'name' => $donnees['nom'],
                'email' => $donnees['email'],
                'password' => $donnees['mot_de_passe'],
                'telephone' => $donnees['telephone'] ?? null,
                'email_verified_at' => now(),
                // Un accès créé par un administrateur impose un changement de mot de passe ;
                // une inscription volontaire (par code entreprise) non, le mot de passe étant déjà choisi.
                'doit_changer_mot_de_passe' => $donnees['doit_changer_mot_de_passe'] ?? true,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);
            $utilisateur->assignRole($role);

            if ($role === 'responsable_site') {
                Site::where('id', $donnees['site_id'])
                    ->where('entreprise_id', $entreprise->id)
                    ->update(['responsable_id' => $utilisateur->id]);
            }

            if ($role === 'commercial') {
                Commercial::create([
                    'entreprise_id' => $entreprise->id,
                    'site_id' => $donnees['site_id'],
                    'user_id' => $utilisateur->id,
                    'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
                    'nom' => $donnees['nom'],
                    'activite' => $donnees['activite'],
                    'objectif_mensuel' => $donnees['objectif_mensuel'] ?? 0,
                    'statut' => 'Actif',
                    'est_spontane' => false,
                ]);
            }

            return $utilisateur;
        });
    }
}
