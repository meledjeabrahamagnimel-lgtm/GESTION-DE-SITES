<?php

namespace Modules\Noyau\Tracabilite\Ecouteurs;

use Illuminate\Auth\Events\Login;
use Modules\Noyau\Tracabilite\Services\JournalDeNavigation;

/**
 * Une connexion réussie ouvre une ligne de journal.
 *
 * L'événement porte l'utilisateur mais pas la requête : l'adresse et le navigateur sont
 * donc lus sur la requête courante, qui est bien celle du formulaire de connexion.
 */
class OuvreLaSession
{
    public function __construct(private JournalDeNavigation $journal) {}

    public function handle(Login $evenement): void
    {
        if (! $evenement->user instanceof \App\Models\User) {
            return;
        }

        $this->journal->ouvrirSession($evenement->user, request());
    }
}
