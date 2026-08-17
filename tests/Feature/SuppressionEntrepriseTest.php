<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Vider une entreprise, ou l'effacer : deux gestes, deux portées.
 *
 * L'un se rattrape — l'organisation reste debout, on ressaisit. L'autre non. Les
 * confondre serait grave, d'où deux formulaires distincts, deux confirmations par
 * le nom, et ces tests qui vérifient que chacun s'arrête où il doit.
 */
class SuppressionEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $ville;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $this->ville = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        $this->site = Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $this->ville->id,
            'code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1', 'est_actif' => true,
        ]);

        $this->actingAs($this->superAdmin());
    }

    public function test_la_purge_des_acces_epargne_le_gerant(): void
    {
        $gerant = $this->compte('gerant@alpha.test', 'gerant');
        $commercial = $this->compte('com@alpha.test', 'commercial');
        $caissier = $this->compte('caisse@alpha.test', 'caissier');

        $this->ecran()
            ->set('entrepriseId', $this->entreprise->id)
            ->set('purgerAcces', true)
            ->set('confirmation', 'Alpha')
            ->call('purger');

        // Le gérant recréera les autres : sans lui, l'entreprise ne se rouvre plus
        // depuis l'application.
        $this->assertNotNull($gerant->fresh());
        $this->assertNull($commercial->fresh());
        $this->assertNull($caissier->fresh());
    }

    public function test_la_purge_sans_l_option_ne_touche_a_aucun_acces(): void
    {
        $commercial = $this->compte('com@alpha.test', 'commercial');

        $this->ecran()
            ->set('entrepriseId', $this->entreprise->id)
            ->set('confirmation', 'Alpha')
            ->call('purger');

        $this->assertNotNull($commercial->fresh(), "La purge sans l'option ne concerne que les écritures.");
    }

    public function test_la_purge_laisse_l_entreprise_debout(): void
    {
        $this->prospection();

        $this->ecran()
            ->set('entrepriseId', $this->entreprise->id)
            ->set('purgerAcces', true)
            ->set('confirmation', 'Alpha')
            ->call('purger');

        // C'est toute la différence avec la suppression : la structure survit.
        $this->assertNotNull($this->entreprise->fresh());
        $this->assertNotNull($this->ville->fresh());
        $this->assertNotNull($this->site->fresh());
        $this->assertSame(0, Prospection::withoutGlobalScopes()->count());
    }

    public function test_la_suppression_n_epargne_rien(): void
    {
        $gerant = $this->compte('gerant@alpha.test', 'gerant');
        $commercial = $this->compte('com@alpha.test', 'commercial');
        $this->prospection();

        $this->ecran()
            ->set('suppressionId', $this->entreprise->id)
            ->set('confirmationSuppression', 'Alpha')
            ->call('supprimer');

        $this->assertNull($this->entreprise->fresh());
        $this->assertNull($this->ville->fresh());
        $this->assertNull($this->site->fresh());
        $this->assertNull($gerant->fresh(), 'Le gérant part avec le reste : rien ne survit.');
        $this->assertNull($commercial->fresh());
        $this->assertSame(0, Prospection::withoutGlobalScopes()->count());
        $this->assertSame(0, Commercial::withoutGlobalScopes()->count());
        $this->assertDatabaseMissing('roles', ['entreprise_id' => $this->entreprise->id]);
    }

    public function test_une_autre_entreprise_n_est_pas_effleuree(): void
    {
        $voisine = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);
        ProvisionneurEntreprise::creerRoles($voisine);
        $villeVoisine = Ville::create([
            'entreprise_id' => $voisine->id, 'code' => 'BOU', 'nom' => 'Bouaké', 'est_actif' => true,
        ]);
        $compteVoisin = User::create([
            'entreprise_id' => $voisine->id, 'name' => 'Voisin',
            'email' => 'voisin@beta.test', 'password' => Hash::make('password'),
        ]);

        $this->ecran()
            ->set('suppressionId', $this->entreprise->id)
            ->set('confirmationSuppression', 'Alpha')
            ->call('supprimer');

        $this->assertNotNull($voisine->fresh());
        $this->assertNotNull($villeVoisine->fresh());
        $this->assertNotNull($compteVoisin->fresh());
    }

    public function test_un_nom_mal_saisi_ne_supprime_rien(): void
    {
        // La confirmation par le nom est la dernière barrière avant l'irréversible.
        $this->ecran()
            ->set('suppressionId', $this->entreprise->id)
            ->set('confirmationSuppression', 'alpha')
            ->call('supprimer')
            ->assertHasErrors('confirmationSuppression');

        $this->assertNotNull($this->entreprise->fresh());
    }

    public function test_on_ne_supprime_pas_l_entreprise_dont_on_depend(): void
    {
        $interne = User::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Admin interne',
            'email' => 'interne@alpha.test', 'password' => Hash::make('password'),
            'est_fondateur' => true,
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);
        $interne->assignRole('super_admin');

        $this->actingAs($interne->fresh());

        // Se supprimer le sol sous les pieds couperait la session en cours de route.
        $this->ecran()
            ->set('suppressionId', $this->entreprise->id)
            ->set('confirmationSuppression', 'Alpha')
            ->call('supprimer')
            ->assertHasErrors('confirmationSuppression');

        $this->assertNotNull($this->entreprise->fresh());
    }

    public function test_la_suppression_laisse_sa_trace_au_journal(): void
    {
        $this->ecran()
            ->set('suppressionId', $this->entreprise->id)
            ->set('confirmationSuppression', 'Alpha')
            ->call('supprimer');

        // Une trace qui disparaîtrait avec son sujet ne prouverait plus rien : elle
        // est donc écrite avant, et porte le nom en clair.
        $this->assertDatabaseHas('activity_log', [
            'description' => "Suppression définitive de l'entreprise « Alpha »",
        ]);
    }

    private function ecran()
    {
        return Volt::test('superadmin.maintenance');
    }

    private function compte(string $email, string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $compte = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'ville_id' => $this->ville->id,
        ]);
        $compte->assignRole($role);

        return $compte->fresh();
    }

    private function prospection(): Prospection
    {
        $commercial = Commercial::firstOrCreate(
            ['entreprise_id' => $this->entreprise->id, 'numero' => 'C-0001'],
            ['ville_id' => $this->ville->id, 'nom' => 'Koffi Yao', 'statut' => 'Actif', 'est_spontane' => false],
        );

        return Prospection::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $this->site->id,
            'commercial_id' => $commercial->id, 'numero' => 'P-0001',
            'date' => now()->toDateString(), 'client' => 'SIFCA',
            'moyen' => 'RDV', 'activite' => 'Mécanique', 'statut_validation' => 'Validée',
        ]);
    }

    private function superAdmin(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        Role::firstOrCreate([
            'name' => 'super_admin', 'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $compte = User::create([
            'entreprise_id' => null, 'name' => 'Super Admin',
            'email' => 'sa@exemple.test', 'password' => Hash::make('password'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $compte->assignRole('super_admin');

        return $compte->fresh();
    }
}
