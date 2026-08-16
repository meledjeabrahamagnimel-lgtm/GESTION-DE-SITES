<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Super Admin — la plateforme, hors périmètre d'une entreprise
|--------------------------------------------------------------------------
| Chaque écran porte en plus une habilitation : un Super Admin secondaire ne
| voit que les sections qui lui ont été ouvertes.
*/

Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')->name('super-admin.')
    ->group(function () {
        Volt::route('/', 'superadmin.tableau-de-bord')->name('dashboard')->middleware('habilitation:dashboard');
        Volt::route('/entreprises', 'superadmin.entreprises-liste')->name('entreprises.index')->middleware('habilitation:entreprises');
        Volt::route('/entreprises/{entreprise}', 'superadmin.entreprises-detail')->name('entreprises.show')->middleware('habilitation:entreprises');
        Volt::route('/acces', 'superadmin.acces-liste')->name('acces.index')->middleware('habilitation:acces');
        Volt::route('/acces/creer', 'superadmin.acces-creer')->name('acces.creer')->middleware('habilitation:acces');
        Volt::route('/administrateurs', 'superadmin.administrateurs')->name('administrateurs')->middleware('habilitation:acces');
        Volt::route('/journal', 'superadmin.journal')->name('journal.index')->middleware('habilitation:journal');
        Volt::route('/maintenance', 'superadmin.maintenance')->name('maintenance')->middleware('habilitation:maintenance');
    });
