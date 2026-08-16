<?php

namespace Modules\Noyau\Commun\Services;

use Modules\Noyau\Commun\Modeles\NotificationApp;
use Modules\Noyau\Commun\Services\WebPush\EnvoyeurPush;
use App\Jobs\EnvoyerNotificationPush;
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

        self::pousser([$id], $titre, $corps, $lien);
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

        if ($lignes === []) {
            return;
        }

        DB::table('notifications_app')->insert($lignes);

        // Le même signal part vers les appareils abonnés, pour joindre la personne
        // même quand l'application n'est pas ouverte.
        self::pousser(array_column($lignes, 'user_id'), $titre, $corps, $lien);
    }

    /**
     * Programme l'envoi push après la réponse : la page part immédiatement, les appels
     * HTTP vers les services de notification se font ensuite. Rien n'est programmé si
     * les clés VAPID ne sont pas configurées.
     *
     * @param  array<int, int>  $utilisateurIds
     */
    private static function pousser(array $utilisateurIds, string $titre, ?string $corps, ?string $lien): void
    {
        if (! EnvoyeurPush::estConfigure() || $utilisateurIds === []) {
            return;
        }

        EnvoyerNotificationPush::dispatch($utilisateurIds, $titre, $corps, $lien)->afterResponse();
    }

    /**
     * Encadrement à prévenir pour une saisie rattachée à un site : le responsable
     * propre au site, ou à défaut celui de sa ville (qui en couvre alors les deux
     * activités). À défaut de responsable rattaché, on retombe sur le gérant pour
     * qu'aucune transmission ne reste sans destinataire.
     *
     * @return array<int, int>
     */
    public static function encadrementDuSite(?int $siteId, int $entrepriseId): array
    {
        $responsables = DB::table('sites')
            ->join('villes', 'villes.id', '=', 'sites.ville_id')
            ->where('sites.entreprise_id', $entrepriseId)
            ->when($siteId, fn ($q) => $q->where('sites.id', $siteId))
            ->selectRaw('COALESCE(sites.responsable_id, villes.responsable_id) as responsable_id')
            ->pluck('responsable_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return self::responsablesActifsOuGerants($responsables, $entrepriseId);
    }

    /**
     * Encadrement à prévenir pour une saisie d'un commercial : celui-ci travaillant
     * pour une ville entière (les deux activités), on prévient le responsable de
     * chacun de ses deux sites (ou celui de la ville à défaut), pas un site précis.
     *
     * @return array<int, int>
     */
    public static function encadrementDeVille(?int $villeId, int $entrepriseId): array
    {
        $responsables = DB::table('sites')
            ->join('villes', 'villes.id', '=', 'sites.ville_id')
            ->where('sites.entreprise_id', $entrepriseId)
            ->when($villeId, fn ($q) => $q->where('villes.id', $villeId))
            ->selectRaw('COALESCE(sites.responsable_id, villes.responsable_id) as responsable_id')
            ->pluck('responsable_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return self::responsablesActifsOuGerants($responsables, $entrepriseId);
    }

    /** @param  array<int, int>  $responsables
     * @return array<int, int> */
    private static function responsablesActifsOuGerants(array $responsables, int $entrepriseId): array
    {
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
