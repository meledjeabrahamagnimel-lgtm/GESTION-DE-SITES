<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La commande qui change les identifiants du super administrateur.
 *
 * Elle est faite pour être lancée sur un serveur qui porte des données réelles : ce qui
 * doit être prouvé n'est pas seulement qu'elle écrit ce qu'on lui demande, mais qu'elle
 * refuse tout le reste — et qu'en simulation, elle n'écrit rien du tout.
 */
class IdentifiantsSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        Role::firstOrCreate([
            'name' => 'super_admin', 'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $this->admin = User::create([
            'entreprise_id' => null, 'name' => 'Super Admin',
            'email' => 'ancien@exemple.test', 'password' => Hash::make('ancien-mot-de-passe'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_elle_remplace_adresse_et_mot_de_passe(): void
    {
        $this->artisan('superadmin:identifiants', [
            '--compte' => 'ancien@exemple.test',
            '--email' => 'it@dcknowing.com',
            '--mot-de-passe' => '@@@###26dcknowing',
            '--nom' => 'Super Admin DC-KNOWING',
        ])->assertSuccessful();

        $this->admin->refresh();

        $this->assertSame('it@dcknowing.com', $this->admin->email);
        $this->assertSame('Super Admin DC-KNOWING', $this->admin->name);
        // Le point qui compte : la connexion doit réellement fonctionner ensuite.
        $this->assertTrue(Hash::check('@@@###26dcknowing', $this->admin->password));
        $this->assertFalse((bool) $this->admin->doit_changer_mot_de_passe);
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $this->artisan('superadmin:identifiants', [
            '--compte' => 'ancien@exemple.test',
            '--email' => 'it@dcknowing.com',
            '--mot-de-passe' => 'quelquechose1',
            '--simulation' => true,
        ])->assertSuccessful();

        $this->admin->refresh();

        $this->assertSame('ancien@exemple.test', $this->admin->email);
        $this->assertTrue(Hash::check('ancien-mot-de-passe', $this->admin->password));
    }

    public function test_elle_refuse_une_adresse_deja_prise(): void
    {
        User::create([
            'entreprise_id' => null, 'name' => 'Quelqu\'un d\'autre',
            'email' => 'occupe@exemple.test', 'password' => Hash::make('motdepasse123'),
        ]);

        $this->artisan('superadmin:identifiants', [
            '--compte' => 'ancien@exemple.test',
            '--email' => 'occupe@exemple.test',
        ])->assertFailed();

        $this->admin->refresh();

        $this->assertSame('ancien@exemple.test', $this->admin->email, 'Rien ne doit être écrasé.');
    }

    public function test_elle_refuse_une_adresse_sans_arobase(): void
    {
        // La faute de départ, justement : « it.dcknowing.com » n'est pas une adresse.
        // Enregistrée telle quelle, elle rendrait le compte inutilisable.
        $this->artisan('superadmin:identifiants', [
            '--compte' => 'ancien@exemple.test',
            '--email' => 'it.dcknowing.com',
        ])->assertFailed();

        $this->assertSame('ancien@exemple.test', $this->admin->fresh()->email);
    }

    public function test_elle_refuse_un_mot_de_passe_trop_faible(): void
    {
        $this->artisan('superadmin:identifiants', [
            '--compte' => 'ancien@exemple.test',
            '--mot-de-passe' => 'court1',
        ])->assertFailed();

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $this->admin->fresh()->password));
    }

    public function test_elle_refuse_un_compte_qui_n_est_pas_de_la_plateforme(): void
    {
        $quidam = User::create([
            'entreprise_id' => null, 'name' => 'Quidam',
            'email' => 'quidam@exemple.test', 'password' => Hash::make('motdepasse123'),
        ]);

        $this->artisan('superadmin:identifiants', [
            '--compte' => 'quidam@exemple.test',
            '--mot-de-passe' => 'nouveaumotdepasse1',
        ])->assertFailed();

        $this->assertTrue(Hash::check('motdepasse123', $quidam->fresh()->password));
    }
}
