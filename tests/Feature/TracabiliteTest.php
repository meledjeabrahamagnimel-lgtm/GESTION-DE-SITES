<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Tracabilite\Modeles\SessionUtilisateur;
use Modules\Noyau\Tracabilite\Modeles\VisiteEcran;
use Modules\Noyau\Tracabilite\Services\JournalDeNavigation;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le journal de traçabilité.
 *
 * Ce qu'il faut vérifier n'est pas qu'il écrit — c'est qu'il écrit *juste*, et qu'il
 * n'écrit rien d'autre. Un journal qui compte chaque frappe au clavier comme un écran
 * ouvert donne des chiffres énormes et faux ; c'est pire que pas de journal du tout,
 * parce qu'on s'en sert.
 */
class TracabiliteTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
    }

    public function test_une_connexion_ouvre_une_ligne_de_journal(): void
    {
        $gerant = $this->compte('gerant');

        $this->post('/login', ['email' => $gerant->email, 'password' => 'motdepasse123'])
            ->assertRedirect();

        $session = SessionUtilisateur::firstWhere('user_id', $gerant->id);

        $this->assertNotNull($session, 'La connexion doit laisser une trace.');
        $this->assertSame('gerant', $session->role, 'Le rôle du moment est figé avec la session.');
        $this->assertNull($session->fermee_le);
        $this->assertTrue($session->estEnCours());
    }

    public function test_une_deconnexion_ferme_la_session_et_arrete_le_compteur(): void
    {
        $gerant = $this->compte('gerant');

        $this->post('/login', ['email' => $gerant->email, 'password' => 'motdepasse123']);

        // On recule l'ouverture : sans cela, l'aller-retour du test dure zéro seconde et
        // la durée calculée ne prouverait rien.
        $session = SessionUtilisateur::firstWhere('user_id', $gerant->id);
        $session->forceFill(['ouverte_le' => now()->subMinutes(20)])->save();

        $this->post('/logout');

        $session->refresh();

        $this->assertNotNull($session->fermee_le, 'Une déconnexion referme la ligne.');
        $this->assertSame('deconnexion', $session->motif_fin);
        $this->assertGreaterThanOrEqual(1190, $session->duree_secondes, 'Vingt minutes doivent être comptées.');
        $this->assertFalse($session->estEnCours());
    }

    public function test_une_page_ouverte_est_inscrite_sous_son_nom_lisible(): void
    {
        $gerant = $this->compte('gerant');

        $this->actingAs($gerant)->get(route('messages'))->assertOk();

        $visite = VisiteEcran::firstWhere('user_id', $gerant->id);

        $this->assertNotNull($visite, "L'ouverture d'un écran doit être inscrite.");
        $this->assertSame('messages', $visite->route);
        // « messages » ne dit rien dans un rapport : c'est l'intitulé qui est conservé.
        $this->assertSame('Messagerie', $visite->ecran);
    }

    public function test_les_requetes_de_fond_prouvent_la_presence_sans_compter_un_ecran(): void
    {
        $gerant = $this->compte('gerant');

        $this->actingAs($gerant)->get(route('messages'))->assertOk();

        $this->assertSame(1, VisiteEcran::where('user_id', $gerant->id)->count());

        $session = SessionUtilisateur::firstWhere('user_id', $gerant->id);
        $session->forceFill(['derniere_activite_le' => now()->subMinutes(5)])->save();

        // Livewire envoie une requête à chaque frappe : elle prouve qu'on est là, elle
        // n'ouvre pas d'écran. Les compter donnerait cent écrans pour une seule page.
        // L'adresse porte un suffixe propre à l'installation : on la demande plutôt que
        // de l'écrire, sinon le test passerait à côté sans rien signaler.
        $this->actingAs($gerant)
            ->withHeader('X-Livewire', 'true')
            ->post(app(\Livewire\Mechanisms\HandleRequests\HandleRequests::class)->getUpdateUri(), []);

        $this->assertSame(
            1,
            VisiteEcran::where('user_id', $gerant->id)->count(),
            "Une requête Livewire ne doit jamais compter comme un écran ouvert.",
        );

        $session->refresh();
        $this->assertTrue(
            $session->derniere_activite_le->gt(now()->subMinute()),
            'Elle doit en revanche prolonger la présence.',
        );
    }

    public function test_la_duree_d_un_ecran_s_arrete_a_l_ouverture_du_suivant(): void
    {
        $gerant = $this->compte('gerant');

        $this->actingAs($gerant)->get(route('messages'))->assertOk();

        $premiere = VisiteEcran::firstWhere('user_id', $gerant->id);
        $session = SessionUtilisateur::firstWhere('user_id', $gerant->id);

        // Douze minutes plus tard, la personne ouvre un autre écran.
        $premiere->forceFill(['vue_le' => now()->subMinutes(12)])->save();
        $session->forceFill(['derniere_activite_le' => now()])->save();

        $this->actingAs($gerant)->get(route('mes-notifications'))->assertOk();

        $premiere->refresh();

        $this->assertGreaterThanOrEqual(710, $premiere->duree_secondes);
        $this->assertSame(2, VisiteEcran::where('user_id', $gerant->id)->count());
    }

    public function test_une_session_muette_n_est_plus_comptee_comme_presente(): void
    {
        $gerant = $this->compte('gerant');

        $this->actingAs($gerant)->get(route('messages'))->assertOk();

        $session = SessionUtilisateur::firstWhere('user_id', $gerant->id);
        $session->forceFill([
            'ouverte_le' => now()->subHours(3),
            'derniere_activite_le' => now()->subHours(2),
        ])->save();

        $this->assertSame(0, SessionUtilisateur::enCours()->count(), 'Un onglet abandonné n\'est pas une présence.');

        $closes = app(JournalDeNavigation::class)->cloturerLesSessionsInactives();

        $session->refresh();

        $this->assertSame(1, $closes);
        $this->assertSame('expiration', $session->motif_fin);
        // La session s'arrête à la dernière preuve de présence, pas à l'heure du ménage :
        // sinon un entretien lancé une semaine trop tard fabriquerait des journées de
        // sept jours.
        $this->assertLessThan(3700, $session->duree_secondes);
    }

    public function test_la_purge_efface_les_traces_trop_anciennes(): void
    {
        $gerant = $this->compte('gerant');

        $this->actingAs($gerant)->get(route('messages'))->assertOk();

        SessionUtilisateur::query()->update([
            'ouverte_le' => now()->subDays(400),
            'derniere_activite_le' => now()->subDays(400),
            'fermee_le' => now()->subDays(400),
        ]);
        VisiteEcran::query()->update(['vue_le' => now()->subDays(400)]);

        app(JournalDeNavigation::class)->purger(180);

        $this->assertSame(0, SessionUtilisateur::count());
        $this->assertSame(0, VisiteEcran::count());
    }

    public function test_l_ecran_de_tracabilite_est_ferme_aux_autres_roles(): void
    {
        $gerant = $this->compte('gerant');

        // Le middleware de rôle redirige plutôt que d'afficher un refus : ce qui compte
        // est que le journal nominatif de toute la plateforme ne s'ouvre pas.
        $this->actingAs($gerant)->get(route('super-admin.tracabilite'))->assertRedirect();
    }

    private function compte(string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $compte = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => ucfirst($role),
            'email' => $role.'@alpha.test',
            'password' => Hash::make('motdepasse123'),
            'est_actif' => true,
        ]);
        $compte->assignRole($role);

        return $compte->fresh();
    }
}
