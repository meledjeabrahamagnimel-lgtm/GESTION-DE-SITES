<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une prospection peut désormais être saisie par le commercial lui-même depuis son
 * espace, puis transmise au responsable de site qui la valide ou la refuse.
 * Les lignes créées par un responsable restent directement validées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospections', function (Blueprint $table) {
            $table->enum('statut_validation', ['Brouillon', 'Transmise', 'Validée', 'Refusée'])
                ->default('Validée')
                ->after('observations');
            $table->text('motif_refus')->nullable()->after('statut_validation');
            $table->timestamp('transmise_le')->nullable()->after('motif_refus');
        });
    }

    public function down(): void
    {
        Schema::table('prospections', function (Blueprint $table) {
            $table->dropColumn(['statut_validation', 'motif_refus', 'transmise_le']);
        });
    }
};
