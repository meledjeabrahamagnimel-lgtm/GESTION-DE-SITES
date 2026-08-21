<?php

namespace Modules\Noyau\Tracabilite\Ecouteurs;

use Illuminate\Auth\Events\Logout;
use Modules\Noyau\Tracabilite\Services\JournalDeNavigation;

/**
 * Une déconnexion referme la ligne de journal.
 *
 * L'événement est émis pendant `logout()`, donc avant que la session ne soit invalidée :
 * la clef posée à l'entrée est encore lisible. L'identifiant de session est passé en
 * second recours, pour le cas où elle aurait déjà été régénérée.
 */
class FermeLaSession
{
    public function __construct(private JournalDeNavigation $journal) {}

    public function handle(Logout $evenement): void
    {
        $requete = request();

        if (! $requete->hasSession()) {
            return;
        }

        $this->journal->fermerSession(
            $requete->session()->get(JournalDeNavigation::CLEF_SESSION),
            $requete->session()->getId(),
            'deconnexion',
        );

        $requete->session()->forget(JournalDeNavigation::CLEF_SESSION);
    }
}
