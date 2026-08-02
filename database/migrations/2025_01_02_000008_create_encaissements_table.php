<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->date('date');
            $table->enum('type', ['Client', 'Appro', 'Autres'])->default('Client');
            $table->enum('moyen', ['Espèces', 'Mobile Money', 'Chèque', 'Virement', 'Autres'])->default('Espèces');
            $table->unsignedBigInteger('montant');
            $table->string('client')->nullable();
            $table->string('autres_tiers')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entreprise_id', 'site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encaissements');
    }
};
