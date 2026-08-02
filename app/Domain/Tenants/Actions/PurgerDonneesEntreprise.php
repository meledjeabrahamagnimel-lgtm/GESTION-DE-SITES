<?php

namespace App\Domain\Tenants\Actions;

use App\Domain\Operations\Models\Charge;
use App\Domain\Operations\Models\Commercial;
use App\Domain\Operations\Models\CompteurDocument;
use App\Domain\Operations\Models\Devis;
use App\Domain\Operations\Models\Encaissement;
use App\Domain\Operations\Models\Facture;
use App\Domain\Operations\Models\Prospection;
use App\Domain\Operations\Models\SaisieJournaliere;
use App\Domain\Tenants\Models\Entreprise;
use Illuminate\Support\Facades\DB;

/**
 * Purge des données d'exploitation d'une entreprise (jeux de test).
 * Réservé au Super Admin. Les comptes, les sites et la fiche entreprise sont conservés :
 * seules les écritures et les compteurs de numérotation sont remis à zéro.
 */
class PurgerDonneesEntreprise
{
    /** @return array<string,int> nombre de lignes supprimées par table */
    public function executer(Entreprise $entreprise, bool $purgerCommerciaux = false): array
    {
        return DB::transaction(function () use ($entreprise, $purgerCommerciaux) {
            $id = $entreprise->id;
            $compte = [];

            // L'ordre suit les dépendances : encaissements -> factures -> devis -> prospections.
            $compte['encaissements'] = Encaissement::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['factures'] = Facture::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['devis'] = Devis::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['prospections'] = Prospection::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['charges'] = Charge::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            $compte['saisies_journalieres'] = SaisieJournaliere::withoutGlobalScopes()->where('entreprise_id', $id)->delete();

            if ($purgerCommerciaux) {
                $compte['commerciaux'] = Commercial::withoutGlobalScopes()->where('entreprise_id', $id)->delete();
            }

            // Remise à zéro de la numérotation pour repartir sur P-0001, D-0001, F-0001.
            $compte['compteurs'] = CompteurDocument::withoutGlobalScopes()
                ->where('entreprise_id', $id)
                ->when(! $purgerCommerciaux, fn ($q) => $q->where('type', '!=', 'com'))
                ->delete();

            return $compte;
        });
    }
}
