<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Tests\TestCase;

/**
 * La fabrication du courriel d'accueil.
 *
 * Le logo y était joint au message, dans l'idée de contourner le blocage des images
 * distantes. Le résultat était l'inverse de celui recherché : Gmail bloque aussi les
 * images jointes, l'en-tête restait donc blanc, et le logo réapparaissait en grand sous
 * la signature — affiché comme la pièce jointe qu'il était. Le message avait l'air mal
 * fabriqué là où il devait inspirer confiance, puisqu'il demande de choisir un mot de
 * passe.
 *
 * Ces tests tiennent la correction : le logo est appelé par son adresse, rien n'est
 * joint, et un message dont les images sont bloquées reste lisible et signé.
 */
class CourrielAccueilTest extends TestCase
{
    use RefreshDatabase;

    private function entreprise(?string $logo = null): Entreprise
    {
        return Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => $logo,
        ]);
    }

    private function destinataire(Entreprise $entreprise): User
    {
        return User::create([
            'entreprise_id' => $entreprise->id, 'name' => 'Awa Traoré',
            'email' => 'awa@alpha.test', 'password' => Hash::make('motdepasse123'),
            'est_actif' => true, 'doit_changer_mot_de_passe' => true,
        ]);
    }

    private function rendu(Entreprise $entreprise): string
    {
        return (new BienvenueNouvelAcces(
            $this->destinataire($entreprise), $entreprise, 'Gérant',
        ))->render();
    }

    public function test_le_logo_est_appele_par_son_adresse_et_non_joint(): void
    {
        // Préfixe « public: » : fichier livré avec le dépôt, servi tel quel.
        $rendu = $this->rendu($this->entreprise('public:images/logo.png'));

        $this->assertStringContainsString('images/logo.png', $rendu);

        // « cid: » est la marque d'une pièce jointe référencée dans le corps. C'est elle
        // que les messageries mobiles ressortent en bas du message.
        $this->assertStringNotContainsString('cid:', $rendu);
    }

    public function test_l_adresse_du_logo_est_absolue(): void
    {
        $rendu = $this->rendu($this->entreprise('public:images/logo.png'));

        // Un chemin relatif n'a aucun sens dans une boîte aux lettres : il n'y a pas de
        // page courante depuis laquelle le résoudre.
        $this->assertMatchesRegularExpression('~<img src="https?://[^"]+images/logo\.png"~', $rendu);
    }

    public function test_sans_logo_le_bandeau_ne_porte_aucune_image(): void
    {
        $rendu = $this->rendu($this->entreprise());

        $this->assertStringNotContainsString('<img', $rendu);
        $this->assertStringNotContainsString('cid:', $rendu);
    }

    public function test_images_bloquees_le_message_reste_signe(): void
    {
        $entreprise = $this->entreprise('public:images/logo.png');
        $rendu = $this->rendu($entreprise);

        // Ce qui identifie l'expéditeur ne doit pas dépendre d'une image : le nom de
        // l'entreprise et les coordonnées du cabinet sont du texte.
        $this->assertStringContainsString('Alpha', $rendu);
        $this->assertStringContainsString((string) config('cabinet.nom'), $rendu);
    }

    public function test_le_texte_de_remplacement_est_vide_pour_ne_pas_doubler_le_nom(): void
    {
        $rendu = $this->rendu($this->entreprise('public:images/logo.png'));

        // Image bloquée, un alt renseigné afficherait une icône cassée suivie du nom,
        // alors que ce nom est déjà écrit juste en dessous.
        $this->assertMatchesRegularExpression('~<img[^>]+alt=""~', $rendu);
    }

    public function test_le_message_ne_transporte_jamais_le_mot_de_passe(): void
    {
        $entreprise = $this->entreprise();
        $rendu = $this->rendu($entreprise);

        $this->assertStringNotContainsString('motdepasse123', $rendu);
        // Le lien signé, lui, doit bien y être : c'est le seul moyen d'entrer.
        $this->assertStringContainsString('premiere-connexion', $rendu);
    }
}
