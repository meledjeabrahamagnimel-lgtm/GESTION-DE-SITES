<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('commercial_id')->constrained('commerciaux')->cascadeOnDelete();
            $table->foreignId('prospection_id')->nullable()->constrained('prospections')->nullOnDelete();
            $table->string('numero', 20);
            $table->string('n_fiche_reception')->nullable();
            $table->date('date_reception')->nullable();
            $table->date('date_emission');
            $table->string('client');
            $table->enum('activite', ['Mécanique', 'Carrosserie']);
            $table->enum('statut', ['En attente', 'Validé', 'Refusé'])->default('En attente');
            $table->unsignedBigInteger('montant_devis');
            $table->unsignedBigInteger('montant_valide')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entreprise_id', 'site_id', 'statut', 'date_emission']);
            $table->unique(['entreprise_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};
