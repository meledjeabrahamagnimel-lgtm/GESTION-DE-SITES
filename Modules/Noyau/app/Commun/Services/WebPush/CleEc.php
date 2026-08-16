<?php

namespace Modules\Noyau\Commun\Services\WebPush;

use RuntimeException;

/**
 * Manipulation des clés elliptiques P-256 (prime256v1) utilisées par le Web Push.
 *
 * OpenSSL ne sait lire que des clés au format DER/PEM, alors que le protocole Web Push
 * échange des points bruts de 65 octets. Cette classe fait la traduction dans les deux
 * sens, sans dépendance externe.
 */
class CleEc
{
    /** Préfixe DER d'une SubjectPublicKeyInfo pour une clé publique P-256. */
    private const PREFIXE_PUBLIQUE = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Encodage base64 « URL », sans remplissage, imposé par les spécifications Web Push. */
    public static function base64UrlEncoder(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    public static function base64UrlDecoder(string $donnees): string
    {
        $decode = base64_decode(strtr($donnees, '-_', '+/'), true);

        if ($decode === false) {
            throw new RuntimeException('Chaîne base64url invalide.');
        }

        return $decode;
    }

    /**
     * Crée une paire de clés P-256.
     *
     * @return array{privee: string, publique: string} clés brutes : 32 et 65 octets
     */
    public static function genererPaire(): array
    {
        $cle = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($cle === false) {
            throw new RuntimeException('Impossible de générer une clé elliptique P-256.');
        }

        $details = openssl_pkey_get_details($cle);

        return [
            'privee' => self::calerA32($details['ec']['d']),
            'publique' => self::pointDepuisXY($details['ec']['x'], $details['ec']['y']),
        ];
    }

    /** Point non compressé (0x04 || X || Y) à partir des coordonnées brutes. */
    public static function pointDepuisXY(string $x, string $y): string
    {
        return "\x04".self::calerA32($x).self::calerA32($y);
    }

    /** Ressource OpenSSL d'une clé publique à partir de son point non compressé. */
    public static function clePubliqueDepuisPoint(string $point): \OpenSSLAsymmetricKey
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new RuntimeException('Point de courbe attendu sur 65 octets non compressés.');
        }

        $der = hex2bin(self::PREFIXE_PUBLIQUE).$point;
        $cle = openssl_pkey_get_public(self::enveloppePem($der, 'PUBLIC KEY'));

        if ($cle === false) {
            throw new RuntimeException('Clé publique P-256 illisible.');
        }

        return $cle;
    }

    /**
     * Ressource OpenSSL d'une clé privée à partir de ses composantes brutes.
     * La structure produite est un ECPrivateKey SEC1, que OpenSSL lit nativement.
     */
    public static function clePriveeDepuisBrut(string $privee, string $publique): \OpenSSLAsymmetricKey
    {
        $privee = self::calerA32($privee);

        $der = "\x30\x77"                       // SEQUENCE (119 octets)
            ."\x02\x01\x01"                     // version = 1
            ."\x04\x20".$privee                 // OCTET STRING : clé privée (32 octets)
            ."\xa0\x0a\x06\x08".hex2bin('2a8648ce3d030107')  // [0] OID prime256v1
            ."\xa1\x44\x03\x42\x00".$publique;  // [1] BIT STRING : point public (65 octets)

        $cle = openssl_pkey_get_private(self::enveloppePem($der, 'EC PRIVATE KEY'));

        if ($cle === false) {
            throw new RuntimeException('Clé privée P-256 illisible.');
        }

        return $cle;
    }

    /**
     * Signature ECDSA au format « raw » r||s de 64 octets attendu par ES256.
     * openssl_sign produit du DER : il faut en extraire r et s puis les caler à 32 octets.
     */
    public static function signerEs256(string $message, \OpenSSLAsymmetricKey $clePrivee): string
    {
        if (! openssl_sign($message, $der, $clePrivee, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Échec de la signature ES256.');
        }

        $position = 4; // 0x30, longueur totale, 0x02, longueur de r
        $longueurR = ord($der[3]);
        $r = substr($der, $position, $longueurR);

        $position += $longueurR + 2; // saute 0x02 et la longueur de s
        $longueurS = ord($der[$position - 1]);
        $s = substr($der, $position, $longueurS);

        return self::calerA32(ltrim($r, "\x00")).self::calerA32(ltrim($s, "\x00"));
    }

    /** Secret partagé ECDH brut (32 octets). */
    public static function secretPartage(\OpenSSLAsymmetricKey $privee, \OpenSSLAsymmetricKey $publiquePair): string
    {
        $secret = openssl_pkey_derive($publiquePair, $privee, 32);

        if ($secret === false) {
            throw new RuntimeException('Échec du calcul ECDH.');
        }

        return $secret;
    }

    /** Dérivation HKDF (RFC 5869) telle qu'employée par RFC 8291. */
    public static function hkdf(string $sel, string $matiere, string $info, int $longueur): string
    {
        $prk = hash_hmac('sha256', $matiere, $sel, true);

        return substr(hash_hmac('sha256', $info."\x01", $prk, true), 0, $longueur);
    }

    private static function calerA32(string $valeur): string
    {
        return str_pad($valeur, 32, "\x00", STR_PAD_LEFT);
    }

    private static function enveloppePem(string $der, string $intitule): string
    {
        return "-----BEGIN $intitule-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END $intitule-----\n";
    }
}
