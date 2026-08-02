<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Tenants\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
