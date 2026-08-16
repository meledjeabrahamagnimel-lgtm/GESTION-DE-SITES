<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie interne : conversations entre membres autorisés, messages, pièces jointes
 * et notifications applicatives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Nulle pour une conversation ouverte par le Super Admin, qui n'appartient à aucune entreprise.
            $table->foreignId('entreprise_id')->nullable()->constrained('entreprises')->cascadeOnDelete();
            $table->string('sujet')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dernier_message_le')->nullable();
            $table->timestamps();

            $table->index(['entreprise_id', 'dernier_message_le']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('expediteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('corps');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('type_mime', 120)->nullable();
            $table->unsignedInteger('taille')->default(0);
            $table->timestamps();
        });

        Schema::create('notifications_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('canal', 30)->default('systeme');
            $table->string('niveau', 20)->default('info');
            $table->string('titre');
            $table->text('corps')->nullable();
            $table->string('lien')->nullable();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_app');
        Schema::dropIfExists('pieces_jointes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
