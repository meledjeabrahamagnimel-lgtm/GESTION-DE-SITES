<?php

namespace Modules\Noyau\Commun\Mails;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Courriel d'accueil envoyé au titulaire d'un accès nouvellement créé.
 *
 * Il ne transporte jamais le mot de passe : celui-ci est remis de vive voix ou par un
 * autre canal. Le courriel, lui, peut être transféré, archivé ou lu sur un poste
 * partagé — il se borne donc à annoncer l'ouverture du compte et à indiquer le lien.
 */
class BienvenueNouvelAcces extends Mailable
{
    use Queueable, SerializesModels;

    /** Au-delà, mieux vaut réouvrir un accès que laisser un lien dormir dans une boîte. */
    private const VALIDITE_DU_LIEN_EN_JOURS = 7;

    /**
     * L'entreprise est passée explicitement plutôt que lue depuis l'utilisateur : le
     * scope global la masquerait dès que celui qui crée l'accès n'appartient pas à la
     * même entreprise — exactement le cas du super administrateur.
     */
    public function __construct(
        public User $utilisateur,
        public Entreprise $entreprise,
        public string $roleLisible,
        public ?string $perimetre = null,
        /**
         * Vrai quand le message est renvoyé à la demande d'un administrateur, le premier
         * n'étant jamais arrivé. Le contenu ne change pas ; le ton, si — souhaiter la
         * bienvenue à quelqu'un qui travaille depuis trois semaines le ferait douter de
         * l'application avant même d'avoir lu la suite.
         */
        public bool $renvoi = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match (true) {
                ! $this->renvoi => "Bienvenue sur L'Artisan — votre accès est ouvert",
                $this->definirLeMotDePasse() => "Votre accès L'Artisan — choisissez votre mot de passe",
                default => "Votre accès L'Artisan — rappel de connexion",
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'noyau::emails.bienvenue',
            with: [
                'nom' => $this->utilisateur->name,
                'lienConnexion' => $this->lienPremiereConnexion(),
                'cabinet' => config('cabinet'),
                // Le logo est joint au message : une image appelée par URL reste blanche
                // dans la plupart des messageries, qui bloquent les contenus distants.
                'logo' => $this->entreprise->logoChemin(),
                'renvoi' => $this->renvoi,
                // Détermine tout le corps du message : « choisissez votre mot de passe »
                // n'a aucun sens pour quelqu'un qui en a déjà un et s'en sert.
                'definirLeMotDePasse' => $this->definirLeMotDePasse(),
            ],
        );
    }

    /** Vrai si le lien mène à l'écran de choix du mot de passe, faux s'il mène à la connexion. */
    private function definirLeMotDePasse(): bool
    {
        return (bool) $this->utilisateur->doit_changer_mot_de_passe;
    }

    /**
     * Le lien du courriel ne mène pas à la connexion mais à l'écran où l'on choisit
     * son mot de passe : le titulaire n'en a pas encore, lui en demander un pour
     * entrer n'aurait pas de sens.
     *
     * L'adresse est signée avec la clé de l'application — infalsifiable, on ne peut
     * pas y glisser l'identifiant d'un autre compte — et périme au bout d'une
     * semaine. Un accès qui n'a pas servi en sept jours mérite qu'on le renouvelle
     * plutôt qu'il traîne, ouvert, dans une boîte aux lettres.
     */
    private function lienPremiereConnexion(): string
    {
        // Un compte dont le mot de passe est déjà choisi n'a plus rien à définir :
        // on le renvoie simplement à la connexion.
        if (! $this->utilisateur->doit_changer_mot_de_passe) {
            return route('connexion');
        }

        return URL::temporarySignedRoute(
            'mot-de-passe.definir',
            now()->addDays(self::VALIDITE_DU_LIEN_EN_JOURS),
            ['utilisateur' => $this->utilisateur->id],
        );
    }
}
