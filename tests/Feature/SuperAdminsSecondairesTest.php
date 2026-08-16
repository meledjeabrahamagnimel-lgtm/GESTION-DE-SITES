<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminsSecondairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
    }

    private function superAdmin(string $nom, bool $fondateur = false, array $habilitations = [], ?int $creePar = null): User
    {
        $utilisateur = User::create([
            'name' => $nom,
            'email' => str($nom)->slug().'@plateforme.test',
            'password' => 'mot-de-passe-de-test',
            'est_actif' => true,
            'est_fondateur' => $fondateur,
            'habilitations' => $habilitations,
            'cree_par_id' => $creePar,
        ]);

        $utilisateur->assignRole('super_admin');

        return $utilisateur;
    }

    public function test_le_fondateur_dispose_de_toutes_les_sections(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);

        $this->assertSame(array_keys(User::SECTIONS_PLATEFORME), $fondateur->sectionsAutorisees());
        $this->assertTrue($fondateur->peutAccederA('maintenance'));
    }

    public function test_un_secondaire_n_accede_qu_aux_sections_ouvertes(): void
    {
        $secondaire = $this->superAdmin('Secondaire', habilitations: ['entreprises']);

        $this->assertTrue($secondaire->peutAccederA('entreprises'));
        $this->assertFalse($secondaire->peutAccederA('maintenance'));

        $this->actingAs($secondaire);

        $this->get(route('super-admin.entreprises.index'))->assertOk();
        $this->get(route('super-admin.maintenance'))->assertForbidden();
    }

    public function test_le_menu_masque_les_sections_fermees(): void
    {
        $secondaire = $this->superAdmin('Secondaire', habilitations: ['entreprises']);

        $libelles = collect(\Modules\Noyau\Commun\Services\MenuNavigation::pour($secondaire))->pluck('label');

        $this->assertContains('Entreprises', $libelles);
        $this->assertNotContains('Maintenance', $libelles);
        $this->assertContains('Messages', $libelles, 'La messagerie reste ouverte à tous.');
    }

    public function test_personne_ne_peut_toucher_au_fondateur(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);
        $autreFondateurPotentiel = $this->superAdmin('Second Fondateur', fondateur: true);
        $secondaire = $this->superAdmin('Secondaire', habilitations: ['acces'], creePar: $fondateur->id);

        $this->assertFalse($secondaire->peutGerer($fondateur));
        $this->assertFalse($autreFondateurPotentiel->peutGerer($fondateur));
        $this->assertFalse($fondateur->peutGerer($fondateur), 'Nul ne se supprime soi-même depuis cet écran.');
    }

    public function test_un_secondaire_ne_gere_que_ses_propres_creations(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);
        $alice = $this->superAdmin('Alice', habilitations: ['acces'], creePar: $fondateur->id);
        $filleuleDAlice = $this->superAdmin('Filleule', habilitations: [], creePar: $alice->id);
        $filleuleDuFondateur = $this->superAdmin('Autre', habilitations: [], creePar: $fondateur->id);

        $this->assertTrue($alice->peutGerer($filleuleDAlice));
        $this->assertFalse($alice->peutGerer($filleuleDuFondateur));
        $this->assertTrue($fondateur->peutGerer($filleuleDAlice), 'Le fondateur garde la main sur tous les secondaires.');
    }

    public function test_la_suppression_d_un_compte_hors_perimetre_est_refusee(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);
        $alice = $this->superAdmin('Alice', habilitations: ['acces'], creePar: $fondateur->id);
        $hors = $this->superAdmin('Hors Perimetre', creePar: $fondateur->id);

        $this->actingAs($alice);

        Volt::test('superadmin.administrateurs')
            ->call('supprimer', $hors->id)
            ->assertSet('erreur', "Le compte fondateur et les comptes créés par d'autres ne peuvent pas être supprimés.");

        $this->assertDatabaseHas('users', ['id' => $hors->id]);

        Volt::test('superadmin.administrateurs')->call('supprimer', $fondateur->id);
        $this->assertDatabaseHas('users', ['id' => $fondateur->id]);
    }

    public function test_la_creation_d_un_secondaire_enregistre_createur_et_habilitations(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);
        $this->actingAs($fondateur);

        Volt::test('superadmin.administrateurs')
            ->set('nom', 'Nouvel Admin')
            ->set('email', 'nouvel.admin@plateforme.test')
            ->set('motDePasse', 'mot-de-passe-solide')
            ->set('habilitations', ['entreprises', 'journal'])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $cree = User::where('email', 'nouvel.admin@plateforme.test')->firstOrFail();

        $this->assertSame($fondateur->id, $cree->cree_par_id);
        $this->assertFalse($cree->est_fondateur);
        $this->assertTrue($cree->doit_changer_mot_de_passe);
        $this->assertSame(['entreprises', 'journal'], $cree->sectionsAutorisees());
        $this->assertTrue($cree->hasRole('super_admin'));
    }

    /** Une habilitation inventée côté client ne doit jamais être enregistrée. */
    public function test_une_habilitation_inconnue_est_rejetee(): void
    {
        $fondateur = $this->superAdmin('Fondateur', fondateur: true);
        $this->actingAs($fondateur);

        Volt::test('superadmin.administrateurs')
            ->set('nom', 'Curieux')
            ->set('email', 'curieux@plateforme.test')
            ->set('motDePasse', 'mot-de-passe-solide')
            ->set('habilitations', ['entreprises', 'tout_pouvoir'])
            ->call('enregistrer')
            ->assertHasErrors('habilitations.1');

        $this->assertDatabaseMissing('users', ['email' => 'curieux@plateforme.test']);
    }
}
