<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerciaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero', 20);
            $table->string('nom');
            $table->enum('activite', ['Mécanique', 'Sinistre', 'Mécanique/Sinistre'])->nullable();
            $table->unsignedBigInteger('objectif_mecanique')->default(0);
            $table->unsignedBigInteger('objectif_sinistre')->default(0);
            // Toujours égal à objectif_mecanique + objectif_sinistre, tenu à jour par le
            // modèle : conservé en colonne propre pour ne pas casser les agrégations
            // existantes (SUM, tri, filtres) sur l'objectif global.
            $table->unsignedBigInteger('objectif_mensuel')->default(0);
            $table->enum('statut', ['Actif', 'Inactif'])->default('Actif');
            $table->boolean('est_spontane')->default(false);
            $table->timestamps();

            $table->index(['entreprise_id', 'site_id', 'statut']);
            $table->unique(['entreprise_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerciaux');
    }
};
