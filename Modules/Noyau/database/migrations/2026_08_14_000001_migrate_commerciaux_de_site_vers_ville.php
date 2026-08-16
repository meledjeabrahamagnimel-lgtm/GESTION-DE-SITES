<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un commercial n'est pas rattaché à une activité (un site) mais à une ville : il
 * prospecte pour l'une ou l'autre activité selon le client, et c'est la prospection —
 * pas la fiche commercial — qui porte l'activité concernée. `commerciaux.site_id` et
 * `commerciaux.activite` sont remplacés par `commerciaux.ville_id`.
 *
 * Conséquence directe : le « Client spontané » n'a plus de raison d'exister en deux
 * exemplaires par ville (un par activité) — un seul suffit désormais. Les deux
 * exemplaires existants sont donc fusionnés : tout l'historique (prospections, devis,
 * factures) du doublon est réattribué au survivant avant suppression, pour ne perdre
 * aucune donnée déjà saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerciaux', function (Blueprint $table) {
            $table->foreignId('ville_id')->nullable()->after('site_id')->constrained('villes')->cascadeOnDelete();
        });

        // UPDATE ... JOIN n'existe qu'en MySQL : boucle portable (peu de sites en jeu)
        // pour que la migration s'exécute aussi sous SQLite (tests).
        foreach (DB::table('sites')->get(['id', 'ville_id']) as $site) {
            DB::table('commerciaux')->where('site_id', $site->id)->update(['ville_id' => $site->ville_id]);
        }

        $doublonsSpontanes = DB::table('commerciaux')->where('est_spontane', true)->orderBy('id')->get()->groupBy('ville_id');

        foreach ($doublonsSpontanes as $lignes) {
            if ($lignes->count() < 2) {
                continue;
            }

            $survivant = $lignes->first();

            foreach ($lignes->skip(1) as $doublon) {
                foreach (['prospections', 'devis', 'factures'] as $table) {
                    DB::table($table)->where('commercial_id', $doublon->id)->update(['commercial_id' => $survivant->id]);
                }

                DB::table('commerciaux')->where('id', $doublon->id)->delete();
            }
        }

        Schema::table('commerciaux', function (Blueprint $table) {
            $table->foreignId('ville_id')->nullable(false)->change();
            $table->dropForeign(['site_id']);
            // L'ancien index sur site_id doit être retiré avant la colonne : SQLite (tests)
            // refuse de supprimer une colonne encore indexée, contrairement à MySQL.
            $table->dropIndex('commerciaux_entreprise_id_site_id_statut_index');
            $table->dropColumn(['site_id', 'activite']);
            $table->index(['entreprise_id', 'ville_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('commerciaux', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('entreprise_id')->constrained('sites')->cascadeOnDelete();
            $table->enum('activite', ['Mécanique', 'Sinistre', 'Mécanique/Sinistre'])->nullable()->after('site_id');
        });

        // Ancre chaque commercial sur le site Mécanique de sa ville : les doublons
        // « Client spontané » fusionnés par le up() ne sont pas recréés, la clôture
        // n'étant jamais censée s'exécuter sur une base de production.
        foreach (DB::table('sites')->where('activite', 'Mécanique')->get(['id', 'ville_id']) as $site) {
            DB::table('commerciaux')->where('ville_id', $site->ville_id)
                ->update(['site_id' => $site->id, 'activite' => 'Mécanique/Sinistre']);
        }

        Schema::table('commerciaux', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable(false)->change();
            $table->dropForeign(['ville_id']);
            $table->dropIndex(['entreprise_id', 'ville_id', 'statut']);
            $table->dropColumn('ville_id');
            $table->index(['entreprise_id', 'site_id', 'statut']);
        });
    }
};
