<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Shared\Models\DonneeLibre;
use App\Domain\Tenants\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['entreprise_id', 'site_id', 'user_id', 'numero', 'nom', 'activite', 'objectif_mensuel', 'statut', 'est_spontane'])]
class Commercial extends Model
{
    use AppartientAUneEntreprise, HasFactory;

    protected $table = 'commerciaux';

    protected function casts(): array
    {
        return [
            'objectif_mensuel' => 'integer',
            'est_spontane' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function prospections(): HasMany
    {
        return $this->hasMany(Prospection::class);
    }

    public function devis(): HasMany
    {
        return $this->hasMany(Devis::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('statut', 'Actif');
    }

    public function objectifJournalier(): float
    {
        return round($this->objectif_mensuel / 30);
    }

    /** Informations saisies librement, hors colonnes prévues. */
    public function donneesLibres(): MorphMany
    {
        return $this->morphMany(DonneeLibre::class, 'sujet');
    }
}
