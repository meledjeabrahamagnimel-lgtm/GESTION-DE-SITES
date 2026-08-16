<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\CompteurDocument;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Operations\Services\GenerateurNumero;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Ville;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purge des données d'exploitation d'une entreprise (jeux de test).
 * Réservé au Super Admin. Les comptes, les sites et la fiche entreprise sont conservés :
 * seules les écritures et les compteurs de numérotation sont remis à zéro.
 */
class PurgerDonneesEntreprise
{
    /** Rôles dont le titulaire prospecte : sa fiche commercial fait partie de l'organisation, pas des écritures. */
    private const ROLES_COMMERCIAUX = ['responsable_ville', 'responsable_site', 'commercial'];

    /** @return array<string,int> nombre de lignes supprimées par table */
    public function executer(Entreprise $entreprise, bool $purgerCommerciaux = false): array
    {
        return DB::transaction(function () use ($entreprise, $purgerCommerciaux) {
            $id = $entreprise->id;
            $compte = [];

            // L'ordre suit les dépendances : encaissements -> factures -> devis -> prospections.
            $compte['encaissements'] = Encaissement::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['factures'] = Facture::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['devis'] = Devis::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['prospections'] = Prospection::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['charges'] = Charge::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['saisies_journalieres'] = SaisieJournaliere::withoutGlobalScopes()->where('entreprise_id', $id)->delete();

            if ($purgerCommerciaux) {
                $compte['commerciaux'] = Commercial::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            }

            // Remise à zéro de la numérotation pour repartir sur P-0001, D-0001, F-0001.
            $compte['compteurs'] = CompteurDocument::withoutGlobalScopes()
                ->where('entreprise_id', $id)
                ->when(! $purgerCommerciaux, fn ($q) => $q->where('type', '!=', 'com'))
                ->delete();

            if ($purgerCommerciaux) {
                $compte['commerciaux_recrees'] = $this->reconstituerCommerciaux($entreprise);
            }

            return $compte;
        });
    }

    /**
     * Une entreprise vidée de ses commerciaux ne peut plus rien saisir : la liste
     * déroulante « Commercial » est vide et le « Client spontané » a disparu avec le
     * reste. Or ces fiches relèvent de l'organisation, pas des écritures — elles sont
     * donc reconstituées à l'identique après la purge, remises à zéro d'objectifs.
     *
     * @return int nombre de fiches recréées
     */
    private function reconstituerCommerciaux(Entreprise $entreprise): int
    {
        $recreees = 0;

        foreach (Ville::where('entreprise_id', $entreprise->id)->orderBy('id')->get() as $ville) {
            Commercial::create([
                'entreprise_id' => $entreprise->id,
                'ville_id' => $ville->id,
                'numero' => 'SP-'.$ville->code,
                'nom' => 'Client spontané',
                'objectif_mensuel' => 0,
                'statut' => 'Actif',
                'est_spontane' => true,
            ]);
            $recreees++;
        }

        $utilisateurs = User::where('entreprise_id', $entreprise->id)->where('est_actif', true)->orderBy('id')->get();
        $roles = User::nomsRolesParUtilisateur($utilisateurs->pluck('id'));

        foreach ($utilisateurs as $utilisateur) {
            $villeId = $this->villeDe($utilisateur, $entreprise);

            if (! $villeId || ! $this->prospecte($roles[$utilisateur->id] ?? '')) {
                continue;
            }

            Commercial::create([
                'entreprise_id' => $entreprise->id,
                'ville_id' => $villeId,
                'user_id' => $utilisateur->id,
                'numero' => GenerateurNumero::suivant($entreprise->id, 'com'),
                'nom' => $utilisateur->name,
                'objectif_mecanique' => 0,
                'objectif_sinistre' => 0,
                'statut' => 'Actif',
                'est_spontane' => false,
            ]);
            $recreees++;
        }

        return $recreees;
    }

    private function prospecte(string $roles): bool
    {
        foreach (self::ROLES_COMMERCIAUX as $role) {
            if (str_contains($roles, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ville à laquelle rattacher la fiche recréée. Le compte porte lui-même son
     * rattachement (`users.ville_id`), justement pour qu'il survive à la suppression de
     * la fiche ; la ville supervisée et celle du lieu confié servent de garde-fou pour
     * les comptes antérieurs à cet ancrage. Aucun repli arbitraire : sans rattachement
     * connu, mieux vaut ne pas recréer de fiche que d'affecter quelqu'un au hasard.
     */
    private function villeDe(User $utilisateur, Entreprise $entreprise): ?int
    {
        if ($utilisateur->ville_id) {
            return (int) $utilisateur->ville_id;
        }

        $villeSupervisee = Ville::where('entreprise_id', $entreprise->id)->where('responsable_id', $utilisateur->id)->value('id');

        if ($villeSupervisee) {
            return (int) $villeSupervisee;
        }

        $villeDuSite = DB::table('sites')->where('entreprise_id', $entreprise->id)
            ->where('responsable_id', $utilisateur->id)->value('ville_id');

        return $villeDuSite ? (int) $villeDuSite : null;
    }
}
