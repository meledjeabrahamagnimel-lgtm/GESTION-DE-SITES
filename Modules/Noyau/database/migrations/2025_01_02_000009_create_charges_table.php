<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type_operation', ['Charges', 'Transfert', 'Décaissement DG'])->default('Charges');
            $table->string('libelle');
            $table->enum('moyen', ['Espèces', 'Mobile Money', 'Chèque', 'Virement', 'Autres'])->default('Espèces');
            $table->unsignedBigInteger('montant');
            $table->string('tiers')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entreprise_id', 'site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
