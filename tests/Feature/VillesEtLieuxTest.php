<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Une ville tient des lieux, pas des activités.
 *
 * L'écran créait encore deux sites « — Mécanique » et « — Sinistre » : l'ancien
 * modèle, abandonné quand le site est devenu un lieu physique. La colonne `activite`
 * n'existant plus sur les sites, elle était silencieusement écartée — il ne restait
 * que deux lieux fantômes, sans code, là où un seul avait lieu d'être.
 */
class VillesEtLieuxTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $gerant = User::create([
            'entreprise_id' => $this->entreprise->id, 'name' => 'Jean-Baptiste Kouassi',
            'email' => 'gerant@exemple.test', 'password' => Hash::make('password'),
        ]);
        $gerant->assignRole('gerant');

        $this->actingAs($gerant);
    }

    public function test_une_ville_cree_un_seul_lieu_du_meme_nom(): void
    {
        $this->ecran()->set('villeNom', 'Bouaké')->call('ajouterVille');

        $ville = Ville::where('nom', 'Bouaké')->firstOrFail();
        $lieux = Site::where('ville_id', $ville->id)->get();

        $this->assertCount(1, $lieux, "Un lieu accueille les deux activités : il n'en faut pas deux.");
        $this->assertSame('Bouaké', $lieux->first()->nom);
        $this->assertSame('BOU', $lieux->first()->code);
    }

    public function test_aucun_lieu_ne_porte_le_nom_d_une_activite(): void
    {
        $this->ecran()->set('villeNom', 'Bouaké')->call('ajouterVille');

        // « Bouaké — Mécanique » et « Bouaké — Sinistre » étaient deux lieux fantômes :
        // l'activité se saisit ligne par ligne, elle n'est pas un endroit.
        $this->assertSame(0, Site::where('nom', 'like', '%Mécanique%')->count());
        $this->assertSame(0, Site::where('nom', 'like', '%Sinistre%')->count());
    }

    public function test_le_code_de_ville_est_parlant_et_unique(): void
    {
        $ecran = $this->ecran();
        $ecran->set('villeNom', 'Bouaké')->call('ajouterVille');
        $ecran->set('villeNom', 'San Pédro')->call('ajouterVille');
        $ecran->set('villeNom', 'Bouna')->call('ajouterVille');

        // « V1 », « V2 » n'apprenaient rien à personne, et le code se lit ensuite dans
        // celui des lieux. Une collision se départage par un chiffre.
        $this->assertSame('BOU', Ville::where('nom', 'Bouaké')->value('code'));
        $this->assertSame('SAN', Ville::where('nom', 'San Pédro')->value('code'));
        $this->assertSame('BOU2', Ville::where('nom', 'Bouna')->value('code'));
    }

    public function test_une_ville_recoit_un_second_lieu(): void
    {
        $ecran = $this->ecran();
        $ecran->set('villeNom', 'Abidjan')->call('ajouterVille');

        $ville = Ville::where('nom', 'Abidjan')->firstOrFail();

        // Sans ce geste, l'écran promettait un second lieu qu'aucun bouton ne créait.
        $ecran->call('ouvrirAjoutLieu', $ville->id)
            ->set('lieuNom', 'Abidjan — Site 2')
            ->call('ajouterLieu', $ville->id);

        $lieux = Site::where('ville_id', $ville->id)->orderBy('id')->get();

        $this->assertCount(2, $lieux);
        $this->assertSame(['ABI', 'ABI-2'], $lieux->pluck('code')->all());
        $this->assertSame('Abidjan — Site 2', $lieux->last()->nom);
    }

    public function test_un_lieu_ne_s_ajoute_pas_a_la_ville_d_une_autre_entreprise(): void
    {
        $autre = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);
        $villeEtrangere = Ville::create([
            'entreprise_id' => $autre->id, 'code' => 'XXX', 'nom' => 'Ailleurs', 'est_actif' => true,
        ]);

        $this->ecran()->set('lieuNom', 'Intrus')->call('ajouterLieu', $villeEtrangere->id);

        $this->assertSame(0, Site::withoutGlobalScopes()->where('ville_id', $villeEtrangere->id)->count());
    }

    private function ecran()
    {
        return Volt::test('gerant.parametres')->set('onglet', 'villes');
    }
}
