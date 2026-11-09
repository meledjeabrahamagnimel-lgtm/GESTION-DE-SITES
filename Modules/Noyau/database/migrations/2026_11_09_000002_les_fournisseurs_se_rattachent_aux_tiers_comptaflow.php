<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondances_fournisseurs_comptaflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('fournisseur_id')->constrained('referentiel_fournisseurs')->cascadeOnDelete();
            // Identifiant du tiers déjà existant dans COMPTAFLOW ; aucune création distante.
            $table->unsignedBigInteger('comptaflow_tier_id');
            $table->string('numero_de_tiers', 80)->nullable();
            $table->string('intitule', 190)->nullable();
            $table->foreignId('associe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['entreprise_id', 'fournisseur_id'], 'corr_cf_fournisseur_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondances_fournisseurs_comptaflow');
    }
};
