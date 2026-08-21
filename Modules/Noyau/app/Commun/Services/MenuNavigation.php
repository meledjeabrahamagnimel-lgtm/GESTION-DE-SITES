<?php

namespace Modules\Noyau\Commun\Services;

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
                ['label' => 'Traçabilité', 'route' => 'super-admin.tracabilite', 'section' => 'journal'],
                ['label' => 'Journal', 'route' => 'super-admin.journal.index', 'section' => 'journal'],
                ['label' => 'Messages', 'route' => 'messages', 'section' => null],
                ['label' => 'Notifications', 'route' => 'mes-notifications', 'section' => null],
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
                ['label' => 'Notifications', 'route' => 'mes-notifications'],
                ['label' => 'Paramètres', 'route' => 'mon-espace'],
            ]);
        }

        if ($utilisateur->hasRole('caissier')) {
            return self::construire([
                ['label' => 'Tableau de bord', 'route' => 'caissier.tableau-de-bord'],
                ['label' => 'Encaissements', 'route' => 'caissier.encaissements'],
                ['label' => 'Décaissements', 'route' => 'caissier.decaissements'],
                ['label' => 'Messages', 'route' => 'messages'],
                ['label' => 'Notifications', 'route' => 'mes-notifications'],
                ['label' => 'Paramètres', 'route' => 'mon-espace'],
            ]);
        }

        $onglets = [];

        $parametres = 'mon-espace';

        if ($utilisateur->hasRole('gerant')) {
            $onglets[] = ['label' => 'Tableau de bord', 'route' => 'tableau-de-bord'];
            $parametres = 'parametres';
        }

        if ($utilisateur->hasRole('responsable_ville') || $utilisateur->hasRole('responsable_site')) {
            $onglets[] = ['label' => 'Saisie du jour', 'route' => 'saisie-du-jour'];
        }

        $onglets[] = [
            'label' => 'Indicateurs',
            'groupe' => [
                ['label' => 'Prospects', 'route' => 'prospects'],
                ['label' => 'Devis', 'route' => 'devis'],
                ['label' => "Chiffre d'affaires", 'route' => 'chiffre-affaires'],
                ['label' => 'Charges', 'route' => 'charges'],
                ['label' => 'Trésorerie', 'route' => 'tresorerie'],
                ['label' => 'Commerciaux', 'route' => 'commerciaux'],
            ],
        ];

        $onglets[] = [
            'label' => 'Général',
            'groupe' => [
                ['label' => 'Ajouter un accès', 'route' => 'acces.creer'],
                ['label' => 'Messages', 'route' => 'messages'],
                ['label' => 'Notifications', 'route' => 'mes-notifications'],
                ['label' => 'Paramètres', 'route' => $parametres],
            ],
        ];

        return self::construire($onglets);
    }

    private static function construire(array $onglets): array
    {
        return array_map(function ($onglet) {
            if (isset($onglet['groupe'])) {
                $items = array_map(fn ($item) => [
                    'label' => $item['label'],
                    'route' => route($item['route']),
                    'actif' => request()->routeIs(($item['actifPattern'] ?? $item['route']).'*'),
                ], $onglet['groupe']);

                return [
                    'label' => $onglet['label'],
                    'groupe' => $items,
                    'actif' => collect($items)->contains('actif', true),
                ];
            }

            return [
                'label' => $onglet['label'],
                'route' => route($onglet['route']),
                'actif' => request()->routeIs(($onglet['actifPattern'] ?? $onglet['route']).'*'),
            ];
        }, $onglets);
    }
}
