<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Tests\TestCase;

/**
 * Le logo d'une entreprise, et la façon dont il parvient au navigateur.
 *
 * Les fichiers téléversés vivent hors du dossier servi par le serveur web ; seul le lien
 * symbolique `public/storage` les y expose. Ce lien n'est pas versionné et se perd sans
 * bruit sur un hébergement mutualisé. Le logo cessait alors simplement d'exister — dans
 * l'application comme dans les courriels — sans message ni trace.
 *
 * L'invariant tenu ici est le seul qui compte : un logo présent sur le disque a toujours
 * une adresse, quel que soit l'état du lien.
 */
class LogoEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    /** Le plus petit PNG valable, pour ne pas traîner un fichier binaire dans le dépôt. */
    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_un_logo_present_sur_le_disque_a_toujours_une_adresse(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/alpha.png', $this->png());

        $entreprise = Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => 'logos/alpha.png',
        ]);

        // Le lien symbolique peut être là ou non selon la machine : dans les deux cas,
        // l'adresse doit exister et être absolue.
        $this->assertNotNull($entreprise->logoUrl());
        $this->assertStringStartsWith('http', $entreprise->logoUrl());
    }

    public function test_un_logo_livre_avec_le_depot_est_servi_directement(): void
    {
        $entreprise = Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => 'public:logos/artisan-automobile.png',
        ]);

        // Pas d'aller-retour PHP quand le serveur web peut le faire lui-même : l'adresse
        // désigne le fichier, pas la route de repli.
        $this->assertSame(
            '/logos/artisan-automobile.png',
            parse_url($entreprise->logoUrl(), PHP_URL_PATH),
        );
    }

    public function test_sans_logo_il_n_y_a_pas_d_adresse(): void
    {
        $entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);

        $this->assertNull($entreprise->logoUrl());
    }

    public function test_la_route_sert_le_fichier_sans_authentification(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/alpha.png', $this->png());

        $entreprise = Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => 'logos/alpha.png',
        ]);

        // Un courriel est lu hors session : exiger une connexion reviendrait à ne rien
        // afficher chez le destinataire.
        $reponse = $this->get(route('entreprise.logo', $entreprise));

        $reponse->assertSuccessful();
        $this->assertSame($this->png(), $reponse->streamedContent());
    }

    public function test_la_route_refuse_une_entreprise_sans_logo(): void
    {
        $entreprise = Entreprise::create(['nom' => 'Alpha', 'slug' => 'alpha']);

        $this->get(route('entreprise.logo', $entreprise))->assertNotFound();
    }

    public function test_la_route_refuse_un_fichier_absent_du_disque(): void
    {
        Storage::fake('public');

        // La colonne pointe un fichier qui n'existe plus : effacé à la main, ou perdu
        // dans une restauration. On rend 404, jamais une erreur serveur.
        $entreprise = Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => 'logos/disparu.png',
        ]);

        $this->get(route('entreprise.logo', $entreprise))->assertNotFound();
    }

    public function test_la_route_ne_lit_que_le_chemin_inscrit_en_base(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/alpha.png', $this->png());

        $entreprise = Entreprise::create([
            'nom' => 'Alpha', 'slug' => 'alpha', 'logo_chemin' => 'logos/alpha.png',
        ]);

        // Aucun chemin ne vient de la requête : la seule chose qu'on puisse désigner est
        // une entreprise, et elle ne sert que son propre fichier. Un identifiant fantaisiste
        // ne mène nulle part.
        $this->get('/entreprises/'.$entreprise->id.'/logo?chemin=../../.env')->assertSuccessful();
        $this->get('/entreprises/999999/logo')->assertNotFound();
    }
}
