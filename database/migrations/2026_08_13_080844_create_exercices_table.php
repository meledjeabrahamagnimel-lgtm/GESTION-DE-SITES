<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un exercice représente une année civile d'activité. Il se clôture ville par ville
 * (le gérant clôt chaque ville quand elle est prête) ; la clôture de l'exercice
 * complet reste une décision manuelle du gérant, même une fois toutes les villes
 * closes — le système se contente de le lui suggérer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->enum('statut', ['Ouvert', 'Clos'])->default('Ouvert');
            $table->timestamp('cloture_le')->nullable();
            // Un seul exercice « par défaut » par entreprise : celui affiché dans le badge de
            // l'en-tête pour tous. Une interface peut consulter/rouvrir un autre exercice sans
            // que cela change ce qui reste le défaut pour le reste de l'équipe.
            $table->boolean('est_defaut')->default(false);
            $table->timestamps();

            $table->unique(['entreprise_id', 'annee']);
        });

        Schema::create('exercice_villes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('ville_id')->constrained('villes')->cascadeOnDelete();
            $table->enum('statut', ['Ouvert', 'Clos'])->default('Ouvert');
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exercice_id', 'ville_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercice_villes');
        Schema::dropIfExists('exercices');
    }
};
