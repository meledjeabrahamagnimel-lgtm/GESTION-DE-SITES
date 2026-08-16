<?php

namespace Tests\Feature;

use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Commun\Modeles\Referentiel;
use Modules\Noyau\Commun\Services\VentilationActivite;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Charge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Trois promesses faites à l'utilisateur, que rien ne doit reprendre en silence :
 * un accès nouvellement créé reçoit son courriel d'accueil, le super administrateur
 * peut redéfinir un mot de passe perdu, et les listes déroulantes se fixent depuis la
 * direction seule. S'y ajoute la ventilation par activité, qui doit toujours refaire
 * le total exact — reliquat compris.
 */
class AccesEtReferentielsTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $abidjan;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        // Le super administrateur n'appartient à aucune entreprise : son rôle vit hors
        // des équipes, contrairement à ceux que ProvisionneurEntreprise vient de créer.
        Role::findOrCreate('super_admin', 'web');

        $this->abidjan = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        $this->site = Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $this->abidjan->id,
            'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);
    }

    public function test_un_acces_cree_recoit_son_courriel_de_bienvenue(): void
    {
        Mail::fake();

        $utilisateur = (new CreerAcces())->executer($this->entreprise, 'commercial', [
            'nom' => 'Koffi Yao',
            'email' => 'koffi@exemple.test',
            'mot_de_passe' => 'MotDePasse2026',
            'ville_id' => $this->abidjan->id,
        ]);

        Mail::assertSent(BienvenueNouvelAcces::class, function (BienvenueNouvelAcces $mail) use ($utilisateur) {
            return $mail->hasTo($utilisateur->email)
                && $mail->roleLisible === 'Commercial'
                && $mail->perimetre === 'Abidjan';
        });
    }

    public function test_le_courriel_ne_transporte_jamais_le_mot_de_passe(): void
    {
        $utilisateur = $this->compte('commercial', 'koffi@exemple.test');

        $corps = (new BienvenueNouvelAcces($utilisateur, $this->entreprise, 'Commercial', 'Abidjan'))->render();

        $this->assertStringNotContainsString('MotDePasse2026', $corps);
        $this->assertStringContainsString('DC-KNOWING', $corps);
        $this->assertStringContainsString('it.dcknowing@gmail.com', $corps);
        $this->assertStringContainsString('27 22 42 14 43', $corps);
    }

    public function test_un_envoi_qui_echoue_ne_fait_pas_perdre_l_acces(): void
    {
        // Une adresse invalide, un serveur SMTP muet : l'accès doit exister quand même.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('serveur injoignable'));

        $utilisateur = (new CreerAcces())->executer($this->entreprise, 'commercial', [
            'nom' => 'Koffi Yao',
            'email' => 'koffi@exemple.test',
            'mot_de_passe' => 'MotDePasse2026',
            'ville_id' => $this->abidjan->id,
        ]);

        $this->assertTrue($utilisateur->exists);
        $this->assertDatabaseHas('users', ['email' => 'koffi@exemple.test']);
    }

    public function test_le_super_admin_redefinit_un_mot_de_passe_perdu(): void
    {
        $admin = $this->compte('super_admin', 'admin@exemple.test');
        $cible = $this->compte('commercial', 'koffi@exemple.test');

        $this->actingAs($admin);

        Volt::test('superadmin.acces-liste')
            ->call('ouvrirEcrasement', $cible->id)
            ->set('nouveauMotDePasse', 'NouveauMdp2026')
            ->set('nouveauMotDePasseConfirmation', 'NouveauMdp2026')
            ->call('enregistrerMotDePasse');

        $cible->refresh();
        $this->assertTrue(Hash::check('NouveauMdp2026', $cible->password));
        // Le mot de passe a transité de vive voix : il ne doit pas rester le définitif.
        $this->assertTrue($cible->doit_changer_mot_de_passe);
    }

    public function test_deux_mots_de_passe_discordants_sont_refuses(): void
    {
        $admin = $this->compte('super_admin', 'admin@exemple.test');
        $cible = $this->compte('commercial', 'koffi@exemple.test');
        $avant = $cible->password;

        $this->actingAs($admin);

        Volt::test('superadmin.acces-liste')
            ->call('ouvrirEcrasement', $cible->id)
            ->set('nouveauMotDePasse', 'NouveauMdp2026')
            ->set('nouveauMotDePasseConfirmation', 'PasLeMeme2026')
            ->call('enregistrerMotDePasse')
            ->assertHasErrors('nouveauMotDePasse');

        $this->assertSame($avant, $cible->fresh()->password);
    }

    public function test_le_gerant_fixe_les_listes_deroulantes_pour_toute_l_entreprise(): void
    {
        $gerant = $this->compte('gerant', 'gerant@exemple.test');
        $this->actingAs($gerant);

        Volt::test('gerant.parametres')
            ->set('onglet', 'referentiels')
            ->set('nouvelleValeur.moyen_paiement', 'Wave')
            ->call('ajouterValeurReferentiel', 'moyen_paiement');

        $this->assertArrayHasKey('Wave', Referentiel::options(Referentiel::MOYEN_PAIEMENT));

        // Désactivée, elle disparaît des saisies à venir sans effacer l'historique.
        $valeur = Referentiel::where('valeur', 'Wave')->firstOrFail();
        Volt::test('gerant.parametres')->call('basculerValeurReferentiel', $valeur->id);

        $this->assertArrayNotHasKey('Wave', Referentiel::options(Referentiel::MOYEN_PAIEMENT));
    }

    public function test_une_valeur_livree_avec_l_application_ne_peut_pas_etre_dupliquee(): void
    {
        $gerant = $this->compte('gerant', 'gerant@exemple.test');
        $this->actingAs($gerant);

        Volt::test('gerant.parametres')
            ->set('onglet', 'referentiels')
            ->set('nouvelleValeur.moyen_paiement', 'Espèces')
            ->call('ajouterValeurReferentiel', 'moyen_paiement');

        $this->assertDatabaseCount('referentiels', 0);
    }

    public function test_la_ventilation_par_activite_refait_toujours_le_total(): void
    {
        $this->charge('Mécanique', 300_000);
        $this->charge('Sinistre', 200_000);
        // Un loyer sert aux deux activités : le répartir serait inventer une clé.
        $this->charge(null, 100_000);

        $requete = Charge::where('site_id', $this->site->id);
        $repartition = VentilationActivite::repartir(clone $requete);

        $this->assertSame(300_000, $repartition['mecanique']);
        $this->assertSame(200_000, $repartition['sinistre']);
        $this->assertSame(100_000, $repartition['nonVentile']);
        $this->assertSame(
            (int) (clone $requete)->sum('montant'),
            array_sum($repartition),
            'Les trois lignes doivent refaire le total exact.'
        );
    }

    public function test_la_ventilation_donne_le_meme_resultat_en_memoire_et_en_base(): void
    {
        $this->charge('Mécanique', 300_000);
        $this->charge(null, 100_000);

        $requete = Charge::where('site_id', $this->site->id);

        $this->assertSame(
            VentilationActivite::repartir(clone $requete),
            VentilationActivite::repartirCollection((clone $requete)->get()),
        );
    }

    private function compte(string $role, string $email): User
    {
        $utilisateur = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => 'Compte '.$role,
            'email' => $email,
            'password' => Hash::make('password'),
            'ville_id' => $this->abidjan->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);
        $utilisateur->assignRole($role);

        return $utilisateur;
    }

    private function charge(?string $activite, int $montant): void
    {
        Charge::create([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => $this->site->id,
            'date' => now()->toDateString(),
            'type_operation' => 'Charges',
            'libelle' => 'Achats pièces',
            'moyen' => 'Espèces',
            'montant' => $montant,
            'activite' => $activite,
        ]);
    }
}
