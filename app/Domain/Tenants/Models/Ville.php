<?php

namespace App\Domain\Tenants\Models;

use App\Domain\Operations\Models\Commercial;
use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une ville regroupe les sites d'activité de l'entreprise qui s'y trouvent. */
#[Fillable(['entreprise_id', 'code', 'nom', 'commune', 'telephone', 'adresse', 'couleur', 'responsable_id', 'est_actif'])]
class Ville extends Model
{
    use AppartientAUneEntreprise, HasFactory;

    protected function casts(): array
    {
        return ['est_actif' => 'boolean'];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /** Responsable de la ville entière : couvre tous ses sites, sauf site ayant son propre responsable. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function commerciaux(): HasMany
    {
        return $this->hasMany(Commercial::class);
    }
}
