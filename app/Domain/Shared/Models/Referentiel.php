<?php

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Concerns\AppartientAUneEntreprise;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Valeur d'une liste déroulante propre à l'entreprise. Les valeurs par défaut sont
 * fournies par self::DEFAUTS et fusionnées avec celles créées depuis l'application.
 */
#[Fillable(['entreprise_id', 'type', 'valeur', 'rang', 'est_actif'])]
class Referentiel extends Model
{
    use AppartientAUneEntreprise;

    protected $table = 'referentiels';

    public const ACTIVITE = 'activite';

    public const MOYEN_PROSPECTION = 'moyen_prospection';

    public const MOYEN_PAIEMENT = 'moyen_paiement';

    public const LIBELLE_CHARGE = 'libelle_charge';

    public const TYPE_ENCAISSEMENT = 'type_encaissement';

    /** Valeurs livrées avec l'application, toujours disponibles. */
    public const DEFAUTS = [
        self::ACTIVITE => ['Mécanique', 'Carrosserie'],
        self::MOYEN_PROSPECTION => ['RDV', 'Téléphone', 'Mail'],
        self::MOYEN_PAIEMENT => ['Espèces', 'Mobile Money', 'Chèque', 'Virement', 'Autres'],
        self::LIBELLE_CHARGE => ['Achats pièces', 'Salaires & personnel', 'Fonctionnement', 'Autres décaissements'],
        self::TYPE_ENCAISSEMENT => ['Client', 'Appro', 'Autres'],
    ];

    public const LIBELLES = [
        self::ACTIVITE => 'Activités',
        self::MOYEN_PROSPECTION => 'Moyens de prospection',
        self::MOYEN_PAIEMENT => 'Moyens de paiement',
        self::LIBELLE_CHARGE => "Libellés d'opération",
        self::TYPE_ENCAISSEMENT => "Types d'encaissement",
    ];

    protected function casts(): array
    {
        return ['est_actif' => 'boolean'];
    }

    /**
     * Options d'une liste déroulante : valeurs par défaut puis valeurs propres à
     * l'entreprise, dans un tableau valeur => valeur directement exploitable par <select>.
     *
     * @return array<string, string>
     */
    public static function options(string $type, ?int $entrepriseId = null): array
    {
        $entrepriseId ??= auth()->user()?->entreprise_id;

        $ajoutees = $entrepriseId
            ? static::withoutGlobalScopes()
                ->where('entreprise_id', $entrepriseId)
                ->where('type', $type)
                ->where('est_actif', true)
                ->orderBy('rang')->orderBy('valeur')
                ->pluck('valeur')->all()
            : [];

        $valeurs = array_values(array_unique([...(self::DEFAUTS[$type] ?? []), ...$ajoutees]));

        return array_combine($valeurs, $valeurs);
    }

    /** Vrai si la valeur fait partie des valeurs livrées : elle n'est alors pas supprimable. */
    public static function estValeurParDefaut(string $type, string $valeur): bool
    {
        return in_array($valeur, self::DEFAUTS[$type] ?? [], true);
    }
}
