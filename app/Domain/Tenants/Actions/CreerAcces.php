<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Création d'un accès au sein d'une entreprise, quel qu'en soit le rôle. Utilisée par
 * le Gérant, par les Responsables (qui créent Commerciaux et Comptabilité) et par le
 * Super Admin (n'importe quel rôle, dans n'importe quelle entreprise).
 *
 * Le périmètre attendu dans $donnees dépend du rôle :
 * - responsable_ville, commercial, caissier → `ville_id` (la comptabilité et les
 *   commerciaux travaillent pour une ville entière, pas pour un lieu précis) ;
 * - responsable_site → `site_id`, le lieu dont il répond ;
 * - gerant → aucun, son périmètre étant l'entreprise.
 */
class CreerAcces
{
    /** Rôles dont le titulaire prospecte aussi : il doit donc exister comme commercial. */
    private const ROLES_COMMERCIAUX = ['responsable_ville', 'responsable_site', 'commercial'];

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

            $ville = $this->affecterPerimetre($entreprise, $utilisateur, $role, $donnees);

            if (in_array($role, self::ROLES_COMMERCIAUX, true) && $ville) {
                $this->creerFicheCommercial($entreprise, $utilisateur, $ville, $donnees);
            }

            return $utilisateur;
        });
    }

    /**
     * Rattache l'accès à son périmètre et renvoie la ville qui en découle.
     *
     * Un responsable est rattaché en se voyant désigné sur sa ville ou son lieu
     * (`responsable_id`) ; la comptabilité, elle, porte son rattachement sur son propre
     * compte (`users.ville_id`), plusieurs comptables pouvant partager la même ville.
     */
    private function affecterPerimetre(Entreprise $entreprise, User $utilisateur, string $role, array $donnees): ?Ville
    {
        if ($role === 'responsable_site') {
            $site = Site::where('id', $donnees['site_id'] ?? null)->where('entreprise_id', $entreprise->id)->firstOrFail();
            $site->update(['responsable_id' => $utilisateur->id]);

            return $site->ville;
        }

        if (! in_array($role, ['responsable_ville', 'commercial', 'caissier'], true)) {
            return null;
        }

        $ville = Ville::where('id', $donnees['ville_id'] ?? null)->where('entreprise_id', $entreprise->id)->firstOrFail();

        if ($role === 'responsable_ville') {
            $ville->update(['responsable_id' => $utilisateur->id]);
        }

        if ($role === 'caissier') {
            $utilisateur->update(['ville_id' => $ville->id, 'site_id' => null]);
        }

        return $ville;
    }

    /**
     * Un responsable de ville ou de site prospecte lui aussi : il doit donc figurer
     * parmi les commerciaux, avec ses propres objectifs, sans quoi ni ses prospections
     * ni son chiffre d'affaires ne seraient rattachables à quiconque.
     */
    private function creerFicheCommercial(Entreprise $entreprise, User $utilisateur, Ville $ville, array $donnees): void
    {
        Commercial::create([
            'entreprise_id' => $entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $utilisateur->id,
            'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
            'nom' => $donnees['nom'],
            'objectif_mecanique' => (int) ($donnees['objectif_mecanique'] ?? 0),
            'objectif_sinistre' => (int) ($donnees['objectif_sinistre'] ?? 0),
            'statut' => 'Actif',
            'est_spontane' => false,
        ]);
    }
}
