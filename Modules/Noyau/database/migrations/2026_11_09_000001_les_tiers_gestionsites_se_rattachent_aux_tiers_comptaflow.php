<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondances_tiers_comptaflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('tiers_id')->constrained('tiers')->cascadeOnDelete();
            // Identifiant distant : aucune FK locale vers la base COMPTAFLOW.
            $table->unsignedBigInteger('comptaflow_tier_id');
            $table->string('numero_de_tiers', 80)->nullable();
            $table->string('intitule', 190)->nullable();
            $table->foreignId('associe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['entreprise_id', 'tiers_id'], 'corr_cf_tiers_local_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondances_tiers_comptaflow');
    }
};
