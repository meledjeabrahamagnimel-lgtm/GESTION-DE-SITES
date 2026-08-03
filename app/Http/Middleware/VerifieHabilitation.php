<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse l'accès à une section de la plateforme si le Super Admin connecté
 * n'a pas reçu l'habilitation correspondante. Le fondateur passe toujours.
 */
class VerifieHabilitation
{
    public function handle(Request $request, Closure $suivant, string $section): Response
    {
        abort_unless($request->user()?->peutAccederA($section), 403, "Cette section ne vous est pas ouverte.");

        return $suivant($request);
    }
}
