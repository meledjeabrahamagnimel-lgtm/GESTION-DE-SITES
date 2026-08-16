<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Commercial — le terrain
|--------------------------------------------------------------------------
| Il saisit ses prospections et suit sa propre performance. Il n'accède à aucun
| indicateur consolidé : son périmètre s'arrête à ce qu'il a lui-même produit.
*/

Route::middleware(['auth', 'role:commercial'])->group(function () {
    Volt::route('/ma-performance', 'commercial.ma-performance')->name('ma-performance');
    Volt::route('/mes-prospections', 'commercial.mes-prospections')->name('mes-prospections');
    Volt::route('/mes-notes', 'commercial.mes-notes')->name('mes-notes');
});
