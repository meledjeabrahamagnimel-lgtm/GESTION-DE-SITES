<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un abonnement par appareil et par utilisateur : téléphone, tablette, poste de travail.
 * L'endpoint identifie l'appareil auprès du service de push du navigateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('cle_p256dh');
            $table->string('cle_auth');
            $table->string('appareil')->nullable();
            $table->timestamps();

            // L'endpoint dépasse la longueur indexable d'une clé unique en MySQL :
            // on indexe son empreinte, qui joue le même rôle de dédoublonnage.
            $table->string('empreinte', 64);
            $table->unique(['user_id', 'empreinte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_push');
    }
};
