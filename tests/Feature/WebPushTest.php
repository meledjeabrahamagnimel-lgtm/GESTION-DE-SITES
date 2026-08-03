<?php

namespace Tests\Feature;

use App\Domain\Shared\Models\AbonnementPush;
use App\Domain\Shared\Services\Notificateur;
use App\Domain\Shared\Services\WebPush\ChiffrementWebPush;
use App\Domain\Shared\Services\WebPush\CleEc;
use App\Domain\Shared\Services\WebPush\EnvoyeurPush;
use App\Jobs\EnvoyerNotificationPush;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le chiffrement des notifications poussées est entièrement vérifié par
 * aller-retour : ce que produit le serveur doit être déchiffrable avec les clés
 * de l'abonnement, exactement comme le fait le navigateur à la réception.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_message_chiffre_est_relisible_par_l_abonne(): void
    {
        $abonne = CleEc::genererPaire();
        $auth = random_bytes(16);
        $charge = json_encode(['titre' => 'Prospection validée', 'corps' => 'Deux lignes arbitrées'], JSON_UNESCAPED_UNICODE);

        $corps = ChiffrementWebPush::chiffrer($charge, $abonne['publique'], $auth);

        $this->assertSame(
            $charge,
            ChiffrementWebPush::dechiffrer($corps, $abonne['privee'], $abonne['publique'], $auth),
        );
    }

    /**
     * Vecteur de test officiel de la RFC 8291, section 5. Reproduire cet octet pour
     * octet est la seule preuve que les vrais navigateurs sauront déchiffrer nos envois.
     */
    public function test_le_vecteur_officiel_de_la_rfc_8291_est_reproduit(): void
    {
        $clairAttendu = 'When I grow up, I want to be a watermelon';

        $abonnePublique = CleEc::base64UrlDecoder('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
        $abonneAuth = CleEc::base64UrlDecoder('BTBZMqHH6r4Tts7J_aSIgg');
        $sel = CleEc::base64UrlDecoder('DGv6ra1nlYgDCS1FRnbzlw');

        $serveurPublique = CleEc::base64UrlDecoder('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $serveurPrivee = CleEc::base64UrlDecoder('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');

        $attendu = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCY'
            .'LocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

        $corps = ChiffrementWebPush::chiffrer(
            $clairAttendu,
            $abonnePublique,
            $abonneAuth,
            $sel,
            ['privee' => $serveurPrivee, 'publique' => $serveurPublique],
        );

        $this->assertSame($attendu, CleEc::base64UrlEncoder($corps));
    }

    public function test_l_entete_binaire_respecte_le_format_aes128gcm(): void
    {
        $abonne = CleEc::genererPaire();

        $corps = ChiffrementWebPush::chiffrer('coucou', $abonne['publique'], random_bytes(16));

        // 16 octets de sel, 4 octets de taille d'enregistrement, 1 octet de longueur de clé.
        $this->assertSame(4096, unpack('N', substr($corps, 16, 4))[1]);
        $this->assertSame(65, ord($corps[20]));
        $this->assertSame("\x04", $corps[21], 'La clé publique du serveur doit être un point non compressé.');
    }

    public function test_une_cle_d_abonnement_erronee_ne_dechiffre_pas(): void
    {
        $abonne = CleEc::genererPaire();
        $intrus = CleEc::genererPaire();
        $auth = random_bytes(16);

        $corps = ChiffrementWebPush::chiffrer('secret', $abonne['publique'], $auth);

        $this->assertNotSame(
            'secret',
            ChiffrementWebPush::dechiffrer($corps, $intrus['privee'], $intrus['publique'], $auth),
        );
    }

    public function test_la_signature_es256_fait_bien_64_octets(): void
    {
        $paire = CleEc::genererPaire();
        $cle = CleEc::clePriveeDepuisBrut($paire['privee'], $paire['publique']);

        // Plusieurs tirages : une composante r ou s courte doit rester calée à 32 octets.
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(64, strlen(CleEc::signerEs256('message '.$i, $cle)));
        }
    }

    public function test_le_base64url_fait_l_aller_retour(): void
    {
        $donnees = random_bytes(65);
        $encode = CleEc::base64UrlEncoder($donnees);

        $this->assertStringNotContainsString('=', $encode);
        $this->assertStringNotContainsString('+', $encode);
        $this->assertStringNotContainsString('/', $encode);
        $this->assertSame($donnees, CleEc::base64UrlDecoder($encode));
    }

    /** Sans clés VAPID configurées, l'application doit fonctionner sans rien émettre. */
    public function test_sans_cles_vapid_aucun_envoi_n_est_tente(): void
    {
        config(['webpush.cle_publique' => null, 'webpush.cle_privee' => null]);
        Http::fake();

        $this->assertFalse(EnvoyeurPush::estConfigure());

        EnvoyeurPush::diffuser([1], 'Titre', 'Corps', '/');

        Http::assertNothingSent();
    }

    public function test_l_envoi_porte_l_entete_vapid_et_l_encodage_attendu(): void
    {
        $serveur = CleEc::genererPaire();
        config([
            'webpush.cle_publique' => CleEc::base64UrlEncoder($serveur['publique']),
            'webpush.cle_privee' => CleEc::base64UrlEncoder($serveur['privee']),
            'webpush.contact' => 'mailto:test@exemple.test',
        ]);

        $utilisateur = User::create([
            'name' => 'Testeur',
            'email' => 'testeur@exemple.test',
            'password' => 'mot-de-passe-de-test',
            'est_actif' => true,
        ]);

        $abonne = CleEc::genererPaire();

        AbonnementPush::create([
            'user_id' => $utilisateur->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'empreinte' => AbonnementPush::empreinteDe('https://fcm.googleapis.com/fcm/send/abc123'),
            'cle_p256dh' => CleEc::base64UrlEncoder($abonne['publique']),
            'cle_auth' => CleEc::base64UrlEncoder(random_bytes(16)),
        ]);

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);

        EnvoyeurPush::diffuser([$utilisateur->id], 'Nouveau message', 'Bonjour', '/messages');

        Http::assertSent(function ($requete) {
            $this->assertSame('aes128gcm', $requete->header('Content-Encoding')[0]);
            $this->assertStringStartsWith('vapid t=', $requete->header('Authorization')[0]);
            $this->assertStringContainsString(', k=', $requete->header('Authorization')[0]);

            return true;
        });
    }

    public function test_la_page_de_reglage_liste_et_retire_les_appareils(): void
    {
        $serveur = CleEc::genererPaire();
        config([
            'webpush.cle_publique' => CleEc::base64UrlEncoder($serveur['publique']),
            'webpush.cle_privee' => CleEc::base64UrlEncoder($serveur['privee']),
        ]);

        $utilisateur = User::create([
            'name' => 'Testeur',
            'email' => 'reglages@exemple.test',
            'password' => 'mot-de-passe-de-test',
            'est_actif' => true,
        ]);

        $this->actingAs($utilisateur);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/appareil-un';

        $composant = \Livewire\Volt\Volt::test('mes-notifications')
            ->call('enregistrerAbonnement', $endpoint, 'p256dh', 'auth', 'Chrome sur Android')
            ->assertHasNoErrors()
            ->assertSee('Chrome sur Android');

        $abonnement = AbonnementPush::where('user_id', $utilisateur->id)->firstOrFail();

        $composant->call('oublierAppareil', $abonnement->id)->assertDontSee('Chrome sur Android');

        $this->assertDatabaseMissing('abonnements_push', ['id' => $abonnement->id]);
    }

    /** L'appareil d'un collègue ne doit pas pouvoir être retiré. */
    public function test_on_ne_retire_que_ses_propres_appareils(): void
    {
        $moi = User::create(['name' => 'Moi', 'email' => 'moi@exemple.test', 'password' => 'mot-de-passe-de-test', 'est_actif' => true]);
        $autre = User::create(['name' => 'Autre', 'email' => 'autre@exemple.test', 'password' => 'mot-de-passe-de-test', 'est_actif' => true]);

        $sien = AbonnementPush::create([
            'user_id' => $autre->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/pas-a-moi',
            'empreinte' => AbonnementPush::empreinteDe('https://fcm.googleapis.com/fcm/send/pas-a-moi'),
            'cle_p256dh' => 'p', 'cle_auth' => 'a',
        ]);

        $this->actingAs($moi);

        \Livewire\Volt\Volt::test('mes-notifications')->call('oublierAppareil', $sien->id);

        $this->assertDatabaseHas('abonnements_push', ['id' => $sien->id]);
    }

    /** Un abonnement révoqué par le navigateur doit être nettoyé, pas réessayé sans fin. */
    public function test_un_abonnement_expire_est_supprime(): void
    {
        $serveur = CleEc::genererPaire();
        config([
            'webpush.cle_publique' => CleEc::base64UrlEncoder($serveur['publique']),
            'webpush.cle_privee' => CleEc::base64UrlEncoder($serveur['privee']),
        ]);

        $utilisateur = User::create([
            'name' => 'Testeur',
            'email' => 'testeur2@exemple.test',
            'password' => 'mot-de-passe-de-test',
            'est_actif' => true,
        ]);

        $abonne = CleEc::genererPaire();

        $abonnement = AbonnementPush::create([
            'user_id' => $utilisateur->id,
            'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/xyz',
            'empreinte' => AbonnementPush::empreinteDe('https://updates.push.services.mozilla.com/wpush/v2/xyz'),
            'cle_p256dh' => CleEc::base64UrlEncoder($abonne['publique']),
            'cle_auth' => CleEc::base64UrlEncoder(random_bytes(16)),
        ]);

        Http::fake(['*' => Http::response('', 410)]);

        EnvoyeurPush::diffuser([$utilisateur->id], 'Titre', null, null);

        $this->assertDatabaseMissing('abonnements_push', ['id' => $abonnement->id]);
    }
}
