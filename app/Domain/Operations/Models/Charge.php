<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Shared\Models\DonneeLibre;
use App\Domain\Tenants\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['entreprise_id', 'site_id', 'date', 'type_operation', 'libelle', 'moyen', 'montant', 'tiers', 'observations', 'cree_par'])]
class Charge extends Model
{
    use AppartientAUneEntreprise, HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'montant' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Charges d'exploitation décaissées : exclut transferts internes et décaissements DG. */
    public function scopeExploitation($query)
    {
        return $query->where('type_operation', 'Charges');
    }

    /** Informations saisies librement, hors colonnes prévues. */
    public function donneesLibres(): MorphMany
    {
        return $this->morphMany(DonneeLibre::class, 'sujet');
    }
}
