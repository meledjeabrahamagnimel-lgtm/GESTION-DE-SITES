<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\RedirectionController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Noyau — écrans accessibles quel que soit le rôle
|--------------------------------------------------------------------------
| Connexion, inscription, messagerie, notifications, mot de passe et espace
| personnel. Aucun de ces écrans n'appartient à un rôle en particulier : c'est
| pour cela qu'ils vivent dans le socle et non dans un module de rôle.
*/

Route::redirect('/', '/connexion');

// Alias francophone de la route de connexion générée par Fortify (name: login).
Route::get('/connexion', fn () => redirect()->route('login'))->name('connexion');

/*
 * Première connexion : le lien du courriel d'accueil mène ici, pas à la page de
 * connexion. Le titulaire n'a pas encore de mot de passe — lui en réclamer un pour
 * entrer, puis un autre pour en changer, était le parcours qu'il fallait défaire.
 *
 * L'adresse est signée avec la clé de l'application et vaut une semaine ; « signed »
 * la vérifie avant même d'atteindre l'écran. Volontairement hors du groupe « guest » :
 * quelqu'un déjà connecté sur un poste partagé doit pouvoir ouvrir son propre lien
 * sans être renvoyé ailleurs sans explication.
 */
Route::middleware(['signed'])->group(function () {
    Volt::route('/premiere-connexion/{utilisateur}', 'commun.definir-mot-de-passe')->name('mot-de-passe.definir');
});

Route::get('/auth/google', [GoogleAuthController::class, 'rediriger'])->name('auth.google');
Route::get('/auth/callback', [GoogleAuthController::class, 'callback'])->name('auth.callback');

Route::middleware(['guest'])->group(function () {
    Volt::route('/inscription', 'commun.inscription')->name('inscription');
    // Auto-inscription du personnel avec le code communiqué par le gérant.
    Volt::route('/rejoindre', 'commun.inscription-personnel')->name('inscription.personnel');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/redirection', RedirectionController::class)->name('redirection');

    Volt::route('/mon-compte/mot-de-passe', 'commun.mot-de-passe')->name('mot-de-passe.modifier');

    // Messagerie interne : ouverte à tous les rôles, les destinataires étant filtrés
    // par AnnuaireMessagerie selon le rôle et l'entreprise.
    Volt::route('/messages', 'commun.messages')->name('messages');

    // Activation et diagnostic des notifications poussées, ouverts à tous les rôles.
    Volt::route('/mes-notifications', 'commun.mes-notifications')->name('mes-notifications');

    // Espace personnel : profil, photo, rattachement, listes déroulantes.
    Route::middleware(['role:responsable_ville|responsable_site|commercial|caissier'])->group(function () {
        Volt::route('/mon-espace', 'commun.mon-espace')->name('mon-espace');
    });
});
