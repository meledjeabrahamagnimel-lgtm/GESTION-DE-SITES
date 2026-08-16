<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Carrosserie" devient "Sinistre" dans toute l'application. Les migrations de
 * création ont déjà été mises à jour pour les installations neuves ; celle-ci
 * corrige les colonnes enum et les données des installations déjà en place
 * (MySQL uniquement — les autres pilotes créent déjà la colonne à jour).
 *
 * Ne touche pas `commerciaux` : sa colonne `activite` a depuis gagné une troisième
 * valeur (« Mécanique/Sinistre ») portée directement par sa migration de création —
 * la réécrire ici l'aurait ramenée à deux valeurs à chaque installation neuve.
 */
return new class extends Migration
{
    private array $tables = ['prospections', 'devis', 'factures'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'activite')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `activite` ENUM('Mécanique', 'Carrosserie', 'Sinistre') NOT NULL");
            DB::table($table)->where('activite', 'Carrosserie')->update(['activite' => 'Sinistre']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `activite` ENUM('Mécanique', 'Sinistre') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'activite')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `activite` ENUM('Mécanique', 'Sinistre', 'Carrosserie') NOT NULL");
            DB::table($table)->where('activite', 'Sinistre')->update(['activite' => 'Carrosserie']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `activite` ENUM('Mécanique', 'Carrosserie') NOT NULL");
        }
    }
};
