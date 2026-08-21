<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Entretien du journal de traçabilité. Déclaré ici pour l'hébergement qui dispose d'une
 * tâche planifiée ; là où il n'y en a pas, l'écran reste juste sans elle — une session
 * silencieuse n'y est pas comptée comme présente, et les durées sont tenues à jour à
 * chaque requête. La commande range l'historique, elle ne le fabrique pas.
 */
Schedule::command('tracabilite:entretenir')->hourly()->withoutOverlapping();
