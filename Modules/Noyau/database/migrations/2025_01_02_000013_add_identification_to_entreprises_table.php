<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Informations légales et fiscales de l'entreprise (contexte ivoirien : RCCM, NCC, DGI)
 * et code d'entreprise servant à rattacher le personnel qui s'inscrit lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            // Code de rattachement, généré par le Gérant, communiqué à son personnel.
            $table->string('code_entreprise', 20)->nullable()->unique()->after('slug');

            // Informations générales
            $table->string('gerant_nom')->nullable();
            $table->string('gerant_prenom')->nullable();
            $table->string('gerant_fonction')->nullable()->default('Gérant');
            $table->string('gerant_email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('rccm', 60)->nullable();

            // Informations fiscales
            $table->string('ncc', 40)->nullable();
            $table->string('regime_imposition')->nullable();
            $table->string('centre_impots')->nullable();
            $table->string('compte_contribuable', 40)->nullable();

            // DGI & local professionnel
            $table->string('idu', 60)->nullable();
            $table->string('commune')->nullable();
            $table->string('quartier')->nullable();
            $table->string('reference_cadastrale')->nullable();
            $table->string('proprietaire_local')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn([
                'code_entreprise', 'gerant_nom', 'gerant_prenom', 'gerant_fonction', 'gerant_email',
                'adresse', 'telephone', 'email', 'rccm', 'ncc', 'regime_imposition', 'centre_impots',
                'compte_contribuable', 'idu', 'commune', 'quartier', 'reference_cadastrale', 'proprietaire_local',
            ]);
        });
    }
};
