<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coordonnées d'un site (point de vente / atelier). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('ville')->nullable()->after('nom');
            $table->string('commune')->nullable()->after('ville');
            $table->string('telephone', 40)->nullable()->after('commune');
            $table->string('adresse')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['ville', 'commune', 'telephone', 'adresse']);
        });
    }
};
