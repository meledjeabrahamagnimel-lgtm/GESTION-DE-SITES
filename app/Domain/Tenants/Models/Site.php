<?php

namespace App\Domain\Tenants\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un site est l'endroit d'activité précis où l'entreprise opère (une ville, une activité). */
#[Fillable(['entreprise_id', 'ville_id', 'activite', 'nom', 'responsable_id', 'est_actif'])]
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

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    /** Responsable propre à ce site. S'il est vide, le site est couvert par le responsable de sa ville. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Vrai si $user est responsable de ce site, directement ou via sa ville. */
    public function estResponsablePar(User $user): bool
    {
        return $this->responsable_id === $user->id
            || $this->ville?->responsable_id === $user->id;
    }

    /**
     * Sites que $user peut voir, selon son rôle :
     * - Gérant : tous les sites de son entreprise.
     * - Responsable de site : les sites dont il est responsable, directement ou via sa ville.
     * - Caissier : son site de rattachement, ou tous les sites de sa ville s'il est rattaché à une ville entière.
     */
    public static function visiblesPour(User $user): Collection
    {
        if ($user->hasRole('gerant')) {
            return static::where('entreprise_id', $user->entreprise_id)->orderBy('nom')->get();
        }

        if ($user->hasRole('responsable_site')) {
            return static::where('entreprise_id', $user->entreprise_id)
                ->where(function ($requete) use ($user) {
                    $requete->where('responsable_id', $user->id)
                        ->orWhereHas('ville', fn ($q) => $q->where('responsable_id', $user->id));
                })
                ->orderBy('nom')->get();
        }

        if ($user->hasRole('caissier')) {
            if ($user->site_id) {
                return static::where('id', $user->site_id)->get();
            }

            if ($user->ville_id) {
                return static::where('ville_id', $user->ville_id)->orderBy('nom')->get();
            }
        }

        return new Collection();
    }
}
