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
            // Un Super Admin secondaire ne voit que les sections qui lui ont été ouvertes :
            // afficher un onglet menant à un 403 n'aurait aucun intérêt.
            $sections = [
                ['label' => 'Tableau de bord', 'route' => 'super-admin.dashboard', 'section' => 'dashboard'],
                ['label' => 'Entreprises', 'route' => 'super-admin.entreprises.index', 'section' => 'entreprises'],
                ['label' => 'Accès', 'route' => 'super-admin.acces.index', 'actifPattern' => 'super-admin.acces.*', 'section' => 'acces'],
                ['label' => 'Administrateurs', 'route' => 'super-admin.administrateurs', 'section' => 'acces'],
                ['label' => 'Journal', 'route' => 'super-admin.journal.index', 'section' => 'journal'],
                ['label' => 'Messages', 'route' => 'messages', 'section' => null],
                ['label' => 'Maintenance', 'route' => 'super-admin.maintenance', 'section' => 'maintenance'],
            ];

            return self::construire(array_filter(
                $sections,
                fn ($onglet) => $onglet['section'] === null || $utilisateur->peutAccederA($onglet['section']),
            ));
        }

        if ($utilisateur->hasRole('commercial')) {
            return self::construire([
                ['label' => 'Ma performance individuelle', 'route' => 'ma-performance'],
                ['label' => 'Mes prospections', 'route' => 'mes-prospections'],
                ['label' => 'Mes notes', 'route' => 'mes-notes'],
                ['label' => 'Messages', 'route' => 'messages'],
                ['label' => 'Paramètres', 'route' => 'mon-espace'],
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
            $suffixe[] = ['label' => 'Paramètres', 'route' => 'mon-espace'];
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
            ['label' => 'Messages', 'route' => 'messages'],
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
