<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Objectif par activité : la saisie se fait désormais en deux montants (Mécanique et
 * Sinistre) dont la somme alimente automatiquement l'objectif global. L'activité du
 * commercial gagne une troisième valeur, « Mécanique/Sinistre », pour les commerciaux
 * polyvalents — c'est la valeur par défaut à la création.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('commerciaux', 'objectif_mecanique')) {
            return;
        }

        Schema::table('commerciaux', function (Blueprint $table) {
            $table->unsignedBigInteger('objectif_mecanique')->default(0)->after('activite');
            $table->unsignedBigInteger('objectif_sinistre')->default(0)->after('objectif_mecanique');
        });

        // Les commerciaux existants ont un objectif global mais rien par activité :
        // on le reporte intégralement sur leur activité déclarée, pour ne rien perdre.
        DB::table('commerciaux')->where('activite', 'Mécanique')->update(['objectif_mecanique' => DB::raw('objectif_mensuel')]);
        DB::table('commerciaux')->where('activite', 'Sinistre')->update(['objectif_sinistre' => DB::raw('objectif_mensuel')]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `commerciaux` MODIFY `activite` ENUM('Mécanique', 'Sinistre', 'Mécanique/Sinistre') NULL");
        }
    }

    public function down(): void
    {
        Schema::table('commerciaux', function (Blueprint $table) {
            $table->dropColumn(['objectif_mecanique', 'objectif_sinistre']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `commerciaux` MODIFY `activite` ENUM('Mécanique', 'Sinistre') NULL");
        }
    }
};
