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
 * Création d'un accès (Gérant, Responsable de site, Commercial ou Caissier) au sein
 * d'une entreprise. Utilisée par le Gérant (crée Responsables/Commerciaux/Caissiers),
 * le Responsable (crée des Commerciaux/Caissiers) et le Super Admin (crée n'importe
 * quel rôle, dans n'importe quelle entreprise).
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
                $this->affecterPerimetre($entreprise, $utilisateur, $donnees['perimetre']);
            }

            if ($role === 'caissier') {
                $this->affecterPerimetre($entreprise, $utilisateur, $donnees['perimetre'], surUtilisateur: true);
            }

            if ($role === 'commercial') {
                $ville = Ville::where('id', $donnees['ville_id'])->where('entreprise_id', $entreprise->id)->firstOrFail();
                $objectifMecanique = (int) ($donnees['objectif_mecanique'] ?? 0);
                $objectifSinistre = (int) ($donnees['objectif_sinistre'] ?? 0);

                Commercial::create([
                    'entreprise_id' => $entreprise->id,
                    'ville_id' => $ville->id,
                    'user_id' => $utilisateur->id,
                    'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
                    'nom' => $donnees['nom'],
                    'objectif_mecanique' => $objectifMecanique,
                    'objectif_sinistre' => $objectifSinistre,
                    'statut' => 'Actif',
                    'est_spontane' => false,
                ]);
            }

            return $utilisateur;
        });
    }

    /**
     * Affecte un responsable ou un caissier à son périmètre : une ville entière (les
     * deux sites) ou un site précis (une seule activité), selon la valeur choisie dans
     * la liste déroulante — au format "ville:<id>" ou "site:<id>".
     *
     * Le responsable est rattaché en marquant la ville/le site comme le sien
     * (`responsable_id`) ; le caissier, lui, porte directement son rattachement sur son
     * propre compte (`users.ville_id`/`site_id`), car plusieurs caissiers peuvent
     * partager le même site.
     */
    private function affecterPerimetre(Entreprise $entreprise, User $utilisateur, string $perimetre, bool $surUtilisateur = false): void
    {
        [$type, $id] = explode(':', $perimetre, 2);

        if ($surUtilisateur) {
            $utilisateur->update($type === 'ville' ? ['ville_id' => $id, 'site_id' => null] : ['site_id' => $id, 'ville_id' => null]);

            return;
        }

        if ($type === 'ville') {
            Ville::where('id', $id)->where('entreprise_id', $entreprise->id)->update(['responsable_id' => $utilisateur->id]);

            return;
        }

        Site::where('id', $id)->where('entreprise_id', $entreprise->id)->update(['responsable_id' => $utilisateur->id]);
    }
}
