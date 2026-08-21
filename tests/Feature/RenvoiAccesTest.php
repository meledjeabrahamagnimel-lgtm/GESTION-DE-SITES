<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Entreprises\Actions\RenvoyerLAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le renvoi du courriel d'accès.
 *
 * Un courriel qui n'arrive pas ne fait pas de bruit : l'accès est ouvert côté
 * administration, la personne n'a rien reçu, et chacun attend l'autre.
 *
 * Ce qu'il faut prouver n'est pas seulement que le message repart. C'est qu'il ne casse
 * rien au passage : sur un compte déjà en service, le mot de passe doit rester
 * exactement celui de son titulaire. Un renvoi bien intentionné qui déconnecterait
 * quelqu'un en pleine saisie serait pire que le silence qu'il prétend réparer.
 */
class RenvoiAccesTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $ville;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);

        $this->ville = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);
        $this->site = Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $this->ville->id,
            'code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1', 'est_actif' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | L'accès qui n'a jamais servi — le cas visé
    |--------------------------------------------------------------------------
    */

    public function test_un_acces_jamais_ouvert_est_active_et_recoit_un_lien_de_mot_de_passe(): void
    {
        $admin = $this->superAdmin();
        $compte = $this->compte('commercial', actif: false, doitChanger: true);

        $this->equipePlateforme();

        $bilan = app(RenvoyerLAcces::class)->executer($admin, $compte);

        $compte->refresh();

        $this->assertTrue($compte->est_actif, "L'accès encore fermé doit s'ouvrir : c'est l'étape manquée.");
        $this->assertTrue((bool) $compte->doit_changer_mot_de_passe);
        $this->assertSame('definition', $bilan['lien']);
        $this->assertTrue($bilan['active']);

        Mail::assertSent(BienvenueNouvelAcces::class, fn ($mail) => $mail->hasTo($compte->email) && $mail->renvoi);
    }

    public function test_un_acces_ouvert_mais_jamais_utilise_recoit_aussi_un_lien(): void
    {
        $admin = $this->superAdmin();

        // Ouvert depuis des semaines, mais personne n'est jamais entré : c'est très
        // exactement la situation décrite — « je n'ai jamais reçu le message ».
        $compte = $this->compte('commercial', actif: true, doitChanger: true);

        $this->equipePlateforme();

        $bilan = app(RenvoyerLAcces::class)->executer($admin, $compte);

        $this->assertFalse($bilan['active'], "L'accès était déjà ouvert : rien à ouvrir.");
        $this->assertSame('definition', $bilan['lien']);
        Mail::assertSent(BienvenueNouvelAcces::class);
    }

    /*
    |--------------------------------------------------------------------------
    | L'accès déjà en service — ce qu'il ne faut surtout pas casser
    |--------------------------------------------------------------------------
    */

    public function test_un_compte_en_service_garde_son_mot_de_passe_intact(): void
    {
        $admin = $this->superAdmin();

        // Le titulaire a choisi son mot de passe : le drapeau est retombé.
        $compte = $this->compte('commercial', actif: true, doitChanger: false);
        $empreinte = $compte->password;

        $this->equipePlateforme();

        $bilan = app(RenvoyerLAcces::class)->executer($admin, $compte);

        $compte->refresh();

        $this->assertSame($empreinte, $compte->password, 'Le mot de passe ne doit pas bouger d\'un octet.');
        $this->assertFalse((bool) $compte->doit_changer_mot_de_passe,
            "On n'impose pas un changement de mot de passe à quelqu'un qui n'a rien demandé.");
        $this->assertSame('rappel', $bilan['lien']);
        $this->assertTrue(Hash::check('motdepasse123', $compte->password));
    }

    public function test_un_compte_qui_porte_des_saisies_est_tenu_pour_en_service(): void
    {
        $admin = $this->superAdmin();

        // Drapeau encore levé, mais des écritures existent sous son nom : quelqu'un s'en
        // sert. Dans le doute, on ne touche à rien.
        $compte = $this->compte('responsable_site', actif: true, doitChanger: true);

        // Une charge plutôt qu'une facture : elle ne réclame pas de commercial, et ce
        // qu'on veut prouver ici n'est pas le type de la saisie mais son existence.
        DB::table('charges')->insert([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => $this->site->id,
            'date' => now()->toDateString(),
            'libelle' => 'Achat de pièces',
            'montant' => 500000,
            'cree_par' => $compte->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(RenvoyerLAcces::class)->aDejaServi($compte));

        $this->equipePlateforme();

        $bilan = app(RenvoyerLAcces::class)->executer($admin, $compte);

        $this->assertSame('rappel', $bilan['lien']);
    }

    public function test_une_connexion_au_journal_suffit_a_tenir_le_compte_pour_en_service(): void
    {
        $compte = $this->compte('commercial', actif: true, doitChanger: true);

        $this->assertFalse(app(RenvoyerLAcces::class)->aDejaServi($compte));

        \Modules\Noyau\Tracabilite\Modeles\SessionUtilisateur::create([
            'user_id' => $compte->id,
            'entreprise_id' => $this->entreprise->id,
            'ouverte_le' => now()->subDay(),
            'derniere_activite_le' => now()->subDay(),
        ]);

        $this->assertTrue(app(RenvoyerLAcces::class)->aDejaServi($compte->fresh()));
    }

    /*
    |--------------------------------------------------------------------------
    | Les refus
    |--------------------------------------------------------------------------
    */

    public function test_un_compte_de_plateforme_n_a_pas_de_courriel_d_accueil(): void
    {
        $admin = $this->superAdmin();

        $autre = User::create([
            'entreprise_id' => null, 'name' => 'Second admin',
            'email' => 'second@exemple.test', 'password' => Hash::make('motdepasse123'), 'est_actif' => true,
        ]);

        // Le message parle au nom d'une entreprise — son logo, son nom, son périmètre.
        // Un compte de plateforme n'en a pas : il n'y a rien à écrire.
        $this->equipePlateforme();

        $this->assertNotNull(app(RenvoyerLAcces::class)->motifDuRefus($admin, $autre));

        $this->expectException(\RuntimeException::class);
        app(RenvoyerLAcces::class)->executer($admin, $autre);
    }

    public function test_un_commercial_ne_renvoie_le_courriel_de_personne(): void
    {
        $commercial = $this->compte('commercial', actif: true, doitChanger: false);
        $collegue = $this->compte('caissier', actif: true, doitChanger: false, email: 'collegue@alpha.test');

        $this->assertFalse(app(RenvoyerLAcces::class)->autorise($commercial, $collegue));

        Mail::assertNothingSent();
    }

    /*
    |--------------------------------------------------------------------------
    | L'écran : filtre par entreprise et envoi groupé
    |--------------------------------------------------------------------------
    */

    public function test_choisir_une_entreprise_coche_tout_son_personnel(): void
    {
        $admin = $this->superAdmin();

        $this->compte('gerant', actif: true, doitChanger: false, email: 'g@alpha.test');
        $this->compte('commercial', actif: false, doitChanger: true, email: 'c@alpha.test');

        // Une voisine, dont personne ne doit être coché.
        $voisine = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);
        ProvisionneurEntreprise::creerRoles($voisine);
        User::create([
            'entreprise_id' => $voisine->id, 'name' => 'Voisin',
            'email' => 'voisin@beta.test', 'password' => Hash::make('motdepasse123'), 'est_actif' => true,
        ]);

        $this->equipePlateforme();

        $composant = Volt::actingAs($admin)->test('superadmin.acces-liste')
            ->set('entrepriseFiltre', (string) $this->entreprise->id);

        $selection = $composant->get('selection');

        $this->assertCount(2, $selection, "Les deux comptes d'Alpha doivent être cochés, et eux seuls.");
        $this->assertNotContains((string) User::firstWhere('email', 'voisin@beta.test')->id, $selection);
    }

    public function test_le_renvoi_groupe_atteint_toute_la_selection(): void
    {
        $admin = $this->superAdmin();

        $this->compte('gerant', actif: true, doitChanger: false, email: 'g@alpha.test');
        $this->compte('commercial', actif: false, doitChanger: true, email: 'c@alpha.test');

        $this->equipePlateforme();

        Volt::actingAs($admin)->test('superadmin.acces-liste')
            ->set('entrepriseFiltre', (string) $this->entreprise->id)
            ->call('renvoyerSelection');

        Mail::assertSent(BienvenueNouvelAcces::class, 2);

        // Le compte préparé s'ouvre au passage ; celui déjà en service garde son mot de passe.
        $this->assertTrue(User::firstWhere('email', 'c@alpha.test')->est_actif);
        $this->assertFalse((bool) User::firstWhere('email', 'g@alpha.test')->doit_changer_mot_de_passe);
    }

    public function test_le_renvoi_a_l_unite_laisse_une_trace(): void
    {
        $admin = $this->superAdmin();
        $compte = $this->compte('commercial', actif: false, doitChanger: true);

        $this->equipePlateforme();

        Volt::actingAs($admin)->test('superadmin.acces-liste')
            ->call('renvoyer', $compte->id);

        // Un courriel nominatif qui repart se sait : c'est ce qui permet de répondre,
        // trois semaines plus tard, à « on ne m'a jamais rien renvoyé ».
        $this->assertDatabaseHas('activity_log', [
            'description' => "Renvoi du courriel d'accès à {$compte->name}",
            'causer_id' => $admin->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Outillage
    |--------------------------------------------------------------------------
    */

    private function compte(string $role, bool $actif, bool $doitChanger, ?string $email = null): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $compte = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => ucfirst($role),
            'email' => $email ?? $role.'@alpha.test',
            'password' => Hash::make('motdepasse123'),
            'ville_id' => $this->ville->id,
            'est_actif' => $actif,
            'doit_changer_mot_de_passe' => $doitChanger,
        ]);
        $compte->assignRole($role);

        return $compte->fresh();
    }

    /**
     * Rejoue ce que fait DefinirEquipePermissions à chaque requête.
     *
     * Spatie filtre les rôles sur l'équipe posée dans le contexte courant. Créer un
     * compte d'entreprise après le super administrateur laisse cette équipe sur
     * l'entreprise, et hasRole('super_admin') — dont le rôle vit dans l'équipe 0 —
     * répond alors « non ». En requête réelle le middleware s'en charge ; hors requête,
     * c'est au test de le faire.
     */
    private function equipePlateforme(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);
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
            'email' => 'sa@exemple.test', 'password' => Hash::make('motdepasse123'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $compte->assignRole('super_admin');

        return $compte->fresh();
    }
}
