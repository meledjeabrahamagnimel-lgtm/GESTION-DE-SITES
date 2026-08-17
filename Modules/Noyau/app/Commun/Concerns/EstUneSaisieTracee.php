<?php

namespace Modules\Noyau\Commun\Concerns;

use Modules\Noyau\Commun\Services\CodeAuteur;
use Modules\Noyau\Exploitation\Services\GenerateurNumero;

/**
 * Toute ligne saisie porte deux marques, posées d'elles-mêmes.
 *
 *   - son numéro de document, unique et continu dans l'entreprise ;
 *   - le code de celui qui l'a saisie, avec son rang à lui.
 *
 * Le travail se fait dans un écouteur du modèle, et non dans chaque écran : cinq
 * interfaces créent ces lignes, et une seule oubliée aurait suffi à trouer la
 * numérotation. Ici, aucun appel à ne pas oublier — la ligne ne peut pas naître sans.
 *
 * Le modèle déclare son type de compteur :
 *
 *      protected static string $typeDeSaisie = 'enc';
 */
trait EstUneSaisieTracee
{
    public static function bootEstUneSaisieTracee(): void
    {
        static::creating(function ($ligne) {
            $entrepriseId = $ligne->entreprise_id ?? auth()->user()?->entreprise_id;

            // Le « ?? » protège les écrans qui posent déjà leur numéro eux-mêmes : on
            // ne le remplace jamais, sous peine de consommer deux numéros pour une ligne.
            if ($entrepriseId && static::$typeDeSaisie && ! $ligne->numero) {
                $ligne->numero = GenerateurNumero::suivant($entrepriseId, static::$typeDeSaisie);
            }

            if (! $ligne->code_auteur) {
                $ligne->code_auteur = CodeAuteur::attribuer(auth()->user(), static::$typeDeSaisie);
            }
        });
    }
}
