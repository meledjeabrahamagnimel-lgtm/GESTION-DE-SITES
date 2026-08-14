<?php

namespace Tests\Feature;

use App\Domain\Tenants\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deux onglets du même navigateur partagent une seule session : se connecter à un
 * second compte dans l'onglet B remplace aussi la session de l'onglet A, resté ouvert
 * sur une page dont le rôle ne correspond plus à qui est réellement connecté. La
 * prochaine requête depuis cet onglet A tombait alors sur un 403 "User does not have
 * the right roles" — techniquement correct mais incompréhensible pour l'utilisateur.
 * Elle doit désormais renvoyer vers /redirection, qui route vers l'espace du compte
 * réellement connecté.
 */
class SecondOngletMauvaisRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_role_incorrect_redirige_vers_redirection_au_lieu_d_un_403(): void
    {
        $entreprise = Entreprise::create(['nom' => 'Smoke', 'slug' => 'smoke']);
        Role::findOrCreate('gerant', 'web');
        Role::findOrCreate('commercial', 'web');
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        $commercial = User::create([
            'name' => 'Commercial', 'email' => 'commercial-smoke@exemple.test', 'password' => 'x',
            'entreprise_id' => $entreprise->id, 'est_actif' => true,
        ]);
        $commercial->assignRole('commercial');

        // La route /tableau-de-bord exige le rôle gérant : un compte commercial qui y
        // accède (session partagée avec un onglet resté ouvert sur cette page) doit être
        // redirigé, jamais confronté à une page d'erreur brute.
        $response = $this->actingAs($commercial)->get('/tableau-de-bord');

        $response->assertRedirect(route('redirection'));
    }
}
