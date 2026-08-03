<?php

namespace App\Domain\Shared\Services\WebPush;

/**
 * Chiffrement du contenu d'une notification poussée, selon RFC 8291 (Message Encryption
 * for Web Push) et RFC 8188 (encodage « aes128gcm »).
 *
 * Le navigateur ne déchiffre le message qu'avec la clé issue de son propre abonnement :
 * ni le service de push de Google, Apple ou Mozilla ne peut lire ce qui transite.
 */
class ChiffrementWebPush
{
    /** Taille d'enregistrement annoncée dans l'en-tête ; une notification tient largement dedans. */
    private const TAILLE_ENREGISTREMENT = 4096;

    /**
     * Produit le corps binaire à poster au service de push.
     *
     * @param  string  $charge  contenu en clair (JSON)
     * @param  string  $clientPublique  clé p256dh de l'abonnement, 65 octets bruts
     * @param  string  $clientAuth  secret auth de l'abonnement, 16 octets bruts
     */
    public static function chiffrer(string $charge, string $clientPublique, string $clientAuth, ?string $sel = null, ?array $ephemere = null): string
    {
        $sel ??= random_bytes(16);
        $ephemere ??= CleEc::genererPaire();

        $secret = CleEc::secretPartage(
            CleEc::clePriveeDepuisBrut($ephemere['privee'], $ephemere['publique']),
            CleEc::clePubliqueDepuisPoint($clientPublique),
        );

        // Étape propre au Web Push : le secret ECDH est d'abord mélangé au secret
        // d'authentification de l'abonnement, avec les deux clés publiques dans l'info.
        $info = "WebPush: info\x00".$clientPublique.$ephemere['publique'];
        $matiere = CleEc::hkdf($clientAuth, $secret, $info, 32);

        $cle = CleEc::hkdf($sel, $matiere, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = CleEc::hkdf($sel, $matiere, "Content-Encoding: nonce\x00", 12);

        // 0x02 marque le dernier (et unique) enregistrement du flux.
        $chiffre = openssl_encrypt(
            $charge."\x02",
            'aes-128-gcm',
            $cle,
            OPENSSL_RAW_DATA,
            $nonce,
            $balise,
            '',
            16,
        );

        $entete = $sel
            .pack('N', self::TAILLE_ENREGISTREMENT)
            .chr(strlen($ephemere['publique']))
            .$ephemere['publique'];

        return $entete.$chiffre.$balise;
    }

    /**
     * Déchiffre un corps produit par chiffrer(). Sert à vérifier la chaîne complète
     * dans les tests : c'est exactement ce que fait le navigateur à la réception.
     */
    public static function dechiffrer(string $corps, string $clientPrivee, string $clientPublique, string $clientAuth): string
    {
        $sel = substr($corps, 0, 16);
        $longueurCle = ord($corps[20]);
        $serveurPublique = substr($corps, 21, $longueurCle);
        $chiffre = substr($corps, 21 + $longueurCle);

        $secret = CleEc::secretPartage(
            CleEc::clePriveeDepuisBrut($clientPrivee, $clientPublique),
            CleEc::clePubliqueDepuisPoint($serveurPublique),
        );

        $info = "WebPush: info\x00".$clientPublique.$serveurPublique;
        $matiere = CleEc::hkdf($clientAuth, $secret, $info, 32);

        $cle = CleEc::hkdf($sel, $matiere, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = CleEc::hkdf($sel, $matiere, "Content-Encoding: nonce\x00", 12);

        $balise = substr($chiffre, -16);
        $clair = openssl_decrypt(substr($chiffre, 0, -16), 'aes-128-gcm', $cle, OPENSSL_RAW_DATA, $nonce, $balise, '');

        return rtrim((string) $clair, "\x02");
    }
}
