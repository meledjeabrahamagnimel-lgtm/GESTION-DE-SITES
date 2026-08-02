<?php

namespace App\Models;

use App\Domain\Tenants\Models\Entreprise;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'entreprise_id', 'telephone', 'est_actif', 'doit_changer_mot_de_passe', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'password' => 'hashed',
            'est_actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /** Un utilisateur sans entreprise est un membre de l'équipe plateforme (Super Admin). */
    public function estSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Noms des rôles d'un ensemble d'utilisateurs, sans filtrage par équipe courante.
     * Utile pour les écrans Super Admin qui listent des utilisateurs de plusieurs entreprises à la fois.
     *
     * @return array<int, string> nom_role_1, nom_role_2 indexé par user_id
     */
    public static function nomsRolesParUtilisateur(iterable $userIds): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', self::class)
            ->whereIn('model_has_roles.model_id', collect($userIds)->all())
            ->select('model_has_roles.model_id as user_id', 'roles.name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($lignes) => $lignes->pluck('name')->implode(', '))
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'est_actif', 'entreprise_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
