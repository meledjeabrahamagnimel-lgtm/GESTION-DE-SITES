<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Superviseur de ville — le pilotage
|--------------------------------------------------------------------------
| Le superviseur répond de tous les lieux de sa ville : son métier est de lire
| les indicateurs et de nommer les accès sous lui. Ces écrans lui appartiennent.
|
| Deux autres rôles les consultent : le gérant, qui suit sans saisir, et le
| responsable de site, à qui Site::visiblesPour() n'ouvre que son propre lieu.
| Une route ne pouvant être déclarée qu'une fois pour une URL donnée, elle est
| déclarée ici — chez celui dont c'est le métier — et son middleware nomme les
| trois rôles admis.
*/

Route::middleware(['auth', 'role:gerant|responsable_ville|responsable_site'])->group(function () {
    Volt::route('/prospects', 'pilotage.prospects')->name('prospects');
    Volt::route('/devis', 'pilotage.devis')->name('devis');
    Volt::route('/chiffre-affaires', 'pilotage.chiffre-affaires')->name('chiffre-affaires');
    Volt::route('/charges', 'pilotage.charges')->name('charges');
    Volt::route('/tresorerie', 'pilotage.tresorerie')->name('tresorerie');
    Volt::route('/commerciaux', 'pilotage.commerciaux')->name('commerciaux');
    Volt::route('/acces/creer', 'pilotage.acces-creer')->name('acces.creer');
});
