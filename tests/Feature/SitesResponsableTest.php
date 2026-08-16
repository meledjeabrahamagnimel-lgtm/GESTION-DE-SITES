<?php

namespace Tests\Feature;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le responsable de site tient les villes à jour depuis « Mon espace », sans
 * dépendre de la disponibilité du gérant. Créer une ville crée aussitôt ses deux
 * sites d'activité (Mécanique et Sinistre). Le commercial n'y a pas accès.
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

        foreach (['responsable_ville', 'commercial'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);

        $this->responsable = $this->membre('responsable_ville', 'resp@exemple.test');
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

    public function test_le_responsable_cree_une_ville_avec_son_site(): void
    {
        $this->actingAs($this->responsable);

        Volt::test('commun.mon-espace')
            ->assertSet('estResponsable', true)
            ->set('villeNom', 'Abidjan')
            ->set('villeCommune', 'Cocody')
            ->set('villeTelephone', '+225 27 22 00 00')
            ->call('enregistrerVille')
            ->assertHasNoErrors();

        $ville = Ville::where('entreprise_id', $this->alpha->id)->firstOrFail();

        $this->assertSame('Abidjan', $ville->nom);
        $this->assertSame('Cocody', $ville->commune);
        $this->assertNotEmpty($ville->code);
        $this->assertTrue($ville->est_actif);

        // Une ville naît avec un seul lieu, confondu avec elle : les deux activités s'y
        // pratiquent, seule Abidjan ayant réellement deux endroits distincts.
        $sites = Site::where('ville_id', $ville->id)->get();
        $this->assertCount(1, $sites);
        $this->assertSame('Abidjan', $sites->first()->nom);
    }

    public function test_le_nom_est_obligatoire(): void
    {
        $this->actingAs($this->responsable);

        Volt::test('commun.mon-espace')
            ->call('enregistrerVille')
            ->assertHasErrors(['villeNom' => 'required']);

        $this->assertSame(0, Ville::where('entreprise_id', $this->alpha->id)->count());
    }

    public function test_le_responsable_modifie_une_ville_existante(): void
    {
        $ville = Ville::create([
            'entreprise_id' => $this->alpha->id,
            'code' => 'V01',
            'nom' => 'Ancien nom',
        ]);

        $this->actingAs($this->responsable);

        Volt::test('commun.mon-espace')
            ->call('editerVille', $ville->id)
            ->assertSet('villeNom', 'Ancien nom')
            ->set('villeNom', 'Nouveau nom')
            ->call('enregistrerVille')
            ->assertHasNoErrors();

        $this->assertSame('Nouveau nom', $ville->fresh()->nom);
        $this->assertSame('V01', $ville->fresh()->code, 'Le code ne doit pas changer à la modification.');
    }

    public function test_une_ville_d_une_autre_entreprise_est_hors_de_portee(): void
    {
        $villeEtrangere = Ville::withoutGlobalScopes()->create([
            'entreprise_id' => $this->beta->id,
            'code' => 'B01',
            'nom' => 'Ville Beta',
        ]);

        $this->actingAs($this->responsable);

        // Le périmètre d'entreprise est appliqué par le scope global : la requête
        // ne trouve tout simplement pas la ligne, et rien n'est modifiable.
        $this->expectException(ModelNotFoundException::class);

        Volt::test('commun.mon-espace')->call('editerVille', $villeEtrangere->id);
    }

    public function test_un_commercial_ne_peut_pas_creer_de_ville(): void
    {
        $this->actingAs($this->commercial);

        Volt::test('commun.mon-espace')
            ->assertSet('estResponsable', false)
            ->set('villeNom', 'Ville pirate')
            ->call('enregistrerVille')
            ->assertForbidden();

        $this->assertDatabaseMissing('villes', ['nom' => 'Ville pirate']);
    }

    public function test_la_desactivation_d_une_ville_est_reversible(): void
    {
        $ville = Ville::create([
            'entreprise_id' => $this->alpha->id,
            'code' => 'V01',
            'nom' => 'Abidjan',
        ]);

        $this->actingAs($this->responsable);

        Volt::test('commun.mon-espace')->call('basculerVille', $ville->id);
        $this->assertFalse($ville->fresh()->est_actif);

        Volt::test('commun.mon-espace')->call('basculerVille', $ville->id);
        $this->assertTrue($ville->fresh()->est_actif);
    }

    public function test_la_desactivation_d_un_site_est_reversible(): void
    {
        $ville = Ville::create(['entreprise_id' => $this->alpha->id, 'code' => 'V01', 'nom' => 'Abidjan']);
        $site = Site::create([
            'entreprise_id' => $this->alpha->id,
            'ville_id' => $ville->id,
            'nom' => 'Abidjan',
        ]);

        $this->actingAs($this->responsable);

        Volt::test('commun.mon-espace')->call('basculerSite', $site->id);
        $this->assertFalse($site->fresh()->est_actif);

        Volt::test('commun.mon-espace')->call('basculerSite', $site->id);
        $this->assertTrue($site->fresh()->est_actif);
    }
}
