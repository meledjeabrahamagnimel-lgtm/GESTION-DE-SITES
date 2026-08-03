<?php

namespace Tests\Feature;

use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le responsable de site tient les sites à jour depuis « Mon espace », sans
 * dépendre de la disponibilité du gérant. Le commercial n'y a pas accès.
 */
class SitesResponsableTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $alpha;

    private Entreprise $beta;

    private User $responsable;

    private User $commercial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        $this->beta = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);

        foreach (['responsable_site', 'commercial'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);

        $this->responsable = $this->membre('responsable_site', 'resp@exemple.test');
        $this->commercial = $this->membre('commercial', 'com@exemple.test');
    }

    private function membre(string $role, string $email): User
    {
        $utilisateur = User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'mot-de-passe-de-test',
            'entreprise_id' => $this->alpha->id,
            'est_actif' => true,
        ]);

        $utilisateur->assignRole($role);

        return $utilisateur;
    }

    public function test_le_responsable_cree_un_site_avec_un_code_attribue(): void
    {
        $this->actingAs($this->responsable);

        Volt::test('mon-espace')
            ->assertSet('estResponsable', true)
            ->set('siteNom', 'Abidjan — Site 2')
            ->set('siteVille', 'Abidjan')
            ->set('siteCommune', 'Cocody')
            ->set('siteTelephone', '+225 27 22 00 00')
            ->call('enregistrerSite')
            ->assertHasNoErrors();

        $site = Site::where('entreprise_id', $this->alpha->id)->firstOrFail();

        $this->assertSame('Abidjan — Site 2', $site->nom);
        $this->assertSame('Cocody', $site->commune);
        $this->assertNotEmpty($site->code);
        $this->assertTrue($site->est_actif);
    }

    public function test_le_nom_et_la_ville_sont_obligatoires(): void
    {
        $this->actingAs($this->responsable);

        Volt::test('mon-espace')
            ->call('enregistrerSite')
            ->assertHasErrors(['siteNom' => 'required', 'siteVille' => 'required']);

        $this->assertSame(0, Site::where('entreprise_id', $this->alpha->id)->count());
    }

    public function test_le_responsable_modifie_un_site_existant(): void
    {
        $site = Site::create([
            'entreprise_id' => $this->alpha->id,
            'code' => 'S01',
            'nom' => 'Ancien nom',
            'ville' => 'Bouaké',
        ]);

        $this->actingAs($this->responsable);

        Volt::test('mon-espace')
            ->call('editerSite', $site->id)
            ->assertSet('siteNom', 'Ancien nom')
            ->set('siteNom', 'Nouveau nom')
            ->call('enregistrerSite')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $site->fresh()->nom);
        $this->assertSame('S01', $site->fresh()->code, 'Le code ne doit pas changer à la modification.');
    }

    public function test_un_site_d_une_autre_entreprise_est_hors_de_portee(): void
    {
        $siteEtranger = Site::withoutGlobalScopes()->create([
            'entreprise_id' => $this->beta->id,
            'code' => 'B01',
            'nom' => 'Site Beta',
            'ville' => 'Yamoussoukro',
        ]);

        $this->actingAs($this->responsable);

        // Le périmètre d'entreprise est appliqué par le scope global : la requête
        // ne trouve tout simplement pas la ligne, et rien n'est modifiable.
        $this->expectException(ModelNotFoundException::class);

        Volt::test('mon-espace')->call('editerSite', $siteEtranger->id);
    }

    public function test_un_commercial_ne_peut_pas_creer_de_site(): void
    {
        $this->actingAs($this->commercial);

        Volt::test('mon-espace')
            ->assertSet('estResponsable', false)
            ->set('siteNom', 'Site pirate')
            ->set('siteVille', 'Abidjan')
            ->call('enregistrerSite')
            ->assertForbidden();

        $this->assertDatabaseMissing('sites', ['nom' => 'Site pirate']);
    }

    public function test_la_desactivation_d_un_site_est_reversible(): void
    {
        $site = Site::create([
            'entreprise_id' => $this->alpha->id,
            'code' => 'S01',
            'nom' => 'Site 1',
            'ville' => 'Abidjan',
        ]);

        $this->actingAs($this->responsable);

        Volt::test('mon-espace')->call('basculerSite', $site->id);
        $this->assertFalse($site->fresh()->est_actif);

        Volt::test('mon-espace')->call('basculerSite', $site->id);
        $this->assertTrue($site->fresh()->est_actif);
    }
}
