<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Noyau\Entreprises\Actions\SupprimerAcces;

/**
 * Fait le ménage parmi les comptes de la plateforme — ceux qui n'appartiennent à
 * aucune entreprise.
 *
 * Une installation qui a vécu en accumule : le compte de démonstration du premier jour,
 * l'administrateur secondaire d'un essai, celui créé pour de bon ensuite. Tous portent
 * le rôle `super_admin`, c'est-à-dire la totalité des droits sur la totalité des
 * entreprises. Un compte oublié dont personne ne connaît plus le mot de passe reste une
 * porte ouverte : on ne la referme pas en l'ignorant.
 *
 * La commande est écrite pour une base qui porte des données réelles, d'où sa forme :
 *
 * 1. **Elle montre d'abord.** Sans option, elle ne fait qu'inventorier : pour chaque
 *    compte, ce qu'il porte — écritures métier, accès ouverts, désignations, connexions.
 *    On ne supprime pas un administrateur sur la foi de son adresse.
 *
 * 2. **Elle n'écrit qu'avec `--confirmer`.** Le déroulé complet est visible avant, et
 *    identique après : ce que la simulation annonce est ce qui sera fait.
 *
 * 3. **Elle refuse de laisser la plateforme sans administrateur.** Le compte gardé est
 *    vérifié — existant, porteur du rôle, hors entreprise — avant que quoi que ce soit
 *    ne soit effacé.
 *
 * Ce que la suppression ne détruit pas : les écritures. Une facture saisie par un compte
 * effacé reste une facture ; son `cree_par` retombe à null, mais le `code_auteur` inscrit
 * à côté garde la trace de qui l'a tapée. Le journal d'activité survit lui aussi —
 * supprimer quelqu'un ne doit pas effacer la preuve de ce qu'il a fait.
 */
class MenageSuperAdmin extends Command
{
    protected $signature = 'superadmin:menage
                            {--garder= : adresse du compte de plateforme à conserver}
                            {--supprimer=* : adresse(s) à supprimer, répétable}
                            {--confirmer : écrire réellement ; sans ce drapeau, rien n\'est modifié}';

    protected $description = 'Inventorie les comptes de la plateforme et supprime ceux qui ne servent plus';

    public function handle(SupprimerAcces $suppression): int
    {
        $comptes = User::withoutGlobalScopes()
            ->whereNull('entreprise_id')
            ->orderBy('id')
            ->get();

        if ($comptes->isEmpty()) {
            $this->error('Aucun compte hors entreprise : rien à inventorier.');

            return self::FAILURE;
        }

        $this->inventaire($comptes);

        $garder = trim((string) $this->option('garder'));
        $aSupprimer = array_filter(array_map('trim', (array) $this->option('supprimer')));

        if ($garder === '' && $aSupprimer === []) {
            $this->newLine();
            $this->line('Inventaire seul : rien n\'a été modifié.');
            $this->line('  Pour agir : --garder=<adresse> --supprimer=<adresse> [--supprimer=…]');
            $this->line('  Ajouter --confirmer une fois le déroulé vérifié.');

            return self::SUCCESS;
        }

        $conserve = $this->comptePreserve($comptes, $garder);

        if (! $conserve) {
            return self::FAILURE;
        }

        $cibles = $this->ciblesValides($comptes, $aSupprimer, $conserve);

        if ($cibles === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Déroulé prévu');
        $this->line("  conservé   {$conserve->email} (#{$conserve->id}) — devient l'unique compte fondateur");

        foreach ($cibles as $cible) {
            $this->line("  supprimé   {$cible->email} (#{$cible->id}) — {$cible->name}");
        }

        $autresFondateurs = $comptes
            ->where('est_fondateur', true)
            ->where('id', '!=', $conserve->id)
            ->reject(fn (User $c) => $cibles->contains('id', $c->id));

        foreach ($autresFondateurs as $compte) {
            $this->line("  rétrogradé {$compte->email} (#{$compte->id}) — perd le statut de fondateur, garde son accès");
        }

        if (! $this->option('confirmer')) {
            $this->newLine();
            $this->warn('Simulation : rien n\'a été écrit. Relancer la même ligne avec --confirmer.');

            return self::SUCCESS;
        }

        $this->executer($suppression, $conserve, $cibles, $autresFondateurs);

        $this->newLine();
        $this->info('Ménage terminé.');
        $this->line('  Videz les caches : php artisan optimize:clear');

        return self::SUCCESS;
    }

    /**
     * Ce que chaque compte porte, avant toute décision.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $comptes
     */
    private function inventaire($comptes): void
    {
        $this->newLine();
        $this->info('Comptes de la plateforme (hors entreprise)');

        foreach ($comptes as $compte) {
            $this->newLine();
            $this->line("  #{$compte->id}  {$compte->email}");
            $this->detail('nom', $compte->name);
            $this->detail('rôles en base', $this->rolesDe($compte) ?: 'AUCUN — ce compte ne peut rien faire');
            $this->detail('fondateur', $compte->est_fondateur ? 'oui' : 'non');
            $this->detail('actif', $compte->est_actif ? 'oui' : 'non');
            $this->detail('dernière connexion', $this->derniereConnexion($compte));

            foreach ($this->cequIlPorte($compte) as $libelle => $nombre) {
                $this->detail($libelle, (string) $nombre);
            }
        }
    }

    /**
     * Le poids d'un compte, en nombre de lignes qui le désignent.
     *
     * Aucun de ces chiffres n'empêche la suppression — tout est prévu pour y survivre.
     * Ils sont là pour qu'on sache ce qu'on efface : un compte à zéro partout est un
     * résidu d'installation, un compte qui a saisi trois cents factures est quelqu'un.
     *
     * @return array<string, int>
     */
    private function cequIlPorte(User $compte): array
    {
        $porte = [
            'accès qu\'il a ouverts' => DB::table('users')->where('cree_par_id', $compte->id)->count(),
            'entrées au journal' => $this->compter('activity_log', 'causer_id', $compte->id),
            'connexions tracées' => $this->compter('sessions_utilisateur', 'user_id', $compte->id),
        ];

        $ecritures = 0;

        foreach (['prospections', 'devis', 'factures', 'encaissements', 'charges'] as $table) {
            $ecritures += $this->compter($table, 'cree_par', $compte->id);
        }

        $porte['écritures métier saisies'] = $ecritures;

        return $porte;
    }

    /**
     * Un comptage qui ne suppose pas que la table existe.
     *
     * `sessions_utilisateur` arrive avec la traçabilité : sur un serveur où la migration
     * n'est pas encore passée, un inventaire qui tomberait en erreur empêcherait
     * justement de faire le ménage avant de migrer.
     */
    private function compter(string $table, string $colonne, int $id): int
    {
        try {
            return DB::table($table)->where($colonne, $id)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function derniereConnexion(User $compte): string
    {
        try {
            $vue = DB::table('sessions_utilisateur')
                ->where('user_id', $compte->id)
                ->max('derniere_activite_le');
        } catch (\Throwable) {
            return 'inconnue (traçabilité pas encore installée)';
        }

        return $vue ? (string) $vue : 'jamais vu se connecter';
    }

    /**
     * Le compte à conserver, vérifié plutôt que supposé.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $comptes
     */
    private function comptePreserve($comptes, string $garder): ?User
    {
        if ($garder === '') {
            $this->newLine();
            $this->error('Préciser --garder=<adresse> : la commande ne choisit pas à votre place quel administrateur survit.');

            return null;
        }

        $conserve = $comptes->firstWhere('email', $garder);

        if (! $conserve) {
            $this->newLine();
            $this->error("Aucun compte de plateforme à l'adresse « $garder ».");
            $this->line('  Les adresses connues sont listées ci-dessus.');

            return null;
        }

        if ($this->rolesDe($conserve) === '') {
            $this->newLine();
            $this->error("Le compte « $garder » ne porte aucun rôle : le garder seul fermerait la plateforme à tout le monde.");
            $this->line('  Le remettre d\'aplomb d\'abord : php artisan superadmin:reparer '.$garder);

            return null;
        }

        return $conserve;
    }

    /**
     * Les comptes réellement supprimables, ou null si l'un d'eux ne l'est pas.
     *
     * On s'arrête au premier refus sans rien effacer : une suppression partielle
     * laisserait la plateforme dans un état que personne n'a demandé, et qu'il faudrait
     * deviner pour le rattraper.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $comptes
     * @param  array<int, string>  $adresses
     * @return \Illuminate\Support\Collection<int, User>|null
     */
    private function ciblesValides($comptes, array $adresses, User $conserve)
    {
        $cibles = collect();

        foreach ($adresses as $adresse) {
            if ($adresse === $conserve->email) {
                $this->newLine();
                $this->error("« $adresse » est à la fois gardé et supprimé : décidez.");

                return null;
            }

            $cible = $comptes->firstWhere('email', $adresse);

            if (! $cible) {
                $this->newLine();
                $this->error("Aucun compte de plateforme à l'adresse « $adresse ».");
                $this->line('  Cette commande ne touche que les comptes hors entreprise.');
                $this->line('  Un accès rattaché à une entreprise se supprime depuis l\'écran Accès, qui vérifie la hiérarchie.');

                return null;
            }

            $cibles->push($cible);
        }

        if ($cibles->isEmpty()) {
            $this->newLine();
            $this->warn('Aucune adresse en --supprimer : seul le statut de fondateur sera ajusté.');
        }

        return $cibles;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $cibles
     * @param  \Illuminate\Support\Collection<int, User>  $autresFondateurs
     */
    private function executer(SupprimerAcces $suppression, User $conserve, $cibles, $autresFondateurs): void
    {
        $this->newLine();

        foreach ($cibles as $cible) {
            // La trace est écrite avant la suppression : après, la ligne du compte
            // n'existe plus, et une preuve qui disparaît avec son sujet ne prouve rien.
            activity()
                ->withProperties([
                    'nom' => $cible->name,
                    'email' => $cible->email,
                    'porte' => $this->cequIlPorte($cible),
                    'origine' => 'console',
                ])
                ->log("Ménage de la plateforme : suppression du compte {$cible->name} ({$cible->email})");

            $bilan = $suppression->effacer($cible);

            $this->line("  supprimé : {$cible->email} — fiche commerciale : {$bilan['fiche commerciale']}");
        }

        // Le statut de fondateur en dernier : tant que la suppression peut échouer, mieux
        // vaut qu'aucun compte n'ait été rétrogradé pour rien.
        DB::transaction(function () use ($conserve, $autresFondateurs) {
            foreach ($autresFondateurs as $compte) {
                $compte->forceFill(['est_fondateur' => false])->save();
            }

            if (! $conserve->est_fondateur) {
                $conserve->forceFill(['est_fondateur' => true])->save();
            }
        });

        $this->line("  fondateur unique : {$conserve->email}");
    }

    /**
     * Les rôles lus en base, et non par hasRole().
     *
     * Spatie filtre sur l'équipe posée dans la requête courante ; en console il n'y en a
     * aucune, et hasRole() répondrait « non » pour un compte qui porte pourtant le rôle.
     * Un inventaire qui affiche « aucun rôle » partout ne sert à rien.
     */
    private function rolesDe(User $compte): string
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $compte->getMorphClass())
            ->where('model_has_roles.model_id', $compte->id)
            ->pluck('roles.name')
            ->implode(', ');
    }

    /**
     * str_pad() compte des octets : « écritures métier saisies » en fait vingt-huit pour
     * vingt-quatre caractères, et la colonne des valeurs se décale jusqu'à coller au
     * libellé. On complète donc d'après la longueur affichée.
     */
    private function detail(string $libelle, string $valeur): void
    {
        $largeur = 28;
        $espaces = max(1, $largeur - mb_strlen($libelle));

        $this->line('      '.$libelle.str_repeat(' ', $espaces).$valeur);
    }
}
