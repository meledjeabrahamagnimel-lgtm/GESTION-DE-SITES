# Architecture du projet

L'application est découpée en **modules** (`nwidart/laravel-modules`). Chaque module
regroupe tout ce qui concerne un rôle : ses écrans, ses routes, et le middleware qui
en garde l'accès. Ouvrir le dossier d'un module, c'est voir d'un coup d'œil ce que ce
rôle peut faire — et rien d'autre.

```
Modules/
├── Noyau/           le socle : modèles, migrations, services, écrans communs
├── SuperAdmin/      la plateforme
├── Gerant/          la direction
├── Encadrement/     superviseur de ville + responsable de site
├── Comptabilite/    la caisse d'une ville
└── Commercial/      le terrain
```

## Pourquoi le Noyau existe

Une `Prospection` est créée par le commercial, validée par le responsable de site,
consultée par le superviseur et le gérant, encaissée par la comptabilité. Si le modèle
`Prospection` vivait dans le module `Commercial`, les cinq autres modules en
dépendraient — et le jour où l'on toucherait au module Commercial, on risquerait de
casser la comptabilité.

Le **Noyau** rassemble donc tout ce qui est partagé : les modèles, les migrations, les
services métier. Chaque module de rôle en dépend, et **le Noyau ne dépend d'aucun
d'eux**. Les modules de rôle, eux, ne se connaissent pas entre eux. C'est cette règle —
et elle seule — qui empêche les dépendances circulaires.

```
   SuperAdmin   Gerant   Encadrement   Comptabilite   Commercial
        \         |          |              |            /
         \        |          |              |           /
          ─────────────► Noyau ◄────────────────────────
```

**La règle à respecter :** un module de rôle peut appeler le Noyau. Il ne doit jamais
appeler un autre module de rôle. Si deux rôles ont besoin de la même chose, cette chose
appartient au Noyau.

## Contenu du Noyau

```
Modules/Noyau/
├── app/
│   ├── Entreprises/       entreprises, villes, sites, exercices, création d'accès
│   │   ├── Modeles/       Entreprise, Ville, Site, Exercice
│   │   ├── Actions/       CreerAcces, CreerEntreprise, PurgerDonneesEntreprise
│   │   ├── Services/      ProvisionneurEntreprise, EnregistreurLogo
│   │   └── Support/       PerimetreSites — qui voit quoi
│   ├── Exploitation/      le métier quotidien
│   │   ├── Modeles/       Prospection, Devis, Facture, Encaissement, Charge,
│   │   │                  Commercial, SaisieJournaliere, CompteurDocument
│   │   └── Services/      GenerateurNumero
│   ├── Messagerie/        conversations, messages, pièces jointes
│   └── Commun/            notifications, référentiels, notes, notifications poussées
├── database/migrations/   TOUTES les migrations de l'application
├── resources/views/
│   └── commun/            écrans ouverts à tous : messagerie, notifications,
│                          mot de passe, espace personnel, inscription
└── routes/web.php
```

Les migrations sont toutes dans le Noyau, et non réparties par module : une table comme
`factures` n'appartient à aucun rôle en particulier, et les éparpiller rendrait leur
ordre d'exécution difficile à suivre.

## Contenu des modules de rôle

| Module | Écrans | Rôle Spatie |
|---|---|---|
| `SuperAdmin` | tableau de bord, entreprises, accès, administrateurs, journal, maintenance | `super_admin` |
| `Gerant` | tableau de bord, paramètres | `gerant` |
| `Encadrement` | `saisie/` : saisie du jour, fiche prospection<br>`pilotage/` : prospects, devis, CA, charges, trésorerie, commerciaux, création d'accès | `responsable_ville`, `responsable_site` |
| `Comptabilite` | tableau de bord, encaissements, décaissements | `caissier` |
| `Commercial` | mes prospections, ma performance, mes notes | `commercial` |

### Pourquoi Superviseur et Responsable de site partagent un module

Les deux rôles atteignent **exactement les mêmes écrans**. Ce qui les distingue n'est
pas ce qu'ils voient, mais jusqu'où : le superviseur couvre tous les lieux de sa ville,
le responsable de site le sien seulement. Ce périmètre est résolu une fois pour toutes
par `Site::visiblesPour()`, jamais dans les écrans.

Deux dossiers auraient donc contenu deux fois les mêmes fichiers. Le module
`Encadrement` les réunit, et son fichier de routes sépare clairement ce qui relève de
la saisie (les deux rôles) de ce qui relève du pilotage (les deux rôles **et** le gérant).

### Pourquoi les indicateurs sont dans Encadrement et pas dans Gerant

Prospects, Devis, CA, Charges, Trésorerie et Commerciaux sont lus par trois rôles. Ils
sont déclarés une fois, dans `Encadrement`, avec le middleware
`role:gerant|responsable_ville|responsable_site`. Les dupliquer dans `Gerant` aurait
créé deux fois la même route sur la même URL — seule la dernière déclarée aurait
survécu.

## Les routes

`routes/web.php` à la racine est **vide**. Chaque module déclare les siennes :

```
Modules/Noyau/routes/web.php         écrans communs à tous les rôles
Modules/SuperAdmin/routes/web.php    plateforme
Modules/Gerant/routes/web.php        direction
Modules/Encadrement/routes/web.php   superviseur + responsable de site
Modules/Comptabilite/routes/web.php  caisse
Modules/Commercial/routes/web.php    terrain
```

Chaque fichier est chargé par le fournisseur de son module, **dans le groupe de
middleware `web`** — sans quoi les pages authentifiées n'auraient ni session ni
protection CSRF.

## Les écrans (Volt)

Chaque module monte son propre dossier de vues. Un écran est désigné par son chemin
relatif à ce dossier :

| Fichier | Nom Volt |
|---|---|
| `Modules/Encadrement/resources/views/saisie/saisie-du-jour.blade.php` | `saisie.saisie-du-jour` |
| `Modules/Gerant/resources/views/gerant/tableau-de-bord.blade.php` | `gerant.tableau-de-bord` |
| `Modules/Noyau/resources/views/commun/messages.blade.php` | `commun.messages` |

Le sous-dossier fait partie du nom : c'est ce qui garantit qu'aucun écran d'un module
n'entre en collision avec celui d'un autre — deux « tableau-de-bord » peuvent coexister
sans ambiguïté.

## Ce qui reste hors des modules, et pourquoi

| Emplacement | Contenu | Raison |
|---|---|---|
| `app/Models/User.php` | le compte utilisateur | Imposé par Laravel (authentification, Fortify) |
| `app/Http/` | middlewares, contrôleurs d'authentification | Chemin imposé par le framework |
| `app/Providers/` | fournisseurs de l'application | Chemin imposé par le framework |
| `resources/views/components/` | briques d'interface (`<x-champ>`, `<x-kpi-card>`…) | Utilisées par tous les modules ; les déplacer imposerait d'écrire `<x-noyau::champ>` partout, sans rien y gagner |
| `resources/views/layouts/`, `auth/` | gabarits et écrans Fortify | Attendus à cet emplacement par Fortify |
| `database/seeders/` | jeux de données | Chargés par `php artisan db:seed`, qui les cherche ici |

Les tables `users`, `sessions`, `jobs`, `cache`, `roles`, `permissions`,
`model_has_roles` et `activity_log` gardent leur nom anglais : elles proviennent de
Laravel, Fortify et Spatie. Les renommer reviendrait à se battre contre ces outils à
chaque mise à jour. Toutes les tables métier, elles, sont en français.

## Ajouter un écran

1. Choisir le module : à quel rôle appartient cet écran ? S'il en concerne plusieurs, il
   va dans le Noyau ou dans le module qui les réunit.
2. Créer le fichier dans `Modules/<Module>/resources/views/<dossier>/mon-ecran.blade.php`.
3. Déclarer la route dans `Modules/<Module>/routes/web.php`, avec son middleware de rôle.
4. Ajouter l'entrée de menu dans `Modules/Noyau/app/Commun/Services/MenuNavigation.php`
   si l'écran doit apparaître dans le bandeau.

## Vérifier que tout tient debout

```bash
php artisan module:list      # les 6 modules doivent être « Enabled »
php artisan route:list       # toutes les routes doivent apparaître
php artisan test             # la suite complète
```
