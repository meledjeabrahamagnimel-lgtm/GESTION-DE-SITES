<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Modules\Noyau\Commun\Mails\BienvenueNouvelAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * L'exploitation réelle s'installe à côté de la démonstration, sans la toucher.
 *
 * Deux entreprises cohabitent : l'une sert aux présentations, l'autre au travail.
 * Le risque n'est pas qu'elles se mélangent — le périmètre d'entreprise l'interdit —
 * mais qu'on les confonde à l'écran, et qu'une facture parte au mauvais nom.
 */
class InstallationArtisanReelTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_demonstration_est_conservee_et_distinguee(): void
    {
        $demo = Entreprise::create(['nom' => "L'Artisan Automobile", 'slug' => 'artisan-automobile']);
        $ville = Ville::create(['entreprise_id' => $demo->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true]);
        $site = Site::create([
            'entreprise_id' => $demo->id, 'ville_id' => $ville->id,
            'code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1', 'est_actif' => true,
        ]);
        $commercial = Commercial::create([
            'entreprise_id' => $demo->id, 'ville_id' => $ville->id, 'numero' => 'C-0001',
            'nom' => 'Koffi Yao', 'statut' => 'Actif', 'est_spontane' => false,
        ]);
        $prospection = Prospection::create([
            'entreprise_id' => $demo->id, 'site_id' => $site->id,
            'commercial_id' => $commercial->id, 'numero' => 'P-0001',
            'date' => now()->toDateString(), 'client' => 'Client démo',
            'moyen' => 'RDV', 'activite' => 'Mécanique', 'statut_validation' => 'Validée',
        ]);

        $this->artisan('artisan-reel:installer')->assertSuccessful();

        // Le renommage ne touche qu'à l'intitulé : les écritures de démonstration
        // restent là, c'est tout leur intérêt pour une présentation.
        $demo->refresh();
        $this->assertStringContainsString('Test', $demo->nom);
        $this->assertSame('artisan-automobile', $demo->slug, 'Le slug ne bouge pas : il sert de repère.');
        $this->assertDatabaseHas('prospections', ['id' => $prospection->id, 'entreprise_id' => $demo->id]);
    }

    public function test_l_entreprise_reelle_recoit_sa_structure_et_ses_acces(): void
    {
        $this->artisan('artisan-reel:installer')->assertSuccessful();

        $reelle = Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile-reel')->firstOrFail();

        $this->assertSame(3, Ville::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->count());
        $this->assertSame(4, Site::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->count());
        $this->assertSame(14, User::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->count());

        // Le superviseur d'Abidjan est bien désigné sur sa ville, sinon il ne verrait
        // rien à piloter.
        $abidjan = Ville::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->where('code', 'ABJ')->firstOrFail();
        $this->assertSame(
            'k.desiree@lartisanauto.com',
            User::withoutGlobalScopes()->find($abidjan->responsable_id)?->email
        );
    }

    public function test_aucun_acces_n_est_ouvert_ni_annonce(): void
    {
        Mail::fake();

        $this->artisan('artisan-reel:installer')->assertSuccessful();

        $reelle = Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile-reel')->firstOrFail();
        $comptes = User::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->get();

        $this->assertTrue($comptes->every(fn (User $u) => ! $u->est_actif), 'Tous les accès sont préparés, aucun ouvert.');

        // Un accès préparé n'annonce rien : souhaiter la bienvenue à quelqu'un qui ne
        // peut pas encore entrer serait le mettre devant une porte fermée.
        Mail::assertNothingSent();
    }

    public function test_le_courriel_part_le_jour_de_l_activation(): void
    {
        Mail::fake();
        $this->artisan('artisan-reel:installer')->assertSuccessful();

        $compte = User::withoutGlobalScopes()->where('email', 'y.ella@lartisanauto.com')->firstOrFail();

        app(\Modules\Noyau\Entreprises\Actions\CreerAcces::class)->activer($compte);

        $this->assertTrue($compte->fresh()->est_actif);
        Mail::assertSent(BienvenueNouvelAcces::class, fn ($mail) => $mail->hasTo('y.ella@lartisanauto.com'));
    }

    public function test_la_commande_se_rejoue_sans_rien_dupliquer(): void
    {
        $this->artisan('artisan-reel:installer')->assertSuccessful();
        $this->artisan('artisan-reel:installer')->assertSuccessful();

        $reelle = Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile-reel')->firstOrFail();

        $this->assertSame(1, Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile-reel')->count());
        $this->assertSame(4, Site::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->count());
        $this->assertSame(14, User::withoutGlobalScopes()->where('entreprise_id', $reelle->id)->count());
    }

    public function test_le_logo_de_la_maison_est_repris(): void
    {
        Entreprise::create([
            'nom' => "L'Artisan Automobile", 'slug' => 'artisan-automobile',
            'logo_chemin' => 'public:images/artisan.png', 'couleur_accent' => '#C8102E',
        ]);

        $this->artisan('artisan-reel:installer')->assertSuccessful();

        $reelle = Entreprise::withoutGlobalScopes()->where('slug', 'artisan-automobile-reel')->firstOrFail();

        // Sans logo, les courriels de bienvenue partiraient avec un en-tête vide.
        $this->assertSame('public:images/artisan.png', $reelle->logo_chemin);
        $this->assertSame('#C8102E', $reelle->couleur_accent);
    }

    public function test_le_super_admin_ouvre_un_dossier_avec_le_seul_nom(): void
    {
        $this->actingAs($this->superAdmin());

        Volt::test('superadmin.entreprises-liste')
            ->set('nouvelleEntreprise', 'Garage Nouveau')
            ->call('creer');

        $creee = Entreprise::withoutGlobalScopes()->where('nom', 'Garage Nouveau')->first();

        $this->assertNotNull($creee, "Le nom doit suffire : le gérant se nomme parfois plus tard.");
        $this->assertDatabaseHas('roles', ['name' => 'gerant', 'entreprise_id' => $creee->id]);
    }

    public function test_le_super_admin_renomme_sans_rien_perdre(): void
    {
        $entreprise = Entreprise::create(['nom' => "L'Artisan Automobile", 'slug' => 'artisan-automobile']);
        $this->actingAs($this->superAdmin());

        Volt::test('superadmin.entreprises-liste')
            ->call('renommer', $entreprise->id)
            ->set('renommeNom', "L'Artisan Automobile — Test")
            ->call('enregistrerRenommage');

        $entreprise->refresh();
        $this->assertSame("L'Artisan Automobile — Test", $entreprise->nom);
        $this->assertSame('artisan-automobile', $entreprise->slug, 'Le slug ne suit pas : il sert de repère stable.');
    }

    private function superAdmin(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\Database\Seeders\SuperAdminSeeder::EQUIPE_PLATEFORME);

        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'super_admin', 'guard_name' => 'web',
            'entreprise_id' => \Database\Seeders\SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $compte = User::create([
            'entreprise_id' => null, 'name' => 'Super Admin',
            'email' => 'sa@exemple.test', 'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $compte->assignRole('super_admin');

        return $compte->fresh();
    }
}
