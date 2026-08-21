# Mise à jour du serveur — `gestionsites.dc-knowing.com`

> **La base en ligne porte des données réelles.** Rien de ce qui suit ne modifie une
> écriture métier : aucune purge, aucun seeder, aucun `migrate:fresh`. La migration
> ajoutée ici **crée deux tables neuves et vides** et ne touche à aucune colonne
> existante. La commande d'identifiants, elle, ne modifie **qu'une seule ligne** de la
> table `users`, et seulement les colonnes nommées.
>
> Avant de commencer, faire malgré tout une sauvegarde depuis cPanel → *Sauvegardes* →
> *Télécharger une sauvegarde de base de données MySQL*. Une sauvegarde inutile ne coûte
> que deux minutes ; son absence, une journée de saisie.

---

## 1. Récupérer le code

```bash
cd ~/gestionsites.dc-knowing.com     # ou le dossier réel de l'application
git pull origin main
```

Aucune dépendance nouvelle n'a été ajoutée : **pas besoin de `composer install`**. Le
générateur de PDF de l'annuaire et le journal de traçabilité sont écrits sans paquet
tiers, précisément pour que `vendor/` — qui n'est pas suivi par git — n'ait pas à bouger.

## 2. Migrer et vider les caches

```bash
php artisan app:deployer
```

Cette commande enchaîne `migrate --force`, puis le vidage des caches de configuration, de
routes, d'évènements et de gabarits. Le vidage des gabarits n'est pas facultatif : Volt met
en cache la classe de chaque écran, et une classe restée en arrière donne une erreur 500 sur
une propriété introuvable.

Ce que la migration fait :

| Objet | Contenu | Effet sur les données existantes |
|---|---|---|
| `sessions_utilisateur` *(table neuve)* | une ligne par connexion | aucun |
| `visites_ecran` *(table neuve)* | une ligne par écran ouvert | aucun |

## 3. Mettre à jour les identifiants du super administrateur

**D'abord en simulation**, pour vérifier qu'on vise bien le bon compte :

```bash
php artisan superadmin:identifiants \
  --compte=superadmin@gmail.com \
  --email=it.dcknowing@gmail.com \
  --nom="Super Admin DC-KNOWING" \
  --mot-de-passe='@@@###26dcknowing' \
  --simulation
```

La commande affiche l'état actuel du compte et les changements demandés, **sans rien
écrire**. Lire cette sortie : si l'adresse actuelle affichée n'est pas celle attendue,
s'arrêter là et corriger `--compte`.

Puis, une fois la sortie vérifiée, relancer **la même ligne sans `--simulation`** :

```bash
php artisan superadmin:identifiants \
  --compte=superadmin@gmail.com \
  --email=it.dcknowing@gmail.com \
  --nom="Super Admin DC-KNOWING" \
  --mot-de-passe='@@@###26dcknowing'
```

> **Les apostrophes simples autour du mot de passe sont indispensables.** Sans elles, le
> shell interprète `#` comme le début d'un commentaire et `@@@` comme du texte : le mot de
> passe réellement enregistré ne serait pas celui qu'on croit, et la connexion échouerait
> ensuite sans qu'on comprenne pourquoi.

Si l'adresse actuelle du compte en ligne n'est plus `superadmin@gmail.com`, la commande le
dit et liste les comptes hors entreprise connus. Elle refuse également d'agir si la nouvelle
adresse appartient déjà à quelqu'un d'autre : rien n'est écrasé en silence.

### Ce que la commande fait, et rien d'autre

- remplace `email`, `name`, `password` sur **cette ligne uniquement** ;
- renouvelle le jeton « se souvenir de moi », pour qu'un cookie resté sur un poste
  n'ouvre plus la session avec l'ancien mot de passe ;
- inscrit le changement au journal d'audit — **sans jamais y écrire le mot de passe**.

> **Ne pas passer par le seeder pour cela.** `SuperAdminSeeder` porte bien la nouvelle
> adresse par défaut, mais il travaille en `firstOrCreate` sur l'e-mail : lancé sur un
> serveur dont la ligne porte encore l'ancienne adresse, il ne la corrigerait pas — il
> créerait un **second** super administrateur. La commande ci-dessus modifie la ligne
> existante ; c'est la seule voie en production.

## 4. Vérifier

```bash
php artisan superadmin:reparer it.dcknowing@gmail.com --diagnostic
php artisan app:diagnostic
```

Le premier doit afficher `est_fondateur : oui`, `rôles en base : super_admin (équipe 0)` et
les cinq sections ouvertes. Puis se déconnecter et se reconnecter avec la nouvelle adresse.

## 5. Facultatif — entretien du journal de traçabilité

Le journal des connexions est nominatif et se conserve six mois. Si l'hébergement dispose
d'une tâche planifiée (cPanel → *Tâches Cron*), y ajouter l'ordonnanceur Laravel :

```
* * * * * cd ~/gestionsites.dc-knowing.com && php artisan schedule:run >> /dev/null 2>&1
```

Sans cron, l'écran de traçabilité reste **juste** : une session silencieuse depuis plus de
quinze minutes n'y est pas comptée comme présente, et les durées sont tenues à jour à chaque
requête. Il suffit alors de lancer l'entretien à la main de temps en temps :

```bash
php artisan tracabilite:entretenir
```

---

## En cas de retour en arrière

Les deux tables ajoutées sont indépendantes du métier : les supprimer ne fait perdre que
l'historique de navigation.

```bash
php artisan migrate:rollback --step=1 --force
```

Pour l'adresse du super administrateur, relancer la commande du point 3 avec l'ancienne
adresse en `--email` et `--compte=it.dcknowing@gmail.com`.
