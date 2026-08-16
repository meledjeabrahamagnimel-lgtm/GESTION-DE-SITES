<?php

namespace Tests\Feature;

use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use Modules\Noyau\Commun\Modeles\NotificationApp;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Circuit de validation : le responsable est prévenu d'une transmission,
 * le commercial est prévenu du retour.
 */
class NotificationsProspectionTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Site $site;

    private User $responsable;

    private User $commercialUser;

    private Commercial $commercial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);

        foreach (['gerant', 'responsable_ville', 'commercial'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $this->responsable = $this->utilisateur('responsable_ville', 'Responsable Un', 'resp@exemple.test');
        $this->commercialUser = $this->utilisateur('commercial', 'Commercial Un', 'com@exemple.test');

        $ville = Ville::create([
            'entreprise_id' => $this->entreprise->id,
            'code' => 'AB1',
            'nom' => 'Abidjan',
            'responsable_id' => $this->responsable->id,
        ]);

        $this->site = Site::create([
            'entreprise_id' => $this->entreprise->id,
            'ville_id' => $ville->id,
            'nom' => 'Abidjan',
        ]);

        $this->commercial = Commercial::create([
            'entreprise_id' => $this->entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $this->commercialUser->id,
            'numero' => 'C001',
            'nom' => 'Commercial Un',
        ]);
    }

    private function utilisateur(string $role, string $nom, string $email): User
    {
        $utilisateur = User::create([
            'name' => $nom,
            'email' => $email,
            'password' => 'mot-de-passe-de-test',
            'entreprise_id' => $this->entreprise->id,
            'est_actif' => true,
        ]);

        $utilisateur->assignRole($role);

        return $utilisateur;
    }

    private function brouillon(string $numero = 'P-001'): Prospection
    {
        return Prospection::create([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => $this->site->id,
            'commercial_id' => $this->commercial->id,
            'numero' => $numero,
            'date' => now()->toDateString(),
            'client' => 'Garage Test',
            'localisation' => 'Cocody',
            'moyen' => 'RDV',
            'activite' => 'Mécanique',
            'passage' => false,
            'devis_apres_passage' => false,
            'statut_validation' => 'Brouillon',
        ]);
    }

    public function test_la_transmission_previent_le_responsable_du_site(): void
    {
        $prospection = $this->brouillon();

        $this->actingAs($this->commercialUser);

        Volt::test('commercial.mes-prospections')
            ->set('selection', [$prospection->id => true])
            ->call('transmettreSelection');

        $this->assertSame('Transmise', $prospection->fresh()->statut_validation);

        $notification = NotificationApp::where('user_id', $this->responsable->id)->firstOrFail();

        $this->assertSame(NotificationApp::CANAL_GESTION, $notification->canal);
        $this->assertStringContainsString('à valider', $notification->titre);
        $this->assertSame(route('saisie-du-jour'), $notification->lien);

        // Le commercial ne se notifie pas lui-même.
        $this->assertSame(0, NotificationApp::where('user_id', $this->commercialUser->id)->count());
    }

    public function test_la_validation_previent_le_commercial(): void
    {
        $prospection = $this->brouillon();
        $prospection->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

        $this->actingAs($this->responsable);

        Volt::test('saisie.saisie-du-jour')->call('validerProspection', $prospection->id);

        $this->assertSame('Validée', $prospection->fresh()->statut_validation);

        $notification = NotificationApp::where('user_id', $this->commercialUser->id)->firstOrFail();

        $this->assertSame('Prospection validée', $notification->titre);
        $this->assertSame(NotificationApp::NIVEAU_SUCCES, $notification->niveau);
        $this->assertSame(route('mes-prospections'), $notification->lien);
    }

    public function test_le_refus_previent_le_commercial_avec_le_motif(): void
    {
        $prospection = $this->brouillon();
        $prospection->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

        $this->actingAs($this->responsable);

        Volt::test('saisie.saisie-du-jour')
            ->set('motifRefus', [$prospection->id => 'Client déjà démarché'])
            ->call('refuserProspection', $prospection->id);

        $this->assertSame('Refusée', $prospection->fresh()->statut_validation);

        $notification = NotificationApp::where('user_id', $this->commercialUser->id)->firstOrFail();

        $this->assertSame('Prospection refusée', $notification->titre);
        $this->assertStringContainsString('Client déjà démarché', $notification->corps);
        $this->assertSame(NotificationApp::NIVEAU_CRITIQUE, $notification->niveau);
    }

    public function test_la_validation_en_masse_previent_chaque_commercial_une_seule_fois(): void
    {
        $premier = $this->brouillon('P-001');
        $second = $this->brouillon('P-002');

        Prospection::whereIn('id', [$premier->id, $second->id])
            ->update(['statut_validation' => 'Transmise', 'transmise_le' => now()]);

        $this->actingAs($this->responsable);

        Volt::test('saisie.saisie-du-jour')->call('validerToutesProspections');

        $this->assertSame(0, Prospection::where('statut_validation', 'Transmise')->count());
        $this->assertSame(1, NotificationApp::where('user_id', $this->commercialUser->id)->count());
    }
}
