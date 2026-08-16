<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saisie libre : couple intitulé / valeur rattaché à n'importe quelle écriture
 * (prospection, devis, facture, encaissement, charge…), pour consigner une information
 * qu'aucune colonne existante ne prévoit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donnees_libres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->morphs('sujet');
            $table->string('intitule');
            $table->text('valeur')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donnees_libres');
    }
};
