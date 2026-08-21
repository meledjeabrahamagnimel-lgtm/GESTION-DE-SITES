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
 * Le ménage parmi les comptes de la plateforme.
 *
 * La commande efface des administrateurs sur une base qui porte des données réelles :
 * ce qui doit être prouvé n'est pas d'abord qu'elle supprime, mais qu'elle ne supprime
 * rien tant qu'on ne l'a pas confirmé, qu'elle refuse ce qui laisserait la plateforme
 * sans administrateur, et que les écritures survivent à leur auteur.
 */
class MenageSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $garde;

    private User $residu;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        Role::firstOrCreate([
            'name' => 'super_admin', 'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $this->garde = $this->comptePlateforme('reel@exemple.test', 'Super Admin réel');
        $this->residu = $this->comptePlateforme('demo@plateforme.local', 'Super Admin de démonstration');
    }

    private function comptePlateforme(string $email, string $nom): User
    {
        $compte = User::create([
            'entreprise_id' => null, 'name' => $nom, 'email' => $email,
            'password' => Hash::make('mot-de-passe-quelconque'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $compte->assignRole('super_admin');

        return $compte;
    }

    public function test_sans_option_elle_inventorie_sans_rien_ecrire(): void
    {
        $this->artisan('superadmin:menage')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'demo@plateforme.local']);
        $this->assertDatabaseHas('users', ['email' => 'reel@exemple.test']);
    }

    public function test_sans_confirmer_elle_annonce_mais_n_efface_rien(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
        ])->assertSuccessful();

        // Le point entier de la simulation : le compte annoncé est toujours là.
        $this->assertDatabaseHas('users', ['email' => 'demo@plateforme.local']);
        $this->assertTrue((bool) $this->residu->refresh()->est_fondateur);
    }

    public function test_avec_confirmer_elle_efface_et_laisse_un_seul_fondateur(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
            '--confirmer' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'demo@plateforme.local']);
        $this->assertTrue((bool) $this->garde->refresh()->est_fondateur);

        $fondateurs = User::withoutGlobalScopes()->where('est_fondateur', true)->count();
        $this->assertSame(1, $fondateurs);
    }

    public function test_un_compte_non_supprime_perd_le_statut_de_fondateur_mais_garde_son_acces(): void
    {
        $secondaire = $this->comptePlateforme('secondaire@exemple.test', 'Admin secondaire');

        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
            '--confirmer' => true,
        ])->assertSuccessful();

        $secondaire->refresh();

        $this->assertDatabaseHas('users', ['email' => 'secondaire@exemple.test']);
        $this->assertFalse((bool) $secondaire->est_fondateur);
        $this->assertTrue((bool) $secondaire->est_actif);
        // Rétrograder n'est pas révoquer : le rôle reste, seul le privilège de fondateur part.
        $this->assertSame(1, DB::table('model_has_roles')
            ->where('model_type', $secondaire->getMorphClass())
            ->where('model_id', $secondaire->id)->count());
    }

    public function test_elle_refuse_de_garder_un_compte_inconnu(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'personne@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
            '--confirmer' => true,
        ])->assertFailed();

        $this->assertDatabaseHas('users', ['email' => 'demo@plateforme.local']);
    }

    public function test_elle_refuse_de_garder_un_compte_sans_role(): void
    {
        $sansRole = User::create([
            'entreprise_id' => null, 'name' => 'Coquille vide',
            'email' => 'vide@exemple.test', 'password' => Hash::make('x'),
            'est_actif' => true,
        ]);

        $this->artisan('superadmin:menage', [
            '--garder' => $sansRole->email,
            '--supprimer' => ['demo@plateforme.local', 'reel@exemple.test'],
            '--confirmer' => true,
        ])->assertFailed();

        // Sans ce refus, la plateforme n'aurait plus aucun administrateur capable d'entrer.
        $this->assertDatabaseHas('users', ['email' => 'reel@exemple.test']);
        $this->assertDatabaseHas('users', ['email' => 'demo@plateforme.local']);
    }

    public function test_elle_refuse_de_supprimer_le_compte_qu_on_lui_demande_de_garder(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['reel@exemple.test'],
            '--confirmer' => true,
        ])->assertFailed();

        $this->assertDatabaseHas('users', ['email' => 'reel@exemple.test']);
    }

    public function test_elle_refuse_de_toucher_un_compte_rattache_a_une_entreprise(): void
    {
        $entreprise = DB::table('entreprises')->insertGetId([
            'nom' => 'Garage test', 'slug' => 'garage-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $salarie = User::create([
            'entreprise_id' => $entreprise, 'name' => 'Salarié',
            'email' => 'salarie@exemple.test', 'password' => Hash::make('x'),
            'est_actif' => true,
        ]);

        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => [$salarie->email],
            '--confirmer' => true,
        ])->assertFailed();

        // Un accès d'entreprise se supprime là où la hiérarchie est vérifiée, pas ici.
        $this->assertDatabaseHas('users', ['email' => 'salarie@exemple.test']);
    }

    public function test_une_seule_adresse_refusee_annule_tout_le_lot(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local', 'inexistant@exemple.test'],
            '--confirmer' => true,
        ])->assertFailed();

        // Rien de partiel : le premier nom valable n'est pas effacé pour autant.
        $this->assertDatabaseHas('users', ['email' => 'demo@plateforme.local']);
    }

    public function test_les_acces_ouverts_par_un_compte_efface_survivent(): void
    {
        $entreprise = DB::table('entreprises')->insertGetId([
            'nom' => 'Garage test', 'slug' => 'garage-test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $filleul = User::create([
            'entreprise_id' => $entreprise, 'name' => 'Gérant',
            'email' => 'gerant@exemple.test', 'password' => Hash::make('x'),
            'est_actif' => true, 'cree_par_id' => $this->residu->id,
        ]);

        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
            '--confirmer' => true,
        ])->assertSuccessful();

        $filleul->refresh();

        // Supprimer celui qui a ouvert un accès ne ferme pas cet accès.
        $this->assertNull($filleul->cree_par_id);
        $this->assertTrue((bool) $filleul->est_actif);
        $this->assertSame($entreprise, $filleul->entreprise_id);
    }

    public function test_la_suppression_laisse_une_trace_au_journal(): void
    {
        $this->artisan('superadmin:menage', [
            '--garder' => 'reel@exemple.test',
            '--supprimer' => ['demo@plateforme.local'],
            '--confirmer' => true,
        ])->assertSuccessful();

        // La preuve doit survivre à son sujet, sinon elle ne prouve rien.
        $this->assertTrue(
            DB::table('activity_log')->where('description', 'like', '%demo@plateforme.local%')->exists(),
        );
    }
}
