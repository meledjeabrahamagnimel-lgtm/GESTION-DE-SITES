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

    // Messagerie interne : ouverte à tous les rôles, les destinataires étant
    // filtrés par AnnuaireMessagerie selon le rôle et l'entreprise.
    Volt::route('/messages', 'messages')->name('messages');

    // Activation et diagnostic des notifications poussées, ouverts à tous les rôles.
    Volt::route('/mes-notifications', 'mes-notifications')->name('mes-notifications');

    // Espace personnel : profil, photo, rattachement, listes déroulantes.
    Route::middleware(['role:responsable_ville|responsable_site|commercial|caissier'])->group(function () {
        Volt::route('/mon-espace', 'mon-espace')->name('mon-espace');
    });

    // Espace entreprise (Gérant, Responsable de site, Commercial).
    Route::middleware(['role:gerant'])->group(function () {
        Volt::route('/tableau-de-bord', 'tableau-de-bord')->name('tableau-de-bord');
        Volt::route('/parametres', 'parametres')->name('parametres');
    });

    Route::middleware(['role:responsable_ville|responsable_site'])->group(function () {
        Volt::route('/saisie-du-jour', 'saisie-du-jour')->name('saisie-du-jour');
        Volt::route('/saisie-du-jour/prospections/{prospection}', 'prospection-voir')->name('prospection.voir');
    });

    Route::middleware(['role:gerant|responsable_ville|responsable_site'])->group(function () {
        Volt::route('/prospects', 'prospects')->name('prospects');
        Volt::route('/devis', 'devis')->name('devis');
        Volt::route('/chiffre-affaires', 'chiffre-affaires')->name('chiffre-affaires');
        Volt::route('/charges', 'charges')->name('charges');
        Volt::route('/tresorerie', 'tresorerie')->name('tresorerie');
        Volt::route('/commerciaux', 'commerciaux')->name('commerciaux');
    });

    Route::middleware(['role:gerant|responsable_ville|responsable_site'])->group(function () {
        Volt::route('/acces/creer', 'acces-creer')->name('acces.creer');
    });

    Route::middleware(['role:commercial'])->group(function () {
        Volt::route('/ma-performance', 'ma-performance')->name('ma-performance');
        Volt::route('/mes-prospections', 'mes-prospections')->name('mes-prospections');
        Volt::route('/mes-notes', 'mes-notes')->name('mes-notes');
    });

    Route::prefix('caissier')->name('caissier.')->middleware(['role:caissier'])->group(function () {
        Volt::route('/tableau-de-bord', 'caissier-tableau-de-bord')->name('tableau-de-bord');
        Volt::route('/encaissements', 'caissier-encaissements')->name('encaissements');
        Volt::route('/decaissements', 'caissier-decaissements')->name('decaissements');
    });

    // Super Admin — plateforme, hors périmètre d'une entreprise.
    Route::prefix('super-admin')->name('super-admin.')->middleware(['role:super_admin'])->group(function () {
        Volt::route('/', 'super-admin-dashboard')->name('dashboard')->middleware('habilitation:dashboard');
        Volt::route('/entreprises', 'super-admin-entreprises-index')->name('entreprises.index')->middleware('habilitation:entreprises');
        Volt::route('/entreprises/{entreprise}', 'super-admin-entreprises-detail')->name('entreprises.show')->middleware('habilitation:entreprises');
        Volt::route('/acces', 'super-admin-acces-index')->name('acces.index')->middleware('habilitation:acces');
        Volt::route('/acces/creer', 'super-admin-acces-creer')->name('acces.creer')->middleware('habilitation:acces');
        Volt::route('/administrateurs', 'super-admin-administrateurs')->name('administrateurs')->middleware('habilitation:acces');
        Volt::route('/journal', 'super-admin-journal-index')->name('journal.index')->middleware('habilitation:journal');
        Volt::route('/maintenance', 'super-admin-maintenance')->name('maintenance')->middleware('habilitation:maintenance');
    });
});
