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

#[Fillable([
    'entreprise_id', 'site_id', 'user_id', 'numero', 'nom', 'activite',
    'objectif_mecanique', 'objectif_sinistre', 'objectif_mensuel', 'statut', 'est_spontane',
])]
class Commercial extends Model
{
    use AppartientAUneEntreprise, HasFactory;

    protected $table = 'commerciaux';

    /** Chiffre d'affaires mensuel par défaut à atteindre, réparti 70 % Mécanique / 30 % Sinistre. */
    public const OBJECTIF_MENSUEL_DEFAUT = 20_000_000;

    public const PART_MECANIQUE_DEFAUT = 0.70;

    public const PART_SINISTRE_DEFAUT = 0.30;

    protected function casts(): array
    {
        return [
            'objectif_mecanique' => 'integer',
            'objectif_sinistre' => 'integer',
            'objectif_mensuel' => 'integer',
            'est_spontane' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // L'objectif global n'est jamais saisi directement : il est toujours le cumul
        // des deux objectifs par activité, pour qu'aucun écran ne puisse les faire diverger.
        static::saving(function (self $commercial) {
            $commercial->objectif_mensuel = $commercial->objectif_mecanique + $commercial->objectif_sinistre;
        });
    }

    public function objectifAnnuel(): int
    {
        return $this->objectif_mensuel * 12;
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
