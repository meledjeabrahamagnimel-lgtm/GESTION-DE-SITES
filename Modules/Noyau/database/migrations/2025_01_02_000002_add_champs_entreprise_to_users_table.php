<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('entreprise_id')->nullable()->after('id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('telephone')->nullable()->after('email');
            $table->boolean('est_actif')->default(true)->after('password');
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('est_actif');
            $table->timestamp('derniere_connexion_le')->nullable()->after('doit_changer_mot_de_passe');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entreprise_id');
            $table->dropColumn(['telephone', 'est_actif', 'doit_changer_mot_de_passe', 'derniere_connexion_le']);
        });
    }
};
