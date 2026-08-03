<?php

namespace Tests\Feature;

use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Services\AnnuaireMessagerie;
use App\Domain\Messagerie\Services\Messagerie;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Tenants\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MessagerieTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $alpha;

    private Entreprise $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        $this->beta = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);

        foreach (['super_admin', 'gerant', 'responsable_site', 'commercial'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function membre(string $role, ?Entreprise $entreprise, string $nom): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise?->id ?? 0);

        $utilisateur = User::create([
            'name' => $nom,
            'email' => str($nom)->slug().'@exemple.test',
            'password' => 'secret-de-test',
            'entreprise_id' => $entreprise?->id,
            'est_actif' => true,
        ]);

        $utilisateur->assignRole($role);

        return $utilisateur;
    }

    /** Le cloisonnement inter-entreprises est la règle non négociable de l'annuaire. */
    public function test_aucune_communication_entre_deux_entreprises(): void
    {
        $commercialAlpha = $this->membre('commercial', $this->alpha, 'Commercial Alpha');
        $commercialBeta = $this->membre('commercial', $this->beta, 'Commercial Beta');
        $gerantBeta = $this->membre('gerant', $this->beta, 'Gerant Beta');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);

        $destinataires = AnnuaireMessagerie::destinataires($commercialAlpha)->pluck('id');

        $this->assertNotContains($commercialBeta->id, $destinataires);
        $this->assertNotContains($gerantBeta->id, $destinataires);
        $this->assertFalse(AnnuaireMessagerie::peutEcrireA($commercialAlpha, $commercialBeta->id));
    }

    public function test_le_commercial_ecrit_aux_responsables_et_aux_commerciaux_de_son_entreprise(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $autreCommercial = $this->membre('commercial', $this->alpha, 'Commercial Deux');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');
        $gerant = $this->membre('gerant', $this->alpha, 'Gerant Alpha');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);

        $ids = AnnuaireMessagerie::destinataires($commercial)->pluck('id');

        $this->assertContains($autreCommercial->id, $ids);
        $this->assertContains($responsable->id, $ids);
        // Le commercial passe par son responsable, jamais directement par le gérant.
        $this->assertNotContains($gerant->id, $ids);
    }

    public function test_le_gerant_joint_son_personnel_et_la_plateforme(): void
    {
        $gerant = $this->membre('gerant', $this->alpha, 'Gerant Alpha');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $superAdmin = $this->membre('super_admin', null, 'Super Admin');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);

        $ids = AnnuaireMessagerie::destinataires($gerant)->pluck('id');

        $this->assertContains($responsable->id, $ids);
        $this->assertContains($commercial->id, $ids);
        $this->assertContains($superAdmin->id, $ids, 'Le gérant doit pouvoir écrire au Super Admin.');
    }

    public function test_le_super_admin_ecrit_a_toutes_les_entreprises(): void
    {
        $superAdmin = $this->membre('super_admin', null, 'Super Admin');
        $alpha = $this->membre('commercial', $this->alpha, 'Commercial Alpha');
        $beta = $this->membre('gerant', $this->beta, 'Gerant Beta');

        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $ids = AnnuaireMessagerie::destinataires($superAdmin)->pluck('id');

        $this->assertContains($alpha->id, $ids);
        $this->assertContains($beta->id, $ids);
    }

    /** Un identifiant hors annuaire glissé dans la requête doit être écarté côté serveur. */
    public function test_un_destinataire_interdit_est_ecarte_a_l_envoi(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');
        $intrus = $this->membre('commercial', $this->beta, 'Commercial Beta');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($commercial);

        $conversation = Messagerie::ouvrir($commercial, [$responsable->id, $intrus->id], 'Objet', 'Bonjour');

        $participants = $conversation->participants()->pluck('users.id');

        $this->assertContains($responsable->id, $participants);
        $this->assertNotContains($intrus->id, $participants, "L'intrus ne doit jamais rejoindre la conversation.");
    }

    public function test_un_message_depose_une_notification_chez_les_destinataires(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($commercial);

        Messagerie::ouvrir($commercial, [$responsable->id], null, 'Bonjour responsable');

        $this->assertSame(1, NotificationApp::where('user_id', $responsable->id)->nonLues()->count());
        $this->assertSame(0, NotificationApp::where('user_id', $commercial->id)->count());
    }

    public function test_un_tiers_ne_peut_pas_lire_ni_repondre_a_une_conversation(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');
        $tiers = $this->membre('commercial', $this->alpha, 'Commercial Trois');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($commercial);

        $conversation = Messagerie::ouvrir($commercial, [$responsable->id], null, 'Confidentiel');

        $this->assertNull(Conversation::query()->visiblePour($tiers)->find($conversation->id));

        try {
            Messagerie::repondre($conversation, $tiers, 'Je m’invite');
            $this->fail('Un non-participant ne doit pas pouvoir répondre.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('messages', ['corps' => 'Je m’invite']);
    }

    public function test_la_page_messages_s_affiche_et_envoie(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($commercial);

        Volt::test('messages')
            ->call('nouvelleConversation')
            ->set('destinataires', [$responsable->id])
            ->set('corpsNouveau', 'Premier message')
            ->call('envoyerNouvelle')
            ->assertHasNoErrors();

        $this->assertSame(1, Conversation::count());
        $this->assertDatabaseHas('messages', ['corps' => 'Premier message']);
    }

    public function test_la_cloche_compte_les_notifications_non_lues(): void
    {
        $commercial = $this->membre('commercial', $this->alpha, 'Commercial Un');
        $responsable = $this->membre('responsable_site', $this->alpha, 'Responsable Un');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($commercial);
        Messagerie::ouvrir($commercial, [$responsable->id], null, 'Coucou');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $this->actingAs($responsable);

        // Le contenu du panneau n'est rendu qu'une fois celui-ci ouvert.
        Volt::test('cloche-notifications')
            ->assertSee('1')
            ->call('basculer')
            ->assertSee('Message de Commercial Un')
            ->call('toutMarquerLu');

        $this->assertSame(0, NotificationApp::where('user_id', $responsable->id)->nonLues()->count());
    }
}
