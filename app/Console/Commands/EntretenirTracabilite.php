<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Noyau\Tracabilite\Modeles\SessionUtilisateur;
use Modules\Noyau\Tracabilite\Services\JournalDeNavigation;

/**
 * Entretien du journal de traçabilité.
 *
 * Deux gestes, tous deux sans effet sur les données métier : refermer les sessions que
 * personne n'a fermées (navigateur quitté, machine éteinte), et effacer les traces
 * devenues trop anciennes pour renseigner quoi que ce soit.
 *
 * L'écran de traçabilité ne dépend pas de cette commande pour dire juste : une session
 * silencieuse depuis un quart d'heure n'y est déjà plus comptée comme présente, et les
 * durées sont tenues à jour à chaque requête. La commande range ; elle ne corrige pas.
 *
 * Sur un hébergement mutualisé sans tâche planifiée, la lancer à la main de temps à
 * autre suffit — ou la laisser de côté, au prix de lignes « ouvertes » qui ne le sont
 * plus vraiment dans l'historique.
 */
class EntretenirTracabilite extends Command
{
    protected $signature = 'tracabilite:entretenir
                            {--jours=180 : durée de conservation des traces, en jours}
                            {--sans-purge : se contenter de refermer les sessions inactives}';

    protected $description = 'Referme les sessions inactives et purge les traces trop anciennes';

    public function handle(JournalDeNavigation $journal): int
    {
        $closes = $journal->cloturerLesSessionsInactives();

        $this->line("  Sessions refermées (inactives depuis plus de "
            .SessionUtilisateur::MINUTES_AVANT_INACTIVITE." min) : $closes");

        if ($this->option('sans-purge')) {
            return self::SUCCESS;
        }

        $jours = max(1, (int) $this->option('jours'));
        $bilan = $journal->purger($jours);

        $this->line("  Traces de plus de $jours jours effacées : "
            ."{$bilan['sessions']} session(s), {$bilan['visites']} visite(s) d'écran");

        return self::SUCCESS;
    }
}
