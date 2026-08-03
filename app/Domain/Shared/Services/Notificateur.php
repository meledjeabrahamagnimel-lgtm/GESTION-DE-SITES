<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\NotificationApp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée unique pour déposer une notification applicative.
 * Chaque dépôt écrit une ligne lue par la cloche du bandeau.
 */
class Notificateur
{
    /** Dépose une notification pour un utilisateur. */
    public static function pour(
        User|int $destinataire,
        string $titre,
        ?string $corps = null,
        string $canal = NotificationApp::CANAL_SYSTEME,
        string $niveau = NotificationApp::NIVEAU_INFO,
        ?string $lien = null,
    ): void {
        $id = $destinataire instanceof User ? $destinataire->id : $destinataire;

        NotificationApp::create([
            'user_id' => $id,
            'canal' => $canal,
            'niveau' => $niveau,
            'titre' => $titre,
            'corps' => $corps,
            'lien' => $lien,
        ]);
    }

    /**
     * Dépose la même notification pour plusieurs destinataires, en une seule requête.
     *
     * @param  iterable<int, User|int>  $destinataires
     */
    public static function pourPlusieurs(
        iterable $destinataires,
        string $titre,
        ?string $corps = null,
        string $canal = NotificationApp::CANAL_SYSTEME,
        string $niveau = NotificationApp::NIVEAU_INFO,
        ?string $lien = null,
    ): void {
        $maintenant = now();

        $lignes = collect($destinataires)
            ->map(fn ($d) => $d instanceof User ? $d->id : (int) $d)
            ->unique()
            ->map(fn (int $id) => [
                'user_id' => $id,
                'canal' => $canal,
                'niveau' => $niveau,
                'titre' => $titre,
                'corps' => $corps,
                'lien' => $lien,
                'lu_le' => null,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ])
            ->all();

        if ($lignes !== []) {
            DB::table('notifications_app')->insert($lignes);
        }
    }

    /**
     * Encadrement à prévenir pour une saisie rattachée à un site : le responsable
     * désigné sur la fiche du site. À défaut de responsable rattaché, on retombe sur
     * le gérant pour qu'aucune transmission ne reste sans destinataire.
     *
     * @return array<int, int>
     */
    public static function encadrementDuSite(?int $siteId, int $entrepriseId): array
    {
        $responsables = DB::table('sites')
            ->where('entreprise_id', $entrepriseId)
            ->when($siteId, fn ($q) => $q->where('id', $siteId))
            ->whereNotNull('responsable_id')
            ->pluck('responsable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $actifs = $responsables === []
            ? []
            : User::query()->whereIn('id', $responsables)->where('est_actif', true)->pluck('id')->all();

        if ($actifs !== []) {
            return $actifs;
        }

        $gerants = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', 'gerant')
            ->pluck('model_has_roles.model_id');

        return User::query()
            ->where('entreprise_id', $entrepriseId)
            ->where('est_actif', true)
            ->whereIn('id', $gerants)
            ->pluck('id')
            ->all();
    }
}
