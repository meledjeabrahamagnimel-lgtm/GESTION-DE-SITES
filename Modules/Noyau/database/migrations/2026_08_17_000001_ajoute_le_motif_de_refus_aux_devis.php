<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un devis refusé sans motif ne s'explique pas : trois mois plus tard, personne ne sait
 * si le client a trouvé le prix trop élevé, le délai trop long, ou s'il est simplement
 * parti ailleurs. La prospection portait déjà cette colonne ; le devis, qui est l'étape
 * où le refus se joue vraiment, ne l'avait pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->string('motif_refus', 255)->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('devis', fn (Blueprint $table) => $table->dropColumn('motif_refus'));
    }
};
