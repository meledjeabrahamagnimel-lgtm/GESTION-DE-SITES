<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Tests\TestCase;

/**
 * Le parcours d'un accès qui vient d'être ouvert.
 *
 * Le titulaire n'a pas encore de mot de passe. Lui en réclamer un pour entrer, puis un
 * autre pour en changer, était un aller-retour absurde. Le lien du courriel mène donc
 * directement à l'écran de création — protégé par une signature, et par lui seul.
 */
class PremiereConnexionTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
    }

    public function test_le_lien_du_courriel_mene_a_la_creation_du_mot_de_passe(): void
    {
        $compte = $this->nouvelAcces();

        $corps = (new BienvenueNouvelAcces($compte, $this->entreprise, 'Commercial'))->render();

        $this->assertStringContainsString('/premiere-connexion/'.$compte->id, $corps);
        $this->assertStringContainsString('signature=', $corps, 'Le lien doit être signé, sinon on pourrait viser un autre compte.');
        $this->assertStringNotContainsString('Me connecter', $corps);
    }

    public function test_un_lien_non_signe_est_refuse(): void
    {
        $compte = $this->nouvelAcces();

        $this->get('/premiere-connexion/'.$compte->id)->assertForbidden();
    }

    public function test_une_signature_ne_vaut_pas_pour_un_autre_compte(): void
    {
        $sien = $this->nouvelAcces();
        $autre = $this->nouvelAcces('autre@exemple.test');

        // On garde la signature et on change l'identifiant visé : la falsification
        // doit échouer, sans quoi tout titulaire de lien pourrait ouvrir tout compte.
        $lien = $this->lienSigne($sien);
        $falsifie = str_replace('/premiere-connexion/'.$sien->id, '/premiere-connexion/'.$autre->id, $lien);

        $this->get($falsifie)->assertForbidden();
    }

    public function test_un_lien_perime_est_refuse(): void
    {
        $compte = $this->nouvelAcces();
        $lien = URL::temporarySignedRoute('mot-de-passe.definir', now()->subMinute(), ['utilisateur' => $compte->id]);

        $this->get($lien)->assertForbidden();
    }

    public function test_on_choisit_son_mot_de_passe_sans_donner_l_ancien(): void
    {
        $compte = $this->nouvelAcces();

        $this->get($this->lienSigne($compte))
            ->assertOk()
            ->assertSee('Choisissez votre mot de passe')
            ->assertDontSee('Mot de passe actuel');

        Volt::test('commun.definir-mot-de-passe', ['utilisateur' => $compte->id])
            ->set('nouveauMotDePasse', 'artisan2026')
            ->set('nouveauMotDePasse_confirmation', 'artisan2026')
            ->call('enregistrer')
            ->assertRedirect(route('login'));

        $compte->refresh();
        $this->assertTrue(Hash::check('artisan2026', $compte->password));
        $this->assertFalse($compte->doit_changer_mot_de_passe, "Le mot de passe est choisi : on ne le redemande plus.");
    }

    public function test_un_mot_de_passe_trop_simple_est_refuse(): void
    {
        $compte = $this->nouvelAcces();
        $avant = $compte->password;

        Volt::test('commun.definir-mot-de-passe', ['utilisateur' => $compte->id])
            ->set('nouveauMotDePasse', 'abc')
            ->set('nouveauMotDePasse_confirmation', 'abc')
            ->call('enregistrer')
            ->assertHasErrors('nouveauMotDePasse');

        $this->assertSame($avant, $compte->fresh()->password);
    }

    public function test_deux_saisies_differentes_sont_refusees(): void
    {
        $compte = $this->nouvelAcces();

        Volt::test('commun.definir-mot-de-passe', ['utilisateur' => $compte->id])
            ->set('nouveauMotDePasse', 'artisan2026')
            ->set('nouveauMotDePasse_confirmation', 'artisan2027')
            ->call('enregistrer')
            ->assertHasErrors('nouveauMotDePasse');

        $this->assertTrue($compte->fresh()->doit_changer_mot_de_passe);
    }

    public function test_le_lien_ne_sert_qu_une_fois(): void
    {
        $compte = $this->nouvelAcces();
        $lien = $this->lienSigne($compte);

        $compte->forceFill(['doit_changer_mot_de_passe' => false])->save();

        // La signature reste valable, mais il n'y a plus rien à définir : rejouer le
        // lien ne doit pas permettre d'écraser un mot de passe déjà choisi.
        $this->get($lien)->assertOk()->assertSee('déjà servi');

        Volt::test('commun.definir-mot-de-passe', ['utilisateur' => $compte->id])
            ->set('nouveauMotDePasse', 'intrus2026')
            ->set('nouveauMotDePasse_confirmation', 'intrus2026')
            ->call('enregistrer')
            ->assertNoRedirect();

        $this->assertFalse(Hash::check('intrus2026', $compte->fresh()->password));
    }

    private function nouvelAcces(string $email = 'nouveau@exemple.test'): User
    {
        return User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => 'Koffi Yao',
            'email' => $email,
            'password' => Hash::make('provisoire'),
            'doit_changer_mot_de_passe' => true,
        ]);
    }

    private function lienSigne(User $compte): string
    {
        return URL::temporarySignedRoute('mot-de-passe.definir', now()->addDays(7), ['utilisateur' => $compte->id]);
    }
}
