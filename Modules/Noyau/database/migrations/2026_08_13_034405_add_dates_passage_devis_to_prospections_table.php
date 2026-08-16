<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date à laquelle le passage a eu lieu, et date à laquelle le devis après passage a été
 * établi. Un devis après passage implique nécessairement un passage, à la même date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospections', function (Blueprint $table) {
            $table->date('date_passage')->nullable()->after('passage');
            $table->date('date_devis')->nullable()->after('devis_apres_passage');
        });
    }

    public function down(): void
    {
        Schema::table('prospections', function (Blueprint $table) {
            $table->dropColumn(['date_passage', 'date_devis']);
        });
    }
};
