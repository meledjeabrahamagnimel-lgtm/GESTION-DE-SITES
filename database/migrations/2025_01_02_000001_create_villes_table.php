<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ville regroupe les sites d'activité de l'entreprise qui s'y trouvent
 * (ex : Abidjan contient un site Mécanique et un site Sinistre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('nom');
            $table->string('couleur')->default('#2563EB');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();

            $table->unique(['entreprise_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villes');
    }
};
