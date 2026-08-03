<?php

namespace App\Domain\Messagerie\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fil de discussion entre plusieurs membres.
 *
 * Volontairement sans le trait AppartientAUneEntreprise : une conversation ouverte par
 * le Super Admin porte entreprise_id = null et doit rester visible de ses participants.
 * Le cloisonnement est assuré par scopeVisiblePour(), qui part des participants.
 */
#[Fillable(['entreprise_id', 'sujet', 'cree_par', 'dernier_message_le'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return ['dernier_message_le' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function dernierMessage(): HasMany
    {
        return $this->messages()->latest('id')->limit(1);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['lu_le'])
            ->withTimestamps();
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** Seules les conversations où l'utilisateur figure comme participant. */
    public function scopeVisiblePour(Builder $requete, User $utilisateur): Builder
    {
        return $requete->whereHas('participants', fn (Builder $q) => $q->whereKey($utilisateur->id));
    }

    /** Nombre de messages non lus pour un participant donné. */
    public function nonLusPour(User $utilisateur): int
    {
        $lu = $this->participants->firstWhere('id', $utilisateur->id)?->pivot?->lu_le;

        return $this->messages
            ->where('expediteur_id', '!=', $utilisateur->id)
            ->when($lu, fn ($m) => $m->where('created_at', '>', $lu))
            ->count();
    }

    /** Intitulé affiché : le sujet saisi, sinon les autres participants. */
    public function intitulePour(User $utilisateur): string
    {
        if (filled($this->sujet)) {
            return $this->sujet;
        }

        $autres = $this->participants->where('id', '!=', $utilisateur->id)->pluck('name');

        return $autres->isEmpty() ? 'Conversation' : $autres->implode(', ');
    }
}
