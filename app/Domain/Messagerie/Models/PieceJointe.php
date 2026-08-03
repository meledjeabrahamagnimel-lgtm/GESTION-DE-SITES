<?php

namespace App\Domain\Messagerie\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['message_id', 'nom_original', 'chemin', 'type_mime', 'taille'])]
class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** URL de téléchargement, ou null si le fichier a disparu du disque. */
    public function url(): ?string
    {
        if (! $this->chemin || ! Storage::disk('public')->exists($this->chemin)) {
            return null;
        }

        return Storage::disk('public')->url($this->chemin);
    }

    /** Taille lisible : 12 Ko, 3,4 Mo… */
    public function tailleLisible(): string
    {
        if ($this->taille < 1024) {
            return $this->taille.' o';
        }

        if ($this->taille < 1024 * 1024) {
            return round($this->taille / 1024).' Ko';
        }

        return number_format($this->taille / 1048576, 1, ',', ' ').' Mo';
    }
}
