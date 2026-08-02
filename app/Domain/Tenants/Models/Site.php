<?php

namespace App\Domain\Tenants\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entreprise_id', 'code', 'nom', 'couleur', 'responsable_id', 'est_actif'])]
class Site extends Model
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

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
