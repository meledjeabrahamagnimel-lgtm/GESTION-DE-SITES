<?php

namespace Modules\Noyau\Tracabilite\Modeles;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Noyau\Entreprises\Modeles\Entreprise;

/**
 * Une connexion : de l'entrée à la sortie.
 *
 * Volontairement hors du périmètre automatique des entreprises (pas de
 * `AppartientAUneEntreprise`) : le Super Admin n'appartient à aucune entreprise, et une
 * portée globale posée par un trait l'empêcherait de lire les sessions de qui que ce
 * soit. Le filtre par entreprise est ici explicite, à l'endroit qui le demande.
 */
#[Fillable([
    'user_id', 'entreprise_id', 'role', 'identifiant_session', 'adresse_ip',
    'navigateur', 'plateforme', 'ouverte_le', 'derniere_activite_le',
    'fermee_le', 'duree_secondes', 'motif_fin',
])]
class SessionUtilisateur extends Model
{
    protected $table = 'sessions_utilisateur';

    /**
     * Au-delà de ce silence, on ne considère plus la personne devant son écran. La durée
     * correspond à celle d'une session Laravel inactive : au-delà, le cookie ne vaut plus
     * rien de toute façon, et la ligne resterait « ouverte » indéfiniment.
     */
    public const MINUTES_AVANT_INACTIVITE = 15;

    protected function casts(): array
    {
        return [
            'ouverte_le' => 'datetime',
            'derniere_activite_le' => 'datetime',
            'fermee_le' => 'datetime',
            'duree_secondes' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function visites(): HasMany
    {
        return $this->hasMany(VisiteEcran::class, 'session_utilisateur_id');
    }

    /**
     * Sessions que l'on tient pour actives : ni fermées, ni silencieuses depuis trop
     * longtemps. Une session laissée ouverte sur un onglet abandonné n'est pas quelqu'un
     * « en ligne » — l'afficher comme tel donnerait une présence fausse.
     */
    public function scopeEnCours(Builder $requete): Builder
    {
        return $requete->whereNull('fermee_le')
            ->where('derniere_activite_le', '>=', now()->subMinutes(self::MINUTES_AVANT_INACTIVITE));
    }

    public function estEnCours(): bool
    {
        return $this->fermee_le === null
            && $this->derniere_activite_le?->gt(now()->subMinutes(self::MINUTES_AVANT_INACTIVITE));
    }

    /** « 1 h 24 min », « 7 min », « 12 s » — une durée se lit, elle ne se compte pas. */
    public static function enClair(int $secondes): string
    {
        if ($secondes < 60) {
            return $secondes.' s';
        }

        $minutes = intdiv($secondes, 60);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $reste = $minutes % 60;

        return intdiv($minutes, 60).' h'.($reste ? ' '.$reste.' min' : '');
    }

    public function duree(): string
    {
        return self::enClair($this->duree_secondes);
    }
}
