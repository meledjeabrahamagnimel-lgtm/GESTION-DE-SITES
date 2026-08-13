<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Point d'entrée unique après connexion : chaque rôle a son écran d'atterrissage.
 */
class RedirectionController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $utilisateur = auth()->user();

        return match (true) {
            $utilisateur->hasRole('super_admin') => redirect()->route('super-admin.dashboard'),
            $utilisateur->hasRole('gerant') => redirect()->route('tableau-de-bord'),
            $utilisateur->hasRole('responsable_site') => redirect()->route('saisie-du-jour'),
            $utilisateur->hasRole('commercial') => redirect()->route('ma-performance'),
            $utilisateur->hasRole('caissier') => redirect()->route('caissier.tableau-de-bord'),
            default => redirect()->route('connexion')->withErrors(['email' => "Aucun rôle n'est associé à ce compte."]),
        };
    }
}
