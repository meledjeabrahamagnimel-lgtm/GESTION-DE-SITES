<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('logo_chemin')->nullable();
            $table->string('couleur_ink')->default('#191B20');
            $table->string('couleur_paper')->default('#F4F3EF');
            $table->string('couleur_ligne')->default('#E2E0D8');
            $table->string('couleur_accent')->default('#C8102E');
            $table->string('couleur_succes')->default('#0E9F6E');
            $table->string('couleur_alerte')->default('#D97706');
            $table->string('couleur_info')->default('#2563EB');
            $table->string('plan')->default('starter');
            $table->boolean('est_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
