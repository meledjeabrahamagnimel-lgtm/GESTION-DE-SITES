<?php

namespace Modules\Noyau\Commun\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Noyau\Exploitation\Modeles\CompteurAuteur;

/**
 * Le code qui dit qui a saisi quoi, et à quel rang dans son propre travail.
 *
 *      A-C-KY-0007
 *      │ │ │   └── la 7ᵉ prospection de cette personne
 *      │ │ └────── Koffi Yao
 *      │ └──────── Commercial
 *      └────────── Abidjan
 *
 * Il complète le numéro du document sans le remplacer : « P-0565 » dit le rang de la
 * prospection dans toute l'entreprise, « A-C-KY-0007 » dit de qui elle vient. Les deux
 * sont nécessaires — le premier pour classer, le second pour rendre des comptes.
 *
 * Le code est figé au moment de la saisie et n'est jamais recalculé : une personne qui
 * change de ville ou de rôle plus tard ne doit pas réécrire l'histoire de ce qu'elle a
 * déjà saisi.
 */
final class CodeAuteur
{
    /**
     * Une lettre par rôle, toutes distinctes.
     *
     * La première lettre du rôle ne suffisait pas : Commercial et Caissier donnent tous
     * deux « C », les deux Responsables tous deux « R » — le code aurait cessé de dire
     * qui avait saisi, ce qui est précisément son objet. « K » pour la caisse et « S »
     * pour le superviseur lèvent l'ambiguïté sans allonger le code.
     */
    public const LETTRES_ROLE = [
        'super_admin' => 'P',        // Plateforme
        'gerant' => 'G',
        'responsable_ville' => 'S',  // Superviseur de ville
        'responsable_site' => 'R',
        'commercial' => 'C',
        'caissier' => 'K',           // Comptabilité
    ];

    /** Marque un auteur dont le rôle n'est pas reconnu — visible plutôt que silencieux. */
    private const ROLE_INCONNU = 'X';

    /**
     * Le prochain code de cette personne pour ce type de saisie, en le consommant.
     *
     * Le compteur est verrouillé le temps de l'incrément : deux saisies simultanées du
     * même agent, depuis deux onglets, ne peuvent pas repartir avec le même rang.
     */
    public static function attribuer(?User $auteur, string $type): ?string
    {
        // Import, seeder, tâche planifiée : personne derrière l'écran, donc personne à
        // désigner. Mieux vaut une colonne vide qu'un code attribué à tort.
        if (! $auteur) {
            return null;
        }

        $rang = DB::transaction(function () use ($auteur, $type) {
            $compteur = CompteurAuteur::query()
                ->where('user_id', $auteur->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first()
                ?? CompteurAuteur::create([
                    'user_id' => $auteur->id,
                    'type' => $type,
                    'dernier_numero' => 0,
                ]);

            $compteur->increment('dernier_numero');

            return $compteur->dernier_numero;
        });

        return self::composer($auteur, $rang);
    }

    /** Le code tel qu'il s'écrit, sans toucher au compteur. */
    public static function composer(User $auteur, int $rang): string
    {
        return implode('-', [
            self::lettreVille($auteur),
            self::lettreRole($auteur),
            self::initiales($auteur->name),
            str_pad((string) $rang, 4, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * La première lettre de la ville de rattachement.
     *
     * Le gérant et la plateforme ne sont d'aucune ville : ils portent alors la première
     * lettre de l'entreprise, qui est bien leur périmètre.
     */
    private static function lettreVille(User $auteur): string
    {
        $nom = $auteur->ville?->nom
            ?? $auteur->site?->ville?->nom
            ?? $auteur->entreprise?->nom
            ?? '';

        return self::premiereLettre($nom) ?: '·';
    }

    /**
     * La lettre du rôle, lue sans passer par l'équipe courante.
     *
     * getRoleNames() filtre sur l'équipe posée dans la requête en cours : hors requête
     * — une migration, une commande, une file d'attente — l'équipe n'est pas posée et
     * la méthode ne renvoie rien. Le code se retrouvait alors marqué « rôle inconnu »
     * pour des agents qui en avaient bien un. On interroge donc la table directement.
     */
    private static function lettreRole(User $auteur): string
    {
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $auteur->getMorphClass())
            ->where('model_has_roles.model_id', $auteur->getKey())
            ->pluck('roles.name');

        foreach ($roles as $role) {
            if (isset(self::LETTRES_ROLE[$role])) {
                return self::LETTRES_ROLE[$role];
            }
        }

        return self::ROLE_INCONNU;
    }

    /**
     * Les initiales du nom et du prénom : « Koffi Yao » donne KY.
     *
     * Un nom d'un seul mot donne ses deux premières lettres — une initiale seule serait
     * trop souvent partagée entre deux agents d'une même ville.
     */
    private static function initiales(string $nom): string
    {
        $mots = preg_split('/[\s\-]+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($mots) === 0) {
            return '··';
        }

        if (count($mots) === 1) {
            return Str::upper(Str::ascii(Str::substr($mots[0], 0, 2)));
        }

        return self::premiereLettre($mots[0]).self::premiereLettre(end($mots));
    }

    private static function premiereLettre(string $mot): string
    {
        // Passage en ASCII : « Élise » doit donner E, pas un caractère accentué qui se
        // lirait mal dans un tableau ou dans une recherche.
        return Str::upper(Str::ascii(Str::substr(trim($mot), 0, 1)));
    }
}
