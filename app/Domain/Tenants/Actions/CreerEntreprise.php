<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Inscription publique d'une nouvelle entreprise cliente : crée le tenant avec ses
 * informations légales et fiscales, ses rôles, et le premier compte Gérant.
 * Les sites et le personnel sont créés aux étapes suivantes de l'assistant.
 */
class CreerEntreprise
{
    public function executer(array $donnees): User
    {
        return DB::transaction(function () use ($donnees) {
            $entreprise = Entreprise::create([
                'nom' => $donnees['nom'],
                'code_entreprise' => $donnees['code_entreprise'] ?? Entreprise::genererCode($donnees['nom']),
                'gerant_nom' => $donnees['gerant_nom'] ?? null,
                'gerant_prenom' => $donnees['gerant_prenom'] ?? null,
                'gerant_fonction' => $donnees['gerant_fonction'] ?? 'Gérant',
                'gerant_email' => $donnees['gerant_email'] ?? null,
                'adresse' => $donnees['adresse'] ?? null,
                'telephone' => $donnees['telephone'] ?? null,
                'email' => $donnees['email'] ?? null,
                'rccm' => $donnees['rccm'] ?? null,
                'ncc' => $donnees['ncc'] ?? null,
                'regime_imposition' => $donnees['regime_imposition'] ?? null,
                'centre_impots' => $donnees['centre_impots'] ?? null,
                'compte_contribuable' => $donnees['compte_contribuable'] ?? null,
                'idu' => $donnees['idu'] ?? null,
                'commune' => $donnees['commune'] ?? null,
                'quartier' => $donnees['quartier'] ?? null,
                'reference_cadastrale' => $donnees['reference_cadastrale'] ?? null,
                'proprietaire_local' => $donnees['proprietaire_local'] ?? null,
            ]);

            ProvisionneurEntreprise::creerRoles($entreprise);
            app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

            $gerant = User::create([
                'entreprise_id' => $entreprise->id,
                'name' => trim(($donnees['gerant_prenom'] ?? '').' '.($donnees['gerant_nom'] ?? '')) ?: $donnees['nom'],
                'email' => $donnees['compte_email'],
                'password' => $donnees['compte_mot_de_passe'],
                'telephone' => $donnees['telephone'] ?? null,
                'email_verified_at' => now(),
            ]);
            $gerant->assignRole('gerant');

            return $gerant;
        });
    }
}
