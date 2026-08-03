<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** La racine mène à la page de connexion : l'application n'a pas de page publique. */
    public function test_la_racine_mene_a_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/connexion');
    }
}
