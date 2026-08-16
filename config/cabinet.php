<?php

/*
|--------------------------------------------------------------------------
| Cabinet éditeur de l'application
|--------------------------------------------------------------------------
| Coordonnées affichées en pied des courriels envoyés aux utilisateurs. Elles
| vivent ici plutôt qu'en dur dans les gabarits : un numéro qui change se
| corrige alors à un seul endroit, sans toucher au code ni aux vues.
*/

return [
    'nom' => env('CABINET_NOM', 'DC-KNOWING'),
    'email' => env('CABINET_EMAIL', 'it.dcknowing@gmail.com'),
    'fixe' => env('CABINET_FIXE', '27 22 42 14 43'),
    'telephones' => [
        env('CABINET_TEL_1', '07 67 13 19 93'),
        env('CABINET_TEL_2', '01 70 51 89 67'),
    ],
];
