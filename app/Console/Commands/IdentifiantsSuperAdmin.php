<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Change l'adresse et le mot de passe d'un compte super administrateur.
 *
 * Écrite pour être lancée sur un serveur qui porte des données réelles. Trois principes
 * en découlent, et expliquent la longueur du fichier :
 *
 * 1. **Une seule ligne est touchée**, celle du compte visé, et seulement les colonnes
 *    nommées. Aucune migration, aucune purge, aucun seeder. Une commande d'identifiants
 *    qui toucherait autre chose n'aurait pas sa place près d'une base de production.
 *
 * 2. **On montre avant d'agir.** L'état actuel est affiché, la cible est nommée, et
 *    `--simulation` permet de tout dérouler sans rien écrire. Sur un serveur distant, on
 *    ne devine pas sur quel compte on est en train de taper.
 *
 * 3. **On refuse plutôt que d'écraser.** Si l'adresse demandée appartient déjà à
 *    quelqu'un d'autre, la commande s'arrête : deux comptes ne peuvent pas porter la
 *    même adresse, et deviner lequel garder n'est pas le rôle d'un script.
 *
 * Le mot de passe n'est jamais réaffiché ni journalisé : il est transformé en empreinte
 * et perdu. Le journal garde qu'un changement a eu lieu, quand, et sur quel compte.
 */
class IdentifiantsSuperAdmin extends Command
{
    protected $signature = 'superadmin:identifiants
                            {--compte= : adresse actuelle du compte à mettre à jour}
                            {--email= : nouvelle adresse de connexion}
                            {--mot-de-passe= : nouveau mot de passe}
                            {--nom= : nom affiché, si on veut le changer aussi}
                            {--simulation : dérouler sans rien écrire}';

    protected $description = "Met à jour l'adresse et le mot de passe d'un super administrateur";

    /** En dessous, un mot de passe d'administration de plateforme n'a pas de sens. */
    private const LONGUEUR_MINIMALE = 10;

    public function handle(): int
    {
        $cible = $this->trouverLeCompte();

        if (! $cible) {
            return self::FAILURE;
        }

        $email = trim((string) $this->option('email')) ?: null;
        $motDePasse = (string) $this->option('mot-de-passe');
        $nom = trim((string) $this->option('nom')) ?: null;

        $this->afficherLEtat($cible);

        if ($email === null && $motDePasse === '' && $nom === null) {
            $this->warn('Rien à changer : préciser --email, --mot-de-passe ou --nom.');

            return self::INVALID;
        }

        if ($email !== null && ! $this->adresseAcceptable($email, $cible)) {
            return self::FAILURE;
        }

        if ($motDePasse !== '' && ! $this->motDePasseAcceptable($motDePasse)) {
            return self::FAILURE;
        }

        $changements = array_filter([
            'name' => $nom,
            'email' => $email,
        ]);

        if ($motDePasse !== '') {
            $changements['password'] = Hash::make($motDePasse);
            // Le mot de passe est choisi en connaissance de cause : on ne demande pas à
            // l'administrateur d'en inventer un autre à la connexion suivante.
            $changements['doit_changer_mot_de_passe'] = false;
            // Un cookie « se souvenir de moi » resté sur un poste continuerait d'ouvrir
            // la session avec l'ancien mot de passe. Le jeton est donc renouvelé : c'est
            // la seule façon de rendre effectif un changement de mot de passe.
            $changements['remember_token'] = Str::random(60);
        }

        $this->newLine();
        $this->info('Changements demandés');
        $this->ligne('adresse', $email ?? '(inchangée)');
        $this->ligne('nom', $nom ?? '(inchangé)');
        $this->ligne('mot de passe', $motDePasse !== '' ? 'remplacé ('.strlen($motDePasse).' caractères)' : '(inchangé)');

        if ($this->option('simulation')) {
            $this->newLine();
            $this->warn('Simulation : rien n\'a été écrit.');

            return self::SUCCESS;
        }

        $ancienEmail = $cible->email;

        DB::transaction(function () use ($cible, $changements) {
            // forceFill : `password` et `remember_token` ne sont pas assignables en masse,
            // et c'est très bien ainsi partout ailleurs.
            $cible->forceFill($changements)->save();
        });

        // Le journal retient le geste, jamais le secret.
        activity()
            ->performedOn($cible)
            ->withProperties([
                'ancien_email' => $ancienEmail,
                'nouvel_email' => $cible->email,
                'mot_de_passe_change' => isset($changements['password']),
                'origine' => 'console',
            ])
            ->log("Identifiants du super administrateur mis à jour");

        $this->newLine();
        $this->info("Compte mis à jour : {$cible->email}");
        $this->line('  Videz les caches, puis reconnectez-vous : php artisan optimize:clear');

        if (isset($changements['password'])) {
            $this->line('  Les sessions « se souvenir de moi » ouvertes ailleurs sont désormais caduques.');
        }

        return self::SUCCESS;
    }

    /**
     * Le compte visé. Sans `--compte`, la commande ne devine que s'il n'y a aucune
     * ambiguïté : un seul fondateur. Avec plusieurs, elle les liste et s'arrête —
     * choisir à la place de l'opérateur serait exactement l'erreur à ne pas commettre.
     */
    private function trouverLeCompte(): ?User
    {
        $indication = trim((string) $this->option('compte'));

        if ($indication !== '') {
            $compte = User::withoutGlobalScopes()->where('email', $indication)->first();

            if (! $compte) {
                $this->error("Aucun compte à l'adresse « $indication ».");
                $this->listerLesAdministrateurs();

                return null;
            }

            if (! $this->estAdministrateurPlateforme($compte)) {
                $this->error("Le compte « $indication » n'est pas un super administrateur.");
                $this->line('  Cette commande ne sert qu\'aux comptes de la plateforme.');

                return null;
            }

            return $compte;
        }

        $fondateurs = User::withoutGlobalScopes()->where('est_fondateur', true)->get();

        if ($fondateurs->count() === 1) {
            return $fondateurs->first();
        }

        $this->error($fondateurs->isEmpty()
            ? 'Aucun compte fondateur trouvé : préciser --compte=adresse.'
            : 'Plusieurs comptes fondateurs : préciser --compte=adresse.');
        $this->listerLesAdministrateurs();

        return null;
    }

    /**
     * Le rôle est lu en base, et non par hasRole().
     *
     * Spatie filtre les rôles sur l'équipe posée dans la requête courante ; en console,
     * il n'y en a aucune. `hasRole('super_admin')` y répond donc « non » pour un compte
     * qui porte pourtant le rôle — et la commande refuserait de s'exécuter sur le seul
     * compte pour lequel elle est faite.
     */
    private function estAdministrateurPlateforme(User $compte): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $compte->getMorphClass())
            ->where('model_has_roles.model_id', $compte->id)
            ->where('roles.name', 'super_admin')
            ->exists();
    }

    private function adresseAcceptable(string $email, User $cible): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("« $email » n'est pas une adresse de courriel valide.");
            $this->line('  Une adresse comporte un @ : « it.dcknowing@gmail.com », pas « it.dcknowing.gmail.com ».');

            return false;
        }

        $occupant = User::withoutGlobalScopes()->where('email', $email)->first();

        if ($occupant && $occupant->id !== $cible->id) {
            $this->error("L'adresse « $email » appartient déjà au compte « {$occupant->name} » (#{$occupant->id}).");
            $this->line('  Rien n\'a été modifié : deux comptes ne peuvent pas partager une adresse.');

            return false;
        }

        return true;
    }

    private function motDePasseAcceptable(string $motDePasse): bool
    {
        if (strlen($motDePasse) < self::LONGUEUR_MINIMALE) {
            $this->error('Mot de passe trop court : '.self::LONGUEUR_MINIMALE.' caractères au minimum.');

            return false;
        }

        if (! preg_match('/[a-zA-Z]/', $motDePasse) || ! preg_match('/\d/', $motDePasse)) {
            $this->error('Le mot de passe doit comporter au moins une lettre et un chiffre.');

            return false;
        }

        return true;
    }

    private function afficherLEtat(User $compte): void
    {
        $this->newLine();
        $this->info('Compte visé');
        $this->ligne('nom', $compte->name);
        $this->ligne('adresse actuelle', $compte->email);
        $this->ligne('fondateur', $compte->est_fondateur ? 'oui' : 'non');
        $this->ligne('actif', $compte->est_actif ? 'oui' : 'NON — la connexion est refusée');
    }

    private function listerLesAdministrateurs(): void
    {
        $connus = User::withoutGlobalScopes()->whereNull('entreprise_id')->pluck('email');

        $this->line('  Comptes hors entreprise connus : '.($connus->implode(', ') ?: 'aucun'));
    }

    private function ligne(string $libelle, string $valeur): void
    {
        $this->line('  '.str_pad($libelle, 20).$valeur);
    }
}
