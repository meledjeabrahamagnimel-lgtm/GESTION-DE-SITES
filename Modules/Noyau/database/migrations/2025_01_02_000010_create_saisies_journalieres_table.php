<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saisies_journalieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('vehicules_sans_facture')->default(0);
            $table->text('commentaire_prospects')->nullable();
            $table->text('commentaire_devis')->nullable();
            $table->text('commentaire_ca')->nullable();
            $table->text('commentaire_tresorerie')->nullable();
            $table->text('commentaire_charges')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saisies_journalieres');
    }
};
