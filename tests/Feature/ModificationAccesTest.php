<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reprendre un accès se fait sur un écran, pas dans une cellule de tableau.
 *
 * Le formulaire logé dans la ligne devenait illisible dès que l'adresse dépassait
 * la largeur de la colonne — et il désorganisait le rendu de la page, au point que
 * la pagination cessait de répondre juste après.
 */
class ModificationAccesTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $ville;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);

        $this->ville = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        $this->actingAs($this->superAdmin());
    }

    public function test_l_ecran_s_ouvre_prerempli(): void
    {
        $compte = $this->acces();

        $this->get(route('super-admin.acces.modifier', $compte))
            ->assertOk()
            ->assertSee('Modifier un accès')
            ->assertSee('koffi@exemple.test');

        $ecran = Volt::test('superadmin.acces-creer', ['utilisateur' => $compte->id]);

        $ecran->assertSet('nom', 'Koffi Yao')
            ->assertSet('email', 'koffi@exemple.test')
            ->assertSet('roleActif', 'commercial')
            ->assertSet('ouverture', 'inactif');
    }

    public function test_la_correction_suit_jusqu_a_la_fiche_commerciale(): void
    {
        $compte = $this->acces();
        Commercial::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $this->ville->id,
            'user_id' => $compte->id, 'numero' => 'C-0001',
            'nom' => 'Koffi Yao', 'statut' => 'Actif', 'est_spontane' => false,
        ]);

        Volt::test('superadmin.acces-creer', ['utilisateur' => $compte->id])
            ->set('nom', 'KOUASSI Koffi')
            ->set('email', 'kouassi@exemple.test')
            ->set('telephone', '+225 07 00 00 00')
            ->set('villeChoix', $this->ville->id)
            ->call('enregistrer');

        $compte->refresh();
        $this->assertSame('KOUASSI Koffi', $compte->name);
        $this->assertSame('kouassi@exemple.test', $compte->email);

        // Deux orthographes de la même personne dans les tableaux seraient pires
        // que l'ancienne : on ne saurait plus laquelle est la bonne.
        $this->assertSame('KOUASSI Koffi', Commercial::where('user_id', $compte->id)->value('nom'));
    }

    public function test_un_mot_de_passe_laisse_vide_n_est_pas_touche(): void
    {
        $compte = $this->acces();
        $avant = $compte->password;

        Volt::test('superadmin.acces-creer', ['utilisateur' => $compte->id])
            ->set('nom', 'Koffi Yao')
            ->set('villeChoix', $this->ville->id)
            ->call('enregistrer');

        // Remplacer le mot de passe coupe la connexion en cours du titulaire : ce n'est
        // pas un effet de bord acceptable pour une correction d'orthographe.
        $this->assertSame($avant, $compte->fresh()->password);
    }

    public function test_un_acces_en_service_ne_change_plus_de_role(): void
    {
        $compte = $this->acces(['est_actif' => true]);
        $compte->forceFill(['derniere_connexion_le' => now()])->save();

        $ecran = Volt::test('superadmin.acces-creer', ['utilisateur' => $compte->id]);
        $ecran->assertSet('roleActif', 'commercial');

        // Ses écritures lui sont rattachées : le déplacer les laisserait derrière lui.
        $ecran->call('choisirRole', 'gerant')->assertSet('roleActif', 'commercial');
    }

    public function test_un_acces_jamais_ouvert_se_remodele_entierement(): void
    {
        $compte = $this->acces();

        Volt::test('superadmin.acces-creer', ['utilisateur' => $compte->id])
            ->call('choisirRole', 'caissier')
            ->assertSet('roleActif', 'caissier');
    }

    public function test_l_ecran_de_creation_reste_vierge(): void
    {
        $this->get(route('super-admin.acces.creer'))
            ->assertOk()
            ->assertSee("Créer un accès")
            ->assertDontSee('Modifier un accès');
    }

    public function test_la_liste_pagine_jusqu_au_bout(): void
    {
        // La ligne éditable désorganisait le rendu et la pagination cessait de
        // répondre juste après. Elle a disparu ; on vérifie que les pages tournent.
        for ($i = 0; $i < 25; $i++) {
            $this->acces(['email' => "compte$i@exemple.test"]);
        }

        $ecran = Volt::test('superadmin.acces-liste');
        $total = $ecran->instance()->utilisateurs->count();

        $ecran->set('pageUtilisateurs', 3)->assertSet('pageUtilisateurs', 3);

        $this->assertGreaterThan(20, $total);
        $ecran->assertSee('Page 3');
    }

    private function acces(array $attributs = []): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $compte = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => 'Koffi Yao',
            'email' => 'koffi@exemple.test',
            'password' => Hash::make('password'),
            'ville_id' => $this->ville->id,
            'est_actif' => false,
            ...$attributs,
        ]);
        $compte->assignRole('commercial');

        return $compte->fresh();
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
