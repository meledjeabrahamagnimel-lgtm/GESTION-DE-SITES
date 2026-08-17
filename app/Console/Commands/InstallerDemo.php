<?php

namespace App\Console\Commands;

use Database\Seeders\DemoCompletSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Installe le jeu de démonstration complet en une seule commande, sans nom de classe
 * à saisir : « --seeder=Database\Seeders\... » se comporte différemment selon le shell
 * (PowerShell ne traite pas l'antislash comme un échappement), ce qui provoquait
 * régulièrement une erreur « Target class does not exist ».
 */
class InstallerDemo extends Command
{
    protected $signature = 'demo:installer
        {--fraiche : Vide et recrée toute la base avant d\'installer les données}
        {--comptes : N\'aligne que les 14 accès, sans toucher aux écritures}';

    protected $description = 'Installe le jeu de données de démonstration (L\'Artisan Automobile)';

    public function handle(): int
    {
        // Rattrapage seul : le serveur a déjà ses données, seuls les comptes ont divergé
        // (adresse renommée, rôle changé, compte ajouté depuis l'installation).
        if ($this->option('comptes')) {
            $this->components->info('Alignement des accès sur la liste de référence…');
            Artisan::call('db:seed', [
                '--class' => ComptesDeTestSeeder::class,
                '--force' => true,
            ], $this->output);

            return self::SUCCESS;
        }

        if ($this->option('fraiche')) {
            if (! $this->option('no-interaction') && ! $this->confirm('Cette action EFFACE toutes les données existantes. Continuer ?', false)) {
                $this->warn('Installation annulée.');

                return self::SUCCESS;
            }

            $this->components->info('Réinitialisation de la base…');
            Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        } else {
            $this->components->info('Application des migrations en attente…');
            Artisan::call('migrate', ['--force' => true], $this->output);
        }

        $this->components->info('Installation des données de démonstration…');
        Artisan::call('db:seed', [
            '--class' => DemoCompletSeeder::class,
            '--force' => true,
        ], $this->output);

        return self::SUCCESS;
    }
}
