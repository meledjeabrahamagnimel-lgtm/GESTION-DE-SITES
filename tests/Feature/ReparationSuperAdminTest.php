<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Les trois façons de murer la plateforme, et la commande qui les défait.
 *
 * Elles donnent toutes la même page — « Cette section ne vous est pas ouverte » —
 * et rien à l'écran ne dit laquelle. Chacune est reproduite ici, puis réparée, et
 * l'on vérifie que la page s'ouvre réellement : constater que le drapeau est posé
 * ne prouvait pas que l'accès fonctionnait.
 */
class ReparationSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_super_admin_sans_drapeau_fondateur_est_repare(): void
    {
        $compte = $this->superAdmin(['est_fondateur' => false, 'habilitations' => null]);

        $this->actingAs($compte)->get('/super-admin')->assertForbidden();

        $this->artisan('superadmin:reparer', ['email' => $compte->email])->assertSuccessful();

        $this->actingAs($compte->fresh())->get('/super-admin')->assertOk();
    }

    public function test_un_super_admin_sans_role_est_repare(): void
    {
        $compte = $this->superAdmin();

        // Le lien vers le rôle a disparu : le compte ne franchit même plus le contrôle
        // de rôle, bien avant celui des sections. Le symptôme diffère — une redirection,
        // non un 403 — ce qui permet de distinguer les deux pannes à l'œil.
        DB::table('model_has_roles')->where('model_id', $compte->id)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($compte->fresh())->get('/super-admin')->assertRedirect();

        $this->artisan('superadmin:reparer', ['email' => $compte->email])->assertSuccessful();

        $this->actingAs($compte->fresh())->get('/super-admin')->assertOk();
    }

    public function test_un_super_admin_rattache_a_une_entreprise_est_detache(): void
    {
        $entreprise = \Modules\Noyau\Entreprises\Modeles\Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        $compte = $this->superAdmin(['entreprise_id' => $entreprise->id]);

        // L'équipe bascule alors sur l'entreprise, et le rôle — posé dans l'équipe
        // conventionnelle 0 — devient introuvable pour lui : il est refoulé dès le
        // contrôle de rôle, donc redirigé plutôt que 403.
        $this->actingAs($compte->fresh())->get('/super-admin')->assertRedirect();

        $this->artisan('superadmin:reparer', ['email' => $compte->email])->assertSuccessful();

        $this->assertNull($compte->fresh()->entreprise_id);
        $this->actingAs($compte->fresh())->get('/super-admin')->assertOk();
    }

    public function test_la_commande_signale_une_adresse_inconnue_sans_rien_creer(): void
    {
        // Réparer un compte qui n'existe pas en le créant serait ouvrir un accès
        // d'administration sur une faute de frappe.
        $this->artisan('superadmin:reparer', ['email' => 'inconnu@exemple.test'])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'inconnu@exemple.test']);
    }

    public function test_le_diagnostic_seul_ne_modifie_rien(): void
    {
        $compte = $this->superAdmin(['est_fondateur' => false]);

        $this->artisan('superadmin:reparer', ['email' => $compte->email, '--diagnostic' => true])->assertSuccessful();

        $this->assertFalse($compte->fresh()->est_fondateur);
    }

    private function superAdmin(array $attributs = []): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $compte = User::create([
            'entreprise_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@exemple.test',
            'password' => Hash::make('password'),
            'est_actif' => true,
            'est_fondateur' => true,
            ...$attributs,
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => $compte->getMorphClass(),
            'model_id' => $compte->id,
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $compte->fresh();
    }
}
