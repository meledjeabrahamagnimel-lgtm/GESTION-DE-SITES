<?php

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtre automatiquement chaque requête sur l'entreprise de l'utilisateur connecté.
 * Un Super Admin (sans entreprise_id) n'est pas filtré : il doit explicitement
 * scoper ses requêtes (->where('entreprise_id', ...)) dans le module Super Admin.
 */
trait AppartientAUneEntreprise
{
    public static function bootAppartientAUneEntreprise(): void
    {
        static::addGlobalScope('entreprise', function (Builder $builder) {
            $utilisateur = auth()->user();

            if ($utilisateur && $utilisateur->entreprise_id) {
                $builder->where($builder->getModel()->getTable().'.entreprise_id', $utilisateur->entreprise_id);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->entreprise_id) && auth()->check() && auth()->user()->entreprise_id) {
                $model->entreprise_id = auth()->user()->entreprise_id;
            }
        });
    }
}
