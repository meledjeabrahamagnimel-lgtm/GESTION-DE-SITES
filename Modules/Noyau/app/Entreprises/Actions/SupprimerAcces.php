<?php

namespace Modules\Noyau\Entreprises\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Noyau\Entreprises\Support\HierarchieAcces;
use Modules\Noyau\Exploitation\Modeles\Commercial;

/**
 * Supprime un accès — le compte d'une personne, actif ou non.
 *
 * Deux choses en découlent, qu'il ne faut pas confondre : la personne perd son moyen
 * d'entrer, mais ce qu'elle a saisi reste. Une prospection appartient à l'entreprise,
 * pas à celui qui l'a tapée ; l'effacer avec lui trouerait les chiffres d'un exercice
 * clos et falsifierait un chiffre d'affaires déjà déclaré.
 *
 * D'où le traitement de la fiche commerciale. Tant qu'elle porte des écritures, elle
 * est détachée du compte et passée en Inactif : les prospections gardent leur auteur,
 * les tableaux cessent de la proposer. Elle n'est réellement supprimée que si elle n'a
 * jamais rien porté — auquel cas la garder n'encombrerait les listes pour rien.
 *
 * Le reste part de lui-même : messages, notes, notifications, abonnements et compteurs
 * sont déclarés en cascade dans le schéma, et le journal d'activité survit — supprimer
 * quelqu'un ne doit pas effacer la trace de ce qu'il a fait.
 */
class SupprimerAcces
{
    /**
     * Vrai si $acteur peut supprimer $cible. Les refus sont explicités par motifDuRefus().
     */
    public function autorise(User $acteur, User $cible): bool
    {
        return $this->motifDuRefus($acteur, $cible) === null;
    }

    /** Le motif du refus, ou null si la suppression est permise. */
    public function motifDuRefus(User $acteur, User $cible): ?string
    {
        if ($acteur->id === $cible->id) {
            return 'Vous ne pouvez pas supprimer votre propre accès : la session en cours serait coupée à mi-chemin.';
        }

        // Le reste — hiérarchie, entreprise, ville — est la même question que pour
        // activer ou révoquer : elle se tranche à un seul endroit.
        return HierarchieAcces::motifDuRefus($acteur, $cible);
    }

    /**
     * @return array<string, int|string> ce qui a été fait, pour l'annoncer sans deviner
     */
    public function executer(User $acteur, User $cible): array
    {
        $motif = $this->motifDuRefus($acteur, $cible);

        if ($motif !== null) {
            throw new \RuntimeException($motif);
        }

        $nom = $cible->name;
        $email = $cible->email;

        // La trace est écrite avant : la ligne du compte n'existera plus après, et une
        // preuve qui disparaît avec son sujet ne prouve plus rien.
        activity()
            ->causedBy($acteur)
            ->withProperties(['nom' => $nom, 'email' => $email, 'entreprise_id' => $cible->entreprise_id])
            ->log("Suppression de l'accès de $nom ($email)");

        return $this->effacer($cible);
    }

    /**
     * L'effacement lui-même, une fois la question du droit tranchée ailleurs.
     *
     * Séparé de executer() parce que la console n'a pas d'acteur au sens de
     * HierarchieAcces : personne n'y est « connecté », Spatie n'y a pas d'équipe posée,
     * et un contrôle de rôle y répondrait non pour tout le monde. Sur un serveur, le
     * droit d'agir est celui d'avoir l'accès au shell ; le reste — quoi effacer, dans
     * quel ordre, et ce qui doit survivre — est identique et ne se recopie pas.
     *
     * @return array<string, int|string>
     */
    public function effacer(User $cible): array
    {
        return DB::transaction(function () use ($cible) {
            $bilan = ['fiche commerciale' => 'aucune'];

            // Les désignations d'abord : une ville qui pointe encore son responsable
            // bloquerait la suppression, ou laisserait un responsable fantôme.
            DB::table('villes')->where('responsable_id', $cible->id)->update(['responsable_id' => null]);
            DB::table('sites')->where('responsable_id', $cible->id)->update(['responsable_id' => null]);
            DB::table('users')->where('cree_par_id', $cible->id)->update(['cree_par_id' => null]);

            $bilan['fiche commerciale'] = $this->traiterLaFicheCommerciale($cible);

            // Spatie relie les rôles par un morph sans clef étrangère : rien ne partirait
            // tout seul, et une ligne orpheline finirait par redonner le rôle à celui qui
            // hériterait de l'identifiant.
            $morph = (new User)->getMorphClass();
            DB::table('model_has_roles')->where('model_type', $morph)->where('model_id', $cible->id)->delete();
            DB::table('model_has_permissions')->where('model_type', $morph)->where('model_id', $cible->id)->delete();

            $cible->delete();

            return $bilan;
        });
    }

    /** Détache la fiche si elle porte des écritures, la supprime si elle n'a jamais servi. */
    private function traiterLaFicheCommerciale(User $cible): string
    {
        return Commercial::withoutGlobalScopes()->where('user_id', $cible->id)->first()
            ?->retirerDuService() ?? 'aucune';
    }
}
