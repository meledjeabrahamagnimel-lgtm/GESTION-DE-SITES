<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un site est l'endroit d'activité précis où l'entreprise opère (ex : l'atelier
 * Mécanique d'Abidjan, ou son atelier Sinistre). Une ville peut contenir plusieurs
 * sites — pour l'instant, au plus un par activité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('ville_id')->constrained('villes')->cascadeOnDelete();
            $table->string('nom');
            $table->enum('activite', ['Mécanique', 'Sinistre']);
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->unique(['ville_id', 'activite']);
            $table->index(['entreprise_id', 'est_actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
