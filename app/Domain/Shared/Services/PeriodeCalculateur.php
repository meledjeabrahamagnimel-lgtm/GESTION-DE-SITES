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

        [$debut, $fin] = match ($periode) {
            'jour' => [$aujourdhui->copy(), $aujourdhui->copy()],
            'semaine' => [$aujourdhui->copy()->startOfWeek(), $aujourdhui->copy()->endOfWeek()],
            'mois' => [$aujourdhui->copy()->startOfMonth(), $aujourdhui->copy()->endOfMonth()],
            'periode' => [
                $debutPersonnalise ? Carbon::parse($debutPersonnalise) : $aujourdhui->copy()->subDays(29),
                $finPersonnalisee ? Carbon::parse($finPersonnalisee) : $aujourdhui->copy(),
            ],
            default => [$aujourdhui->copy()->startOfWeek(), $aujourdhui->copy()->endOfWeek()],
        };

        // endOfWeek()/endOfMonth() renvoient 23:59:59.999999 : sans normalisation, un
        // diffInDays() donne 6,9999 au lieu de 7 et fausse tous les objectifs au prorata.
        return [$debut->startOfDay(), $fin->startOfDay()];
    }

    /** Nombre de jours calendaires inclus dans la plage (1 pour une journée). */
    public static function nombreDeJours(Carbon $debut, Carbon $fin): int
    {
        return (int) $debut->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay()) + 1;
    }

    /**
     * Découpe la plage en points de graphique, avec une granularité adaptée à sa durée :
     * une semaine se lit jour par jour, un mois semaine par semaine, une période plus
     * longue mois par mois. Sans cela une période courte ne produit qu'une seule barre
     * et le graphique perd tout intérêt — et une longue période plafonnée aux 12
     * premières semaines depuis son début manquerait toutes les données récentes.
     */
    public static function points(Carbon $debut, Carbon $fin): array
    {
        $jours = self::nombreDeJours($debut, $fin);

        if ($jours <= 1) {
            return [['label' => $debut->translatedFormat('d/m'), 'debut' => $debut->copy(), 'fin' => $debut->copy()]];
        }

        if ($jours <= 31) {
            return self::pointsJournaliers($debut, $fin);
        }

        if ($jours <= 120) {
            return self::pointsHebdomadaires($debut, $fin);
        }

        return self::pointsMensuels($debut, $fin);
    }

    /** Un point par jour, en sautant un libellé sur deux au-delà de deux semaines. */
    private static function pointsJournaliers(Carbon $debut, Carbon $fin): array
    {
        $jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $points = [];
        $curseur = $debut->copy();
        $court = self::nombreDeJours($debut, $fin) <= 8;

        while ($curseur->lessThanOrEqualTo($fin)) {
            $points[] = [
                'label' => $court ? $jours[$curseur->dayOfWeek].' '.$curseur->format('d') : $curseur->format('d/m'),
                'debut' => $curseur->copy(),
                'fin' => $curseur->copy(),
            ];
            $curseur->addDay();
        }

        return $points;
    }

    /** Découpe la plage en points hebdomadaires pour les graphiques, sur toute la période demandée. */
    public static function pointsHebdomadaires(Carbon $debut, Carbon $fin): array
    {
        $points = [];
        $curseur = $debut->copy()->startOfWeek();
        $i = 1;

        while ($curseur->lessThanOrEqualTo($fin)) {
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

    /** Découpe la plage en points mensuels pour les graphiques, sur toute la période demandée. */
    private static function pointsMensuels(Carbon $debut, Carbon $fin): array
    {
        $points = [];
        $curseur = $debut->copy()->startOfMonth();

        while ($curseur->lessThanOrEqualTo($fin)) {
            $finMois = $curseur->copy()->endOfMonth();
            $points[] = [
                'label' => ucfirst($curseur->translatedFormat('M')).' '.$curseur->format('y'),
                'debut' => $curseur->copy()->max($debut),
                'fin' => $finMois->copy()->min($fin),
            ];
            $curseur->addMonthNoOverflow();
        }

        return $points;
    }
}
