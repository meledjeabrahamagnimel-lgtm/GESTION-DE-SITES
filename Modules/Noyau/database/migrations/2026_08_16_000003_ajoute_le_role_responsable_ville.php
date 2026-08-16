<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le responsable de ville et le responsable de site ne couvrent pas le même terrain :
 * le premier répond de toute sa ville — donc de chacun de ses lieux —, le second d'un
 * seul lieu. Jusqu'ici les deux partageaient le rôle `responsable_site`, leur périmètre
 * n'étant distingué que par la colonne (`villes.responsable_id` ou `sites.responsable_id`)
 * qui les désignait : lisible en base, mais invisible à l'écran et dans les habilitations.
 *
 * Le rôle `responsable_ville` est donc créé pour chaque entreprise, et les responsables
 * déjà nommés sur une ville y basculent.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('entreprises')->pluck('id') as $entrepriseId) {
            $existant = DB::table('roles')
                ->where('name', 'responsable_ville')->where('entreprise_id', $entrepriseId)->value('id');

            $roleVilleId = $existant ?: DB::table('roles')->insertGetId([
                'name' => 'responsable_ville',
                'guard_name' => 'web',
                'entreprise_id' => $entrepriseId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleSiteId = DB::table('roles')
                ->where('name', 'responsable_site')->where('entreprise_id', $entrepriseId)->value('id');

            if (! $roleSiteId) {
                continue;
            }

            // Un responsable désigné sur une ville entière relève désormais du rôle ville.
            $responsablesDeVille = DB::table('villes')
                ->where('entreprise_id', $entrepriseId)->whereNotNull('responsable_id')->pluck('responsable_id');

            foreach ($responsablesDeVille as $userId) {
                DB::table('model_has_roles')
                    ->where('role_id', $roleSiteId)
                    ->where('model_id', $userId)
                    ->where('entreprise_id', $entrepriseId)
                    ->update(['role_id' => $roleVilleId]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->where('name', 'responsable_ville')->get(['id', 'entreprise_id']) as $roleVille) {
            $roleSiteId = DB::table('roles')
                ->where('name', 'responsable_site')->where('entreprise_id', $roleVille->entreprise_id)->value('id');

            if ($roleSiteId) {
                DB::table('model_has_roles')->where('role_id', $roleVille->id)->update(['role_id' => $roleSiteId]);
            }

            DB::table('roles')->where('id', $roleVille->id)->delete();
        }
    }
};
