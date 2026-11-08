# Préparation du déversement comptable vers COMPTAFLOW

> Document de cadrage technique — version du 8 novembre 2026.  
> Objectif : préparer une passerelle commune à GESTION-DE-SITES, SELFLOW et RH-FLOW, sans mélanger leurs identités ni leurs opérations.

## 1. Décision d'architecture

COMPTAFLOW est le **système comptable maître**. Il reste propriétaire des dossiers comptables, des exercices, des journaux, du plan comptable, des tiers et des règles de validation/comptabilisation.

Les applications sources transmettent des opérations identifiées et leurs références métier. Elles ne doivent pas créer elles-mêmes des comptes, journaux ou tiers dans la base COMPTAFLOW et ne doivent pas contourner son mécanisme natif d'import.

Chaque intégration possède une identité, une clé et un identifiant de dossier source distincts :

| Source | Identifiant source dans COMPTAFLOW | Nature des opérations |
|---|---|---|
| SELFLOW | `selflow_company_id` | Ventes, achats et opérations issues de SELFLOW |
| GESTION-DE-SITES | `gestionsites_company_id` | Chiffre d'affaires, dépenses et autres opérations de gestion |
| RH-FLOW | À ajouter (ex. `rhflow_company_id`) | Écritures de paie en journal OD |

Une liaison ne doit jamais réutiliser l'identifiant ou la clé d'une autre application. Un même dossier COMPTAFLOW ne doit pas être lié à deux sources incompatibles sans règle explicite de multi-sources.

## 2. Ce que COMPTAFLOW attend actuellement

Les routes et contrôleurs présents sur `main` exposent les contrats suivants. Les champs sont à considérer comme le contrat observé dans le code, pas comme une garantie que toutes les fonctions métier sont déjà terminées.

### 2.1 Authentification des appels

Pour les routes de synchronisation protégées par `cle.entreprise` :

- `X-Company-Key` : clé dédiée à la liaison et au dossier COMPTAFLOW.
- `X-Sync-Secret` : secret serveur partagé, contrôlé par `config('external_sync.external_sync_secret')`, donc la variable COMPTAFLOW `EXTERNAL_SYNC_SECRET`.
- La clé entreprise détermine le dossier. L'identifiant transmis dans le JSON est comparé à celui porté par la clé.
- Les appels sans clé entreprise doivent être refusés.

Pour provisionner une liaison, l'API GESTION-DE-SITES transmet le secret serveur, l'identifiant de son entreprise et l'identifiant du dossier COMPTAFLOW existant. COMPTAFLOW renvoie une clé dédiée.

**Configuration serveur :** l'utilisateur confirme que `COMPTAFLOW_API_URL` et `COMPTAFLOW_API_SECRET` sont déjà configurés dans GESTION-DE-SITES, et que le secret correspond à `EXTERNAL_SYNC_SECRET` dans COMPTAFLOW. Ne pas redemander cette configuration sauf si un contrôle serveur révèle une anomalie.

### 2.2 Vérification de liaison

Route : `POST /api/external/gestionsites/companies/verify`

Corps envoyé :
- `comptaflow_company_id`
- `gestionsites_company_id`

La clé de l'en-tête doit désigner le même dossier COMPTAFLOW et la même entreprise source.

### 2.3 Déversement du référentiel

Route : `POST /api/external/gestionsites/referentiel/deverser`

Corps actuellement attendu :
- `comptaflow_company_id` : entier obligatoire ;
- `gestionsites_company_id` : entier obligatoire pour cette source ;
- `plan_comptable` : tableau facultatif de lignes `numero_de_compte`, `intitule` ;
- `codes_journaux` : tableau facultatif de lignes `code_journal`, `intitule`, `type`, `compte_numero` ;
- `tiers` : tableau facultatif de lignes `numero_de_tiers`, `intitule`, `compte_general`, et éventuellement `informations`.

Le contrôleur fait passer ces éléments dans le mécanisme d'import natif de COMPTAFLOW. Il renvoie un bilan des éléments reçus, créés, déjà présents et écartés.

Attention : le nom du champ journal lu par le contrôleur est actuellement `compte_numero` pour le compte de trésorerie, alors que le mapping de l'import utilise un champ de staging nommé `compte_de_tresorerie`. Le contrat doit être normalisé et couvert par test avant d'envoyer les journaux réels.

### 2.4 Déversement des écritures

Route commune : `POST /api/external/ecritures/deverser`, protégée par la clé entreprise.

Le contrat de haut niveau utilisé par GESTION-DE-SITES est :
- `comptaflow_company_id`
- `gestionsites_company_id`
- `source` = `GESTION_DE_SITES`
- `operation` : identifiant unique et stable de l'opération source ;
- `atomique` = `true` ;
- `exercice_debut`, `exercice_fin` ;
- `ecritures` : tableau de lignes comptables.

**À confirmer par tests et à figer dans une spécification versionnée :** les champs obligatoires de chaque ligne, notamment date, pièce, journal, compte général, libellé, débit, crédit, tiers, référence source, point de vente et exercice. Le code d'import doit refuser une écriture déséquilibrée et une ligne qui pointe vers un journal, un compte ou un tiers non résolu.

Une opération doit être idempotente : renvoyer le même identifiant d'opération ne doit jamais créer une deuxième écriture.

## 3. Référentiels : mécanisme recommandé

### 3.1 Tiers : COMPTAFLOW est maître

Ne pas créer automatiquement un tiers à chaque déversement. Cela crée des doublons et des tiers concurrents.

Mécanisme recommandé :
1. COMPTAFLOW expose la liste paginée des tiers du dossier : identifiant interne, numéro, intitulé, type (client/fournisseur/autre), compte collectif et état.
2. GESTION-DE-SITES importe cette liste en lecture et propose une correspondance entre son client/fournisseur et le tiers COMPTAFLOW.
3. La correspondance est persistée côté GESTION-DE-SITES avec l'identifiant interne COMPTAFLOW, le type et le numéro affiché.
4. La recherche d'un tiers existant se fait d'abord par identifiant externe déjà mémorisé, puis par identifiant fiscal (NCC/RCCM si disponible), puis par numéro exact, puis par nom normalisé avec validation humaine.
5. Si aucun tiers n'existe, un utilisateur habilité peut demander sa création via une route dédiée COMPTAFLOW. COMPTAFLOW applique ses propres validations, attribue le numéro et l'identifiant interne, puis retourne ceux-ci.
6. Le tiers créé est ensuite réutilisé par toutes les écritures suivantes.

La création doit être explicite et contrôlée. Un tiers introuvable ne doit pas être silencieusement remplacé par un autre tiers au nom ressemblant. Selon la nature du compte, COMPTAFLOW peut autoriser une écriture au seul compte collectif, mais cette dégradation doit être signalée et acceptée par la règle métier.

**À développer dans COMPTAFLOW :**
- endpoint de liste des tiers par dossier, filtrable par type et recherche ;
- endpoint de création contrôlée (ou réutilisation d'un endpoint métier existant après audit) ;
- endpoint de consultation d'un tiers par identifiant ;
- réponse stable contenant l'ID interne, numéro, intitulé, type, compte collectif et identifiant externe éventuel.

### 3.2 Journaux

COMPTAFLOW expose déjà les codes journaux du dossier dans son import de référentiel. Il faut ajouter ou confirmer une API de consultation en lecture seule qui retourne au minimum :
- ID interne ;
- code journal ;
- intitulé ;
- type/nature ;
- compte de trésorerie ou compte de contrepartie si applicable ;
- statut actif/inactif.

GESTION-DE-SITES et RH-FLOW doivent sélectionner un journal existant. Ils ne doivent pas inventer un code journal local qui ne serait pas reconnu par COMPTAFLOW.

Pour RH-FLOW, le journal cible est le journal de type **OD / Opérations diverses** du dossier. Le code exact est choisi dans la liste du dossier ; ne pas supposer qu'il s'appelle toujours `OD`.

### 3.3 Plan comptable

COMPTAFLOW expose déjà un import de plan comptable. Il faut fournir une API de liste/recherche par dossier avec ID, numéro, intitulé, classe, type et état. Les applications sources mémorisent les correspondances de comptes et envoient les numéros validés.

Les comptes déjà configurés dans COMPTAFLOW ne doivent pas être renommés ni retypés par une synchronisation externe. Un compte absent doit faire l'objet d'une règle de création explicite et validée, ou être refusé avant déversement.

## 4. RH-FLOW : règles spécifiques de paie

RH-FLOW ne doit pas envoyer des bulletins ou des objets de paie bruts à l'API d'écritures. Il doit envoyer une **écriture comptable agrégée et équilibrée**, généralement composée de plusieurs lignes :

- journal : journal OD sélectionné dans COMPTAFLOW ;
- date comptable et exercice ;
- numéro de pièce / référence de paie stable ;
- libellé explicite (ex. Paie — période AAAA-MM) ;
- comptes généraux de charges, dettes sociales/fiscales, avances, acomptes et comptes de personnel selon le paramétrage validé ;
- tiers si la comptabilité attend un tiers pour certaines lignes ;
- total débit égal au total crédit ;
- identifiant d'opération RH-FLOW immuable pour empêcher les doublons ;
- période de paie et référence source pour audit.

Les comptes de paie sont paramétrés dans COMPTAFLOW ou dans une table de correspondance RH-FLOW → comptes COMPTAFLOW. Ne jamais coder en dur des numéros de comptes supposés universels. Le journal OD est sélectionné dans le référentiel du dossier COMPTAFLOW.

Prévoir les cas de régularisation, rappel, avance, acompte, opposition, cotisations, impôts, net à payer, paiement de salaires et annulation/reprise. Il faut définir si RH-FLOW déverse une écriture de constatation seule ou aussi les règlements ; ne pas mélanger les deux sans spécification.

## 5. Isolation obligatoire entre sources

- Une clé dédiée par liaison et par source.
- Une colonne/identité de source explicite sur les imports, opérations, journaux d'audit et clés d'idempotence.
- Une contrainte d'unicité sur `(source, dossier_comptaflow, operation_id)`, pas uniquement sur l'identifiant métier local.
- Les écritures d'une source ne doivent jamais être modifiées, supprimées ou dédupliquées sur la seule base d'un numéro de pièce partagé avec une autre source.
- Les migrations GESTION-DE-SITES ne doivent pas modifier les colonnes ou clés SELFLOW.
- Le middleware doit déterminer la source depuis la clé authentifiée et refuser toute contradiction avec le corps.
- Le traitement commun doit être non régressif : les règles Selflow restent couvertes par des tests de caractérisation.
- Les écritures importées doivent garder `source`, `source_operation_id`, `source_entry_id` et un lien de traçabilité vers le payload reçu.
- Toute correction d'une écriture déjà comptabilisée doit suivre une procédure d'extourne/contrepassation, jamais une réécriture silencieuse.

## 6. Risques constatés à traiter avant mise en production

1. Le contrôleur commun de déversement et le middleware commun identifient plusieurs sources. Une régression dans la détection de source ou dans le choix du champ d'identifiant peut affecter Selflow.
2. La recherche de clé teste d'abord la clé Selflow puis celle de GESTION-DE-SITES. Les clés doivent être uniques entre les deux espaces ; ajouter une contrainte et un test qui garantit qu'une même empreinte ne peut pas exister dans les deux colonnes.
3. Le contrôleur de réception des écritures contient des fonctions historiques nommées et commentées Selflow. Le traitement commun doit recevoir une source explicite et ne pas déduire la source à partir d'un champ libre du JSON.
4. Le traitement des tiers observé dans le contrôleur de déversement d'écritures ne crée pas le tiers manquant : il tente de le résoudre et laisse le tiers nul si non trouvé. Ce comportement doit être rendu visible dans le rapport et décidé avec le comptable.
5. L'import du référentiel appelle `ImportCommitJob::dispatchSync` : il est synchrone aujourd'hui, et non un traitement en arrière-plan.
6. Le contrat des journaux présente une incohérence de nom de champ à clarifier (`compte_numero` / `compte_de_tresorerie`).
7. Le code de réception du référentiel et des écritures doit être vérifié sur tous les chemins d'exécution : transaction, rollback, session/utilisateur courant, et restauration du contexte d'authentification.
8. La liaison GESTION-DE-SITES interdit actuellement le dossier COMPTAFLOW déjà lié à SELFLOW. C'est une règle prudente pour l'instant ; une éventuelle multi-source sur un même dossier doit être une décision explicite, pas un effet secondaire.

## 7. Plan de réalisation

### Phase A — Audit et contrat API
- [ ] Vérifier intégralement les routes, middleware, contrôleurs, modèles et migrations de COMPTAFLOW.
- [ ] Documenter le schéma exact d'une ligne d'écriture et les réponses API.
- [ ] Uniformiser les noms des champs journaux.
- [ ] Ajouter versionnement du contrat et erreurs structurées.
- [ ] Ajouter endpoints de lecture des journaux, comptes et tiers.

### Phase B — Référentiel et correspondances
- [ ] Écran GESTION-DE-SITES de consultation des journaux COMPTAFLOW.
- [ ] Écran de consultation/recherche des comptes.
- [ ] Écran de rapprochement des tiers locaux avec les tiers COMPTAFLOW.
- [ ] Création contrôlée d'un tiers manquant par l'API COMPTAFLOW.
- [ ] Persistance des identifiants COMPTAFLOW et gestion des changements/désactivations.
- [ ] Correspondances des libellés de recettes et dépenses vers les comptes, avec dépenses limitées à l'exercice courant selon la règle métier demandée.

### Phase C — Déversement GESTION-DE-SITES
- [ ] Construire les écritures depuis les pièces sources sans perdre tiers, point de vente, exercice, avoirs liés ou référence d'origine.
- [ ] Valider journal, comptes, tiers, dates et équilibre avant envoi.
- [ ] Déverser en tâche asynchrone avec verrouillage et idempotence.
- [ ] Écran « Déverser », progression, résultat et possibilité de relancer uniquement les opérations en échec.
- [ ] Rapport consultable par journal, période et pièce, avec lignes acceptées/refusées et motif.
- [ ] Ne pas traiter une fiche de réception comme un numéro de devis ; utiliser une référence de devis uniquement si elle existe réellement et est unique.

### Phase D — Préparer RH-FLOW
- [ ] Ajouter l'identité de source RH-FLOW et sa clé distincte dans COMPTAFLOW.
- [ ] Définir le format de l'écriture de paie agrégée.
- [ ] Exposer les journaux et comptes du dossier, sélectionner le journal OD réel.
- [ ] Définir la table de correspondance des rubriques de paie vers les comptes.
- [ ] Définir le traitement des tiers salariés et organismes (création contrôlée ou compte collectif).
- [ ] Ajouter tests d'équilibre, période, idempotence, annulation et reprise.

### Phase E — Non-régression et recette
- [ ] Tests Selflow : liaison, vérification, déversement de référentiel, ventes, achats, tiers, exercices, doublons, erreurs et renouvellement/révocation de clé.
- [ ] Tests GESTION-DE-SITES : même batterie sur un dossier isolé.
- [ ] Tests RH-FLOW : OD de paie, journal OD, comptes, tiers, doublon et reprise.
- [ ] Vérifier qu'une clé d'une source ne peut jamais agir sur le dossier ou les écritures d'une autre source.
- [ ] Comparer les balances et journaux avant/après les tests ; aucun écart inattendu sur Selflow.
- [ ] Exécuter les migrations sur une copie de base avant production.
- [ ] Recette finale par un comptable avant activation.

## 8. Critères d'acceptation

Le chantier ne sera considéré terminé que lorsque :
- les trois sources sont identifiées et isolées ;
- les référentiels COMPTAFLOW peuvent être consultés et rapprochés ;
- les tiers manquants sont créés uniquement par le mécanisme natif COMPTAFLOW après validation ;
- chaque déversement est équilibré, traçable et idempotent ;
- un rapport par journal et par opération permet de comprendre chaque résultat ;
- les tests Selflow passent avant et après activation des autres sources ;
- aucune écriture Selflow existante n'a été modifiée par l'intégration GESTION-DE-SITES ou RH-FLOW.

---
Ce document est un plan d'implémentation et de recette. Il ne signifie pas que les endpoints de consultation/création des référentiels, les écrans, les tâches asynchrones ou les tests de bout en bout sont déjà développés.
