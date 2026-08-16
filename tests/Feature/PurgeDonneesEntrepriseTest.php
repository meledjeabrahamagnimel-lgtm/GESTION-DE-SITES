<?php

namespace Tests\Feature;

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Tenants\Actions\PurgerDonneesEntreprise;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Domain\Tenants\Models\Ville;
use App\Domain\Tenants\Services\ProvisionneurEntreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La purge sert à rendre une entreprise vierge après une période de test, sans avoir à
 * la recréer : elle efface les écritures et la numérotation, mais jamais l'organisation
 * — comptes, villes, lieux. Et lorsqu'elle emporte aussi les fiches commerciales, elle
 * les reconstitue derrière elle : une entreprise sans commercial ni « Client spontané »
 * ne peut plus rien saisir du tout.
 */
class PurgeDonneesEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $abidjan;

    private Ville $bouake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $this->abidjan = $this->ville('ABJ', 'Abidjan');
        $this->bouake = $this->ville('BOU', 'Bouaké');
    }

    public function test_la_purge_efface_les_ecritures_et_conserve_l_organisation(): void
    {
        $commercial = $this->ficheCommerciale($this->abidjan, 'C-0001', $this->compte('commercial', 'com@exemple.test', $this->abidjan));
        $this->ecritureComplete($commercial);

        $compte = (new PurgerDonneesEntreprise())->executer($this->entreprise);

        $this->assertSame(1, $compte['prospections']);
        $this->assertSame(0, Prospection::count());
        $this->assertSame(0, Devis::count());
        $this->assertSame(0, Facture::count());
        $this->assertSame(0, Encaissement::count());
        $this->assertSame(0, Charge::count());

        // L'organisation, elle, reste intacte : c'est tout l'intérêt d'une purge.
        $this->assertSame(1, Commercial::count());
        $this->assertSame(2, Ville::count());
        $this->assertSame(2, Site::count());
        $this->assertSame(1, User::count());
    }

    public function test_la_purge_des_commerciaux_les_reconstitue_dans_leur_propre_ville(): void
    {
        $superviseur = $this->compte('responsable_ville', 'sup@exemple.test', $this->abidjan);
        $this->abidjan->update(['responsable_id' => $superviseur->id]);
        $this->ficheCommerciale($this->abidjan, 'C-0001', $superviseur);

        $commercialBouake = $this->compte('commercial', 'com-bouake@exemple.test', $this->bouake);
        $this->ficheCommerciale($this->bouake, 'C-0002', $commercialBouake);

        $comptable = $this->compte('caissier', 'compta@exemple.test', $this->bouake);

        (new PurgerDonneesEntreprise())->executer($this->entreprise, purgerCommerciaux: true);

        // Un « Client spontané » par ville, sans quoi une vente sans commercial nommé
        // n'aurait plus où être rattachée.
        $spontanes = Commercial::where('est_spontane', true)->get();
        $this->assertCount(2, $spontanes);
        $this->assertEqualsCanonicalizing(['SP-ABJ', 'SP-BOU'], $spontanes->pluck('numero')->all());

        // Chacun retrouve SA ville : le rattachement est porté par le compte, pas par la
        // fiche qui vient d'être supprimée.
        $this->assertSame($this->abidjan->id, Commercial::where('user_id', $superviseur->id)->value('ville_id'));
        $this->assertSame($this->bouake->id, Commercial::where('user_id', $commercialBouake->id)->value('ville_id'));

        // La comptabilité ne prospecte pas : elle n'a aucune raison d'avoir une fiche.
        $this->assertSame(0, Commercial::where('user_id', $comptable->id)->count());
    }

    private function ville(string $code, string $nom): Ville
    {
        $ville = Ville::create(['entreprise_id' => $this->entreprise->id, 'code' => $code, 'nom' => $nom]);

        Site::create(['entreprise_id' => $this->entreprise->id, 'ville_id' => $ville->id, 'code' => $code, 'nom' => $nom]);

        return $ville;
    }

    private function compte(string $role, string $email, Ville $ville): User
    {
        $utilisateur = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'mot-de-passe-de-test',
            'entreprise_id' => $this->entreprise->id,
            'ville_id' => $ville->id,
            'est_actif' => true,
        ]);

        $utilisateur->assignRole($role);

        return $utilisateur;
    }

    private function ficheCommerciale(Ville $ville, string $numero, User $utilisateur): Commercial
    {
        return Commercial::create([
            'entreprise_id' => $this->entreprise->id,
            'ville_id' => $ville->id,
            'user_id' => $utilisateur->id,
            'numero' => $numero,
            'nom' => $utilisateur->name,
            'objectif_mecanique' => 1_000_000,
            'objectif_sinistre' => 500_000,
        ]);
    }

    /** Une chaîne complète prospection → devis → facture → encaissement, plus une charge. */
    private function ecritureComplete(Commercial $commercial): void
    {
        $siteId = Site::where('ville_id', $commercial->ville_id)->value('id');

        $prospection = Prospection::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $siteId, 'commercial_id' => $commercial->id,
            'numero' => 'P-0001', 'date' => now()->toDateString(), 'client' => 'Client Test', 'activite' => 'Mécanique',
        ]);

        $devis = Devis::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $siteId, 'commercial_id' => $commercial->id,
            'prospection_id' => $prospection->id, 'numero' => 'D-0001', 'date_emission' => now()->toDateString(),
            'client' => 'Client Test', 'activite' => 'Mécanique', 'statut' => 'Validé',
            'montant_devis' => 500_000, 'montant_valide' => 500_000,
        ]);

        $facture = Facture::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $siteId, 'devis_id' => $devis->id,
            'commercial_id' => $commercial->id, 'numero' => 'F-0001', 'n_facture' => 'FA-0001',
            'date' => now()->toDateString(), 'client' => 'Client Test', 'activite' => 'Mécanique', 'montant' => 500_000,
        ]);

        Encaissement::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $siteId, 'facture_id' => $facture->id,
            'date' => now()->toDateString(), 'type' => 'Client', 'moyen' => 'Espèces', 'montant' => 500_000,
        ]);

        Charge::create([
            'entreprise_id' => $this->entreprise->id, 'site_id' => $siteId, 'date' => now()->toDateString(),
            'type_operation' => 'Charges', 'libelle' => 'Achats pièces', 'moyen' => 'Espèces', 'montant' => 100_000,
        ]);
    }
}
