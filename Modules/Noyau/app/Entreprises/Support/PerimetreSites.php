<?php

namespace Modules\Noyau\Entreprises\Support;

use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Résout le périmètre villes/sites visible par un utilisateur pour les écrans de
 * filtre par période. Le filtre se lit en trois niveaux, du plus large au plus fin :
 *
 *   1. la ville — le Gérant choisit parmi toutes les siennes, les autres rôles n'en
 *      ont qu'une, déjà connue ;
 *   2. le site — le lieu physique, proposé seulement quand la ville retenue en compte
 *      plusieurs (Abidjan) ; ailleurs le site se confond avec la ville ;
 *   3. l'activité — Mécanique ou Sinistre, pratiquées l'une comme l'autre sur chaque
 *      lieu. Ce dernier niveau ne restreint plus des sites mais filtre directement la
 *      colonne `activite` des opérations, chaque page l'appliquant à sa propre requête.
 */
class PerimetreSites
{
    /** Villes visibles par l'utilisateur : toutes celles de l'entreprise pour le Gérant, la sienne pour les autres rôles. */
    public static function villesVisibles(User $utilisateur): Collection
    {
        if ($utilisateur->hasRole('gerant')) {
            return Ville::where('entreprise_id', $utilisateur->entreprise_id)->where('est_actif', true)->orderBy('nom')->get();
        }

        $villeIds = Site::visiblesPour($utilisateur)->pluck('ville_id')->unique();

        return Ville::whereIn('id', $villeIds)->orderBy('nom')->get();
    }

    /**
     * Identifiants des sites à interroger, résolus depuis la ville puis le lieu retenus.
     * L'activité n'intervient pas ici : un lieu accueille les deux activités.
     */
    public static function idsRetenus(User $utilisateur, ?string $villeFiltre, ?string $siteFiltre = null): array
    {
        $sites = Site::visiblesPour($utilisateur);

        if ($villeFiltre) {
            $sites = $sites->where('ville_id', (int) $villeFiltre);
        }

        if ($siteFiltre) {
            $sites = $sites->where('id', (int) $siteFiltre);
        }

        return $sites->pluck('id')->all();
    }

    /**
     * Identifiants des villes à interroger pour les commerciaux : un commercial n'est
     * rattaché ni à une activité ni à un lieu, mais à une ville entière.
     */
    public static function idsVillesRetenus(User $utilisateur, ?string $villeFiltre): array
    {
        $villes = self::villesVisibles($utilisateur);

        if ($villeFiltre) {
            $villes = $villes->where('id', (int) $villeFiltre);
        }

        return $villes->pluck('id')->all();
    }

    /**
     * Options du filtre Ville : toujours proposées au Gérant, dont le périmètre
     * s'étend structurellement à toute l'entreprise — même s'il n'a qu'une seule ville
     * aujourd'hui, il doit pouvoir la sélectionner explicitement pour en préciser
     * ensuite le lieu ou l'activité. Null pour les autres rôles, dont la ville est déjà connue.
     */
    public static function optionsVilles(User $utilisateur): ?Collection
    {
        if (! $utilisateur->hasRole('gerant')) {
            return null;
        }

        $villes = self::villesVisibles($utilisateur);

        return $villes->isEmpty() ? null : $villes;
    }

    /**
     * Options du filtre Site, une fois la ville connue. Null tant qu'aucune ville n'est
     * en contexte, et null aussi quand elle n'a qu'un seul lieu : proposer un choix
     * unique n'apprendrait rien, le site étant alors la ville elle-même.
     */
    public static function optionsSites(User $utilisateur, ?string $villeFiltre): ?Collection
    {
        $villeId = $villeFiltre ? (int) $villeFiltre : self::villeUnique($utilisateur)?->id;

        if (! $villeId) {
            return null;
        }

        $sites = Site::visiblesPour($utilisateur)->where('ville_id', $villeId)->values();

        return $sites->count() > 1 ? $sites : null;
    }

    /** La ville de l'utilisateur quand elle est connue d'avance (tous les rôles hors Gérant), affichée en lecture seule. */
    public static function villeUnique(User $utilisateur): ?Ville
    {
        if ($utilisateur->hasRole('gerant')) {
            return null;
        }

        return self::villesVisibles($utilisateur)->first();
    }

    /**
     * Description courte du périmètre actuellement retenu, pour que les libellés de
     * KPI (« CA — … ») reflètent toujours ce qui est réellement affiché : la ville, le
     * lieu quand il est isolé, et l'activité quand elle l'est aussi.
     */
    public static function libellePerimetre(User $utilisateur, ?string $villeFiltre, ?string $siteFiltre = null, ?string $activiteFiltre = null): string
    {
        $morceaux = [];

        if ($villeFiltre && $nomVille = Ville::find($villeFiltre)?->nom) {
            $morceaux[] = $nomVille;
        }

        if ($siteFiltre && $nomSite = Site::find($siteFiltre)?->nom) {
            // Le nom du lieu porte déjà celui de sa ville : le répéter alourdirait le libellé.
            $morceaux = [$nomSite];
        }

        $morceaux[] = $activiteFiltre ?: 'consolidé';

        if ($morceaux === ['consolidé']) {
            return $utilisateur->hasRole('gerant') ? 'toutes villes' : 'consolidé';
        }

        return implode(' — ', $morceaux);
    }
}
