<?php

namespace Tests\Feature;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La commande qui remet les accès du serveur d'aplomb.
 *
 * Elle est faite pour être tapée en production, où une erreur ne se rattrape pas d'un
 * clic : on l'exécute donc réellement ici, et non seulement sa configuration. Un nom de
 * classe mal résolu ne se voit pas autrement qu'en la lançant.
 */
class InstallationDesComptesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_commande_s_execute_meme_sans_entreprise(): void
    {
        // Base vierge : la commande doit le dire et s'arrêter proprement, jamais planter.
        $this->artisan('demo:installer --comptes')->assertSuccessful();
    }

    public function test_la_commande_installe_les_quatorze_acces(): void
    {
        $this->preparerEntreprise();

        $this->artisan('demo:installer --comptes')->assertSuccessful();

        foreach ([
            'superadmin@gmail.com', 'support@gmail.com', 'gerant@gmail.com',
            'superviseurabidjan@gmail.com', 'responsableabidjansite1@gmail.com',
            'responsableabidjansite2@gmail.com', 'commercialabidjan@gmail.com',
            'comptabiliteabidjan@gmail.com', 'superviseurbouake@gmail.com',
            'commercialbouake@gmail.com', 'comptabilitebouake@gmail.com',
            'superviseursanpedro@gmail.com', 'commercialsanpedro@gmail.com',
            'comptabilitesanpedro@gmail.com',
        ] as $email) {
            $compte = User::where('email', $email)->first();

            $this->assertNotNull($compte, "Le compte $email devrait exister.");
            $this->assertTrue(Hash::check('password', $compte->password), "Le mot de passe de $email devrait être « password ».");
        }
    }

    public function test_une_adresse_renommee_conserve_son_historique(): void
    {
        $entreprise = $this->preparerEntreprise();

        // Le serveur porte encore l'ancienne adresse, avec ses saisies derrière elle.
        $ancien = User::create([
            'entreprise_id' => $entreprise->id,
            'name' => 'Fatou Diabaté',
            'email' => 'comptabiliteabidjansite2@gmail.com',
            'password' => Hash::make('password'),
        ]);

        $this->artisan('demo:installer --comptes')->assertSuccessful();

        // Renommé, et non recréé : le même identifiant, donc le même historique.
        $this->assertSame('comptabiliteabidjan@gmail.com', $ancien->fresh()->email);
        $this->assertDatabaseMissing('users', ['email' => 'comptabiliteabidjansite2@gmail.com']);
    }

    public function test_la_commande_est_rejouable_sans_creer_de_doublon(): void
    {
        $this->preparerEntreprise();

        $this->artisan('demo:installer --comptes')->assertSuccessful();
        $apresLePremier = User::count();
        $fichesApresLePremier = Commercial::count();

        $this->artisan('demo:installer --comptes')->assertSuccessful();

        $this->assertSame($apresLePremier, User::count());
        $this->assertSame($fichesApresLePremier, Commercial::count());
    }

    public function test_chaque_ville_recoit_son_client_spontane(): void
    {
        $this->preparerEntreprise();

        $this->artisan('demo:installer --comptes')->assertSuccessful();

        // Une vente sans commercial nommé doit pouvoir se rattacher, dans chaque ville.
        $spontanes = Commercial::where('est_spontane', true)->pluck('numero');

        $this->assertEqualsCanonicalizing(['SP-ABJ', 'SP-BOU', 'SP-SPD'], $spontanes->all());
    }

    /** L'organisation minimale que la commande s'attend à trouver : trois villes, trois lieux. */
    private function preparerEntreprise(): Entreprise
    {
        $entreprise = Entreprise::create(['nom' => "L'Artisan Automobile", 'slug' => 'artisan-automobile']);

        ProvisionneurEntreprise::creerRoles($entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        foreach ([
            ['ABJ', 'Abidjan', [['ABJ-1', 'Abidjan — Site 1'], ['ABJ-2', 'Abidjan — Site 2']]],
            ['BOU', 'Bouaké', [['BOU', 'Bouaké']]],
            ['SPD', 'San Pedro', [['SPD', 'San Pedro']]],
        ] as [$code, $nom, $lieux]) {
            $ville = Ville::create([
                'entreprise_id' => $entreprise->id, 'code' => $code, 'nom' => $nom, 'est_actif' => true,
            ]);

            foreach ($lieux as [$codeSite, $nomSite]) {
                Site::create([
                    'entreprise_id' => $entreprise->id, 'ville_id' => $ville->id,
                    'code' => $codeSite, 'nom' => $nomSite, 'est_actif' => true,
                ]);
            }
        }

        return $entreprise;
    }
}
