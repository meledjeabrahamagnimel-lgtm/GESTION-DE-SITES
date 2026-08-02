<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Couple intitulé / valeur rattaché librement à une écriture. */
#[Fillable(['entreprise_id', 'sujet_type', 'sujet_id', 'intitule', 'valeur', 'cree_par'])]
class DonneeLibre extends Model
{
    use AppartientAUneEntreprise;

    protected $table = 'donnees_libres';

    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }
}
