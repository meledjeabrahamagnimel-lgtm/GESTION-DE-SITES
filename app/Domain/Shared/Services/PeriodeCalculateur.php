<?php

namespace App\Domain\Shared\Services;

use Carbon\Carbon;

/**
 * Calcule la plage de dates [début, fin] pour les filtres Jour/Semaine/Mois/Période
 * partagés par le tableau de bord et les onglets de consultation.
 */
class PeriodeCalculateur
{
    public static function plage(string $periode, ?string $debutPersonnalise = null, ?string $finPersonnalisee = null): array
    {
        $aujourdhui = Carbon::today();

        return match ($periode) {
            'jour' => [$aujourdhui->copy(), $aujourdhui->copy()],
            'semaine' => [$aujourdhui->copy()->startOfWeek(), $aujourdhui->copy()->endOfWeek()],
            'mois' => [$aujourdhui->copy()->startOfMonth(), $aujourdhui->copy()->endOfMonth()],
            'periode' => [
                $debutPersonnalise ? Carbon::parse($debutPersonnalise) : $aujourdhui->copy()->subDays(29),
                $finPersonnalisee ? Carbon::parse($finPersonnalisee) : $aujourdhui->copy(),
            ],
            default => [$aujourdhui->copy()->startOfWeek(), $aujourdhui->copy()->endOfWeek()],
        };
    }

    /** Découpe la plage en points hebdomadaires pour les graphiques (max ~12 points). */
    public static function pointsHebdomadaires(Carbon $debut, Carbon $fin): array
    {
        $points = [];
        $curseur = $debut->copy()->startOfWeek();
        $i = 1;

        while ($curseur->lessThanOrEqualTo($fin) && $i <= 12) {
            $finSemaine = $curseur->copy()->endOfWeek();
            $points[] = [
                'label' => 'S'.$i,
                'debut' => $curseur->copy(),
                'fin' => $finSemaine->copy(),
            ];
            $curseur->addWeek();
            $i++;
        }

        return $points;
    }
}
