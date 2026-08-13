<?php

namespace Tests\Feature;

use App\Domain\Tenants\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectionSansRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_sans_role_reconnu_est_deconnecte_au_lieu_de_boucler(): void
    {
        $entreprise = Entreprise::create(['nom' => 'Smoke', 'slug' => 'smoke']);

        $utilisateur = User::create([
            'name' => 'Sans Role', 'email' => 'sans-role@exemple.test', 'password' => 'x',
            'entreprise_id' => $entreprise->id, 'est_actif' => true,
        ]);
        // Volontairement : aucun rôle assigné.

        $this->actingAs($utilisateur);

        $response = $this->get(route('redirection'));
        $response->assertRedirect(route('connexion'));

        // La session doit être coupée : la requête suivante ne doit plus être authentifiée.
        $this->assertGuest();
    }
}
