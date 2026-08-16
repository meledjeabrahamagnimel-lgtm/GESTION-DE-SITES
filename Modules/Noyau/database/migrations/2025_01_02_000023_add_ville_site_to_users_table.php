<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un caissier : à un site précis (une seule activité) ou à une
 * ville entière (les deux activités), au choix. Exactement l'un des deux est
 * renseigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ville_id')->nullable()->after('entreprise_id')->constrained('villes')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->after('ville_id')->constrained('sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ville_id');
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
