<?php

namespace Modules\Noyau\Commun\Services\WebPush;

use Modules\Noyau\Commun\Modeles\AbonnementPush;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi d'une notification poussée aux appareils abonnés.
 *
 * L'authentification serveur suit VAPID (RFC 8292) : un jeton ES256 signé par la clé
 * privée de l'application, que le service de push vérifie avec la clé publique.
 */
class EnvoyeurPush
{
    /** Le service de push conserve la notification 4 semaines si l'appareil est hors ligne. */
    private const DUREE_DE_VIE = 2419200;

    public static function estConfigure(): bool
    {
        return filled(config('webpush.cle_publique')) && filled(config('webpush.cle_privee'));
    }

    /**
     * Pousse une notification vers tous les appareils de ces utilisateurs.
     *
     * @param  iterable<int, int>  $utilisateurIds
     */
    public static function diffuser(iterable $utilisateurIds, string $titre, ?string $corps, ?string $lien): void
    {
        if (! self::estConfigure()) {
            return;
        }

        $ids = collect($utilisateurIds)->unique()->all();

        if ($ids === []) {
            return;
        }

        $charge = json_encode([
            'titre' => $titre,
            'corps' => $corps,
            'lien' => $lien,
        ], JSON_UNESCAPED_UNICODE);

        foreach (AbonnementPush::whereIn('user_id', $ids)->get() as $abonnement) {
            self::envoyer($abonnement, $charge);
        }
    }

    private static function envoyer(AbonnementPush $abonnement, string $charge): void
    {
        try {
            $corps = ChiffrementWebPush::chiffrer(
                $charge,
                CleEc::base64UrlDecoder($abonnement->cle_p256dh),
                CleEc::base64UrlDecoder($abonnement->cle_auth),
            );

            $reponse = Http::withHeaders([
                'Authorization' => self::enteteVapid($abonnement->endpoint),
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                'TTL' => (string) self::DUREE_DE_VIE,
                'Urgency' => 'normal',
            ])->withBody($corps, 'application/octet-stream')
                ->timeout(8)
                ->post($abonnement->endpoint);

            // 404 et 410 signifient que l'appareil s'est désabonné : on nettoie plutôt
            // que de réessayer indéfiniment à chaque notification.
            if (in_array($reponse->status(), [404, 410], true)) {
                $abonnement->delete();

                return;
            }

            if ($reponse->failed()) {
                Log::warning('Push refusé par le service de notification.', [
                    'statut' => $reponse->status(),
                    'abonnement' => $abonnement->id,
                ]);
            }
        } catch (\Throwable $e) {
            // Une notification poussée est un complément : son échec ne doit jamais
            // interrompre l'action métier qui l'a déclenchée.
            Log::warning('Échec de l’envoi push : '.$e->getMessage(), ['abonnement' => $abonnement->id]);
        }
    }

    /** En-tête « Authorization: vapid t=<jeton>, k=<clé publique> ». */
    private static function enteteVapid(string $endpoint): string
    {
        $parties = parse_url($endpoint);
        $audience = $parties['scheme'].'://'.$parties['host'];

        $entete = CleEc::base64UrlEncoder(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $charge = CleEc::base64UrlEncoder(json_encode([
            'aud' => $audience,
            'exp' => now()->addHours(12)->timestamp,
            'sub' => config('webpush.contact'),
        ]));

        $publique = CleEc::base64UrlDecoder(config('webpush.cle_publique'));
        $privee = CleEc::base64UrlDecoder(config('webpush.cle_privee'));

        $signature = CleEc::signerEs256(
            $entete.'.'.$charge,
            CleEc::clePriveeDepuisBrut($privee, $publique),
        );

        $jeton = $entete.'.'.$charge.'.'.CleEc::base64UrlEncoder($signature);

        return 'vapid t='.$jeton.', k='.config('webpush.cle_publique');
    }
}
