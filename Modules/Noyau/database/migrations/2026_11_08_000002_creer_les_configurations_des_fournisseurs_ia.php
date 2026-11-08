<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations_ia', function (Blueprint $table) {
            $table->id();
            $table->string('fournisseur', 40)->unique();
            $table->string('modele', 150);
            $table->longText('cle_api_chiffree');
            $table->boolean('actif')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations_ia');
    }
};
