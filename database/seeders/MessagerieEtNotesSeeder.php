<?php

namespace Database\Seeders;

use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Shared\Models\DossierNote;
use App\Domain\Shared\Models\Note;
use App\Domain\Shared\Models\NotificationApp;
use App\Domain\Tenants\Models\Entreprise;
use App\Domain\Tenants\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peuple les fonctionnalités qui ne se déduisent pas des écritures d'exploitation :
 * messagerie, notifications, bloc-notes et un Super Admin secondaire d'exemple.
 * Sans ces données, ces pages s'affichent vides après l'installation de démonstration
 * alors que l'entreprise pilote est censée avoir déjà fonctionné plusieurs semaines.
 */
class MessagerieEtNotesSeeder extends Seeder
{
    public function run(): void
    {
        $entreprise = Entreprise::where('slug', 'artisan-automobile')->first();

        if (! $entreprise) {
            return;
        }

        // Réexécution sur une base déjà peuplée : on ne recrée rien.
        if (Conversation::where('entreprise_id', $entreprise->id)->exists()) {
            $this->command?->warn('Messagerie et notes déjà présentes — étape ignorée.');

            return;
        }

        $gerant = $this->utilisateursAyantLeRole('gerant', $entreprise->id)->first();
        $responsables = $this->utilisateursAyantLeRole('responsable_site', $entreprise->id);
        $commerciaux = $this->utilisateursAyantLeRole('commercial', $entreprise->id);
        $superAdmin = $this->utilisateursAyantLeRole('super_admin', 0)->first();

        if (! $gerant || $responsables->isEmpty() || $commerciaux->isEmpty()) {
            return;
        }

        // Comptes mis en avant partout ailleurs (identifiants de connexion communiqués) :
        // c'est avec eux que la démonstration doit avoir du contenu visible en premier.
        $commercialPrincipal = $this->parEmailOuPremier($commerciaux, 'k-aya@artisan-automobile.ci');
        $responsablePrincipal = $this->parEmailOuPremier($responsables, 'david.k@artisan-automobile.ci');

        // Le responsable RÉEL du commercial principal, via la fiche site — et non un
        // responsable choisi au hasard : K. Aya est rattachée à Abidjan — Site 1, pas
        // au site de David K. (Bouaké). S'adresser au mauvais responsable rendrait la
        // démonstration incohérente avec le circuit de validation déjà en place.
        $responsableDuCommercial = $this->responsableDuSite($commercialPrincipal, $responsables) ?? $responsablePrincipal;

        $this->genererMessagerie($entreprise->id, $gerant, $responsables, $commerciaux, $superAdmin, $responsablePrincipal, $commercialPrincipal, $responsableDuCommercial);
        $this->genererNotifications($entreprise->id, $gerant, $responsablePrincipal, $commercialPrincipal, $responsableDuCommercial);
        $this->genererNotes($entreprise->id, $commercialPrincipal);
        $this->genererSuperAdminSecondaire();

        $this->command?->info('Messagerie, notifications, notes et Super Admin secondaire installés.');
    }

    /**
     * Utilisateurs portant ce rôle dans cette équipe, table de liaison lue directement.
     *
     * La relation roles() de Spatie est filtrée sur l'équipe COURANTE (celle définie par
     * le dernier appel à setPermissionsTeamId) : selon l'ordre des seeders précédents,
     * elle peut ne rien renvoyer même quand la ligne existe. Lire model_has_roles évite
     * toute dépendance à cet état ambiant.
     *
     * @return Collection<int, User>
     */
    private function utilisateursAyantLeRole(string $role, int $equipe): Collection
    {
        $ids = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.entreprise_id', $equipe)
            ->where('roles.name', $role)
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * L'utilisateur portant cette adresse dans la collection, ou à défaut le premier.
     *
     * Sert à cibler délibérément les comptes de démonstration mis en avant partout
     * ailleurs (k-aya@…, david.k@…) plutôt que le premier de la liste par identifiant,
     * qui serait un compte encore « à nommer » sans lien avec ce que l'utilisateur
     * a l'habitude de tester.
     */
    private function parEmailOuPremier(Collection $utilisateurs, string $email): ?User
    {
        return $utilisateurs->firstWhere('email', $email) ?? $utilisateurs->first();
    }

    /** Responsable réellement en charge du site auquel ce commercial est rattaché. */
    private function responsableDuSite(?User $commercial, Collection $responsables): ?User
    {
        if (! $commercial) {
            return null;
        }

        $fiche = Commercial::where('user_id', $commercial->id)->first();
        $site = $fiche ? Site::find($fiche->site_id) : null;

        return $site?->responsable_id ? $responsables->firstWhere('id', $site->responsable_id) : null;
    }

    /**
     * Conversations couvrant chaque relation autorisée par AnnuaireMessagerie :
     * gérant ↔ responsables, gérant ↔ super admin, responsable ↔ commercial,
     * responsable ↔ responsable, commercial ↔ commercial.
     */
    private function genererMessagerie(int $entrepriseId, User $gerant, $responsables, $commerciaux, ?User $superAdmin, User $responsablePrincipal, User $commercialPrincipal, User $responsableDuCommercial): void
    {
        // Gérant → responsable de site. Fil ancien, entièrement lu par les deux parties.
        $this->conversation($entrepriseId, 'Objectifs du mois', $gerant, [$responsablePrincipal], [
            [$gerant, "Bonjour, où en est-on sur l'objectif du site ce mois-ci ?"],
            [$responsablePrincipal, 'Nous sommes à environ 80 % à dix jours de la fin, ça devrait passer.'],
            [$gerant, "Très bien, tenez-moi au courant s'il y a un point de blocage."],
        ], Carbon::now()->subDays(4));

        // Gérant → tout le personnel d'un site (diffusion). Encore non lue par les destinataires :
        // la cloche de chacun d'eux a donc quelque chose à afficher.
        $destinatairesDiffusion = $responsables->merge($commerciaux)->unique('id')->values()->all();
        $this->conversation($entrepriseId, 'Rappel : clôture de fin de mois', $gerant, $destinatairesDiffusion, [
            [$gerant, 'Merci de transmettre toutes vos saisies en attente avant vendredi soir pour la clôture.'],
        ], Carbon::now()->subDays(2), lu: false);

        // Responsable → gérant, sans réponse : illustre un message encore non lu chez le gérant.
        $autreResponsable = $responsables->firstWhere('id', '!=', $responsablePrincipal->id);
        if ($autreResponsable) {
            $this->conversation($entrepriseId, 'Demande de matériel', $autreResponsable, [$gerant], [
                [$autreResponsable, "Bonjour, l'atelier a besoin d'un renouvellement d'outillage. Je vous transmets le devis fournisseur cette semaine."],
            ], Carbon::now()->subHours(6), lu: false);
        }

        // Commercial → responsable RÉEL de son site (pas nécessairement le responsable
        // mis en avant plus haut, dont le site peut être différent).
        $this->conversation($entrepriseId, null, $commercialPrincipal, [$responsableDuCommercial], [
            [$commercialPrincipal, 'Bonjour, un client demande un devis urgent pour demain matin, possible ?'],
            [$responsableDuCommercial, 'Oui, transmettez-moi les informations, je le prépare ce soir.'],
        ], Carbon::now()->subDays(1));

        // Responsable ↔ responsable, entre pairs de la même entreprise.
        if ($autreResponsable) {
            $this->conversation($entrepriseId, 'Entraide inter-sites', $responsablePrincipal, [$autreResponsable], [
                [$responsablePrincipal, 'Un client de ton secteur me demande un rendez-vous, tu es disponible cette semaine ?'],
                [$autreResponsable, 'Oui, dis-lui de passer jeudi matin.'],
            ], Carbon::now()->subDays(3));
        }

        // Commercial ↔ commercial, entre pairs de la même entreprise. Non lue par le
        // commercial principal : la cloche du compte de démonstration a du contenu à afficher.
        $autreCommercial = $commerciaux->firstWhere('id', '!=', $commercialPrincipal->id);
        if ($autreCommercial) {
            $this->conversation($entrepriseId, null, $autreCommercial, [$commercialPrincipal], [
                [$autreCommercial, 'Tu as le contact du fournisseur de pare-brise ? Un client en cherche un.'],
            ], Carbon::now()->subHours(20), lu: false);
        }

        // Gérant ↔ Super Admin : seule liaison autorisée entre l'entreprise et la plateforme.
        if ($superAdmin) {
            $this->conversation(null, 'Question sur la facturation FNE', $gerant, [$superAdmin], [
                [$gerant, 'Bonjour, un point sur la génération des factures FNE : est-ce automatique ?'],
                [$superAdmin, 'Bonjour, oui, le type de facture est choisi à la saisie, aucune action supplémentaire nécessaire.'],
            ], Carbon::now()->subDays(6));
        }
    }

    /**
     * @param  array<int, User>  $destinataires
     * @param  array<int, array{0: User, 1: string}>  $messages  paires [expéditeur, texte]
     * @param  bool  $lu  faux pour laisser les destinataires (hors expéditeur du dernier
     *                    message) sur un fil non lu, afin que la cloche ait du contenu
     */
    private function conversation(?int $entrepriseId, ?string $sujet, User $createur, array $destinataires, array $messages, Carbon $debut, bool $lu = true): void
    {
        $conversation = Conversation::create([
            'entreprise_id' => $entrepriseId,
            'sujet' => $sujet,
            'cree_par' => $createur->id,
        ]);

        $participants = collect([$createur, ...$destinataires])->unique('id');
        $conversation->participants()->attach($participants->pluck('id'));

        $instant = $debut->copy();
        $dernier = null;

        foreach ($messages as [$expediteur, $texte]) {
            // created_at/updated_at ne figurent pas dans la liste #[Fillable] du modèle :
            // Message::create() les ignorerait (ou lèverait, hors production). On les pose
            // après coup via forceFill(), qui contourne volontairement cette protection.
            $dernier = Message::create([
                'conversation_id' => $conversation->id,
                'expediteur_id' => $expediteur->id,
                'corps' => $texte,
            ]);
            $dernier->forceFill(['created_at' => $instant, 'updated_at' => $instant])->save();

            $instant = $instant->copy()->addMinutes(random_int(5, 90));
        }

        $conversation->update(['dernier_message_le' => $dernier?->created_at ?? $debut]);

        foreach ($participants as $participant) {
            // L'auteur du dernier message est toujours à jour ; les autres ne le sont
            // que si le fil est marqué lu, pour laisser des non-lus dans la démonstration.
            $aJourDeToutesFacons = $dernier && $participant->id === $dernier->expediteur_id;

            $conversation->participants()->updateExistingPivot($participant->id, [
                'lu_le' => ($lu || $aJourDeToutesFacons) ? now() : null,
            ]);
        }
    }

    /** Alimente la cloche : quelques notifications lues, quelques-unes non lues. */
    private function genererNotifications(int $entrepriseId, User $gerant, User $responsablePrincipal, User $commercialPrincipal, User $responsableDuCommercial): void
    {
        $lignes = [
            // Gestion : transmission de prospections en attente d'arbitrage, adressée au
            // véritable responsable du commercial — pas à un responsable pris au hasard.
            [$responsableDuCommercial, NotificationApp::CANAL_GESTION, NotificationApp::NIVEAU_ALERTE,
                '3 prospection(s) à valider', $commercialPrincipal->name.' vient de transmettre 3 prospection(s).', route('saisie-du-jour'), null],
            [$commercialPrincipal, NotificationApp::CANAL_GESTION, NotificationApp::NIVEAU_SUCCES,
                'Prospection validée', 'Votre responsable a validé 2 prospection(s).', route('mes-prospections'), now()->subHours(3)],
            [$commercialPrincipal, NotificationApp::CANAL_GESTION, NotificationApp::NIVEAU_CRITIQUE,
                'Prospection refusée', 'Votre responsable a refusé une prospection. Motif : coordonnées client incomplètes.', route('mes-prospections'), now()->subDays(2)],

            // Système : rappels de gestion courante.
            [$gerant, NotificationApp::CANAL_SYSTEME, NotificationApp::NIVEAU_INFO,
                'Clôture mensuelle à venir', 'Pensez à valider toutes les saisies avant la fin du mois.', null, now()->subDay()],
            [$responsablePrincipal, NotificationApp::CANAL_SYSTEME, NotificationApp::NIVEAU_ALERTE,
                'Objectif du mois', "Le site est à 80 % de l'objectif à dix jours de l'échéance.", null, null],
        ];

        foreach ($lignes as [$destinataire, $canal, $niveau, $titre, $corps, $lien, $lu]) {
            NotificationApp::create([
                'user_id' => $destinataire->id,
                'canal' => $canal,
                'niveau' => $niveau,
                'titre' => $titre,
                'corps' => $corps,
                'lien' => $lien,
                'lu_le' => $lu,
            ]);
        }
    }

    /**
     * Quelques dossiers et notes pour le commercial de démonstration.
     *
     * Réservé au commercial : la page « Mes notes » n'est ouverte qu'au rôle
     * commercial (routes/web.php) — y seeder des notes pour un responsable
     * créerait des données que l'application ne permet à personne d'atteindre.
     */
    private function genererNotes(int $entrepriseId, User $commercial): void
    {
        $suivi = DossierNote::create(['entreprise_id' => $entrepriseId, 'user_id' => $commercial->id, 'nom' => 'Suivi clients', 'couleur' => '#2563EB']);
        $relances = DossierNote::create(['entreprise_id' => $entrepriseId, 'user_id' => $commercial->id, 'nom' => 'Relances', 'couleur' => '#D97706']);

        Note::create(['entreprise_id' => $entrepriseId, 'user_id' => $commercial->id, 'dossier_note_id' => $suivi->id, 'titre' => 'Garage Koffi — flotte véhicules', 'corps' => 'Intéressé par un contrat annuel entretien. Relancer après le devis sinistre.', 'est_epinglee' => true]);
        Note::create(['entreprise_id' => $entrepriseId, 'user_id' => $commercial->id, 'dossier_note_id' => $relances->id, 'titre' => 'Mme Traoré — devis en attente', 'corps' => 'Devis envoyé le 28, attend le retour assurance.', 'rappel_le' => now()->addDays(2)]);
        Note::create(['entreprise_id' => $entrepriseId, 'user_id' => $commercial->id, 'dossier_note_id' => null, 'titre' => 'Idée : carnet clients zone industrielle', 'corps' => 'Prospecter davantage la zone industrielle en début de semaine.']);
    }

    /** Un Super Admin secondaire d'exemple, créé par le fondateur avec des habilitations partielles. */
    private function genererSuperAdminSecondaire(): void
    {
        $fondateur = User::whereNull('entreprise_id')->where('est_fondateur', true)->first();

        if (! $fondateur || User::where('email', 'support@plateforme.local')->exists()) {
            return;
        }

        $secondaire = User::create([
            'entreprise_id' => null,
            'name' => 'Support Plateforme',
            'email' => 'support@plateforme.local',
            'password' => 'password',
            'email_verified_at' => now(),
            'cree_par_id' => $fondateur->id,
            'est_fondateur' => false,
            'doit_changer_mot_de_passe' => true,
            'habilitations' => ['entreprises', 'journal'],
        ]);

        // Les Super Admins n'appartiennent à aucune entreprise : équipe conventionnelle 0.
        // Sans cette remise à zéro, le rôle serait rattaché à la dernière équipe active
        // (celle de l'entreprise pilote), et ce compte ne serait jamais reconnu Super Admin.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $secondaire->assignRole('super_admin');
    }
}
