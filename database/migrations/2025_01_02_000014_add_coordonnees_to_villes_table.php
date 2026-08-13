<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coordonnées d'une ville. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villes', function (Blueprint $table) {
            $table->string('commune')->nullable()->after('nom');
            $table->string('telephone', 40)->nullable()->after('commune');
            $table->string('adresse')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('villes', function (Blueprint $table) {
            $table->dropColumn(['commune', 'telephone', 'adresse']);
        });
    }
};
