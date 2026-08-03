<?php

namespace App\Console\Commands;

use App\Domain\Shared\Models\AbonnementPush;
use App\Domain\Shared\Services\WebPush\EnvoyeurPush;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie en une commande que l'installation locale est complète.
 * Répond à la question « pourquoi je ne vois pas la cloche / la messagerie ? ».
 */
class Diagnostic extends Command
{
    protected $signature = 'app:diagnostic';

    protected $description = "Contrôle l'état de l'installation : migrations, ressources compilées, notifications.";

    private bool $toutVaBien = true;

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== Version du code ===');
        $this->ligne('Branche Git', trim((string) shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null')) ?: 'inconnue');
        $this->ligne('Dernier commit', trim((string) shell_exec('git log --oneline -1 2>/dev/null')) ?: 'inconnu');

        $this->newLine();
        $this->info('=== Tables attendues ===');
        foreach ([
            'conversations' => 'Messagerie',
            'messages' => 'Messagerie',
            'notifications_app' => 'Cloche de notifications',
            'notes' => 'Bloc-notes',
            'dossiers_notes' => 'Bloc-notes',
            'abonnements_push' => 'Notifications poussées',
        ] as $table => $usage) {
            $this->verifier(
                "Table $table ($usage)",
                Schema::hasTable($table),
                'Lancez : php artisan migrate',
            );
        }

        $this->verifier(
            'Colonne users.habilitations (Super Admins secondaires)',
            Schema::hasColumn('users', 'habilitations'),
            'Lancez : php artisan migrate',
        );

        $this->newLine();
        $this->info('=== Fichiers servis au navigateur ===');
        $this->verifier(
            'Ressources compilées (public/build/manifest.json)',
            file_exists(public_path('build/manifest.json')),
            'Lancez : npm install && npm run build',
        );
        $this->verifier(
            'Agent de service (public/sw.js)',
            file_exists(public_path('sw.js')),
            'Le fichier manque : votre copie du code n’est pas à jour.',
        );
        $this->verifier(
            'Lien public/storage (photos, pièces jointes)',
            file_exists(public_path('storage')),
            'Lancez : php artisan storage:link',
        );

        $this->newLine();
        $this->info('=== Caches ===');
        foreach ([
            'Cache de configuration' => 'bootstrap/cache/config.php',
            'Cache des routes' => 'bootstrap/cache/routes-v7.php',
        ] as $intitule => $chemin) {
            if (file_exists(base_path($chemin))) {
                $this->line("  <fg=yellow>!</> $intitule actif — après une mise à jour, lancez : php artisan optimize:clear");
            } else {
                $this->line("  <fg=green>✓</> $intitule inactif (les changements sont pris en compte immédiatement)");
            }
        }

        $this->newLine();
        $this->info('=== Notifications ===');
        $this->line('  <fg=green>✓</> Cloche dans l’application (badge + son) : toujours active');

        if (EnvoyeurPush::estConfigure()) {
            $this->line('  <fg=green>✓</> Notifications poussées : clés VAPID configurées');
            $this->ligne('Appareils abonnés', (string) AbonnementPush::count());
        } else {
            $this->line('  <fg=yellow>!</> Notifications poussées : inactives (clés VAPID absentes)');
            $this->line('      Pour les activer : php artisan push:cles, puis recopier les lignes dans .env');
            $this->line('      Elles exigent une adresse en https:// (ou localhost).');
        }

        $this->newLine();
        $this->info('=== Données ===');
        foreach (['entreprises', 'sites', 'users', 'prospections', 'factures'] as $table) {
            if (Schema::hasTable($table)) {
                $this->ligne(ucfirst($table), (string) DB::table($table)->count());
            }
        }

        $this->newLine();

        if ($this->toutVaBien) {
            $this->info('Installation complète : la cloche et la messagerie doivent être visibles.');
        } else {
            $this->warn('Des éléments manquent — suivez les indications ci-dessus, puis relancez cette commande.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function verifier(string $intitule, bool $present, string $remede): void
    {
        if ($present) {
            $this->line("  <fg=green>✓</> $intitule");

            return;
        }

        $this->toutVaBien = false;
        $this->line("  <fg=red>✗</> $intitule");
        $this->line("      → $remede");
    }

    private function ligne(string $intitule, string $valeur): void
    {
        $this->line("  <fg=gray>·</> ".str_pad($intitule, 34).$valeur);
    }
}
