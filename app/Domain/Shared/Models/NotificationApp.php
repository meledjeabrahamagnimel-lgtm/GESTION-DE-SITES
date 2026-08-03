<?php

namespace App\Domain\Shared\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification applicative destinée à un utilisateur précis : nouveau message,
 * prospection à valider, retour du responsable, alerte de gestion.
 */
#[Fillable(['user_id', 'canal', 'niveau', 'titre', 'corps', 'lien', 'lu_le'])]
class NotificationApp extends Model
{
    protected $table = 'notifications_app';

    public const CANAL_MESSAGE = 'message';

    public const CANAL_GESTION = 'gestion';

    public const CANAL_SYSTEME = 'systeme';

    public const NIVEAU_INFO = 'info';

    public const NIVEAU_SUCCES = 'succes';

    public const NIVEAU_ALERTE = 'alerte';

    public const NIVEAU_CRITIQUE = 'critique';

    /** Couleur d'affichage de la pastille, alignée sur la charte de la maquette. */
    public const COULEURS = [
        self::NIVEAU_INFO => '#2563EB',
        self::NIVEAU_SUCCES => '#0E9F6E',
        self::NIVEAU_ALERTE => '#D97706',
        self::NIVEAU_CRITIQUE => '#C8102E',
    ];

    protected function casts(): array
    {
        return ['lu_le' => 'datetime'];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeNonLues(Builder $requete): Builder
    {
        return $requete->whereNull('lu_le');
    }

    public function couleur(): string
    {
        return self::COULEURS[$this->niveau] ?? self::COULEURS[self::NIVEAU_INFO];
    }
}
