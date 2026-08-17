<?php

namespace Tests\Feature;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Modules\Noyau\Exploitation\Modeles\Commercial;
use Modules\Noyau\Exploitation\Modeles\Devis;
use Modules\Noyau\Exploitation\Modeles\Facture;
use Modules\Noyau\Exploitation\Modeles\Prospection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le trajet d'une affaire, du terrain à la caisse :
 *
 *   prospection transmise → corrigée → validée → devis → montant retenu → facture
 *
 * Chaque étape doit faire entrer la ligne dans le bon tableau de la saisie du jour et
 * la faire sortir du précédent. Un maillon qui casse ne se voit pas : la ligne reste
 * simplement dans un tableau où plus personne ne la regarde.
 */
class ChaineProspectionDevisFactureTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Site $site;

    private Commercial $commercial;

    private User $responsable;

    private string $jour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jour = now()->toDateString();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $ville = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);

        $this->site = Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $ville->id,
            'code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1', 'est_actif' => true,
        ]);

        $this->responsable = User::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Sylvain Kouassi',
            'email' => 'resp@exemple.test', 'password' => Hash::make('password'),
            'ville_id' => $ville->id, 'site_id' => $this->site->id,
        ]);
        $this->responsable->assignRole('responsable_site');
        $this->site->update(['responsable_id' => $this->responsable->id]);

        $this->commercial = Commercial::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $ville->id,
            'numero' => 'C-0001', 'nom' => 'Koffi Yao', 'statut' => 'Actif', 'est_spontane' => false,
        ]);

        $this->actingAs($this->responsable);
    }

    public function test_le_responsable_corrige_une_transmission_avant_de_la_valider(): void
    {
        $prospection = $this->prospectionTransmise(['passage' => false, 'devis_apres_passage' => false]);

        $ecran = $this->ecran();
        $this->assertTrue($ecran->instance()->prospectionsATraiter->contains('id', $prospection->id));

        // Cocher le devis coche aussi le passage : un devis ne naît pas d'une visite
        // qui n'a pas eu lieu.
        $ecran->call('basculerDevisApres', $prospection->id);

        $prospection->refresh();
        $this->assertTrue($prospection->devis_apres_passage);
        $this->assertTrue($prospection->passage);

        // Et la correction du client se fait sans avoir à refuser la prospection.
        $ecran->call('modifierProspection', $prospection->id)
            ->set('editionProsClient', 'Client corrigé')
            ->call('enregistrerEditionProspection');

        $this->assertSame('Client corrigé', $prospection->fresh()->client);
    }

    public function test_decocher_le_passage_retire_le_devis_qui_en_decoulait(): void
    {
        $prospection = $this->prospectionTransmise(['passage' => true, 'devis_apres_passage' => true]);

        $this->ecran()->call('basculerPassage', $prospection->id);

        $prospection->refresh();
        $this->assertFalse($prospection->passage);
        $this->assertFalse($prospection->devis_apres_passage, 'Un devis après passage sans passage serait incohérent.');
    }

    public function test_une_prospection_validee_change_de_tableau(): void
    {
        $prospection = $this->prospectionTransmise(['passage' => true, 'devis_apres_passage' => true]);

        $this->ecran()->call('validerProspection', $prospection->id);

        $this->assertSame('Validée', $prospection->fresh()->statut_validation);

        $ecran = $this->ecran();
        $this->assertFalse($ecran->instance()->prospectionsATraiter->contains('id', $prospection->id));
        $this->assertTrue($ecran->instance()->prospectionsDuJour->contains('id', $prospection->id));
        $this->assertTrue($ecran->instance()->prospectionsAttenteDevis->contains('id', $prospection->id));
    }

    public function test_un_devis_ne_se_valide_pas_sans_montant_retenu(): void
    {
        $devis = $this->devis(montant: 800_000);

        // Choisir « Validé » dans la liste ne fait qu'ouvrir la saisie du montant.
        $ecran = $this->ecran()->call('changerStatutDevis', $devis->id, 'Validé');

        $this->assertSame('En attente', $devis->fresh()->statut);
        $this->assertSame($devis->id, $ecran->instance()->devisAValiderId);

        // Un montant vide est refusé : un devis validé porte forcément un montant.
        $ecran->set('montantValidation', '')->call('confirmerValidationDevis')
            ->assertHasErrors('montantValidation');

        $this->assertSame('En attente', $devis->fresh()->statut);
    }

    public function test_le_montant_retenu_peut_differer_du_montant_propose(): void
    {
        $devis = $this->devis(montant: 800_000);

        $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Validé')
            ->set('montantValidation', '750000')
            ->call('confirmerValidationDevis');

        $devis->refresh();
        $this->assertSame('Validé', $devis->statut);
        $this->assertSame(750_000, (int) $devis->montant_valide);
        $this->assertSame(800_000, (int) $devis->montant_devis, 'Le montant proposé reste la trace de la négociation.');
    }

    public function test_un_devis_refuse_ne_garde_aucun_montant_retenu(): void
    {
        $devis = $this->devis(montant: 800_000);

        $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Validé')
            ->set('montantValidation', '750000')
            ->call('confirmerValidationDevis');

        $this->ecran()->call('changerStatutDevis', $devis->id, 'Refusé');

        $devis->refresh();
        $this->assertSame('Refusé', $devis->statut);
        $this->assertNull($devis->montant_valide);
    }

    public function test_un_devis_valide_devient_une_facture_au_montant_retenu(): void
    {
        $devis = $this->devis(montant: 800_000);

        $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Validé')
            ->set('montantValidation', '750000')
            ->call('confirmerValidationDevis');

        $ecran = $this->ecran();
        $this->assertFalse($ecran->instance()->devisEnAttente->contains('id', $devis->id));
        $this->assertTrue($ecran->instance()->devisValidesNonFactures->contains('id', $devis->id));

        // La facturation reste un geste volontaire : on choisit le devis, puis on émet.
        $ecran->set('factureSelection', [$devis->id => true])->call('genererBrouillonsFactures');

        $brouillon = $ecran->instance()->factureBrouillon;
        $this->assertCount(1, $brouillon);
        $this->assertSame(750_000, (int) $brouillon[0]['montant'], 'La facture part du montant retenu, pas du montant proposé.');

        $ecran->call('validerFactures');

        $facture = Facture::where('devis_id', $devis->id)->first();
        $this->assertNotNull($facture);
        $this->assertSame(750_000, (int) $facture->montant);
        $this->assertSame($devis->activite, $facture->activite);

        $ecran = $this->ecran();
        $this->assertTrue($ecran->instance()->facturesDuJour->contains('id', $facture->id));
        $this->assertFalse($ecran->instance()->devisValidesNonFactures->contains('id', $devis->id));
        $this->assertTrue($ecran->instance()->facturesAvecReste->contains('id', $facture->id), 'La facture doit rester encaissable par la comptabilité.');
    }

    // ------------------------------------------------------------------ Outils

    private function ecran()
    {
        return Volt::test('saisie.saisie-du-jour')->set('date', $this->jour);
    }

    private function prospectionTransmise(array $attributs = []): Prospection
    {
        return Prospection::create([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => $this->site->id,
            'commercial_id' => $this->commercial->id,
            'numero' => 'P-0001',
            'date' => $this->jour,
            'client' => 'Client Chaîne',
            'localisation' => 'Zone 4',
            'moyen' => 'RDV',
            'activite' => 'Mécanique',
            'statut_validation' => 'Transmise',
            'transmise_le' => now(),
            ...$attributs,
        ]);
    }

    private function devis(int $montant): Devis
    {
        return Devis::create([
            'entreprise_id' => $this->entreprise->id,
            'site_id' => $this->site->id,
            'commercial_id' => $this->commercial->id,
            'numero' => 'D-0001',
            'date_emission' => $this->jour,
            'client' => 'Client Chaîne',
            'activite' => 'Mécanique',
            'statut' => 'En attente',
            'montant_devis' => $montant,
        ]);
    }
}
