<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entreprise_id', 'user_id', 'dossier_note_id', 'titre', 'corps', 'est_epinglee', 'rappel_le'])]
class Note extends Model
{
    protected function casts(): array
    {
        return [
            'est_epinglee' => 'boolean',
            'rappel_le' => 'date',
        ];
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(DossierNote::class, 'dossier_note_id');
    }
}
