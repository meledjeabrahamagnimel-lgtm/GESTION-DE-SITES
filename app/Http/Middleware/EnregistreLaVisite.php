<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Noyau\Tracabilite\Services\JournalDeNavigation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inscrit au journal l'écran que l'on vient d'ouvrir.
 *
 * Le travail est fait dans `terminate()`, après que la réponse est partie chez
 * l'utilisateur : le journal ne doit rien coûter au temps d'affichage. Personne ne doit
 * attendre une écriture de traçabilité pour voir sa page.
 *
 * Ce qui est écarté, et pourquoi :
 *
 * - les requêtes Livewire (POST /livewire/update) : elles partent à chaque frappe au
 *   clavier. Les compter comme des visites remplirait la table en une journée et
 *   afficherait cent écrans ouverts là où il n'y en a eu qu'un. Elles valent en revanche
 *   comme preuve de présence : c'est ce qui distingue « encore là » de « parti » ;
 * - les redirections : elles n'affichent rien. L'écran réellement ouvert est celui
 *   d'arrivée, qui passera par ici juste après ;
 * - les erreurs : une page refusée n'a pas été consultée.
 */
class EnregistreLaVisite
{
    public function __construct(private JournalDeNavigation $journal) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $utilisateur = $request->user();

        if (! $utilisateur) {
            return;
        }

        // Le chemin de Livewire porte un suffixe aléatoire propre à l'installation
        // (« livewire-6bac6bb7/update ») : c'est l'en-tête qui identifie ces requêtes
        // de façon sûre, le motif de chemin ne servant que de second filet.
        if ($request->hasHeader('X-Livewire') || $request->is('livewire*/*')) {
            $this->journal->marquerPresence($utilisateur, $request);

            return;
        }

        if (! $request->isMethod('GET') || $request->ajax() || $request->expectsJson()) {
            return;
        }

        if ($response->isRedirection() || ! $response->isSuccessful()) {
            return;
        }

        $this->journal->enregistrerVisite($utilisateur, $request);
    }
}
