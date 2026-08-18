<?php

namespace Modules\Noyau\Entreprises\Services;

use App\Models\User;
use Modules\Noyau\Commun\Services\DocumentPdf;

/**
 * Met l'annuaire en page.
 *
 * Le document est fait pour être imprimé et posé sur un bureau : une entreprise par
 * bloc, une ville par section, et sur chaque ligne de quoi joindre la personne sans
 * rouvrir l'application. Le périmètre du lecteur est rappelé en sous-titre — un
 * superviseur qui imprime sa ville doit voir écrit que c'est sa ville, sinon il croira
 * l'entreprise réduite à ses dix noms.
 */
class AnnuairePdf
{
    /**
     * Largeurs de colonnes, en points, pour les 511 points utiles d'un A4 portrait.
     *
     * Le périmètre a été préféré au téléphone : à Abidjan, deux responsables de site
     * portent le même rôle dans la même ville, et sans cette colonne le document ne
     * dirait pas lequel tient quel lieu.
     */
    private const COLONNES = [
        'Rôle' => 100.0,
        'Nom et prénoms' => 112.0,
        'Adresse e-mail' => 142.0,
        'Périmètre' => 105.0,
        'Statut' => 52.0,
    ];

    public function pour(User $lecteur, ?int $entrepriseId = null): string
    {
        $blocs = Annuaire::pour($lecteur, $entrepriseId);

        $pdf = new DocumentPdf('Annuaire des accès');
        $pdf->titre('Annuaire des accès');
        $pdf->sousTitre($this->perimetre($lecteur, $blocs).' — établi le '.now()->format('d/m/Y à H\hi').' par '.$lecteur->name);

        if ($blocs === []) {
            $pdf->paragraphe("Aucun accès ne relève de votre périmètre pour l'instant.");

            return $pdf->rendu();
        }

        foreach ($blocs as $bloc) {
            $this->ecrireUneEntreprise($pdf, $bloc);
        }

        return $pdf->rendu();
    }

    /** Nom de fichier proposé au navigateur, sans espaces ni accents. */
    public function nomDuFichier(User $lecteur): string
    {
        $racine = $lecteur->hasRole('super_admin')
            ? 'annuaire-plateforme'
            : 'annuaire-'.str($lecteur->entreprise?->nom ?? 'entreprise')->slug();

        return $racine.'-'.now()->format('Y-m-d').'.pdf';
    }

    /**
     * @param  array{entreprise: \Modules\Noyau\Entreprises\Modeles\Entreprise, villes: array<string, array<int, array<string, string>>>, total: int}  $bloc
     */
    private function ecrireUneEntreprise(DocumentPdf $pdf, array $bloc): void
    {
        $pdf->section($bloc['entreprise']->nom.'  —  '.$bloc['total'].' accès');

        foreach ($bloc['villes'] as $ville => $lignes) {
            $pdf->paragraphe(
                $ville === Annuaire::HORS_VILLE
                    ? "Direction — périmètre : l'entreprise entière"
                    : $ville.' — '.count($lignes).' '.(count($lignes) > 1 ? 'personnes' : 'personne'),
                10.5,
            );

            $pdf->tableau(
                array_keys(self::COLONNES),
                array_values(self::COLONNES),
                array_map(fn (array $l) => [
                    $l['role'],
                    $l['nom'],
                    $l['email'],
                    $l['perimetre'],
                    $l['statut'],
                ], $lignes),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocs
     */
    private function perimetre(User $lecteur, array $blocs): string
    {
        if ($lecteur->hasRole('super_admin')) {
            return count($blocs) === 1
                ? 'Entreprise : '.$blocs[0]['entreprise']->nom
                : 'Toutes les entreprises de la plateforme';
        }

        if ($lecteur->hasRole('gerant')) {
            return 'Toute l\'entreprise';
        }

        $villes = collect($blocs)->flatMap(fn (array $bloc) => array_keys($bloc['villes']))->unique();

        return $villes->isEmpty() ? 'Votre périmètre' : 'Votre périmètre : '.$villes->implode(', ');
    }
}
