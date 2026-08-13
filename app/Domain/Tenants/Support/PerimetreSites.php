<?php

namespace App\Domain\Tenants\Support;

use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Résout le périmètre villes/sites visible par un utilisateur pour les écrans de
 * filtre par période. Le filtre se lit en deux niveaux : une ville (le Gérant choisit
 * parmi toutes les siennes ; les autres rôles n'en ont qu'une, déjà connue), puis une
 * précision d'activité au sein de cette ville — consolidé (les deux sites) par
 * défaut, ou Mécanique/Sinistre pour isoler un site précis.
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
     * Identifiants des sites à interroger, résolus depuis le filtre à deux niveaux :
     * une ville (ou toutes les villes visibles si non précisée) puis une activité (ou
     * les deux sites si non précisée — c'est le mode « consolidé »).
     */
    public static function idsRetenus(User $utilisateur, ?string $villeFiltre, ?string $activiteFiltre = null): array
    {
        $sites = Site::visiblesPour($utilisateur);

        if ($villeFiltre) {
            $sites = $sites->where('ville_id', (int) $villeFiltre);
        }

        if ($activiteFiltre) {
            $sites = $sites->where('activite', $activiteFiltre);
        }

        return $sites->pluck('id')->all();
    }

    /**
     * Options du filtre Ville : toujours proposées au Gérant, dont le périmètre
     * s'étend structurellement à toute l'entreprise — même s'il n'a qu'une seule ville
     * aujourd'hui, il doit pouvoir la sélectionner explicitement pour en préciser
     * ensuite l'activité. Null pour les autres rôles, dont la ville est déjà connue.
     */
    public static function optionsVilles(User $utilisateur): ?Collection
    {
        if (! $utilisateur->hasRole('gerant')) {
            return null;
        }

        $villes = self::villesVisibles($utilisateur);

        return $villes->isEmpty() ? null : $villes;
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
     * KPI (« CA — … ») reflètent toujours ce qui est réellement affiché.
     */
    public static function libellePerimetre(User $utilisateur, ?string $villeFiltre, ?string $activiteFiltre = null): string
    {
        if (! $villeFiltre) {
            return $utilisateur->hasRole('gerant') ? 'toutes villes' : 'consolidé';
        }

        $ville = Ville::find($villeFiltre);
        $nomVille = $ville?->nom ?? 'ville';

        return $activiteFiltre ? "{$nomVille} — {$activiteFiltre}" : "{$nomVille} — consolidé";
    }
}
