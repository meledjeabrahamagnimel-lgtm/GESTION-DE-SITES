<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Noyau\Commun\Services\DocumentPdf;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\Annuaire;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * L'annuaire dit qui travaille où, et à quel titre.
 *
 * Ce qu'il faut vérifier n'est pas qu'il s'affiche, mais qu'il s'arrête : un document
 * nominatif qui déborde du périmètre de son lecteur est une fuite, et elle ne se voit
 * pas — le fichier s'ouvre normalement, avec des noms en trop.
 */
class AnnuaireAccesTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    private Ville $abidjan;

    private Ville $bouake;

    private Site $siteAbidjan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);
        ProvisionneurEntreprise::creerRoles($this->entreprise);

        $this->abidjan = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'ABJ', 'nom' => 'Abidjan', 'est_actif' => true,
        ]);
        $this->bouake = Ville::create([
            'entreprise_id' => $this->entreprise->id, 'code' => 'BOU', 'nom' => 'Bouaké', 'est_actif' => true,
        ]);
        $this->siteAbidjan = Site::create([
            'entreprise_id' => $this->entreprise->id, 'ville_id' => $this->abidjan->id,
            'code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1', 'est_actif' => true,
        ]);
    }

    public function test_le_gerant_voit_toute_son_entreprise(): void
    {
        $gerant = $this->compte('gerant@alpha.test', 'gerant', null);
        $this->compte('abj@alpha.test', 'commercial', $this->abidjan->id);
        $this->compte('bou@alpha.test', 'commercial', $this->bouake->id);

        $blocs = Annuaire::pour($gerant);

        $this->assertCount(1, $blocs);
        $this->assertSame(3, $blocs[0]['total']);
        $this->assertSame(
            [Annuaire::HORS_VILLE, 'Abidjan', 'Bouaké'],
            array_keys($blocs[0]['villes']),
            'La direction vient en tête, puis les villes par ordre alphabétique.',
        );
    }

    public function test_le_superviseur_ne_voit_que_sa_ville(): void
    {
        $superviseur = $this->compte('sup@alpha.test', 'responsable_ville', $this->abidjan->id);
        $this->abidjan->forceFill(['responsable_id' => $superviseur->id])->save();

        $this->compte('gerant@alpha.test', 'gerant', null);
        $this->compte('abj@alpha.test', 'commercial', $this->abidjan->id);
        $this->compte('bou@alpha.test', 'commercial', $this->bouake->id);

        $blocs = Annuaire::pour($superviseur);

        $this->assertSame(['Abidjan'], array_keys($blocs[0]['villes']));

        $adresses = collect($blocs[0]['villes']['Abidjan'])->pluck('email');
        $this->assertContains('abj@alpha.test', $adresses);

        // Le gérant n'est rattaché à aucune ville : il ne doit pas apparaître, pas plus
        // que le commercial de Bouaké.
        $this->assertNotContains('gerant@alpha.test', $adresses);
        $this->assertNotContains('bou@alpha.test', $adresses);
    }

    public function test_un_gerant_ne_lit_pas_l_annuaire_d_une_autre_entreprise(): void
    {
        $voisine = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);
        ProvisionneurEntreprise::creerRoles($voisine);
        User::create([
            'entreprise_id' => $voisine->id, 'name' => 'Voisin',
            'email' => 'voisin@beta.test', 'password' => Hash::make('password'),
        ]);

        $gerant = $this->compte('gerant@alpha.test', 'gerant', null);

        // L'identifiant est glissé dans l'URL : il doit rester sans effet.
        $blocs = Annuaire::pour($gerant, $voisine->id);

        $this->assertCount(1, $blocs);
        $this->assertSame('Alpha', $blocs[0]['entreprise']->nom);
    }

    public function test_le_super_admin_voit_toutes_les_entreprises_ou_une_seule(): void
    {
        $voisine = Entreprise::create(['nom' => 'Beta', 'slug' => 'beta']);
        ProvisionneurEntreprise::creerRoles($voisine);
        User::create([
            'entreprise_id' => $voisine->id, 'name' => 'Voisin',
            'email' => 'voisin@beta.test', 'password' => Hash::make('password'),
        ]);
        $this->compte('gerant@alpha.test', 'gerant', null);

        $admin = $this->superAdmin();

        $this->assertCount(2, Annuaire::pour($admin));
        $this->assertCount(1, Annuaire::pour($admin, $voisine->id));
    }

    public function test_l_annuaire_s_arrete_au_superviseur(): void
    {
        $responsable = $this->compte('site@alpha.test', 'responsable_site', $this->abidjan->id);
        $responsable->forceFill(['site_id' => $this->siteAbidjan->id])->save();

        // Un responsable de site n'encadre qu'un lieu, dont il connaît déjà les noms.
        $this->assertFalse(Annuaire::ouvertA($responsable->fresh()));

        // Le middleware de rôle renvoie vers l'accueil plutôt que d'afficher un refus :
        // ce qui compte est que le document ne parte pas.
        $this->actingAs($responsable->fresh())
            ->get(route('annuaire'))
            ->assertRedirect();
    }

    public function test_le_telechargement_renvoie_un_pdf(): void
    {
        $gerant = $this->compte('gerant@alpha.test', 'gerant', null);
        $this->compte('abj@alpha.test', 'commercial', $this->abidjan->id);

        $reponse = $this->actingAs($gerant)->get(route('annuaire'));

        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'application/pdf');

        $contenu = $reponse->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $contenu);
        $this->assertStringEndsWith('%%EOF', $contenu);
        // Le nom doit être dans le fichier, sinon le document est vide de sens.
        $this->assertStringContainsString('abj@alpha.test', $contenu);
    }

    public function test_le_telechargement_laisse_une_trace(): void
    {
        $gerant = $this->compte('gerant@alpha.test', 'gerant', null);

        $this->actingAs($gerant)->get(route('annuaire'))->assertOk();

        // Un document nominatif qui sort de l'application se sait.
        $this->assertDatabaseHas('activity_log', [
            'description' => "Téléchargement de l'annuaire des accès",
            'causer_id' => $gerant->id,
        ]);
    }

    public function test_le_pdf_tient_sur_plusieurs_pages_sans_perdre_ses_colonnes(): void
    {
        $pdf = new DocumentPdf('Essai');
        $pdf->titre('Essai');
        $pdf->tableau(
            ['Rôle', 'Nom'],
            [120.0, 200.0],
            array_map(fn (int $i) => ['Commercial', "Personne $i"], range(1, 120)),
        );

        $rendu = $pdf->rendu();

        $this->assertStringStartsWith('%PDF-1.4', $rendu);
        $this->assertGreaterThan(1, substr_count($rendu, '/Type /Page '), 'Cent vingt lignes tiennent sur plusieurs pages.');
        // L'en-tête est redessiné en tête de chaque page : sans lui, une page de noms
        // ne se lit plus.
        $this->assertGreaterThan(1, substr_count($rendu, '(Nom) Tj'));
        $this->assertStringContainsString('(Page 1 sur ', $rendu);
    }

    public function test_la_table_de_reference_pointe_au_bon_octet(): void
    {
        $pdf = new DocumentPdf('Essai');
        $pdf->titre('Essai');
        $pdf->tableau(['Rôle', 'Nom'], [120.0, 200.0], array_map(
            fn (int $i) => ['Commercial', "Personne $i"], range(1, 90),
        ));

        $rendu = $pdf->rendu();

        // Un lecteur PDF ne lit pas le fichier de bout en bout : il saute directement
        // aux décalages donnés par la table xref. Un seul octet d'écart, et le document
        // s'ouvre vide ou refuse de s'ouvrir — sans que rien d'autre ne le signale.
        preg_match('/startxref\s+(\d+)/', $rendu, $m);
        $table = substr($rendu, (int) $m[1]);

        $this->assertStringStartsWith('xref', $table, 'startxref doit pointer sur la table.');

        preg_match_all('/(\d{10}) (\d{5}) ([nf])/', $table, $entrees, PREG_SET_ORDER);

        foreach ($entrees as $numero => $entree) {
            if ($entree[3] !== 'n') {
                continue;
            }

            $this->assertStringStartsWith(
                "$numero 0 obj",
                substr($rendu, (int) $entree[1], 20),
                "L'entrée $numero de la table ne pointe pas sur son objet.",
            );
        }

        // /Length annonce la taille du flux : trop court, la page est tronquée ;
        // trop long, le lecteur lit au-delà et rejette le fichier.
        preg_match_all('/<< \/Length (\d+) >>\s*stream\s(.*?)\sendstream/s', $rendu, $flux, PREG_SET_ORDER);

        $this->assertNotEmpty($flux);

        foreach ($flux as $f) {
            $this->assertSame((int) $f[1], strlen($f[2]), 'Longueur de flux déclarée à tort.');
        }
    }

    public function test_les_accents_survivent_a_l_encodage(): void
    {
        $pdf = new DocumentPdf('Essai');
        $pdf->titre('Bouaké — périmètre');

        // Windows-1252 code « é » sur un seul octet, 0xE9 : c'est ce que déclare
        // /WinAnsiEncoding, et c'est ce qui doit se trouver dans le flux.
        $this->assertStringContainsString("Bouak\xE9", $pdf->rendu());
    }

    private function compte(string $email, string $role, ?int $villeId): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->entreprise->id);

        $compte = User::create([
            'entreprise_id' => $this->entreprise->id,
            'name' => ucfirst(strstr($email, '@', true)),
            'email' => $email,
            'password' => Hash::make('password'),
            'ville_id' => $villeId,
            'est_actif' => true,
        ]);
        $compte->assignRole($role);

        return $compte->fresh();
    }

    private function superAdmin(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        Role::firstOrCreate([
            'name' => 'super_admin', 'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);

        $compte = User::create([
            'entreprise_id' => null, 'name' => 'Super Admin',
            'email' => 'sa@exemple.test', 'password' => Hash::make('password'),
            'est_actif' => true, 'est_fondateur' => true,
        ]);
        $compte->assignRole('super_admin');

        return $compte->fresh();
    }
}
