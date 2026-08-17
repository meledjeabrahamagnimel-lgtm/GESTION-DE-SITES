<?php

namespace Modules\Noyau\Entreprises\Actions;

use Modules\Noyau\Exploitation\Modeles\Charge;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\CompteurDocument;
use Modules\Noyau\Exploitation\Modeles\Devis;
use Modules\Noyau\Exploitation\Modeles\Encaissement;
use Modules\Noyau\Exploitation\Modeles\Facture;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Modules\Noyau\Exploitation\Modeles\SaisieJournaliere;
use Modules\Noyau\Exploitation\Services\GenerateurNumero;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purge des données d'exploitation d'une entreprise (jeux de test).
 *
 * Réservé au Super Admin. Par défaut, les comptes, les lieux et la fiche entreprise
 * sont conservés : seules les écritures et les compteurs de numérotation repartent à
 * zéro. Deux options élargissent le geste, chacune explicitement demandée — effacer
 * les fiches commerciales, et effacer les accès sauf ceux des gérants.
 */
class PurgerDonneesEntreprise
{
    /** Rôles dont le titulaire prospecte : sa fiche commercial fait partie de l'organisation, pas des écritures. */
    private const ROLES_COMMERCIAUX = ['responsable_ville', 'responsable_site', 'commercial'];

    /** @return array<string,int> nombre de lignes supprimées par table */
    public function executer(Entreprise $entreprise, bool $purgerCommerciaux = false, bool $purgerAcces = false): array
    {
        return DB::transaction(function () use ($entreprise, $purgerCommerciaux, $purgerAcces) {
            $id = $entreprise->id;
            $compte = [];

            // Les informations libres pendent aux écritures : les laisser en place
            // ferait des orphelines, rattachées à des lignes qui n'existent plus.
            $compte['informations_libres'] = DB::table('donnees_libres')->where('entreprise_id', $id)->delete();

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

            // Les accès partent avant que les fiches ne soient reconstituées, sinon on
            // en recréerait pour des comptes qu'on s'apprête à supprimer.
            if ($purgerAcces) {
                $compte['acces'] = $this->supprimerLesAcces($entreprise);
            }

            if ($purgerCommerciaux) {
                $compte['commerciaux_recrees'] = $this->reconstituerCommerciaux($entreprise);
            }

            return $compte;
        });
    }

    /**
     * Supprime les accès de l'entreprise, sauf ceux des gérants.
     *
     * Le gérant est épargné à dessein : c'est lui qui recréera les autres. Une
     * entreprise sans aucun accès ne se rouvre plus depuis l'application — il
     * faudrait repasser par le super administrateur pour chaque compte.
     *
     * Celui qui déclenche la purge est épargné lui aussi, même s'il n'est pas
     * gérant : se supprimer soi-même en cours de route couperait la session et
     * laisserait l'opération à mi-chemin.
     *
     * @return int nombre d'accès supprimés
     */
    private function supprimerLesAcces(Entreprise $entreprise): int
    {
        $comptes = User::where('entreprise_id', $entreprise->id)->get();
        $roles = User::nomsRolesParUtilisateur($comptes->pluck('id'));

        $aSupprimer = $comptes
            ->reject(fn (User $u) => str_contains($roles[$u->id] ?? '', 'gerant'))
            ->reject(fn (User $u) => $u->id === auth()->id())
            ->pluck('id')
            ->all();

        if (empty($aSupprimer)) {
            return 0;
        }

        // Les fiches commerciales de ces comptes partent avec eux : les laisser
        // rattacherait des prospections à venir à des gens qui ne travaillent plus là.
        Commercial::withoutGlobalScopes()->whereIn('user_id', $aSupprimer)->delete();

        // Rien ne doit plus les désigner, sinon la suppression bute sur une clef.
        DB::table('sites')->whereIn('responsable_id', $aSupprimer)->update(['responsable_id' => null]);
        DB::table('villes')->whereIn('responsable_id', $aSupprimer)->update(['responsable_id' => null]);
        DB::table('users')->whereIn('cree_par_id', $aSupprimer)->update(['cree_par_id' => null]);

        DB::table('notifications_app')->whereIn('user_id', $aSupprimer)->delete();
        DB::table('abonnements_push')->whereIn('user_id', $aSupprimer)->delete();
        DB::table('compteurs_auteur')->whereIn('user_id', $aSupprimer)->delete();
        DB::table('notes')->whereIn('user_id', $aSupprimer)->delete();
        DB::table('dossiers_notes')->whereIn('user_id', $aSupprimer)->delete();

        $morph = (new User)->getMorphClass();
        DB::table('model_has_roles')->whereIn('model_id', $aSupprimer)->where('model_type', $morph)->delete();
        DB::table('model_has_permissions')->whereIn('model_id', $aSupprimer)->where('model_type', $morph)->delete();

        return User::whereIn('id', $aSupprimer)->delete();
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
