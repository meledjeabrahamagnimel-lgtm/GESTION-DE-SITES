<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Shared\Models\DonneeLibre;
use App\Domain\Tenants\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'entreprise_id', 'site_id', 'commercial_id', 'numero', 'date', 'client',
    'localisation', 'moyen', 'activite', 'passage', 'devis_apres_passage',
    'observations', 'cree_par', 'statut_validation', 'motif_refus', 'transmise_le',
])]
class Prospection extends Model
{
    use AppartientAUneEntreprise, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'passage' => 'boolean',
            'devis_apres_passage' => 'boolean',
            'transmise_le' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Commercial::class);
    }

    public function devis(): HasOne
    {
        return $this->hasOne(Devis::class);
    }

    /** Prospections visibles dans les consultations : les brouillons en sont exclus. */
    public function scopeVisibles($query)
    {
        return $query->whereIn('statut_validation', ['Transmise', 'Validée']);
    }

    /** En attente de l'arbitrage du responsable de site. */
    public function scopeATraiter($query)
    {
        return $query->where('statut_validation', 'Transmise');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /** Informations saisies librement, hors colonnes prévues. */
    public function donneesLibres(): MorphMany
    {
        return $this->morphMany(DonneeLibre::class, 'sujet');
    }
}
