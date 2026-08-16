<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le compteur 'nfa' (numérotation automatique du N° de facture) à l'enum
 * existant. No-op sur une installation fraîche : la migration de création a déjà
 * la valeur depuis le départ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE compteurs_documents MODIFY type ENUM('pro', 'dev', 'fac', 'com', 'nfa')");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE compteurs_documents MODIFY type ENUM('pro', 'dev', 'fac', 'com')");
    }
};
