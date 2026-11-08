<?php

namespace Modules\Noyau\Imports\Modeles;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Noyau\Commun\Concerns\AppartientAUneEntreprise;

/**
 * Une facture d'achat normalisée, telle que le portail FNE la rend — voir
 * `Modules\Noyau\Imports\Services\AchatsFne`.
 *
 * Elle est un **constat** : elle ne crée ni dette ni charge par elle-même. Elle confirme une
 * ligne du suivi fournisseurs (`facture_fournisseur_id`) — retrouvée par le rapprochement, ou
 * créée par « Porter au suivi ». Un avoir porte des montants négatifs, et son exercice est
 * l'année de sa date.
 */
#[Fillable([
    'entreprise_id', 'releve_fne_id', 'facture_fournisseur_id',
    'reference', 'fne_id', 'type', 'sous_type', 'est_avoir', 'statut_portail',
    'date_facture', 'exercice', 'emetteur_ncc', 'emetteur_nom',
    'montant_ht', 'montant_tva', 'timbre', 'montant_ttc', 'moyen_paiement', 'contenu',
])]
class FactureFne extends Model
{
    use \App\Casts\CompareLesMontants;

    use AppartientAUneEntreprise;

    protected $table = 'factures_fne';

    /**
     * Une facture FNE ne se supprime pas — 06/11. Elle est certifiée par le portail de l'État et compte
     * directement dans les charges fournisseurs ; l'effacer ferait disparaître une dépense réelle.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $facture) {
            throw new \LogicException('Une facture FNE ne peut pas être supprimée.');
        });
    }

    protected function casts(): array
    {
        return [
            'date_facture' => 'date',
            'est_avoir' => 'boolean',
            'exercice' => 'integer',
            'montant_ht' => \App\Casts\Montant::class,
            'montant_tva' => \App\Casts\Montant::class,
            'timbre' => \App\Casts\Montant::class,
            'montant_ttc' => \App\Casts\Montant::class,
            'contenu' => 'array',
        ];
    }

    public function pieceDuSuivi(): BelongsTo
    {
        return $this->belongsTo(FactureFournisseur::class, 'facture_fournisseur_id');
    }

    /**
     * Adresse de vérification officielle de la facture reçue.
     * Le relevé FNE conserve le token dans le contenu brut de la réponse API.
     */
    public function urlDeVerification(): ?string
    {
        $contenu = is_array($this->contenu) ? $this->contenu : [];
        $token = trim((string) ($contenu['token'] ?? ''));

        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, 'https://') || str_starts_with($token, 'http://')) {
            return $token;
        }

        $hote = rtrim((string) config('fne.url_verification', 'https://www.services.fne.dgi.gouv.ci'), '/');

        return $hote === '' ? null : $hote.'/fr/verification/'.$token;
    }
}
