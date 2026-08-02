<?php

namespace App\Domain\Shared\Services;

use App\Models\User;

/**
 * Construit le menu du bandeau supérieur selon le rôle de l'utilisateur connecté.
 */
class MenuNavigation
{
    public static function pour(User $utilisateur): array
    {
        if ($utilisateur->hasRole('super_admin')) {
            return self::construire([
                ['label' => 'Tableau de bord', 'route' => 'super-admin.dashboard'],
                ['label' => 'Entreprises', 'route' => 'super-admin.entreprises.index'],
                ['label' => 'Accès', 'route' => 'super-admin.acces.index', 'actifPattern' => 'super-admin.acces.*'],
                ['label' => 'Journal', 'route' => 'super-admin.journal.index'],
                ['label' => 'Maintenance', 'route' => 'super-admin.maintenance'],
            ]);
        }

        if ($utilisateur->hasRole('commercial')) {
            return self::construire([
                ['label' => 'Ma performance individuelle', 'route' => 'ma-performance'],
            ]);
        }

        $onglets = [];

        $suffixe = [];

        if ($utilisateur->hasRole('gerant')) {
            $onglets[] = ['label' => 'Tableau de bord', 'route' => 'tableau-de-bord'];
            $suffixe[] = ['label' => 'Paramètres', 'route' => 'parametres'];
        }

        if ($utilisateur->hasRole('responsable_site')) {
            $onglets[] = ['label' => 'Saisie du jour', 'route' => 'saisie-du-jour'];
        }

        return self::construire([
            ...$onglets,
            ['label' => 'Prospects', 'route' => 'prospects'],
            ['label' => 'Devis', 'route' => 'devis'],
            ['label' => "Chiffre d'affaires", 'route' => 'chiffre-affaires'],
            ['label' => 'Charges', 'route' => 'charges'],
            ['label' => 'Trésorerie', 'route' => 'tresorerie'],
            ['label' => 'Commerciaux', 'route' => 'commerciaux'],
            ['label' => 'Ajouter un accès', 'route' => 'acces.creer'],
            ...$suffixe,
        ]);
    }

    private static function construire(array $onglets): array
    {
        return array_map(fn ($onglet) => [
            'label' => $onglet['label'],
            'route' => route($onglet['route']),
            'actif' => request()->routeIs(($onglet['actifPattern'] ?? $onglet['route']).'*'),
        ], $onglets);
    }
}
