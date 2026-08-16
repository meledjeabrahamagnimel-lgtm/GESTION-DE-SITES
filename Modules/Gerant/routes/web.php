<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Gérant — direction de l'entreprise
|--------------------------------------------------------------------------
| Deux écrans qui lui sont propres. Les indicateurs qu'il consulte par ailleurs
| (Prospects, Devis, CA, Charges, Trésorerie, Commerciaux) sont déclarés par le
| module Superviseur, qui les partage avec le gérant et les responsables
| de site : un même écran, un même code, trois rôles qui le lisent.
*/

Route::middleware(['auth', 'role:gerant'])->group(function () {
    Volt::route('/tableau-de-bord', 'gerant.tableau-de-bord')->name('tableau-de-bord');
    Volt::route('/parametres', 'gerant.parametres')->name('parametres');
});
