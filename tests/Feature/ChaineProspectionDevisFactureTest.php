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

    public function test_la_saisie_ne_garde_que_les_prospections_en_cours(): void
    {
        $transmise = $this->prospectionTransmise();
        $attendUnDevis = $this->prospectionTransmise([
            'numero' => 'P-0002', 'statut_validation' => 'Validée',
            'passage' => true, 'devis_apres_passage' => true,
        ]);
        $sansSuite = $this->prospectionTransmise(['numero' => 'P-0003', 'statut_validation' => 'Validée']);
        $refusee = $this->prospectionTransmise(['numero' => 'P-0004', 'statut_validation' => 'Refusée', 'motif_refus' => 'Doublon']);

        $lignes = $this->ecran()->instance()->prospectionsDuJour;

        // Reste ce sur quoi il y a encore quelque chose à faire.
        $this->assertTrue($lignes->contains('id', $transmise->id), 'Une transmission attend une décision.');
        $this->assertTrue($lignes->contains('id', $attendUnDevis->id), 'Un devis annoncé reste à établir.');

        // Sort ce dont l'histoire est finie : cela se consulte dans la page Prospects.
        $this->assertFalse($lignes->contains('id', $sansSuite->id), 'Validée sans suite attendue : rien à faire.');
        $this->assertFalse($lignes->contains('id', $refusee->id), 'Une prospection refusée est close.');

        // Les transmissions en tête : c'est là qu'une décision est attendue.
        $this->assertSame($transmise->id, $lignes->first()->id);
    }

    public function test_la_saisie_ne_garde_que_les_devis_en_cours(): void
    {
        $enAttente = $this->devis(montant: 800_000);
        $valideNonFacture = $this->devis(montant: 500_000, attributs: ['numero' => 'D-0002', 'statut' => 'Validé', 'montant_valide' => 500_000]);
        $refuse = $this->devis(montant: 300_000, attributs: ['numero' => 'D-0003', 'statut' => 'Refusé', 'motif_refus' => 'Prix']);

        $lignes = $this->ecran()->instance()->devisDuJour;

        $this->assertTrue($lignes->contains('id', $enAttente->id), 'Un devis sans statut attend une décision.');
        $this->assertTrue($lignes->contains('id', $valideNonFacture->id), 'Un devis validé attend sa facture.');
        $this->assertFalse($lignes->contains('id', $refuse->id), 'Un devis refusé est clos.');
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

    public function test_valider_une_prospection_avec_devis_ouvre_aussitot_le_devis(): void
    {
        $prospection = $this->prospectionTransmise(['passage' => true, 'devis_apres_passage' => true]);

        $ecran = $this->ecran()->call('validerProspection', $prospection->id);

        $brouillon = $ecran->instance()->devisBrouillon;
        $this->assertCount(1, $brouillon, 'Le devis annonce doit s ouvrir sans avoir a le rechercher.');
        $this->assertSame($prospection->id, $brouillon[0]['prospection_id']);
        $this->assertSame($prospection->client, $brouillon[0]['client']);
    }

    public function test_valider_une_prospection_sans_devis_n_ouvre_rien(): void
    {
        // Toutes les visites ne débouchent pas sur un devis : sans la case cochée,
        // la validation s'arrête là.
        $prospection = $this->prospectionTransmise(['passage' => true, 'devis_apres_passage' => false]);

        $ecran = $this->ecran()->call('validerProspection', $prospection->id);

        $this->assertSame('Validée', $prospection->fresh()->statut_validation);
        $this->assertEmpty($ecran->instance()->devisBrouillon);
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

    public function test_un_devis_ne_se_refuse_pas_sans_motif(): void
    {
        $devis = $this->devis(montant: 800_000);

        // Choisir « Refusé » ouvre la saisie du motif, sans rien enregistrer encore.
        $ecran = $this->ecran()->call('changerStatutDevis', $devis->id, 'Refusé');

        $this->assertSame('En attente', $devis->fresh()->statut);
        $this->assertSame($devis->id, $ecran->instance()->devisARefuserId);

        $ecran->set('motifRefusDevis', '')->call('confirmerRefusDevis')
            ->assertHasErrors('motifRefusDevis');

        $this->assertSame('En attente', $devis->fresh()->statut);
    }

    public function test_un_devis_refuse_garde_son_motif_et_perd_son_montant(): void
    {
        $devis = $this->devis(montant: 800_000);

        $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Validé')
            ->set('montantValidation', '750000')
            ->call('confirmerValidationDevis');

        $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Refusé')
            ->set('motifRefusDevis', 'Prix jugé trop élevé')
            ->call('confirmerRefusDevis');

        $devis->refresh();
        $this->assertSame('Refusé', $devis->statut);
        $this->assertSame('Prix jugé trop élevé', $devis->motif_refus);
        $this->assertNull($devis->montant_valide, 'Un devis refusé ne retient aucun montant.');
    }

    public function test_un_devis_valide_devient_une_facture_au_montant_retenu(): void
    {
        $devis = $this->devis(montant: 800_000);

        $ecran = $this->ecran()
            ->call('changerStatutDevis', $devis->id, 'Validé')
            ->set('montantValidation', '750000')
            ->call('confirmerValidationDevis');

        // Le devis validé ouvre aussitôt sa facture, au montant retenu : plus besoin
        // d'aller le rechercher dans la liste des devis à facturer.
        $brouillon = $ecran->instance()->factureBrouillon;
        $this->assertCount(1, $brouillon);
        $this->assertSame($devis->id, $brouillon[0]['devis_id']);
        $this->assertSame(750_000, (int) $brouillon[0]['montant'], 'La facture part du montant retenu, pas du montant proposé.');

        $controle = $this->ecran();
        $this->assertFalse($controle->instance()->devisEnAttente->contains('id', $devis->id));
        $this->assertTrue($controle->instance()->devisValidesNonFactures->contains('id', $devis->id));

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
            'motif_refus' => null,
            'client' => 'Client Chaîne',
            'localisation' => 'Zone 4',
            'moyen' => 'RDV',
            'activite' => 'Mécanique',
            'statut_validation' => 'Transmise',
            'transmise_le' => now(),
            ...$attributs,
        ]);
    }

    private function devis(int $montant, array $attributs = []): Devis
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
            ...$attributs,
        ]);
    }
}
