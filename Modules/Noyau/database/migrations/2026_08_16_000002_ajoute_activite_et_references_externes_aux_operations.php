<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deux conséquences du site devenu lieu physique :
 *
 * 1. L'activité ne peut plus se déduire du site. Les charges et les encaissements,
 *    qui s'en remettaient jusqu'ici au site pour être classés Mécanique ou Sinistre,
 *    portent donc leur propre activité. Elle reste facultative : beaucoup de charges
 *    (loyer, salaires) concernent réellement le lieu entier, pas une activité.
 *
 * 2. Toutes les opérations ne naissent pas dans l'application — certaines viennent du
 *    terrain ou, demain, de l'application Windev. Une référence d'origine libre permet
 *    de rattacher une facture à son devis, ou un encaissement/décaissement à sa pièce
 *    justificative, même quand celle-ci n'existe pas en base ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->enum('activite', ['Mécanique', 'Sinistre'])->nullable()->after('type_operation');
            $table->string('reference_origine', 60)->nullable()->after('tiers');
        });

        Schema::table('encaissements', function (Blueprint $table) {
            $table->enum('activite', ['Mécanique', 'Sinistre'])->nullable()->after('type');
            $table->string('reference_origine', 60)->nullable()->after('autres_tiers');
        });

        Schema::table('factures', function (Blueprint $table) {
            // N° du devis d'origine : recopié du devis lié quand il existe en base,
            // saisi à la main quand la facture vient d'ailleurs (Windev, papier…).
            $table->string('reference_devis', 60)->nullable()->after('devis_id');
        });

        // Les encaissements adossés à une facture héritent naturellement de son activité :
        // on la reporte pour que l'historique déjà saisi reste ventilable.
        foreach (DB::table('factures')->get(['id', 'activite', 'numero']) as $facture) {
            DB::table('encaissements')->where('facture_id', $facture->id)->update(['activite' => $facture->activite]);
        }

        foreach (DB::table('factures')->whereNotNull('devis_id')->get(['devis_id', 'id']) as $facture) {
            $numero = DB::table('devis')->where('id', $facture->devis_id)->value('numero');

            if ($numero) {
                DB::table('factures')->where('id', $facture->id)->update(['reference_devis' => $numero]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['activite', 'reference_origine']);
        });

        Schema::table('encaissements', function (Blueprint $table) {
            $table->dropColumn(['activite', 'reference_origine']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('reference_devis');
        });
    }
};
