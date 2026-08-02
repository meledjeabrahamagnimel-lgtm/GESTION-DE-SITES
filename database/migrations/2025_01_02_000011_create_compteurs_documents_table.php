<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteurs_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->enum('type', ['pro', 'dev', 'fac']);
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();

            $table->unique(['entreprise_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteurs_documents');
    }
};
