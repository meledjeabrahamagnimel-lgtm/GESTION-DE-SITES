<?php

namespace Modules\Noyau\Imports\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Exploitation\Modeles\LiaisonComptaflow;

class CorrespondanceFournisseurComptaflow extends Model
{
    protected $table = 'correspondances_fournisseurs_comptaflow';

    protected $guarded = ['id'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(FournisseurReferentiel::class, 'fournisseur_id');
    }

    public function liaison(): BelongsTo
    {
        return $this->belongsTo(LiaisonComptaflow::class, 'entreprise_id', 'entreprise_id');
    }
}
