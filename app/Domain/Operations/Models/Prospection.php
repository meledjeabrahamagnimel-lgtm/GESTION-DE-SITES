<?php

namespace App\Domain\Operations\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Domain\Tenants\Models\Site;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'entreprise_id', 'site_id', 'commercial_id', 'numero', 'date', 'client',
    'localisation', 'moyen', 'activite', 'passage', 'devis_apres_passage',
    'observations', 'cree_par',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
