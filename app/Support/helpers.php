<?php

if (! function_exists('ae')) {
    /** Formate un montant en FCFA — ex : 1 250 000 F. */
    function ae(?int $montant): string
    {
        if ($montant === null) {
            return '—';
        }

        return number_format($montant, 0, ',', ' ').' F';
    }
}

if (! function_exists('an')) {
    /** Formate un taux en pourcentage — ex : 42,3 %. */
    function an(?float $taux): string
    {
        if ($taux === null || is_nan($taux)) {
            return '—';
        }

        return number_format($taux * 100, 1, ',', ' ').' %';
    }
}
