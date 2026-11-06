<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relie un avoir fournisseur à la facture qu'il corrige.
 * Additif : aucune facture existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_fournisseurs', function (Blueprint $table) {
            $table->foreignId('avoir_de_id')->nullable()->after('id')
                ->constrained('factures_fournisseurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factures_fournisseurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avoir_de_id');
        });
    }
};
