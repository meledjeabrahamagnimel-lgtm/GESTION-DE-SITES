<?php

namespace Modules\Noyau\Commun\Controleurs;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Noyau\Entreprises\Services\Annuaire;
use Modules\Noyau\Entreprises\Services\AnnuairePdf;

/**
 * Renvoie l'annuaire en PDF.
 *
 * Un téléchargement ne peut pas passer par Livewire — il faut une vraie réponse HTTP,
 * avec ses en-têtes. D'où cette route classique, dont le seul travail est de vérifier
 * qui demande, puis de laisser le service décider de ce qu'il a le droit de voir.
 *
 * Le paramètre `entreprise` n'est honoré que pour un Super Admin : pour tous les autres,
 * Annuaire l'ignore et s'en tient à leur propre entreprise. Un identifiant glissé dans
 * l'URL par un gérant curieux ne lui ouvre donc rien.
 */
class TelechargerAnnuaire
{
    public function __invoke(Request $request, AnnuairePdf $document): Response
    {
        $lecteur = $request->user();

        // Le middleware de route nomme déjà les rôles admis ; cette vérification-ci
        // tient au service, pas à la route, pour qu'un futur point d'entrée ne
        // puisse pas ouvrir l'annuaire à quelqu'un qui n'y a pas droit.
        abort_unless($lecteur && Annuaire::ouvertA($lecteur), 403);

        $entrepriseId = $request->integer('entreprise') ?: null;

        activity()
            ->causedBy($lecteur)
            ->withProperties(['entreprise' => $entrepriseId])
            ->log("Téléchargement de l'annuaire des accès");

        return response($document->pour($lecteur, $entrepriseId), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->nomDuFichier($lecteur).'"',
            // Un annuaire nominatif n'a rien à faire dans un cache partagé.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
