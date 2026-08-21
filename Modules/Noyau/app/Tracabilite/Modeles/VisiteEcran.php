<?php

namespace Modules\Noyau\Tracabilite\Modeles;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un écran ouvert, et le temps qu'il est resté à l'écran.
 *
 * La durée est celle qui sépare cette page de la suivante. C'est donc un temps
 * d'affichage : la personne peut avoir répondu au téléphone entre-temps. La dernière
 * page d'une session est bornée à la dernière activité constatée, sans quoi un onglet
 * laissé ouvert le vendredi soir compterait tout le week-end.
 */
#[Fillable([
    'session_utilisateur_id', 'user_id', 'entreprise_id',
    'route', 'url', 'ecran', 'vue_le', 'duree_secondes',
])]
class VisiteEcran extends Model
{
    protected $table = 'visites_ecran';

    /** Une visite ne se modifie pas : elle est écrite une fois, puis close. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'vue_le' => 'datetime',
            'duree_secondes' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionUtilisateur::class, 'session_utilisateur_id');
    }

    public function duree(): string
    {
        return SessionUtilisateur::enClair($this->duree_secondes);
    }
}
