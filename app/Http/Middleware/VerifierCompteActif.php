<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un accès révoqué par le Super Admin doit couper la session en cours,
 * pas seulement empêcher une future connexion.
 */
class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur && (! $utilisateur->est_actif || ($utilisateur->entreprise_id && ! $utilisateur->entreprise?->est_active))) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('connexion')->withErrors([
                'email' => 'Cet accès a été désactivé. Contactez votre administrateur.',
            ]);
        }

        return $next($request);
    }
}
