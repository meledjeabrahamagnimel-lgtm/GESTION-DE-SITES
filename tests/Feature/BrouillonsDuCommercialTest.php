<?php

namespace Tests\Feature;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\CompteurDocument;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ce que le commercial peut faire de ses propres lignes.
 *
 * La frontière est nette : tant qu'une prospection est un brouillon, elle lui appartient
 * — il la corrige, la coche, la supprime. Une fois transmise, elle ne lui appartient
 * plus : elle ne doit pas changer sous les yeux du responsable en train de l'arbitrer.
 */
class BrouillonsDuCommercialTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Commercial $commercial;

    private User $utilisateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $ville = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $ville->id,
            'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        $this->utilisateur = User::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Koffi Yao',
            'email' => 'koffi@exemple.test', 'password' => Hash::make('password'),
            'ville_id' => $ville->id,
        ]);
        $this->utilisateur->assignRole('commercial');

        $this->commercial = Commercial::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $ville->id,
            'user_id' => $this->utilisateur->id, 'numero' => 'C-0001',
            'nom' => 'Koffi Yao', 'statut' => 'Actif', 'est_spontane' => false,
        ]);

        CompteurDocument::create([
            'entreprise_id' => $this->entreprise->id, 'type' => 'pro', 'dernier_numero' => 0,
        ]);

        $this->actingAs($this->utilisateur);
    }

    public function test_le_bouton_brouillon_met_de_cote_sans_transmettre(): void
    {
        $this->ecran()->set('client', 'SIFCA')->call('ajouterEnBrouillon');

        $prospection = Prospection::firstOrFail();
        $this->assertSame('Brouillon', $prospection->statut_validation);
        $this->assertNull($prospection->transmise_le);
    }

    public function test_le_bouton_ajouter_transmet_directement(): void
    {
        $this->ecran()->set('client', 'SIFCA')->call('ajouterEtTransmettre');

        $prospection = Prospection::firstOrFail();
        $this->assertSame('Transmise', $prospection->statut_validation);
        $this->assertNotNull($prospection->transmise_le, 'Une transmission est datée : le responsable doit voir depuis quand elle attend.');
    }

    public function test_un_brouillon_se_modifie(): void
    {
        $prospection = $this->brouillon(['client' => 'Faute de frappe']);

        $this->ecran()
            ->call('modifier', $prospection->id)
            ->set('eClient', 'SIFCA')
            ->set('eLocalisation', 'Zone 4')
            ->call('enregistrerEdition');

        $prospection->refresh();
        $this->assertSame('SIFCA', $prospection->client);
        $this->assertSame('Zone 4', $prospection->localisation);
    }

    public function test_une_prospection_transmise_ne_se_modifie_plus(): void
    {
        $prospection = $this->brouillon(['statut_validation' => 'Transmise', 'client' => 'SIFCA']);

        // Elle appartient désormais au responsable : la modifier reviendrait à la
        // changer sous ses yeux pendant qu'il l'arbitre.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->ecran()->call('modifier', $prospection->id);
    }

    public function test_les_cases_se_cochent_directement_sur_un_brouillon(): void
    {
        $prospection = $this->brouillon(['passage' => false, 'devis_apres_passage' => false]);

        $this->ecran()->call('basculerDevisApres', $prospection->id);

        $prospection->refresh();
        $this->assertTrue($prospection->devis_apres_passage);
        $this->assertTrue($prospection->passage, 'Un devis suppose un passage.');

        $this->ecran()->call('basculerPassage', $prospection->id);

        $prospection->refresh();
        $this->assertFalse($prospection->passage);
        $this->assertFalse($prospection->devis_apres_passage, 'Sans passage, plus de devis annoncé.');
    }

    private function ecran()
    {
        return Volt::test('commercial.mes-prospections');
    }

    private function brouillon(array $attributs = []): Prospection
    {
        return Prospection::create([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => Site::firstOrFail()->id,
            'commercial_id' => $this->commercial->id,
            'numero' => 'P-0001',
            'date' => now()->toDateString(),
            'client' => 'Client Test',
            'moyen' => 'RDV',
            'activite' => 'Mécanique',
            'statut_validation' => 'Brouillon',
            ...$attributs,
        ]);
    }
}
