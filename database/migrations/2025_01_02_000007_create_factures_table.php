<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('devis_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->foreignId('commercial_id')->constrained('commerciaux')->cascadeOnDelete();
            $table->string('numero', 20)->unique();
            $table->string('n_facture');
            $table->date('date');
            $table->string('client');
            $table->enum('type', ['FNE', 'HT'])->default('FNE');
            $table->enum('activite', ['Mécanique', 'Carrosserie']);
            $table->unsignedBigInteger('montant');
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entreprise_id', 'site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
