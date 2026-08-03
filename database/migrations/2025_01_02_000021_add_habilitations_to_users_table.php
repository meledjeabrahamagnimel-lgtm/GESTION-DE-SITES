<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admins secondaires : chacun reçoit des habilitations par section et
 * ne peut gérer que les comptes qu'il a lui-même créés. Le fondateur reste
 * intouchable, quelles que soient les habilitations accordées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cree_par_id')->nullable()->after('entreprise_id')->constrained('users')->nullOnDelete();
            $table->boolean('est_fondateur')->default(false)->after('est_actif');
            $table->json('habilitations')->nullable()->after('est_fondateur');
        });

        // Sur une base neuve, les tables de permissions sont créées par une migration
        // postérieure : il n'y a alors aucun compte à reprendre, le seeder désignera
        // le fondateur. Sur une base existante, on reprend le Super Admin le plus ancien.
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        $fondateur = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', 'super_admin')
            ->orderBy('model_has_roles.model_id')
            ->value('model_has_roles.model_id');

        if ($fondateur) {
            DB::table('users')->where('id', $fondateur)->update(['est_fondateur' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cree_par_id');
            $table->dropColumn(['est_fondateur', 'habilitations']);
        });
    }
};
