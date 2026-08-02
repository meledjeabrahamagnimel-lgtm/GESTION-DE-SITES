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

#[Fillable(['entreprise_id', 'site_id', 'facture_id', 'date', 'type', 'moyen', 'montant', 'client', 'autres_tiers', 'cree_par'])]
class Encaissement extends Model
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

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    /** Informations saisies librement, hors colonnes prévues. */
    public function donneesLibres(): MorphMany
    {
        return $this->morphMany(DonneeLibre::class, 'sujet');
    }
}
