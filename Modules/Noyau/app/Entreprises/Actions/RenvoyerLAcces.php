<?php

namespace Modules\Noyau\Entreprises\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Support\HierarchieAcces;
use Modules\Noyau\Entreprises\Support\LibellesRoles;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renvoie à quelqu'un le courriel qui lui ouvre son accès.
 *
 * Un courriel qui ne part pas ne fait pas de bruit. L'accès est ouvert du côté de
 * l'administrateur, la personne n'a rien reçu, et chacun attend l'autre — parfois des
 * semaines, jusqu'à ce que quelqu'un pense à demander. Ce renvoi existe pour couper
 * court : un bouton, et le message repart.
 *
 * Deux situations, et elles n'appellent pas le même geste :
 *
 *   1. **L'accès n'a jamais servi.** C'est le cas visé. Le compte est ouvert s'il ne
 *      l'était pas, et le courriel emporte un lien signé vers l'écran où l'on choisit
 *      son mot de passe — le même qu'à la création. La personne entre et le définit.
 *
 *   2. **L'accès a déjà servi.** Quelqu'un travaille dessus, avec un mot de passe qu'il
 *      a choisi. Le courriel repart alors en simple rappel, vers la page de connexion, et
 *      **rien n'est modifié** : ni le mot de passe, ni l'obligation d'en changer, ni le
 *      rôle, ni le périmètre, ni la fiche commerciale. Forcer un changement de mot de
 *      passe à quelqu'un qui saisit tous les jours, pour lui renvoyer un message qu'il
 *      n'avait pas demandé, serait lui couper l'accès pour lui rendre service.
 *
 * Dans les deux cas, aucune donnée métier n'est touchée. Le renvoi ne re-provisionne
 * rien : il ne recrée pas la fiche commerciale, ne réattribue pas les objectifs, ne
 * repose pas le rattachement. Il envoie un courriel, et c'est tout.
 */
class RenvoyerLAcces
{
    /** Le motif du refus, ou null si le renvoi est permis. */
    public function motifDuRefus(User $acteur, User $cible): ?string
    {
        if (! $cible->entreprise_id) {
            // Le courriel d'accueil parle au nom d'une entreprise — son logo, son nom,
            // son périmètre. Un compte de plateforme n'en a pas : il n'y a rien à écrire.
            return "Ce compte n'appartient à aucune entreprise : le courriel d'accueil ne s'applique pas.";
        }

        return HierarchieAcces::motifDuRefus($acteur, $cible);
    }

    public function autorise(User $acteur, User $cible): bool
    {
        return $this->motifDuRefus($acteur, $cible) === null;
    }

    /**
     * @return array{active: bool, lien: string} ce qui a été fait, pour l'annoncer sans deviner
     */
    public function executer(User $acteur, User $cible): array
    {
        $motif = $this->motifDuRefus($acteur, $cible);

        if ($motif !== null) {
            throw new \RuntimeException($motif);
        }

        $entreprise = $cible->entreprise()->withoutGlobalScopes()->first();

        if (! $entreprise) {
            throw new \RuntimeException("L'entreprise de ce compte est introuvable.");
        }

        $aDejaServi = $this->aDejaServi($cible);
        $active = false;

        DB::transaction(function () use ($cible, $aDejaServi, &$active) {
            // Un accès préparé mais jamais ouvert s'ouvre : c'est précisément l'étape
            // dont on nous dit que le courriel ne lui est pas parvenu.
            if (! $cible->est_actif) {
                $cible->forceFill(['est_actif' => true])->save();
                $active = true;
            }

            /*
             * Le lien du courriel ne mène à l'écran de définition du mot de passe que si
             * le compte est marqué comme devant en choisir un. Sans ce drapeau, la
             * personne recevrait un lien vers la connexion — et un mot de passe qu'elle
             * n'a jamais reçu.
             *
             * On ne le pose que sur un accès qui n'a jamais servi : sur un compte en
             * activité, ce serait imposer un changement à quelqu'un qui n'a rien demandé.
             */
            if (! $aDejaServi && ! $cible->doit_changer_mot_de_passe) {
                $cible->forceFill(['doit_changer_mot_de_passe' => true])->save();
            }
        });

        $cible->refresh();

        $this->envoyer($cible, $entreprise);

        activity()
            ->causedBy($acteur)
            ->performedOn($cible)
            ->withProperties([
                'email' => $cible->email,
                'acces_active' => $active,
                'deja_en_service' => $aDejaServi,
            ])
            ->log("Renvoi du courriel d'accès à {$cible->name}");

        return [
            'active' => $active,
            // « definition » : la personne va choisir son mot de passe.
            // « rappel » : elle en a déjà un, on lui redonne seulement l'adresse.
            'lien' => $aDejaServi ? 'rappel' : 'definition',
        ];
    }

    /**
     * Vrai si ce compte a déjà servi à quelqu'un.
     *
     * Trois indices, dont un seul suffit. Ils sont volontairement larges : dans le doute,
     * on classe le compte comme « en service », car l'erreur coûteuse n'est pas d'envoyer
     * un rappel inutile — c'est de forcer un changement de mot de passe à quelqu'un qui
     * travaillait très bien avec le sien.
     */
    public function aDejaServi(User $cible): bool
    {
        // 1. Le mot de passe a été choisi par son titulaire : le drapeau est retombé.
        if (! $cible->doit_changer_mot_de_passe && $cible->est_actif) {
            return true;
        }

        // 2. Une connexion figure au journal de traçabilité.
        if (DB::table('sessions_utilisateur')->where('user_id', $cible->id)->exists()) {
            return true;
        }

        // 3. Quelque chose a été saisi sous son nom. Une seule ligne suffit : c'est déjà
        //    une interface qui porte des données.
        foreach (['prospections', 'devis', 'factures', 'encaissements', 'charges'] as $table) {
            if (DB::table($table)->where('cree_par', $cible->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * L'envoi lui-même.
     *
     * Un échec est journalisé, jamais propagé : le serveur de courrier peut être
     * indisponible, et faire échouer l'écran d'un administrateur pour cela ne l'avancerait
     * en rien. Il verra que le message n'est pas parti et pourra recommencer.
     */
    private function envoyer(User $cible, $entreprise): void
    {
        try {
            Mail::to($cible->email)->send(new BienvenueNouvelAcces(
                $cible,
                $entreprise,
                LibellesRoles::de($this->roleDe($cible)),
                $this->libellePerimetre($cible),
                renvoi: true,
            ));
        } catch (\Throwable $e) {
            Log::warning("Renvoi du courriel d'accès non parti à {$cible->email} : ".$e->getMessage());

            throw new \RuntimeException(
                "Le courriel n'a pas pu partir vers {$cible->email}. Le serveur de messagerie a refusé l'envoi ; "
                ."l'accès, lui, est ouvert : le mot de passe peut être transmis autrement."
            );
        }
    }

    /** Le rôle, lu sans dépendre de l'équipe posée dans la requête en cours. */
    private function roleDe(User $utilisateur): string
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($utilisateur->entreprise_id);

        return $utilisateur->getRoleNames()->first() ?? '';
    }

    /** « Abidjan — Site 2 » pour un responsable de lieu, « Abidjan » pour les autres. */
    private function libellePerimetre(User $utilisateur): ?string
    {
        if ($utilisateur->site_id) {
            return Site::withoutGlobalScopes()->find($utilisateur->site_id)?->nom;
        }

        return $utilisateur->ville_id
            ? Ville::withoutGlobalScopes()->find($utilisateur->ville_id)?->nom
            : null;
    }
}
