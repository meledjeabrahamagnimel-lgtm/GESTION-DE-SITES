<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloc-notes personnel, classé par dossiers. Chaque note appartient à un seul
 * utilisateur : rien n'est partagé entre collègues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->nullable()->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom');
            $table->string('couleur', 9)->default('#2563EB');
            $table->timestamps();

            $table->index(['user_id', 'nom']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->nullable()->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dossier_note_id')->nullable()->constrained('dossiers_notes')->nullOnDelete();
            $table->string('titre');
            $table->text('corps')->nullable();
            $table->boolean('est_epinglee')->default(false);
            $table->date('rappel_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'dossier_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('dossiers_notes');
    }
};
