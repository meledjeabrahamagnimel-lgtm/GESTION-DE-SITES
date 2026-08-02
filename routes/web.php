<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\RedirectionController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/connexion');

// Alias francophone de la route de connexion générée par Fortify (name: login).
Route::get('/connexion', fn () => redirect()->route('login'))->name('connexion');

Route::get('/auth/google', [GoogleAuthController::class, 'rediriger'])->name('auth.google');
Route::get('/auth/callback', [GoogleAuthController::class, 'callback'])->name('auth.callback');

Route::middleware(['guest'])->group(function () {
    Volt::route('/inscription', 'inscription')->name('inscription');
    // Auto-inscription du personnel avec le code communiqué par le gérant.
    Volt::route('/rejoindre', 'inscription-personnel')->name('inscription.personnel');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/redirection', RedirectionController::class)->name('redirection');

    Volt::route('/mon-compte/mot-de-passe', 'mot-de-passe')->name('mot-de-passe.modifier');

    // Espace personnel : profil, photo, rattachement, listes déroulantes.
    Route::middleware(['role:responsable_site|commercial'])->group(function () {
        Volt::route('/mon-espace', 'mon-espace')->name('mon-espace');
    });

    // Espace entreprise (Gérant, Responsable de site, Commercial).
    Route::middleware(['role:gerant'])->group(function () {
        Volt::route('/tableau-de-bord', 'tableau-de-bord')->name('tableau-de-bord');
        Volt::route('/parametres', 'parametres')->name('parametres');
    });

    Route::middleware(['role:responsable_site'])->group(function () {
        Volt::route('/saisie-du-jour', 'saisie-du-jour')->name('saisie-du-jour');
    });

    Route::middleware(['role:gerant|responsable_site'])->group(function () {
        Volt::route('/prospects', 'prospects')->name('prospects');
        Volt::route('/devis', 'devis')->name('devis');
        Volt::route('/chiffre-affaires', 'chiffre-affaires')->name('chiffre-affaires');
        Volt::route('/charges', 'charges')->name('charges');
        Volt::route('/tresorerie', 'tresorerie')->name('tresorerie');
        Volt::route('/commerciaux', 'commerciaux')->name('commerciaux');
    });

    Route::middleware(['role:gerant|responsable_site'])->group(function () {
        Volt::route('/acces/creer', 'acces-creer')->name('acces.creer');
    });

    Route::middleware(['role:commercial'])->group(function () {
        Volt::route('/ma-performance', 'ma-performance')->name('ma-performance');
        Volt::route('/mes-prospections', 'mes-prospections')->name('mes-prospections');
    });

    // Super Admin — plateforme, hors périmètre d'une entreprise.
    Route::prefix('super-admin')->name('super-admin.')->middleware(['role:super_admin'])->group(function () {
        Volt::route('/', 'super-admin-dashboard')->name('dashboard');
        Volt::route('/entreprises', 'super-admin-entreprises-index')->name('entreprises.index');
        Volt::route('/entreprises/{entreprise}', 'super-admin-entreprises-detail')->name('entreprises.show');
        Volt::route('/acces', 'super-admin-acces-index')->name('acces.index');
        Volt::route('/acces/creer', 'super-admin-acces-creer')->name('acces.creer');
        Volt::route('/journal', 'super-admin-journal-index')->name('journal.index');
        Volt::route('/maintenance', 'super-admin-maintenance')->name('maintenance');
    });
});
