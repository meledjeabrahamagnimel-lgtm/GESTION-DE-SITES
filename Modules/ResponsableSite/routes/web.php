<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Responsable de site — la saisie du jour
|--------------------------------------------------------------------------
| La saisie est ancrée sur un lieu : c'est là que la journée d'atelier se
| déroule, et c'est le métier du responsable de site. Ces écrans lui appartiennent.
|
| Le superviseur de ville y accède également : quand sa ville n'a qu'un seul lieu,
| c'est lui qui tient la saisie. Le gérant en est exclu — il ne saisit rien.
*/

Route::middleware(['auth', 'role:responsable_ville|responsable_site'])->group(function () {
    Volt::route('/saisie-du-jour', 'saisie.saisie-du-jour')->name('saisie-du-jour');
    Volt::route('/saisie-du-jour/prospections/{prospection}', 'saisie.prospection-voir')->name('prospection.voir');
});
