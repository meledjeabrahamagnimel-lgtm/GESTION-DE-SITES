<?php

namespace Tests\Feature;

use App\Domain\Tenants\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reproduit le scénario signalé en production : un utilisateur déjà connecté
 * (session partagée entre deux onglets du même navigateur) qui rouvre /login
 * dans un second onglet. Le middleware "guest" natif de Laravel ignorait
 * config('fortify.home') et repartait sur "/", qui renvoyait vers /connexion
 * puis /login : boucle infinie (ERR_TOO_MANY_REDIRECTS).
 */
class SecondOngletDejaConnecteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_deja_connecte_qui_rouvre_login_est_renvoye_vers_son_espace(): void
    {
        $entreprise = Entreprise::create(['nom' => 'Smoke', 'slug' => 'smoke']);
        Role::findOrCreate('gerant', 'web');
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        $gerant = User::create([
            'name' => 'Gerant', 'email' => 'gerant-smoke@exemple.test', 'password' => 'x',
            'entreprise_id' => $entreprise->id, 'est_actif' => true,
        ]);
        $gerant->assignRole('gerant');

        $this->actingAs($gerant);

        $response = $this->get('/login');
        $response->assertRedirect(route('redirection'));

        // La cible ne doit surtout pas être "/" : c'est ce repli qui provoquait la boucle.
        $this->assertNotEquals('/', $response->headers->get('Location'));

        // Suivre la redirection doit atterrir sur l'espace du gérant, sans boucler.
        $suite = $this->get(route('redirection'));
        $suite->assertRedirect(route('tableau-de-bord'));
    }
}
