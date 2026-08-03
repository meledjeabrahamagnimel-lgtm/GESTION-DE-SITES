<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['entreprise_id', 'user_id', 'nom', 'couleur'])]
class DossierNote extends Model
{
    protected $table = 'dossiers_notes';

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'dossier_note_id');
    }
}
