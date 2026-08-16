<?php

namespace Modules\Noyau\Commun\Mails;

use Modules\Noyau\Entreprises\Modeles\Entreprise;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bienvenue sur L'Artisan — votre accès est ouvert",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'noyau::emails.bienvenue',
            with: [
                'nom' => $this->utilisateur->name,
                'lienConnexion' => route('connexion'),
                'cabinet' => config('cabinet'),
                // Le logo est joint au message : une image appelée par URL reste blanche
                // dans la plupart des messageries, qui bloquent les contenus distants.
                'logo' => $this->entreprise->logoChemin(),
            ],
        );
    }
}
