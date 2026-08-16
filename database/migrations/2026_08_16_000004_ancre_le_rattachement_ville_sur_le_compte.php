<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le rattachement d'un commercial à sa ville n'existait que dans sa fiche commercial,
 * et celui d'un responsable que dans la colonne `responsable_id` de sa ville ou de son
 * lieu. Suffisant au quotidien, mais fragile : supprimer les fiches (purge d'un jeu de
 * test) faisait perdre l'information, et rien ne permettait plus de savoir où
 * rattacher qui.
 *
 * `users.ville_id` — jusqu'ici réservé à la comptabilité — porte désormais le
 * rattachement de tous les rôles, et `users.site_id` celui du responsable de site.
 * C'est l'ancrage durable ; les fiches et les `responsable_id` restent la vérité
 * fonctionnelle, mais ne sont plus la seule trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Superviseurs : la ville dont ils répondent.
        foreach (DB::table('villes')->whereNotNull('responsable_id')->get(['id', 'responsable_id']) as $ville) {
            DB::table('users')->where('id', $ville->responsable_id)->update(['ville_id' => $ville->id]);
        }

        // Responsables de site : leur lieu, et la ville qui le contient.
        foreach (DB::table('sites')->whereNotNull('responsable_id')->get(['id', 'ville_id', 'responsable_id']) as $site) {
            DB::table('users')->where('id', $site->responsable_id)
                ->update(['ville_id' => $site->ville_id, 'site_id' => $site->id]);
        }

        // Commerciaux : la ville de leur fiche.
        foreach (DB::table('commerciaux')->whereNotNull('user_id')->get(['user_id', 'ville_id']) as $fiche) {
            DB::table('users')->where('id', $fiche->user_id)->whereNull('ville_id')
                ->update(['ville_id' => $fiche->ville_id]);
        }
    }

    public function down(): void
    {
        // Seule la comptabilité portait ce rattachement auparavant : on le retire de tous
        // les autres comptes, en le laissant à ceux qui n'ont pas de fiche commercial.
        $idsCommerciaux = DB::table('commerciaux')->whereNotNull('user_id')->pluck('user_id');

        DB::table('users')->whereIn('id', $idsCommerciaux)->update(['ville_id' => null, 'site_id' => null]);

        foreach (DB::table('villes')->whereNotNull('responsable_id')->pluck('responsable_id') as $userId) {
            DB::table('users')->where('id', $userId)->update(['ville_id' => null, 'site_id' => null]);
        }
    }
};
