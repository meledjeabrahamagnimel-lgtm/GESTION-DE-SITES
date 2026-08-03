<?php

namespace Tests\Feature;

use App\Domain\Shared\Models\DossierNote;
use App\Domain\Shared\Models\Note;
use App\Domain\Tenants\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    private User $commercial;

    private User $collegue;

    protected function setUp(): void
    {
        parent::setUp();

        $entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        Role::findOrCreate('commercial', 'web');
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        foreach (['commercial', 'collegue'] as $cle) {
            $utilisateur = User::create([
                'name' => ucfirst($cle),
                'email' => $cle.'@exemple.test',
                'password' => 'mot-de-passe-de-test',
                'entreprise_id' => $entreprise->id,
                'est_actif' => true,
            ]);
            $utilisateur->assignRole('commercial');
            $this->{$cle} = $utilisateur;
        }
    }

    public function test_creation_d_un_dossier_et_d_une_note(): void
    {
        $this->actingAs($this->commercial);

        $composant = Volt::test('mes-notes')
            ->set('nouveauDossier', 'Relances')
            ->call('creerDossier')
            ->assertHasNoErrors();

        $dossier = DossierNote::where('user_id', $this->commercial->id)->firstOrFail();
        $this->assertSame('Relances', $dossier->nom);

        $composant
            ->set('titre', 'Rappeler M. Koffi')
            ->set('corps', 'Devis en attente depuis 3 jours')
            ->set('dossierNote', (string) $dossier->id)
            ->call('enregistrerNote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notes', [
            'user_id' => $this->commercial->id,
            'dossier_note_id' => $dossier->id,
            'titre' => 'Rappeler M. Koffi',
        ]);
    }

    /** Les notes sont personnelles : le dossier d'un collègue ne doit jamais être atteignable. */
    public function test_le_dossier_d_un_collegue_est_ignore(): void
    {
        $dossierDuCollegue = DossierNote::create([
            'user_id' => $this->collegue->id,
            'nom' => 'Privé',
        ]);

        $this->actingAs($this->commercial);

        Volt::test('mes-notes')
            ->set('titre', 'Ma note')
            ->set('dossierNote', (string) $dossierDuCollegue->id)
            ->call('enregistrerNote')
            ->assertHasNoErrors();

        $note = Note::where('user_id', $this->commercial->id)->firstOrFail();

        $this->assertNull($note->dossier_note_id, "La note ne doit pas rejoindre le dossier d'un collègue.");
    }

    public function test_impossible_de_supprimer_la_note_d_un_collegue(): void
    {
        $note = Note::create([
            'user_id' => $this->collegue->id,
            'titre' => 'Note privée',
        ]);

        $this->actingAs($this->commercial);

        Volt::test('mes-notes')->call('supprimerNote', $note->id);

        $this->assertDatabaseHas('notes', ['id' => $note->id]);
    }

    public function test_la_suppression_d_un_dossier_conserve_ses_notes(): void
    {
        $this->actingAs($this->commercial);

        $dossier = DossierNote::create(['user_id' => $this->commercial->id, 'nom' => 'Prospection']);
        $note = Note::create([
            'user_id' => $this->commercial->id,
            'dossier_note_id' => $dossier->id,
            'titre' => 'À conserver',
        ]);

        Volt::test('mes-notes')->call('supprimerDossier', $dossier->id);

        $this->assertDatabaseMissing('dossiers_notes', ['id' => $dossier->id]);
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'dossier_note_id' => null]);
    }

    public function test_la_recherche_filtre_les_notes(): void
    {
        $this->actingAs($this->commercial);

        Note::create(['user_id' => $this->commercial->id, 'titre' => 'Garage Koffi']);
        Note::create(['user_id' => $this->commercial->id, 'titre' => 'Transport Diarra']);

        Volt::test('mes-notes')
            ->set('recherche', 'Koffi')
            ->assertSee('Garage Koffi')
            ->assertDontSee('Transport Diarra');
    }
}
