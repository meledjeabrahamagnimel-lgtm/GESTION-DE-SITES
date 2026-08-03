<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clés VAPID
    |--------------------------------------------------------------------------
    |
    | Générées une seule fois avec « php artisan push:cles », puis recopiées dans
    | le fichier .env. Ne jamais les régénérer sur une application en service :
    | tous les appareils déjà abonnés cesseraient de recevoir les notifications.
    |
    | Si ces clés sont absentes, l'application fonctionne normalement : seules les
    | notifications poussées hors navigation sont désactivées.
    |
    */

    'cle_publique' => env('VAPID_CLE_PUBLIQUE'),

    'cle_privee' => env('VAPID_CLE_PRIVEE'),

    /*
    | Adresse de contact transmise au service de push, qui s'en sert pour joindre
    | l'éditeur en cas d'abus. Un « mailto: » ou une URL.
    */

    'contact' => env('VAPID_CONTACT', 'mailto:contact@dc-knowing.com'),

];
