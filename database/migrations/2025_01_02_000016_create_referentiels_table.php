<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listes de valeurs propres à chaque entreprise (activités, moyens de prospection,
 * moyens de paiement, libellés de charge…). Elles alimentent les listes déroulantes
 * et peuvent être enrichies depuis l'application, sans passer par une migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referentiels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('valeur');
            $table->unsignedSmallInteger('rang')->default(0);
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->unique(['entreprise_id', 'type', 'valeur']);
            $table->index(['entreprise_id', 'type', 'est_actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referentiels');
    }
};
