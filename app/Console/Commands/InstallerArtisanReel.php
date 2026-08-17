<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Noyau\Entreprises\Actions\CreerAcces;
use Modules\Noyau\Entreprises\Modeles\Entreprise;
use Modules\Noyau\Entreprises\Modeles\Site;
use Modules\Noyau\Entreprises\Modeles\Ville;
use Modules\Noyau\Entreprises\Services\ProvisionneurEntreprise;
use Spatie\Permission\PermissionRegistrar;

/**
 * Installe l'entreprise réelle de L'Artisan, à côté de celle de démonstration.
 *
 * Les deux cohabitent sans se voir : chaque écriture porte son entreprise_id et le
 * périmètre est posé par un scope global. La démonstration garde donc ses 568
 * prospections pour les présentations, pendant que la vraie exploitation démarre
 * d'une base vierge. Seule leur dénomination pouvait prêter à confusion — d'où le
 * renommage de la première en « Test ».
 *
 * Tous les accès sont créés INACTIFS. Aucun courriel ne part, personne ne peut se
 * connecter : ce sont des accès préparés. On les active un par un, depuis l'écran
 * des accès, le jour où la personne doit commencer — et c'est ce jour-là que part
 * son courriel de bienvenue.
 *
 * La commande est rejouable : elle remet d'aplomb ce qui existe plutôt que de
 * refuser, et ne touche jamais aux écritures.
 */
class InstallerArtisanReel extends Command
{
    protected $signature = 'artisan-reel:installer
                            {--activer : ouvrir les accès aussitôt, avec envoi des courriels}';

    protected $description = "Installe l'entreprise réelle de L'Artisan et ses accès, inactifs par défaut";

    private const NOM_REEL = "L'Artisan Automobile";

    private const NOM_DEMO = "L'Artisan Automobile — Test";

    private const SLUG_DEMO = 'artisan-automobile';

    private const SLUG_REEL = 'artisan-automobile-reel';

    /**
     * Villes et lieux, calqués sur la structure existante.
     *
     * Abidjan tient deux lieux distincts ; Bouaké et San Pédro n'en ont qu'un, qui se
     * confond avec la ville. Les deux activités — Mécanique et Sinistre — se saisissent
     * sur le même lieu, ligne par ligne : ce n'est pas le lieu qui porte l'activité.
     */
    private const STRUCTURE = [
        ['code' => 'ABJ', 'nom' => 'Abidjan', 'couleur' => '#2563EB', 'lieux' => [
            ['code' => 'ABJ-1', 'nom' => 'Abidjan — Site 1'],
            ['code' => 'ABJ-2', 'nom' => 'Abidjan — Site 2'],
        ]],
        ['code' => 'BOU', 'nom' => 'Bouaké', 'couleur' => '#0E9F6E', 'lieux' => [
            ['code' => 'BOU', 'nom' => 'Bouaké'],
        ]],
        ['code' => 'SPD', 'nom' => 'San Pédro', 'couleur' => '#D97706', 'lieux' => [
            ['code' => 'SPD', 'nom' => 'San Pédro'],
        ]],
    ];

    /**
     * Les accès, tels que communiqués.
     *
     * « ville » désigne le rattachement d'un superviseur, d'un commercial ou de la
     * comptabilité ; « lieu » celui d'un responsable de site. Les noms marqués
     * « à confirmer » n'ont pas pu être rapprochés de la liste nominative fournie :
     * ils se corrigent depuis l'écran des accès, l'accès étant inactif.
     */
    private const ACCES = [
        ['email' => 'm.yannick@lartisanauto.com', 'nom' => 'MINLIN Yannick', 'role' => 'gerant'],

        ['email' => 'k.desiree@lartisanauto.com', 'nom' => 'Désirée K.', 'role' => 'responsable_ville', 'ville' => 'ABJ', 'confirmer' => true],

        ['email' => 'g.ange@lartisanauto.com', 'nom' => 'GNANGBY Ange', 'role' => 'responsable_site', 'lieu' => 'ABJ-1'],
        ['email' => 'k.djeneba@lartisanauto.com', 'nom' => 'KONE Djeneba', 'role' => 'responsable_site', 'lieu' => 'ABJ-2'],
        ['email' => 'k.david@lartisanauto.com', 'nom' => 'KRAGBA David', 'role' => 'responsable_site', 'lieu' => 'BOU'],
        ['email' => 'admin-sanpedro@lartisanauto.com', 'nom' => 'GAIHO Rama', 'role' => 'responsable_site', 'lieu' => 'SPD', 'confirmer' => true],

        ['email' => 'comptabilite@lartisanauto.com', 'nom' => 'Comptabilité Abidjan', 'role' => 'caissier', 'ville' => 'ABJ', 'confirmer' => true],

        ['email' => 'y.ella@lartisanauto.com', 'nom' => 'YAO Ella', 'role' => 'commercial', 'ville' => 'ABJ'],
        ['email' => 'k.karel@lartisanauto.com', 'nom' => 'KRAGBE Karel', 'role' => 'commercial', 'ville' => 'ABJ'],
        ['email' => 'o.mariam@lartisanauto.com', 'nom' => 'OUATTARA Taki Mariam', 'role' => 'commercial', 'ville' => 'ABJ'],
        ['email' => 'a.leaticia@lartisanauto.com', 'nom' => 'AMAN Leaticia', 'role' => 'commercial', 'ville' => 'ABJ'],
        // Le tableau des rôles écrit « ADOU Vanessa », la liste nominative
        // « Vanessa FINI-ADOU » : on retient le nom complet.
        ['email' => 'a.vanessa@lartisanauto.com', 'nom' => 'FINI-ADOU Vanessa', 'role' => 'commercial', 'ville' => 'ABJ'],
        ['email' => 'a.melina@lartisanauto.com', 'nom' => 'AKOUE Mélina', 'role' => 'commercial', 'ville' => 'ABJ'],
        ['email' => 't.tou@lartisanauto.com', 'nom' => 'TOU Tahirou', 'role' => 'commercial', 'ville' => 'ABJ'],
    ];

    public function handle(CreerAcces $creerAcces): int
    {
        $demo = $this->renommerLaDemonstration();
        $entreprise = $this->entrepriseReelle($demo);

        $this->structurer($entreprise);
        $this->installerLesAcces($entreprise, $creerAcces);

        $this->newLine();
        $this->info('Entreprise réelle installée : '.$entreprise->nom.' (id '.$entreprise->id.')');

        if (! $this->option('activer')) {
            $this->line("  Tous les accès sont INACTIFS : aucun courriel n'est parti, aucune connexion n'est possible.");
            $this->line('  Ouvrez-les un par un dans Super Admin → Accès, bouton « Activer ».');
        }

        $aConfirmer = collect(self::ACCES)->where('confirmer', true);

        if ($aConfirmer->isNotEmpty()) {
            $this->newLine();
            $this->warn('Noms à confirmer avant activation (corrigibles depuis l\'écran des accès) :');
            $aConfirmer->each(fn ($a) => $this->line('  '.str_pad($a['email'], 38).$a['nom']));
        }

        return self::SUCCESS;
    }

    /**
     * Distingue la démonstration de la vraie exploitation.
     *
     * Deux entreprises portant le même nom, c'est une facture présentée au mauvais
     * client tôt ou tard. Le renommage ne touche à aucune donnée : seul l'intitulé
     * change, les 568 prospections de démonstration restent intactes.
     */
    private function renommerLaDemonstration(): ?Entreprise
    {
        $demo = Entreprise::withoutGlobalScopes()->where('slug', self::SLUG_DEMO)->first();

        if (! $demo) {
            $this->warn('Aucune entreprise de démonstration trouvée : rien à renommer.');

            return null;
        }

        if (! Str::contains($demo->nom, 'Test')) {
            $demo->forceFill(['nom' => self::NOM_DEMO])->save();
            $this->info('Démonstration renommée : '.self::NOM_DEMO);
        } else {
            $this->line('  Démonstration déjà distinguée : '.$demo->nom);
        }

        return $demo;
    }

    /** Crée l'entreprise réelle, ou la retrouve, et lui donne le logo de la maison. */
    private function entrepriseReelle(?Entreprise $demo): Entreprise
    {
        $entreprise = Entreprise::withoutGlobalScopes()->where('slug', self::SLUG_REEL)->first();

        if (! $entreprise) {
            $entreprise = Entreprise::create([
                'nom' => self::NOM_REEL,
                'slug' => self::SLUG_REEL,
                'code_entreprise' => Entreprise::genererCode(self::NOM_REEL),
                'gerant_fonction' => 'Gérant',
                'est_active' => true,
            ]);
            $this->info('Entreprise réelle créée.');
        } else {
            $this->line('  Entreprise réelle déjà présente.');
        }

        // Le logo est le même de part et d'autre : c'est la même maison. On le reprend
        // de la démonstration plutôt que d'attendre un téléversement, sinon les
        // courriels de bienvenue partiraient avec un en-tête vide.
        if (! $entreprise->logo_chemin && $demo?->logo_chemin) {
            $entreprise->forceFill([
                'logo_chemin' => $demo->logo_chemin,
                'couleur_ink' => $demo->couleur_ink,
                'couleur_paper' => $demo->couleur_paper,
                'couleur_ligne' => $demo->couleur_ligne,
                'couleur_accent' => $demo->couleur_accent,
            ])->save();
            $this->line('  Logo et couleurs repris de la démonstration.');
        }

        ProvisionneurEntreprise::creerRoles($entreprise);

        return $entreprise;
    }

    /** Villes et lieux, sans jamais écraser ce qui existe. */
    private function structurer(Entreprise $entreprise): void
    {
        foreach (self::STRUCTURE as $rang => $definition) {
            $ville = Ville::withoutGlobalScopes()->firstOrCreate(
                ['entreprise_id' => $entreprise->id, 'code' => $definition['code']],
                ['nom' => $definition['nom'], 'couleur' => $definition['couleur'], 'est_actif' => true],
            );

            foreach ($definition['lieux'] as $lieu) {
                Site::withoutGlobalScopes()->firstOrCreate(
                    ['entreprise_id' => $entreprise->id, 'code' => $lieu['code']],
                    ['ville_id' => $ville->id, 'nom' => $lieu['nom'], 'est_actif' => true],
                );
            }
        }

        $this->line('  '.count(self::STRUCTURE).' villes, '
            .collect(self::STRUCTURE)->sum(fn ($v) => count($v['lieux'])).' lieux.');
    }

    private function installerLesAcces(Entreprise $entreprise, CreerAcces $creerAcces): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($entreprise->id);

        $villes = Ville::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->pluck('id', 'code');
        $lieux = Site::withoutGlobalScopes()->where('entreprise_id', $entreprise->id)->pluck('id', 'code');

        $crees = 0;
        $existants = 0;

        foreach (self::ACCES as $acces) {
            if (User::withoutGlobalScopes()->where('email', $acces['email'])->exists()) {
                $existants++;

                continue;
            }

            $creerAcces->executer($entreprise, $acces['role'], [
                'nom' => $acces['nom'],
                'email' => $acces['email'],
                // Un mot de passe long et aléatoire, que personne n'a besoin de
                // connaître : le titulaire choisira le sien par le lien du courriel
                // d'accueil, envoyé à l'activation.
                'mot_de_passe' => Str::password(24),
                'ville_id' => isset($acces['ville']) ? $villes[$acces['ville']] ?? null : null,
                'site_id' => isset($acces['lieu']) ? $lieux[$acces['lieu']] ?? null : null,
                'est_actif' => (bool) $this->option('activer'),
            ]);

            $crees++;
        }

        $this->line("  $crees accès créés, $existants déjà présents.");
    }
}
