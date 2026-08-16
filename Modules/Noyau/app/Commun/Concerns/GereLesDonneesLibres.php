<?php

namespace Modules\Noyau\Commun\Concerns;

use Modules\Noyau\Commun\Modeles\DonneeLibre;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajoute à un composant Volt la saisie libre : un couple intitulé / valeur
 * que l'utilisateur rattache à une écriture quand aucune colonne ne convient.
 */
trait GereLesDonneesLibres
{
    public ?string $libreSujetType = null;

    public ?int $libreSujetId = null;

    public string $libreIntitule = '';

    public string $libreValeur = '';

    /** Ouvre le formulaire de saisie libre pour une écriture donnée. */
    public function ouvrirSaisieLibre(string $type, int $id): void
    {
        $this->libreSujetType = $type;
        $this->libreSujetId = $id;
        $this->libreIntitule = '';
        $this->libreValeur = '';
    }

    public function fermerSaisieLibre(): void
    {
        $this->reset(['libreSujetType', 'libreSujetId', 'libreIntitule', 'libreValeur']);
    }

    public function enregistrerSaisieLibre(): void
    {
        $this->validate([
            'libreIntitule' => ['required', 'string', 'max:120'],
            'libreValeur' => ['nullable', 'string', 'max:2000'],
        ], [], ['libreIntitule' => 'intitulé', 'libreValeur' => 'valeur']);

        $modele = $this->libreSujetType;

        // On ne fait pas confiance au type envoyé par le navigateur : il doit désigner
        // un modèle de l'application, et l'écriture doit appartenir à l'entreprise.
        if (! is_subclass_of($modele, Model::class) || ! str_starts_with($modele, 'App\\Domain\\')) {
            $this->fermerSaisieLibre();

            return;
        }

        $sujet = $modele::query()->find($this->libreSujetId);

        if (! $sujet || $sujet->entreprise_id !== auth()->user()->entreprise_id) {
            $this->fermerSaisieLibre();

            return;
        }

        DonneeLibre::create([
            'entreprise_id' => auth()->user()->entreprise_id,
            'sujet_type' => $modele,
            'sujet_id' => $sujet->id,
            'intitule' => $this->libreIntitule,
            'valeur' => $this->libreValeur ?: null,
            'cree_par' => auth()->id(),
        ]);

        $this->fermerSaisieLibre();
    }

    public function supprimerSaisieLibre(int $id): void
    {
        DonneeLibre::where('id', $id)->delete();
    }
}
