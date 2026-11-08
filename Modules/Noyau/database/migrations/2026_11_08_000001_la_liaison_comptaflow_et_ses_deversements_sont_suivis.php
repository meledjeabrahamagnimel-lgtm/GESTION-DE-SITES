<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liaisons_comptaflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete()->unique();
            // Identifiant du dossier dans COMPTAFLOW. Il est distinct de tout identifiant SELFLOW.
            $table->unsignedBigInteger('comptaflow_company_id')->nullable()->unique();
            $table->text('cle_chiffree')->nullable();
            $table->string('statut', 24)->default('non_liee');
            $table->timestamp('liee_le')->nullable();
            $table->timestamp('dernier_deversement_le')->nullable();
            $table->string('dernier_deversement_id', 100)->nullable();
            $table->text('dernier_message')->nullable();
            $table->foreignId('liee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deversements_comptaflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('operation_id', 100);
            $table->string('journal', 12)->nullable();
            $table->date('date_ecriture')->nullable();
            $table->string('reference_piece', 120)->nullable();
            $table->json('lignes');
            $table->string('statut', 24)->default('en_attente');
            $table->unsignedInteger('lignes_acceptees')->default(0);
            $table->unsignedInteger('lignes_refusees')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();
            $table->unique(['entreprise_id', 'operation_id']);
            $table->index(['entreprise_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deversements_comptaflow');
        Schema::dropIfExists('liaisons_comptaflow');
    }
};
