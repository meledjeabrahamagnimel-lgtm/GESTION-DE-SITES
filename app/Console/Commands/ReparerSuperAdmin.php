<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Diagnostique — et répare — un compte super administrateur qui reçoit 403.
 *
 * Trois états distincts produisent la même page « Cette section ne vous est pas
 * ouverte », et rien à l'écran ne dit lequel :
 *
 *   1. le rôle super_admin n'existe pas dans l'équipe plateforme (0) ;
 *   2. le compte ne le porte pas, ou le porte dans la mauvaise équipe ;
 *   3. le compte le porte, mais n'est ni fondateur ni habilité — il franchit alors
 *      le contrôle de rôle et bute sur le contrôle de section.
 *
 * La commande affiche l'état réel avant d'agir, corrige les trois, puis vérifie le
 * résultat en relisant les sections ouvertes. On ne répare pas à l'aveugle un accès
 * d'administration : on montre ce qui n'allait pas.
 */
class ReparerSuperAdmin extends Command
{
    protected $signature = 'superadmin:reparer
                            {email=it@dcknowing.com : adresse du compte à remettre d\'aplomb}
                            {--diagnostic : se contenter d\'afficher l\'état, sans rien modifier}';

    protected $description = "Diagnostique et répare un accès super administrateur qui répond 403";

    public function handle(): int
    {
        $email = $this->argument('email');

        $utilisateur = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $utilisateur) {
            $this->error("Aucun compte à l'adresse $email.");
            $this->ligne('Comptes hors entreprise connus', User::withoutGlobalScopes()
                ->whereNull('entreprise_id')->pluck('email')->implode(', ') ?: 'aucun');

            return self::FAILURE;
        }

        // Les rôles sont lus directement en base : getRoleNames() filtre sur l'équipe
        // posée dans la requête, et hors requête HTTP il n'y en a aucune.
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $utilisateur->getMorphClass())
            ->where('model_has_roles.model_id', $utilisateur->id)
            ->select('roles.name', 'roles.entreprise_id')
            ->get();

        $this->info("État de $email");
        $this->ligne('entreprise_id', $utilisateur->entreprise_id ?? 'null (attendu pour la plateforme)');
        $this->ligne('est_actif', $utilisateur->est_actif ? 'oui' : 'NON — la connexion est refusée');
        $this->ligne('est_fondateur', $utilisateur->est_fondateur ? 'oui' : 'NON');
        $this->ligne('habilitations', json_encode($utilisateur->habilitations) ?: 'null');
        $this->ligne('rôles en base', $roles->map(fn ($r) => $r->name.' (équipe '.($r->entreprise_id ?? 'null').')')->implode(', ') ?: 'AUCUN');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);
        $this->ligne('sections ouvertes', json_encode($utilisateur->sectionsAutorisees()));

        if ($this->option('diagnostic')) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Remise d\'aplomb');

        // 1. Le rôle doit exister dans l'équipe conventionnelle 0 : il n'appartient à
        //    aucune entreprise, la plateforme n'en étant pas une.
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
        ]);
        $this->ligne('rôle plateforme', $role->wasRecentlyCreated ? 'créé' : 'déjà présent');

        // 2. Un super admin rattaché à une entreprise verrait son équipe basculer sur
        //    elle, et le rôle — posé dans l'équipe 0 — deviendrait introuvable.
        if ($utilisateur->entreprise_id !== null) {
            $utilisateur->forceFill(['entreprise_id' => null])->save();
            $this->ligne('rattachement', 'détaché de son entreprise');
        }

        // 3. Le lien vers le rôle, posé sans passer par Spatie pour ne dépendre
        //    d'aucune équipe courante.
        $deja = DB::table('model_has_roles')
            ->where('model_type', $utilisateur->getMorphClass())
            ->where('model_id', $utilisateur->id)
            ->where('role_id', $role->id)
            ->exists();

        if (! $deja) {
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => $utilisateur->getMorphClass(),
                'model_id' => $utilisateur->id,
                'entreprise_id' => SuperAdminSeeder::EQUIPE_PLATEFORME,
            ]);
        }
        $this->ligne('rôle attribué', $deja ? 'déjà attribué' : 'attribué');

        // 4. Le drapeau fondateur : c'est lui qui ouvre toutes les sections. Sans lui
        //    et sans habilitation, le compte traverse le contrôle de rôle puis se
        //    heurte au contrôle de section, page après page.
        $utilisateur->forceFill([
            'est_fondateur' => true,
            'est_actif' => true,
        ])->save();
        $this->ligne('fondateur', 'oui');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(SuperAdminSeeder::EQUIPE_PLATEFORME);

        $utilisateur = $utilisateur->fresh();
        $sections = $utilisateur->sectionsAutorisees();

        $this->newLine();

        if (empty($sections)) {
            $this->error('Toujours aucune section ouverte. Videz les caches : php artisan optimize:clear');

            return self::FAILURE;
        }

        $this->info('Sections désormais ouvertes : '.implode(', ', $sections));
        $this->line('  Videz les caches, puis reconnectez-vous : php artisan optimize:clear');

        return self::SUCCESS;
    }

    private function ligne(string $libelle, string $valeur): void
    {
        $this->line('  '.str_pad($libelle, 22).$valeur);
    }
}
