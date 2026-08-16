<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Encadrement — superviseur de ville et responsable de site
|--------------------------------------------------------------------------
| Les deux rôles atteignent exactement les mêmes écrans : ce qui les distingue
| n'est pas ce qu'ils voient, mais jusqu'où ils le voient — le superviseur
| couvre tous les lieux de sa ville, le responsable de site le sien seulement.
| Ce périmètre est résolu une fois pour toutes par Site::visiblesPour().
*/

Route::middleware(['auth'])->group(function () {

    // 1. La saisie de la journée — réservée à ceux qui encadrent un atelier.
    Route::middleware(['role:responsable_ville|responsable_site'])->group(function () {
        Volt::route('/saisie-du-jour', 'saisie.saisie-du-jour')->name('saisie-du-jour');
        Volt::route('/saisie-du-jour/prospections/{prospection}', 'saisie.prospection-voir')->name('prospection.voir');
    });

    // 2. Les indicateurs — lus aussi par le gérant, qui ne saisit rien mais suit tout.
    Route::middleware(['role:gerant|responsable_ville|responsable_site'])->group(function () {
        Volt::route('/prospects', 'pilotage.prospects')->name('prospects');
        Volt::route('/devis', 'pilotage.devis')->name('devis');
        Volt::route('/chiffre-affaires', 'pilotage.chiffre-affaires')->name('chiffre-affaires');
        Volt::route('/charges', 'pilotage.charges')->name('charges');
        Volt::route('/tresorerie', 'pilotage.tresorerie')->name('tresorerie');
        Volt::route('/commerciaux', 'pilotage.commerciaux')->name('commerciaux');
        Volt::route('/acces/creer', 'pilotage.acces-creer')->name('acces.creer');
    });
});
