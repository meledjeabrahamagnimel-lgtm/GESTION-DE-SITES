<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Shared\Models\DonneeLibre;
use App\Domain\Tenants\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'entreprise_id', 'site_id', 'devis_id', 'commercial_id', 'numero', 'n_facture',
    'date', 'client', 'type', 'activite', 'montant', 'observations', 'cree_par',
])]
class Facture extends Model
{
    use AppartientAUneEntreprise, HasFactory, LogsActivity;

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

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Commercial::class);
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(Devis::class);
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(Encaissement::class);
    }

    public function montantEncaisse(): int
    {
        return (int) $this->encaissements()->sum('montant');
    }

    /**
     * Solde restant à encaisser sur cette facture. C'est ce chiffre qui protège contre
     * la double saisie : responsable et caissier ne voient et ne peuvent saisir que les
     * factures dont le reste est strictement positif ; une fois soldée, la facture
     * disparaît des deux écrans.
     *
     * Réutilise la somme préchargée par `withSum('encaissements', 'montant')` quand elle
     * est disponible, pour éviter une requête par ligne sur les écrans qui listent
     * plusieurs factures à la fois.
     */
    public function resteAEncaisser(): int
    {
        $encaisse = $this->encaissements_sum_montant ?? $this->montantEncaisse();

        return max(0, $this->montant - (int) $encaisse);
    }

    /** Factures dont il reste un solde à encaisser, calculé en base pour rester performant. */
    public function scopeAvecResteAEncaisser($query)
    {
        return $query->whereRaw(
            'montant > (select coalesce(sum(montant), 0) from encaissements where encaissements.facture_id = factures.id)'
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['commercial_id', 'n_facture', 'date', 'client', 'type', 'activite', 'montant', 'observations'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Informations saisies librement, hors colonnes prévues. */
    public function donneesLibres(): MorphMany
    {
        return $this->morphMany(DonneeLibre::class, 'sujet');
    }
}
