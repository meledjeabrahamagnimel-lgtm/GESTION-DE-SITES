<?php

use App\Http\Controllers\RedirectionController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/connexion');

// Alias francophone de la route de connexion générée par Fortify (name: login).
Route::get('/connexion', fn () => redirect()->route('login'))->name('connexion');

Route::middleware(['auth'])->group(function () {
    Route::get('/redirection', RedirectionController::class)->name('redirection');

    // Espace entreprise (Gérant, Responsable de site, Commercial).
    Route::middleware(['role:gerant'])->group(function () {
        Volt::route('/tableau-de-bord', 'tableau-de-bord')->name('tableau-de-bord');
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

    Route::middleware(['role:commercial'])->group(function () {
        Volt::route('/ma-performance', 'ma-performance')->name('ma-performance');
    });

    // Super Admin — plateforme, hors périmètre d'une entreprise.
    Route::prefix('super-admin')->name('super-admin.')->middleware(['role:super_admin'])->group(function () {
        Volt::route('/', 'super-admin-dashboard')->name('dashboard');
        Volt::route('/entreprises', 'super-admin-entreprises-index')->name('entreprises.index');
        Volt::route('/entreprises/{entreprise}', 'super-admin-entreprises-detail')->name('entreprises.show');
        Volt::route('/acces', 'super-admin-acces-index')->name('acces.index');
        Volt::route('/journal', 'super-admin-journal-index')->name('journal.index');
    });
});
