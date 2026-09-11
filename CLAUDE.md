# CLAUDE.md — Contexte module infrastructure

## Aperçu (Overview)

`infrastructure` est un module externe Dolibarr de structuration et organisation des documents commerciaux :

- ajout de titres, sous-titres et sous-totaux (jusqu'à 9 niveaux hiérarchiques) sur propositions, commandes, factures et documents fournisseurs,
- insertion de lignes de texte libre entre les lignes standards,
- réorganisation des lignes par glisser-déposer (drag & drop),
- options d'affichage PDF : masquage, impression en liste ou en mode condensé, répétition d'en-tête,
- dictionnaire de textes libres réutilisables (`c_infrastructure_free_text`),
- sommaire rapide flottant (depuis 3.30.1) pour naviguer entre titres dans les documents longs,
- support des factures de situation (avancement de travaux) avec préservation des structures.

Informations module (issues du code et du changelog local) :

- Éditeur : InfraS - Sylvain Legrand (fork maintenu, basé sur l'original ATM Consulting)
- Numéro module : `550090`
- Licence : GPL v3+
- Compatibilité Dolibarr : `21.0.0` à `24.x.x`
- Compatibilité PHP : `7.4` à `8.4`
- Dernière version locale : `21.8.2` (2026-09)
- Schéma de numérotation : depuis `18.1.0`, le module aligne sa version majeure sur la version minimale de Dolibarr supportée (même convention que `infraspackplus`). Format : `<dolibarrMin>.<mineur>.<patch>`. Les versions antérieures (jusqu'à `3.30.1`) suivaient une numérotation indépendante.
- Dépendance obligatoire : aucune
- Conflit : module **Milestone/Jalon** (iNodbox) — les deux modules ne peuvent pas être activés simultanément
- Emplacement : `htdocs/custom/infrastructure/`

Convention de lecture du descripteur :

- Explications fonctionnelles en français
- Identifiants techniques conservés en anglais (`hooks`, classes, méthodes, constantes, clés de configuration)

## Structure (Summary)

```text
htdocs/custom/infrastructure/
├── CLAUDE.md
├── LICENSE
├── README.md
├── admin/
│   ├── about.php
│   ├── changelog.php
│   └── infrastructuresetup.php
├── backport/
│   └── v19/
│       └── core/
│           └── class/
│               └── commonhookactions.class.php
├── class/
│   ├── actions_infrastructure.class.php
│   ├── api_infrastructure.class.php
│   ├── infrastructure.class.php
│   ├── staticPdf.model.php
│   └── subInfrastructureJsonResponse.class.php
├── config.php
├── core/
│   ├── lib/
│   │   ├── infrastructure.lib.php
│   │   ├── infrastructureAdmin.lib.php
│   │   ├── infrastructureMigrateSubtotal.lib.php
│   │   └── infrastructureMigrateSoustotal.lib.php
│   ├── modules/
│   │   └── modInfrastructure.class.php
│   ├── tpl/
│   │   ├── infrastructureline_edit.tpl.php
│   │   ├── infrastructureline_total.tpl.php
│   │   ├── infrastructureline_row_document.tpl.php
│   │   ├── infrastructureline_row_shipment.tpl.php
│   │   ├── infrastructureline_row_shipping.tpl.php
│   │   ├── infrastructureline_view.tpl.php
│   │   ├── lineol/
│   │   │   ├── header.tpl.php
│   │   │   └── row.tpl.php
│   │   └── originproductline.tpl.php
│   └── triggers/
│       └── interface_90_modInfrastructure_infrastructuretrigger.class.php
├── css/
│   ├── NeuropolRegular.ttf
│   ├── puentebold.ttf
│   ├── infrastructure.css.php
│   └── summary-menu.css.php
├── docs/changelog.xml
├── img/
├── js/
│   ├── infrastructure.lib.js
│   └── summary-menu.js
├── langs/
│   ├── en_US/infrastructure.lang
│   ├── es_ES/infrastructure.lang
│   ├── fr_FR/infrastructure.lang
│   └── it_IT/infrastructure.lang
├── script/
│   ├── interface.php
│   ├── migrate-from-subtotal.php
│   └── migrate-from-soustotal.php
└── sql/
    ├── data.sql
    ├── llx_c_infrastructure_free_text.sql
    └── update.sql
```

## Descripteur module (Module descriptor : `modInfrastructure`)

Dans `core/modules/modInfrastructure.class.php` :

- **Module parts** :
	- `triggers` : 1 trigger (priorité 90)
	- `tpl` : override `originproductline.tpl.php` + 6 templates dédiés (`infrastructureline_*.tpl.php`)
	- `css` : `/infrastructure/css/infrastructure.css.php` (le CSS `summary-menu.css.php` est chargé à la volée par `actions_infrastructure`)
	- `hooks` : 25 contextes (`invoicecard`, `invoicesuppliercard`, `propalcard`, `supplier_proposalcard`, `ordercard`, `ordersuppliercard`, `odtgeneration`, `orderstoinvoice`, `orderstoinvoicesupplier`, `admin`, `invoicereccard`, `consumptionthirdparty`, `ordershipmentcard`, `expeditioncard`, `deliverycard`, `paiementcard`, `referencelettersinstacecard`, `shippableorderlist`, `propallist`, `orderlist`, `invoicelist`, `supplierorderlist`, `supplierinvoicelist`, `cron`, `pdfgeneration`, `checkmarginlist`)
- **Dépendances** : aucune
- **Conflit** : `modMilestone` (iNodbox) — `conflictwith = array('modMilestone')`
- **Dictionnaires** : 1 (`c_infrastructure_free_text` — colonnes `rowid`, `label`, `content`, `active`, `entity`)
- **Boxes** : aucune
- **Cron** : aucune tâche
- **Permissions** : aucune (accès via les droits Dolibarr standards des documents concernés)
- **ExtraFields** : créés automatiquement à l'activation sur `propaldet`, `commandedet`, `facturedet`, `supplier_proposaldet`, `commande_fournisseurdet`, `facture_fourn_det`
- **Famille** : `Modules InfraS` (ou `easya` si la constante `EASYA_VERSION` est présente)
- **Constantes prédéfinies** (dans `$this->const`) :
	- `INFRASTRUCTURE_PDF_TITLE_STYLE_IF_HIDDEN_LINES` (défaut `I`)
	- `INFRASTRUCTURE_ALLOW_ADD_BLOCK` / `INFRASTRUCTURE_ALLOW_EDIT_BLOCK` / `INFRASTRUCTURE_ALLOW_REMOVE_BLOCK` (défaut `1`)
	- `INFRASTRUCTURE_TITLE_STYLE` (défaut `BU`) — sert aussi de fallback PDF si `INFRASTRUCTURE_PDF_TITLE_STYLE` est vide
	- `INFRASTRUCTURE_TOTAL_STYLE` (défaut `B`) — idem fallback PDF si `INFRASTRUCTURE_PDF_TOTAL_STYLE` est vide
	- D'autres constantes par défaut (~30) sont chargées via `sql/data.sql` lors de l'activation

### Initialisation (Lifecycle : `init()`)

`init()` effectue dans l'ordre :

1. Vérification du conflit avec `modMilestone` (blocage si activé)
2. Chargement SQL via `_load_tables('/infrastructure/sql/')` : création de `llx_c_infrastructure_free_text` et insertion des constantes `data.sql`
3. **Migration depuis le module `subtotal` si `isModEnabled('subtotal')`** (voir section dédiée). En cas d'échec, `$this->error` est positionné et `init()` retourne `0` → activation annulée
4. Création des ExtraFields sur les 6 tables de lignes :
	- `show_total_ht` / `show_reduc` / `infrastructure_show_qty` (sur sous-totaux)
	- `hideblock` / `show_table_header_before` / `print_as_list` / `print_condensed` (sur titres)
5. Désactivation conditionnelle du sommaire rapide si `oblyon` actif avec `MAIN_MENU_INVERT`
6. Appel de `$this->_init()` standard

### Désactivation (Lifecycle : `remove()`)

`remove()` délègue à `_remove()` sans nettoyage spécifique : ExtraFields et constantes sont conservés pour permettre une réactivation sans perte de données.

### Migration depuis le module subtotal (Migration from subtotal)

Le module `infrastructure` est un fork/remplacement du module `subtotal` (ATM Consulting). À l'activation, si `isModEnabled('subtotal')` est vrai, une migration automatique est déclenchée depuis `init()`.

**Fichiers impliqués** :

- `core/lib/infrastructureMigrateSubtotal.lib.php` :
	- `infrastructure_migrateFromSubtotal($db, $conf, $dryRun, $logger)` — migration atomique (transaction), retour `['success' => bool, 'errors' => string[]]`
	- `infrastructure_cleanupSubtotal($db, $conf, $logger)` — désactivation + nettoyage, retour `1` / `0`
- `script/migrate-from-subtotal.php` — wrapper CLI/web (admin requis, simulation par défaut)

**Séquence dans `init()`** : dry-run → exécution réelle → cleanup. Toute étape qui échoue annule l'activation.

**Opérations de migration** :

| Étape | Opération |
|-------|-----------|
| 1/3 — Constantes `llx_const` | `SUBTOTAL_*` → `INFRASTRUCTURE_*` (gestion des doubles occurrences `SUBTOTAL_SUBTOTAL_*`). Cas particulier : `NO_TITLE_SHOW_ON_EXPED_GENERATION` → `INFRASTRUCTURE_NO_TITLE_SHOW_ON_EXPED_GENERATION`. Si la constante cible existe déjà pour la même entity, l'ancienne est supprimée |
| 2/3 — ExtraField `subtotal_show_qty` → `infrastructure_show_qty` | Sur les 6 tables de lignes : `UPDATE llx_extrafields` + `ALTER TABLE ..._extrafields CHANGE COLUMN`. Les autres ExtraFields (`show_total_ht`, `show_reduc`, `hideblock`, `show_table_header_before`, `print_as_list`, `print_condensed`) ont des noms identiques |
| 3/3 — Dictionnaire `c_subtotal_free_text` → `c_infrastructure_free_text` | Copie des lignes absentes de la cible (dédoublonnage `label` + `entity`) |

**Opérations de cleanup** : appel de `modSubtotal->remove('')`, suppression des résidus `MAIN_MODULE_SUBTOTAL*` et `SUBTOTAL_*` dans `llx_const`, `DROP TABLE IF EXISTS llx_c_subtotal_free_text`.

**Utilisation manuelle** :

- Web simulation (par défaut) : `.../custom/infrastructure/script/migrate-from-subtotal.php`
- Web exécution : `?confirm=yes` (+ `&cleanup=yes` pour nettoyer)
- CLI : `php migrate-from-subtotal.php confirm [cleanup]`

Clés de traduction : `InfrastructureMigrateSubtotalFailed`, `InfrastructureMigrateSubtotalRealRunFailed`, `InfrastructureCleanupSubtotalFailed`.

### Migration depuis le module soustotal (Iouston — modSousTotal)

En plus du Sous-Total d'ATM, le module remplace aussi le **Sous-Total de Iouston** (`modSousTotal`, numéro 446160), dont le **modèle de données est structurellement différent** (aucun renommage de constantes ne s'applique). À l'activation, si `isModEnabled('soustotal')` est vrai, une migration dédiée est déclenchée depuis `init()`.

**Différence de modèle** (source Iouston vs cible infrastructure) :

- Lignes spéciales identifiées **uniquement** par l'extrafield `options_soustotal_type` (1 = titre, 2 = sous-total, 3 = texte libre) et le niveau par `options_soustotal_level` — `special_code`/`qty`/`product_type` ne sont **pas fiables** (la migration ATM→soustotal les avait remis à 0). Cible : `special_code = 550090` + `product_type = 9` + `qty` (titre = niveau ; sous-total = 100 − niveau ; texte libre = 50).
- Saut de page `options_soustotal_page_break` → `info_bits = 8`. Repli `options_soustotal_hidden` → extrafield `hideblock`.
- Dictionnaire `c_predefined_texts` (colonnes `rowid, code, label, description, rang, color, entity, active`) → `c_infrastructure_free_text` (`description` → `content`).
- Constantes `SOUSTOTAL_*` (couleurs **par niveau** `SOUSTOTAL_NIVEAU_%d_{PDF|FICHE}_*`) → `INFRASTRUCTURE_*` (couleurs **globales**), référence **NIVEAU_1**, le sous-total reprenant la couleur du titre.

**Fichiers impliqués** :

- `core/lib/infrastructureMigrateSoustotal.lib.php` :
	- `infrastructure_migrateFromSoustotal($db, $conf, $dryRun, $logger)` — migration atomique (transaction), retour `['success' => bool, 'errors' => string[]]`
	- `infrastructure_cleanupSoustotal($db, $conf, $logger)` — désactivation + nettoyage, retour `1` / `0`
- `script/migrate-from-soustotal.php` — wrapper CLI/web (admin requis, simulation par défaut)

**Séquence dans `init()`** : dry-run → exécution réelle → cleanup. Bloc **placé après la création des ExtraFields** (le report `soustotal_hidden` → `hideblock` écrit dans la colonne `hideblock`, qui doit exister au préalable). Toute étape qui échoue annule l'activation. Bloc indépendant de celui du subtotal ATM (les deux modules ont des noms techniques distincts : `subtotal` vs `soustotal`).

**Opérations de migration** :

| Étape | Opération |
|-------|-----------|
| 1/3 — Lignes de documents | Sur les 6 tables `*det` : `UPDATE ... JOIN *_extrafields` — réencode `special_code`/`product_type`/`qty` selon `soustotal_type`+`soustotal_level` (garde d'idempotence `special_code <> 550090`), `info_bits = 8` si `soustotal_page_break`, `hideblock = 1` si `soustotal_hidden` (gardé par `SHOW COLUMNS`), backfill de la description depuis `c_predefined_texts` si vide |
| 2/3 — Dictionnaire | `c_predefined_texts` → `c_infrastructure_free_text` (`description` → `content`, dédoublonnage `label` + `entity`) |
| 3/3 — Constantes | **Multi-entité** : découvre les entités possédant des `SOUSTOTAL_*`, puis pour chacune mappe récapitulatifs par document, couleurs et styles (B/U/I) du NIVEAU_1 → `INFRASTRUCTURE_*` (upsert SQL direct, pas de dépendance à `dolibarr_set_const`) |

**Opérations de cleanup** : appel de `modSousTotal->remove('')`, suppression des résidus `MAIN_MODULE_SOUSTOTAL*` et `SOUSTOTAL_*` dans `llx_const`, `DROP TABLE IF EXISTS llx_c_predefined_texts`, suppression des ExtraFields `soustotal_*` orphelins (définitions + colonnes). Les opérations DDL (`DROP TABLE`/`ALTER TABLE`) auto-commitent en MySQL/MariaDB — le cleanup n'est donc pas rejouable par rollback et ne doit s'exécuter qu'après une migration réelle réussie.

**Utilisation manuelle** :

- Web simulation (par défaut) : `.../custom/infrastructure/script/migrate-from-soustotal.php`
- Web exécution : `?confirm=yes` (+ `&cleanup=yes` pour nettoyer)
- CLI : `php migrate-from-soustotal.php confirm [cleanup]`

Clés de traduction : `InfrastructureMigrateSoustotalFailed`, `InfrastructureMigrateSoustotalRealRunFailed`, `InfrastructureCleanupSoustotalFailed`.

## Fonctionnement principal (Core behavior)

Le module s'appuie sur :

- `class/actions_infrastructure.class.php` — classe `ActionsInfrastructure`, hooks d'injection sur les documents,
- `class/infrastructure.class.php` — classe métier `TInfrastructure` (identification des lignes, calculs, manipulations — toutes méthodes statiques),
- `class/api_infrastructure.class.php` — classe `Infrastructure` (API REST),
- `core/lib/infrastructure.lib.php` — helpers génériques,
- `core/lib/infrastructureAdmin.lib.php` — helpers des pages d'administration (onglets, backup/restore, changelog),
- `core/tpl/originproductline.tpl.php` — override du rendu des lignes spéciales lors de la copie depuis un document d'origine,
- `core/tpl/infrastructureline_*.tpl.php` — templates dédiés (vue / édition / sous-total / row document / row shipment / row shipping),
- `core/triggers/interface_90_modInfrastructure_infrastructuretrigger.class.php` — préservation des structures sur événements documentaires,
- `js/infrastructure.lib.js` — helpers drag & drop et titres,
- `js/summary-menu.js` — sommaire rapide flottant (depuis 3.30.1),
- `css/infrastructure.css.php` + `css/summary-menu.css.php` — styles (adaptation oblyon automatique).

### Types de lignes spéciales

Le module ajoute 3 types de lignes spéciales identifiées par `special_code = 550090` (numéro du module) et `product_type = 9`. Le type est distingué par la valeur de `qty` :

| Type | Valeur `qty` | Description |
|------|-------------|-------------|
| **Titre** | 1 à 9 | En-tête de section (niveaux 1 à 9) |
| **Sous-total** | 91 à 99 | Ligne de totalisation intermédiaire (niveaux 1 à 9) |
| **Texte libre** | 50 | Bloc de texte explicatif |

Le niveau d'un titre/sous-total est accessible via `TInfrastructure::getNiveau(&$line)`.

### Logique de calcul des sous-totaux

Pour un sous-total à la position N, on remonte dans les lignes précédentes jusqu'au titre parent (ou au sous-total de même niveau), puis on somme :

- **Total HT** — lignes standards du bloc,
- **Quantité totale** — si option `infrastructure_show_qty` activée,
- **Réduction totale** — si option `show_reduc` activée,
- **TVA** — répartition par taux.

Le calcul exclut automatiquement :

- les lignes spéciales du module (titres, sous-totaux, textes libres) — détection via `TInfrastructure::isModInfrastructureLine()`,
- les lignes masquées par `hideblock = 1` sur le titre parent,
- les lignes de remise du module `infrasdiscount` (via leur `special_code`).

Implémentation principale : `TInfrastructure::getTotalBlockFromTitle(&$object, &$line)`.

### Gestion du drag & drop

Drag & drop natif Dolibarr (`ajaxBlockOrderJs($object)`) renforcé par `js/infrastructure.lib.js` pour la détection des lignes filles d'un titre (`getInfrastructureTitleChilds`). Les sauvegardes de rang passent par `script/interface.php` (endpoint AJAX). Sous-totaux recalculés automatiquement après réorganisation.

### Sommaire rapide flottant (depuis 3.30.1)

Bouton flottant (coin inférieur droit) dépliant un menu listant tous les titres du document. Désactivable via `INFRASTRUCTURE_DISABLE_SUMMARY` et adapté automatiquement au thème `oblyon` (compensation des barres sticky `FIX_AREAREF_CARD` et `FIX_STICKY_TABS_CARD`).

## Hooks et comportement (Hook behavior)

La classe `ActionsInfrastructure` (`class/actions_infrastructure.class.php`) expose les méthodes de hook suivantes :

| Méthode | Contextes utilisés | Rôle |
|---------|-------------------|------|
| `printFieldListSelect` | `consumptionthirdparty` | Injection dans la liste de consommation du tiers |
| `printFieldListWhere` | `propallist`, `orderlist`, `invoicelist`, `supplierorderlist`, `supplierinvoicelist`, `shippableorderlist`, `checkmarginlist` | Exclusion des lignes spéciales des listes / recherches |
| `editDictionaryFieldlist` / `createDictionaryFieldlist` | `admin` | Champs spécifiques du dictionnaire `c_infrastructure_free_text` |
| `formObjectOptions` | cartes de documents | Injection de formulaires et du sommaire JS |
| `formBuilddocOptions` | cartes de documents | Options dans la zone de génération PDF (récap, etc.) |
| `addMoreActionsButtons` | cartes de documents | Boutons d'action : ajouter titre, sous-total, texte libre, dupliquer |
| `doActions` | cartes de documents | Traitement add/edit/remove de blocs, duplicate, hideblock |
| `printObjectLine` | cartes de documents | Override complet du rendu des lignes spéciales (titres, sous-totaux, textes libres). Le tpl `infrastructureline_row_document.tpl.php` rend lui-même la cellule « Opt » (checkbox pour titres, vide pour totaux/freetexts) quand `INFRASTRUCTURE_MANAGE_OL` est actif |
| `infrasprojectEnrichObjectLineTitle` | cartes de documents | Sous-hook exposé par InfraSProject : retourne via `$this->resprints` le `<th class="infrastructure_ol">Opt</th>` que InfraSProject injecte dans son en-tête juste avant `.linecolmove` (cf. *Colonne « Opt »* dans les notes techniques) |
| `infrasprojectEnrichObjectLine` | cartes de documents | Sous-hook exposé par InfraSProject : retourne via `$this->resprints` le `<td class="infrastructure_ol">` avec checkbox pour les lignes standards (chaîne vide pour les lignes spéciales infrastructure, déjà gérées par le tpl `infrastructureline_row_document.tpl.php`) |
| `printOriginObjectLine` / `printOriginObjectSubLine` | création depuis objet d'origine | Affichage des lignes spéciales dans les tables d'origine |
| `ODTSubstitutionLine` | `odtgeneration` | Substitution de variables dans les documents ODT |
| `pdf_writelinedesc` | `pdfgeneration` | Hook natif Dolibarr : construit le libellé de chaque ligne spéciale avant rendu (sur les sous-totaux : concaténation du libellé complet du titre parent si `INFRASTRUCTURE_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL`, sinon ajout de sa seule numérotation si `INFRASTRUCTURE_USE_NUMEROTATION`), gère les sauts de page puis délègue à `pdfAddTitle` / `pdfAddTotal` |
| `pdfAddTitle` / `pdfAddTotal` | `pdfgeneration` | Rendu PDF spécifique des titres et sous-totaux |
| `beforePDFCreation` | `pdfgeneration` | Préparation des lignes (factures de situation, recap), applique la numérotation (`infrastructure_addNumerotation()`) |
| `afterPDFCreation` | `pdfgeneration` | Post-traitement (page récap si configuré) |
| `beforePercentCalculation` | `pdfgeneration` | Support des factures de situation |
| `changeRoundingMode` | `pdfgeneration` | Ajustement arrondis TVA sur blocs condensés |
| `defineColumnField` | `pdfgeneration` | Colonnes personnalisées dans les PDF |
| `isModInfrastructureLine` | génération PDF / autres modules | Test d'appartenance pour modules tiers (InfraSDiscount, marge) |
| `getlinetotalremise` | `pdfgeneration` | Remplacement du calcul de total de remise par ligne |
| `afterCreationOfRecurringInvoice` | `invoicereccard` | Préserve les structures à la création depuis modèle récurrent |
| `printCommonFooter` | tous contextes | Injection de scripts communs en pied de page |
| `updateTotalPrice` | cartes de documents | Si `INFRASTRUCTURE_MANAGE_OL` et au moins une ligne OL (`special_code = 3`) : remplace `update_price()` pour exclure ces lignes des totaux du document |
| `displayMarginInfos` | cartes de documents | Si `INFRASTRUCTURE_MANAGE_OL` et au moins une ligne OL : recalcule `$parameters['marginInfo']` (passé par référence par `FormMargin::displayMarginInfos()`) via le calcul natif `getMarginInfosArray()` sur une copie du document privée des lignes OL, pour aligner le tableau des marges sur le Montant HT (21.8.2+) |

### Flux des hooks (Hook workflow)

```
Utilisateur ouvre une fiche document
    ↓
formObjectOptions() : sommaire JS + formulaires modaux de saisie
    ↓
addMoreActionsButtons() : boutons « Ajouter titre / sous-total / texte libre / dupliquer »
    ↓
doActions() : traitement des soumissions
    - add_title       → qty=1..9, product_type=9, special_code=550090
    - add_infrastructure → qty=91..99
    - add_freetext    → qty=50
    - edit_* / duplicate / hideblock / remove_block
    ↓
printObjectLine() : router vers infrastructureline_row_document/shipment/shipping.tpl.php
    ↓
JS (infrastructure.lib.js + drag & drop core) : réorganisation AJAX via script/interface.php
    ↓
Génération PDF : pdfAddTitle / pdfAddTotal / beforePDFCreation / afterPDFCreation
```

### Modes d'affichage PDF

Trois modes contrôlés par ExtraFields portés par le titre :

| Mode | ExtraField | Comportement |
|------|-----------|-------------|
| **Standard** | — | Affichage complet en mode tableau |
| **Liste** | `print_as_list = 1` | Contenu rendu sous forme de liste à puces |
| **Condensé** | `print_condensed = 1` | Affichage compact (agrégation selon options) |

Option complémentaire : `hideblock = 1` masque le détail dans le PDF (seul le titre et le sous-total restent visibles).

## Trigger et comportement (Trigger behavior)

Classe `InterfaceInfrastructuretrigger` dans `core/triggers/interface_90_modInfrastructure_infrastructuretrigger.class.php` (priorité 90).

### Événements écoutés

| Événement | Action |
|-----------|--------|
| `LINEPROPAL_INSERT` | Ajout de ligne sous un titre en cours d'édition (`AddLineUnderTitle`) |
| `LINEORDER_INSERT` | Idem sur commande |
| `LINEBILL_INSERT` | Idem + logique d'insertion spécifique facture (`LineInvoiceInsert`) |
| `LINEBILL_SUPPLIER_CREATE` | Idem sur facture fournisseur |

### Méthodes principales

| Méthode | Rôle |
|---------|------|
| `AddLineUnderTitle(&$object, $action)` | Insère la nouvelle ligne juste après le titre courant |
| `LineInvoiceInsert($object, $user)` | Cas particuliers à l'insertion d'une ligne de facture |
| `ShippingOriginLine` / `ShippingCreate` | Préservation des titres/sous-totaux lors d'expéditions |
| `CreateFromClone` | Préservation des structures lors d'un clone |
| `OrdersToInvoiceBloc` | Regroupe les lignes par commande dans un bloc titre lors d'une facturation groupée |
| `RecurringInvoiceCreate` | Préserve les structures pour les factures récurrentes |
| `SituationPercentReset` / `SituationFinal` | Gestion des factures de situation (avancement de travaux) |
| `ComprisNonCompris` | Gestion de l'option NC (Non Compris) sur les lignes |
| `getShippingList` | Récupération des expéditions d'une commande pour inclusion dans les titres |
| `addToBegin` / `addToEnd` | Helpers publics statiques d'insertion |

Option introduite en 3.26.0 : la référence d'expédition peut être incluse dans le libellé des titres lors de la génération d'expéditions depuis une commande.

**Point de vigilance (depuis v21.1.5)** : `runTrigger()` est invoqué par Dolibarr pour **tous** les événements métier, pas seulement les événements `LINE*`/`SHIPPING_CREATE`/etc. listés ci-dessus — le log de débogage en tête de méthode lisait `$object->id` sans vérifier son existence, provoquant un avertissement PHP « Undefined property » sur des objets qui n'exposent pas cette propriété (ex. `TPropaleHist`, historique de devis). Un test `isset($object->id)` (repli chaîne vide) protège désormais ce log.

## Données / SQL (Data model)

Une table dictionnaire :

| Table | Description |
|-------|-------------|
| `llx_c_infrastructure_free_text` | Bibliothèque de textes libres réutilisables |

Schéma de `llx_c_infrastructure_free_text` (InnoDB) :

| Colonne | Type | Description |
|---------|------|-------------|
| `rowid` | INTEGER AUTO_INCREMENT PRIMARY KEY | Clé primaire |
| `label` | VARCHAR(255) NOT NULL | Libellé du texte libre |
| `content` | TEXT | Contenu HTML |
| `active` | TINYINT DEFAULT 1 NOT NULL | Actif ou non |
| `entity` | INTEGER DEFAULT 1 NOT NULL | Entité multi-société |

Métadonnées des blocs stockées via ExtraFields sur les lignes de documents :

| ExtraField | Tables cibles | Usage |
|------------|---------------|-------|
| `show_total_ht` | 6 tables de lignes | Sous-totaux : afficher le Total HT |
| `show_reduc` | idem | Sous-totaux : afficher la réduction totale |
| `infrastructure_show_qty` | idem | Sous-totaux : afficher la quantité totale |
| `hideblock` | propal/commande/facture + fournisseurs (sauf supplier_proposaldet) | Titres : masquer les lignes du bloc |
| `show_table_header_before` | 6 tables | Titres : répéter l'en-tête avant ce titre |
| `print_as_list` | 6 tables | Titres : impression en liste à puces |
| `print_condensed` | 6 tables | Titres : impression condensée |

## Constantes de configuration (Key settings)

Constantes actives usuelles (voir `sql/data.sql` et la page `admin/infrastructuresetup.php` pour la liste complète) :

- **Permissions globales** : `INFRASTRUCTURE_ALLOW_ADD_BLOCK` / `_EDIT_BLOCK` / `_REMOVE_BLOCK` / `_DUPLICATE_BLOCK` / `_DUPLICATE_LINE` / `_ADD_LINE_UNDER_TITLE`
- **Comportement d'insertion** : `INFRASTRUCTURE_ADD_LINE_UNDER_TITLE_AT_END_BLOCK`, `INFRASTRUCTURE_AUTO_ADD_TOTAL_ON_ADDING_NEW_TITLE`
- **ExtraFields sur titres** : `INFRASTRUCTURE_ALLOW_EXTRAFIELDS_ON_TITLE`
- **Numérotation automatique des titres** : `INFRASTRUCTURE_USE_NUMEROTATION` — préfixe hiérarchique (`1`, `1.2`, ...) ajouté au libellé des titres par `infrastructure_addNumerotation()` / `infrastructure_formatNumerotation()` (`core/lib/infrastructure.lib.php`), appliqué dans `beforePDFCreation()` avant génération PDF
- **Concaténation labels (PDF)** : `INFRASTRUCTURE_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL` — ajoute le libellé complet du titre parent en fin de libellé du sous-total sur le PDF (`pdf_writelinedesc()`) ainsi que dans les cas hérités où `$line->label` est vide (contexte shipment, ODT). Sans effet sur l'affichage écran du cas courant (sous-total avec label rempli, ex. `21.1.4`+) : voir `INFRASTRUCTURE_SCREEN_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL` ci-dessous. Si inactif et que `INFRASTRUCTURE_USE_NUMEROTATION` est actif, seule la numérotation du titre parent (ex. `1.2`) est ajoutée en fin de libellé du sous-total **sur le PDF** (`infrastructure_getNumerotation()`, appelée depuis `pdf_writelinedesc()`)
- **Concaténation labels (écran)** (21.2.0+) : `INFRASTRUCTURE_SCREEN_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL` — ajoute le libellé complet du titre parent en fin de libellé du sous-total sur l'écran de la fiche document (`core/tpl/infrastructureline_total.tpl.php`, contexte `document`), y compris quand `$line->label` est déjà rempli (cas normal depuis l'ajout d'un libellé par défaut « Sous-total »). Constante indépendante de la variante PDF ci-dessus
- **Styles écran** : `INFRASTRUCTURE_TITLE_STYLE` (défaut `BU`), `INFRASTRUCTURE_TOTAL_STYLE` (défaut `B`) — fallback pour PDF si versions PDF vides
- **Styles PDF** (18.3.0+) : `INFRASTRUCTURE_PDF_TITLE_STYLE`, `INFRASTRUCTURE_PDF_TOTAL_STYLE` (écrasent la version écran)
- **Totaux sur titres** (18.4.0+) : `INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL` (reporte Total HT et taux TVA du bloc directement sur la ligne de titre, supprime l'impression des sous-totaux)
- **Styles spéciaux** : `INFRASTRUCTURE_PDF_TITLE_STYLE_IF_HIDDEN_LINES` (défaut `I`)
- **Couleurs** : `INFRASTRUCTURE_TITLE_BACKGROUND_COLOR` / `_TOTAL_BACKGROUND_COLOR` / `_TITLE_COLOR` / `_TOTAL_COLOR` / `_TITLE_COLOR_BLOC` / `_TEXT_LINE_COLOR` (21.6.0+, défaut `000000`) — couleur dédiée aux lignes de texte libre (libellé dans `infrastructureline_view.tpl.php`, icônes Éditer/Supprimer dans `infrastructureline_row_document.tpl.php`), auparavant confondue avec `_TOTAL_COLOR` pour les icônes et absente pour le libellé
- **Affichage quantités sous-totaux** : `INFRASTRUCTURE_DEFAULT_DISPLAY_QTY_FOR_TOTAL_ON_ELEMENTS` (CSV) + variante PDF `_PDF` (18.3.0+)
- **Pliage** : `INFRASTRUCTURE_BLOC_FOLD_MODE` (`default` / `keepTitle` / `hideAll`), `INFRASTRUCTURE_HIDE_FOLDERS_BY_DEFAULT` (3.28.0+)
- **TVA** : `INFRASTRUCTURE_LIMIT_TVA_ON_CONDENSED_BLOCS` (3.28.4+)
- **Récapitulatif PDF** : `INFRASTRUCTURE_PROPAL_ADD_RECAP` / `_COMMANDE_ADD_RECAP` / `_INVOICE_ADD_RECAP`, `INFRASTRUCTURE_KEEP_RECAP_FILE`
- **Marge sur sous-totaux** : `INFRASTRUCTURE_DISPLAY_MARGIN_ON_TOTAL` (affiche le taux de marge et/ou le taux de marque du bloc dans les colonnes natives correspondantes, suivant les options Dolibarr `DISPLAY_MARGIN_RATES` / `DISPLAY_MARK_RATES` — 21.7.2+). Option dépendante (masquée si la précédente est inactive) : `INFRASTRUCTURE_DISPLAY_COST_PRICE_ON_TOTAL` (21.8.0+, désactivée par défaut) — affiche le prix de revient total cumulé du bloc dans la colonne montant (native PA HT), qui reste vide par défaut depuis que la marge brute n'y est plus affichée systématiquement
- **Lignes optionnelles (OL)** : `INFRASTRUCTURE_MANAGE_OL` (active la case « Opt »), `INFRASTRUCTURE_OL_REDUCE_PA` (vide aussi le prix de revient), `INFRASTRUCTURE_OL_SHOW_DETAILS` (21.4.0+, désactivée par défaut) — conditionne l'affichage du cumul « options non incluses » dans le libellé du sous-total (`infrastructureline_total.tpl.php`) et l'affichage de la quantité individuelle des lignes optionnelles dans les templates `lineviews` d'InfraSProject/InfraSPackPlus (sans cette option, la quantité reste masquée comme une ligne « Option » native Dolibarr classique), `INFRASTRUCTURE_PDF_OL_SHOW_DETAILS` (21.5.0+, désactivée par défaut) — équivalent PDF : même annotation dans le libellé du sous-total PDF (`pdf_writelinedesc()`, précédée d'un `<br />` depuis 21.7.1) et même déblocage de la quantité affichée pour les lignes optionnelles en PDF (`pdf_getlineqty()`), indépendante de la variante écran. Options dépendantes de `INFRASTRUCTURE_PDF_OL_SHOW_DETAILS` (visibles uniquement si celle-ci est active, cf. *Style, couleur et détails PDF des lignes optionnelles*) : `INFRASTRUCTURE_PDF_OL_SHOW_TOTAL_HT_AFTER_DESC` (21.7.1+, désactivée par défaut) — ajoute le Total HT de chaque ligne optionnelle sur une nouvelle ligne, juste après sa description, en PDF ; `INFRASTRUCTURE_PDF_OL_STYLE` / `INFRASTRUCTURE_PDF_OL_COLOR` (21.7.1+) — style (gras/souligné/italique) et couleur de texte appliqués à toutes les colonnes des lignes optionnelles en PDF
- **UI** : `INFRASTRUCTURE_HIDE_OPTIONS_BUILD_DOC`, `INFRASTRUCTURE_DISABLE_SUMMARY`, `INFRASTRUCTURE_FORCE_EXPLODE_ACTION_BTN`, `INFRASTRUCTURE_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC`
- **Expéditions** : `NO_TITLE_SHOW_ON_EXPED_GENERATION`
- **Offsets PDF** : `INFRASTRUCTURE_PDF_TITLE_BACKGROUND_CELL_HEIGHT_OFFSET` / `_POS_Y_OFFSET`, `INFRASTRUCTURE_PDF_TOTAL_BACKGROUND_CELL_HEIGHT_OFFSET` / `_POS_Y_OFFSET`

Point de vigilance : la liste complète des constantes par défaut (~30) est dans `sql/data.sql`. Toute modification manuelle est persistante tant que le module n'est pas désactivé.

## Compatibilité modules tiers (Third-party module compatibility)

Le module est explicitement interopérable avec :

- **Sous-Total** (ATM Consulting) — version originale ; **remplacement automatique** à l'activation
- **Sous-Total** (Iouston — `modSousTotal`) — variante à modèle de données distinct ; **remplacement automatique** à l'activation (voir *Migration depuis le module soustotal*)
- **Milestone / Jalon** (iNodbox) — **CONFLIT BLOQUANT**
- **Ouvrage / Forfait** (Inovea), **Équipement** (Patas-Monkey), **Custom Link** (Patas-Monkey), **Note de Frais Plus** (Mikael Carlavan), **Ultimate** (ATM Consulting)
- **InfraSPackPlus** (InfraS) — support complet des structures dans les modèles PDF (InfraSPlus_Propal, InfraSPlus_Facture, etc.)
- **InfraSDiscount** (InfraS) — exclusion automatique des lignes spéciales infrastructure des calculs de remise via `infrasdiscount_isInfrastructureLine()`
- **Oblyon** (Inovea / InfraS) — CSS du sommaire flottant adapté ; gestion des barres sticky compensée en JS

## Conventions de développement (Development conventions)

Respecter les règles Dolibarr du dépôt parent :

- compatibilité PHP (code base Dolibarr : 7.1–8.4 ; module : 7.4–8.4 selon changelog),
- pas de framework lourd, pas de Composer en core,
- entrées utilisateur via `GETPOST*`,
- constantes via `getDolGlobalString()`, `getDolGlobalInt()`, `getDolGlobalBool()`,
- SQL sécurisé : cast `int`, échappement `$db->escape()` / `$db->escapeforlike()`,
- gestion multi-entité via `entity` / `getEntity('c_infrastructure_free_text')`,
- hooks : retourner 0 (continuer), 1 (remplacer le code standard) ou <0 (erreur),
- respect du marquage `// InfraS change` / `// InfraS add` pour les modifications ciblées dans les fichiers existants.

## Workflow recommandé après changements structurels (Recommended workflow)

Si modification SQL / descripteur / ExtraFields / hooks / trigger :

1. Désactiver puis réactiver le module
2. Vérifier les ExtraFields sur les 6 tables de lignes
3. Vérifier les constantes module (`INFRASTRUCTURE_*`)
4. Vérifier le dictionnaire `c_infrastructure_free_text`
5. Tester l'ajout de titre / sous-total / texte libre sur un devis
6. Tester le drag & drop (y compris déplacement de bloc complet)
7. Tester la conversion devis → commande → facture (préservation des structures)
8. Tester la génération PDF dans les trois modes (standard, liste, condensé)
9. Tester le sommaire rapide flottant sur un document avec plusieurs titres
10. Si thème `oblyon` actif : tester le scroll du sommaire avec `FIX_AREAREF_CARD` / `FIX_STICKY_TABS_CARD`

## Points d'attention (Watchpoints)

- `special_code = 550090` et `product_type = 9` identifient les lignes spéciales — le numéro `550090` est lu via `TInfrastructure::getModuleNumber()` (cache statique) et exposé dans `ActionsInfrastructure->module_number` (aucune valeur en dur dans les classes métier)
- Distinction titre / sous-total / texte libre via `qty` (titre : 1-9, sous-total : 91-99, texte libre : 50)
- Module **incompatible** avec `modMilestone` (iNodbox) — bloqué à l'activation
- La version locale est lue via `infrastructure_getLocalVersionMinDoli('infrastructure')` depuis `docs/changelog.xml`
- Le fork InfraS remplace l'original ATM Consulting ; éditeur affiché : `InfraS - Sylvain Legrand`
- Le drag & drop nécessite `$conf->use_javascript_ajax`
- `originproductline.tpl.php` override le rendu lors de la **copie depuis document d'origine** ; `infrastructureline_*.tpl.php` gèrent les rendus du document courant
- Sommaire rapide automatiquement désactivé si `oblyon` + `MAIN_MENU_INVERT`
- Factures de situation : méthodes de calcul dédiées pour éviter l'accumulation de TVA (DA027405, 3.29.2) ; injection de lignes TVA invisibles pour le calcul Dolibarr (DA027547, 3.29.3)
- Le descripteur référence `class/techatm.class.php` qui n'est plus présent — `dol_include_once` est tolérant et l'absence est silencieuse
- Compatibilité Easya : si `EASYA_VERSION` est définie, la famille de module bascule sur `easya`

## Dernières mises à jour (Recent updates)

Voir `docs/changelog.xml` pour l'historique complet des versions.

## Notes techniques (Technical notes)

### Classe `TInfrastructure` (Business logic)

`class/infrastructure.class.php` — toutes les méthodes sont **statiques**.

#### Identification des lignes

```php
TInfrastructure::isTitle(&$line, $level = -1)         // Détecte un titre (optionnellement d'un niveau donné)
TInfrastructure::isTotal(&$line, $level = -1)         // Détecte un sous-total
TInfrastructure::isFreeText(&$line)                   // Détecte un texte libre
TInfrastructure::isModInfrastructureLine(&$line)      // Toute ligne spéciale du module
TInfrastructure::getNiveau(&$line)                    // Niveau hiérarchique (1-9)
TInfrastructure::hasBreakPage($line)                  // Saut de page associé
TInfrastructure::hasOlTitle(&$line)                   // Titre OL (Optionnel)
```

#### Manipulation des lignes

```php
TInfrastructure::addTitle(&$object, $label, $level, $rang = -1, $desc = '')
TInfrastructure::addTotal(&$object, $label, $level, $rang = -1)
TInfrastructure::addInfrastructureMissing(&$object, $level_new_title)
TInfrastructure::updateRang(&$object, $rang_start, $move_to = 1)
TInfrastructure::duplicateLines(&$object, $lineid, $withBlockLine = false)
TInfrastructure::doUpdateLine(&$object, $rowid, $desc, $pu, $qty, $remise_percent, ...)
```

#### Recherche et parcours

```php
TInfrastructure::getAllTitleFromDocument(&$object, $get_block_total = false)
TInfrastructure::getAllTitleWithoutTotalFromDocument(&$object, $get_block_total = false)
TInfrastructure::getAllTitleFromLine(&$origin_line, $reverse = false)
TInfrastructure::getParentTitleOfLine(&$object, $rang, $lvl = 0)
TInfrastructure::getSubLineOfTitle(&$object, $rang, $lvl = 0)
TInfrastructure::getTotalBlockFromTitle(&$object, &$line, $breakOnTitle = false)
TInfrastructure::getLinesFromTitle(&$object, $key_trad, $level = 1, $under_title = '', $withBlockLine = false, $key_is_id = false)
TInfrastructure::getLinesFromTitleId(&$object, $lineid, $withBlockLine = false)
TInfrastructure::titleHasTotalLine(&$object, &$title_line, $strict_mode = false, $return_rang_on_false = false)
TInfrastructure::getOrderIdFromLineId(int $fk_commandedet, bool $supplier = false)
TInfrastructure::getLastLineOrderId(int $fk_commande, bool $supplier = false)
```

#### Rendu et récapitulatif

```php
TInfrastructure::generateDoc(&$object)                                     // Régénère document (PDF + update_price)
TInfrastructure::addRecapPage(&$parameters, &$origin_pdf, $fromInfraS = 0) // Page récap en fin de PDF
TInfrastructure::concat(&$outputlangs, $files, $fileoutput = '')           // Concaténation de PDF
TInfrastructure::getFreeTextHtml(&$line, $readonly = 0)                    // HTML d'un texte libre
TInfrastructure::getTitleLabel($line)                                      // Libellé d'un titre
TInfrastructure::getHtmlDictionnary(): string                              // HTML du sélecteur du dictionnaire
TInfrastructure::getCommonVATRate($object, $lineRef)                       // Taux TVA commun d'un ensemble
```

### Système de niveaux (Level system)

Gestion hiérarchique via la valeur `qty` :

| Type | Plage `qty` | Niveaux |
|------|-------------|---------|
| Titre | 1-9 | 1 = principal, 2..9 = sous-titres |
| Sous-total | 91-99 | 91 = principal, 92..99 = sous-sous-totaux |
| Texte libre | 50 | — |

Le niveau détermine l'indentation et le style d'affichage, la portée du calcul du sous-total (un sous-total de niveau N couvre jusqu'au titre parent de niveau ≤ N), et la hiérarchie visuelle dans les PDF.

### Calcul en cascade des sous-totaux (Cascade calculation)

```
POUR un sous-total à la position N :
  1. Remonter depuis la position N-1 vers 0
  2. S'arrêter au premier titre de niveau ≤ niveau(sous-total)
     OU au premier sous-total de niveau ≤ niveau(sous-total)
  3. Sommer les lignes standards entre ces bornes
  4. Exclure les lignes spéciales infrastructure (isModInfrastructureLine)
  5. Exclure les lignes masquées (hideblock sur le titre parent)
  6. Exclure les lignes de remise (modules tiers : infrasdiscount, etc.)
```

Exemple :

```
Ligne 1 : Titre « Matériel » (qty=1)
Ligne 2 : Produit A — 100,00 €
Ligne 3 : Produit B — 200,00 €
Ligne 4 : Sous-total « Matériel » (qty=91) → 300,00 €
Ligne 5 : Titre « Services » (qty=1)
Ligne 6 : Service X — 150,00 €
Ligne 7 : Sous-total « Services » (qty=91) → 150,00 €
```

### Gestion des ExtraFields dans les calculs

- **`show_total_ht = 1`** : affiche le montant HT sur la ligne sous-total
- **`show_reduc = 1`** : cumule et affiche le total des réductions du bloc
- **`infrastructure_show_qty = 1`** : cumule et affiche la quantité totale du bloc
- **`hideblock = 1`** (titre) : masque les lignes du bloc, change le style du titre (`INFRASTRUCTURE_PDF_TITLE_STYLE_IF_HIDDEN_LINES`), les lignes cachées restent comptabilisées dans les sous-totaux suivants (selon paramétrage)
- **`show_table_header_before = 1`** (titre) : répète l'en-tête du tableau juste avant ce titre dans le PDF
- **`print_as_list = 1`** (titre) : rendu en liste à puces
- **`print_condensed = 1`** (titre) : rendu condensé ; calcul TVA adapté si `INFRASTRUCTURE_LIMIT_TVA_ON_CONDENSED_BLOCS` activé

### Override de template (Template customization)

`core/tpl/` :

| Fichier | Rôle |
|---------|------|
| `originproductline.tpl.php` | Override du rendu lors de la création depuis un document d'origine |
| `infrastructureline_view.tpl.php` | Cellule libellé (mode consultation) — contextes `'document'` et `'shipment'` |
| `infrastructureline_edit.tpl.php` | Cellule libellé (mode édition) |
| `infrastructureline_total.tpl.php` | Rendu spécifique des lignes sous-total |
| `infrastructureline_row_document.tpl.php` | `<tr>` complet en contexte fiche document principale |
| `infrastructureline_row_shipment.tpl.php` | `<tr>` complet en contexte création d'expédition depuis commande |
| `infrastructureline_row_shipping.tpl.php` | `<tr>` complet en contexte fiche shipping/delivery |

Classes CSS / attributs de données utilisés côté rendu :

- `.infrastructure_label` — libellé principal (ciblé par le sommaire rapide)
- `tr[data-isinfrastructure="title"]`, `tr[data-isinfrastructure="total"]`, `tr[data-isinfrastructure="free-text"]` — distinction au niveau DOM
- `tr[data-level="..."]` — niveau hiérarchique exposé au JS

### Alignement partiel du contexte « shipment » sur le contexte « document » (depuis 21.7.0)

Le contexte `'shipment'` de `infrastructureline_view.tpl.php` couvre 2 blocs distincts de `printObjectLine()` : bloc 2 (création d'expédition depuis une commande, `ordershipmentcard` ou `expeditioncard`+`action=create` — `$object` y est réellement la commande d'origine) et bloc 3 (fiche shipping/delivery existante — `$object` est l'Expedition/Livraison, mais `$line` y est substituée par la ligne de commande d'origine, cf. `printObjectLine()` ~L2131-2143). Cette différence de nature de `$object` explique pourquoi l'alignement n'est que partiel :

- **Style/couleur du libellé (les 2 blocs)** : `INFRASTRUCTURE_TEXT_LINE_STYLE`/`INFRASTRUCTURE_TEXT_LINE_COLOR` s'appliquent désormais uniformément quel que soit `$infrastructureViewContext`, au lieu d'utiliser `INFRASTRUCTURE_TITLE_STYLE` pour tous les types en contexte `'shipment'`. Aucune dépendance CSS/JS trouvée sur l'ancien comportement — changement sans risque.
- **Boutons de pliage des titres (bloc 2 uniquement)** : nouveau paramètre `$infrastructureShowFoldButton` (bool, défaut `false`) sur `infrastructureline_view.tpl.php`, positionné à `true` par `infrastructureline_row_shipment.tpl.php` (bloc 2) uniquement. `ActionsInfrastructure::printCommonFooter()` reconnaît les contextes `ordershipmentcard` et `expeditioncard`+`action=create` pour injecter le JS/CSS de pliage, avec résolution de l'élément porteur des données = la commande d'origine (`Commande`, ou `ucfirst(GETPOST('origin'))` en repli), pas l'Expedition. Persistance de l'état plié (extrafield `hideblock`) déjà fonctionnelle sans changement : `commandedet` porte déjà cet extrafield et `'commande'` est déjà whitelisté dans `script/interface.php`.
- **Bloc 3 délibérément exclu du pliage** : aucun extrafield `hideblock` sur `expeditiondet`/`deliverydet` ; et surtout, `$line->rang` (celui de la ligne de commande substituée) n'est **pas** dans le même espace de numérotation que `$object->lines` (celles de l'Expedition/Livraison, qui ont leur propre `rang` assigné indépendamment à l'insertion) — `TInfrastructure::getParentTitleOfLine($object, $line->rang)` y comparerait des `rang` incompatibles, avec un risque concret de mauvais titre parent détecté (ou `false`), donc de comportement silencieusement faux plutôt qu'une simple limitation cosmétique. Implémenter le pliage sur ce bloc nécessiterait un chaînage d'origine Livraison→Expedition→Commande non trivial, jugé disproportionné pour un gain mineur sur une fiche essentiellement consultative.
- **Sélecteur JS généralisé** : le bouton « Cacher tout / Afficher tout » (JS injecté par `printCommonFooter()`) ciblait en dur `#tablelines`, absent du tableau de la page de création d'expédition (`expedition/card.php?action=create`). Le sélecteur recherche désormais la table ancêtre de la première ligne infrastructure présente sur la page (`$('tr[data-isinfrastructure]').first().closest('table')`), sans dépendance à un id spécifique à une page core Dolibarr.
- **Dispatch vers `infrastructureline_total.tpl.php` (marge/quantité cumulée enrichie) volontairement écarté pour les 2 blocs** : les tableaux shipment/shipping/delivery sont des tableaux logistiques (quantité commandée/expédiée/à expédier, stock, poids, volume) sans aucune colonne financière (pas de PU HT ni Total HT) — il n'existe pas d'emplacement naturel pour une cellule marge/Total HT, et le calcul de colspan de `infrastructureline_total.tpl.php` est finement dépendant de la structure de colonnes du tableau document (marges, multidevise, TVA...), sans équivalent réutilisable ici.

### Interaction avec les factures de situation (Progress invoices)

Traitement spécial réparti entre :

- **Trigger `SituationPercentReset()`** : remet à zéro les pourcentages d'avancement sur titres/sous-totaux
- **Trigger `SituationFinal()`** : gère la dernière facture d'une situation (passage à 100 %)
- **Hook `beforePercentCalculation()`** : intercepte le calcul des pourcentages
- **Hook `beforePDFCreation()`** : injecte les lignes TVA invisibles pour permettre à Dolibarr d'agréger (DA027547, 3.29.3)
- Version 3.29.1 : blocage de la création si la progression est déjà à 100 %
- Version 3.29.2 : correction d'une méthode historique qui accumulait la TVA (DA027405)

### API REST (API endpoints)

La classe `Infrastructure` (`class/api_infrastructure.class.php`) étend `DolibarrApi` et expose :

```
GET /infrastructure/{elementtype}/{idline}
  → getTotalLine() : total calculé d'un bloc sous-total pour une ligne donnée
  → elementtype ∈ { propal, commande, facture, supplier_proposal, supplier_order, supplier_invoice }
```

Helpers internes (`_getTotal`, `_getFkFieldName`) pour abstraire le type de document. Authentification : token API standard Dolibarr (`DOLAPIKEY`).

### Sommaire rapide flottant (Floating quick summary)

Injecté par `actions_infrastructure.class.php::formObjectOptions()` quand `INFRASTRUCTURE_DISABLE_SUMMARY` n'est pas actif. Trois fichiers impliqués :

- **`js/summary-menu.js`** : construit `#infrastructure-summary-floating` avec dropdown listant les titres (`<a class="infrastructure-summary-link">`). Au clic : scroll smooth vers `#row-<lineid>`
- **`css/summary-menu.css.php`** : CSS adapté automatiquement (variables `--bgnavtop*` pour `oblyon` / `--colorbackhmenu1` pour les autres thèmes)
- **Configuration JS** (`infrastructureSummaryJsConf`) injectée par PHP : `langs.InfrastructureSummaryTitle`, `isOblyon`, `fixArearefCard`, `fixStickyTabsCard`

**Compensation du scroll sous oblyon** : quand `FIX_AREAREF_CARD` ou `FIX_STICKY_TABS_CARD` sont actives, `div.arearef` et/ou `div.tabs:first-of-type` deviennent `position: sticky` et masqueraient la ligne cible. Le JS ajoute leur `outerHeight()` à l'offset de scroll quand ces éléments sont effectivement en `position: sticky`.

### Colonne « Opt » (option de ligne) — injection serveur via les sous-hooks InfraSProject / InfraSPackPlus

Depuis `21.0.1`, la colonne « Opt » (libellé lu via la clé de traduction `infrastructure_ol_title`) est rendue **côté serveur**, mais déléguée à un module hôte (InfraSProject ou InfraSPackPlus) pour éviter une double émission de `<thead>` / `<tr>` (le HookManager Dolibarr ne s'arrête pas au 1er retour positif et appelle séquentiellement tous les modules qui implémentent un même hook).

**Architecture — en-tête `<thead>`** (toujours rendu par InfraSProject) :

```
Dolibarr (CommonObject::printObjectLines)
  └─ executeHooks('printObjectLineTitle')
       └─ ActionsInfrasproject::printObjectLineTitle()
            ├─ ob_start
            ├─ include linetitles/v{XX}.tpl.php       (rend le <thead> avec colonne « Projet »)
            ├─ ob_get_clean → $content
            ├─ executeHooks('infrasprojectEnrichObjectLineTitle')
            │    └─ ActionsInfrastructure::infrasprojectEnrichObjectLineTitle()
            │         ├─ vérifie infrastructure_shouldInjectOptColumn
            │         ├─ include core/tpl/lineol/header.tpl.php (capture du <th>)
            │         └─ $this->resprints = "<th class=\"infrastructure_ol\">…</th>"
            ├─ preg_replace : insère resPrint avant <th class="linecolmove">
            └─ print $content + return 1
```

**Architecture — `<tr>` standard** (rendu par InfraSPackPlus en mode view si actif, sinon InfraSProject) :

Le sous-hook `infrasprojectEnrichObjectLine` est appelé indifféremment depuis InfraSProject ou InfraSPackPlus selon le contexte :

- **Mode view + InfraSPackPlus actif** : InfraSProject cède la main via `if ($isView && isModEnabled('infraspackplus')) return 0;`. InfraSPackPlus rend la ligne via `lineviews/v{XX}.tpl.php` et appelle le sous-hook après son include (depuis InfraSPackPlus 21.1.0).
- **Mode edit ou InfraSPackPlus inactif** : InfraSProject rend la ligne (via lineedits ou lineviews) et appelle le sous-hook.

Dans les deux cas, infrastructure répond via `infrasprojectEnrichObjectLine` en retournant `<td class="infrastructure_ol"><input checkbox></td>` via `$this->resprints`.

**Conditions d'activation** (méthode helper `infrastructure_shouldInjectOptColumn`) :

- `$object->statut == 0` (document en brouillon)
- `getDolGlobalString('INFRASTRUCTURE_MANAGE_OL')` actif
- `$action != 'editline'`
- `(int) DOL_VERSION` ∈ `[21, 24]`
- Contexte courant ∈ `invoicecard`, `invoicesuppliercard`, `propalcard`, `supplier_proposalcard`, `ordercard`, `ordersuppliercard`, `invoicereccard`

**Sous-hook `infrasprojectEnrichObjectLineTitle`** (en-tête `<thead>`) :

- Capture `/infrastructure/core/tpl/lineol/header.tpl.php` via `ob_start` (libellé + tooltip via `$form->textwithtooltip()`).
- Affecte le HTML à `$this->resprints`, retourne `0` (non bloquant).
- Le HookManager Dolibarr accumule les `resprints` de tous les modules implémentant le sous-hook dans `$hookmanager->resPrint`, qu'InfraSProject lit puis injecte avant `<th class="linecolmove">`.

**Sous-hook `infrasprojectEnrichObjectLine`** (chaque `<tr>` rendu par InfraSProject) :

- Retourne immédiatement (sans alimenter `resprints`) pour les lignes spéciales infrastructure (`special_code == module_number && product_type == 9`) : leur cellule Opt est déjà rendue par le tpl `infrastructureline_row_document.tpl.php` (avec checkbox pour titres, cellule vide pour sous-totaux et textes libres).
- Pour les lignes standards, capture `/infrastructure/core/tpl/lineol/row.tpl.php` (checkbox initialisée depuis `$line->array_options['options_infrastructure_ol']`) et l'affecte à `$this->resprints`.

**Lignes spéciales infrastructure rendues par notre tpl** : le hook PHP `printObjectLine` du module continue à rendre intégralement le `<tr>` via `infrastructureline_row_document.tpl.php`. InfraSProject `printObjectLine` détecte les lignes à `special_code > 3` et leur cède la main (return 0). Le tpl rend désormais une cellule Opt sur **toutes** les lignes spéciales (titres → checkbox, sous-totaux / textes libres → cellule vide pour préserver l'alignement).

**Partials dédiés** :

- `core/tpl/lineol/header.tpl.php` — `<th>` (Opt + tooltip)
- `core/tpl/lineol/row.tpl.php` — `<td>` (vide pour lignes spéciales infrastructure, checkbox pour lignes standards)

**Logique AJAX (conservée)** : `addMoreActionsButtons` n'imprime plus que le binding `callAjaxUpdateLineOL` sur `$(document).on('change', '.infrastructure_ol_chkbx', ...)` (délégation pour rester robuste si des lignes sont ajoutées dynamiquement). Pour les documents fournisseur, le pré-chargement des extrafields des lignes (`fetch_optionals`) reste effectué dans ce hook : sans cela `$line->array_options['options_infrastructure_ol']` serait vide au moment du rendu serveur.

**Avantages vs ancien mécanisme (jQuery DOM injection avant 21.0.0)** :

- Rendu côté serveur → pas de dépendance JS pour l'affichage initial
- Suppression de l'heuristique DOM (`children('th:last-child').length > 0` pour distinguer V20 vs V21+)
- Pas de risque de double-injection lors d'un re-render AJAX
- Erreurs serveur visibles dans `dolibarr.log`

**Fallback JavaScript (depuis 21.0.2)** — autonomie totale :

Pour garantir l'affichage de la colonne « Opt » dès lors qu'`INFRASTRUCTURE_MANAGE_OL` est actif, quelle que soit la cohabitation avec InfraSProject / InfraSPackPlus, un fallback JS s'active automatiquement quand le rendu serveur via sous-hook ne peut pas couvrir tous les besoins.

| Cohabitation | `<th>` | `<td>` standards | Fallback JS |
|--------------|--------|------------------|-------------|
| InfraSProject actif (± IPP) | sous-hook serveur (IS) | sous-hook serveur (IS ou IPP) | ⛔ désactivé |
| IPP seul (sans InfraSProject) | manquant côté serveur | sous-hook serveur (IPP) | ✅ injecte uniquement le `<th>` (les `<td>` existent déjà) |
| infrastructure seul | manquant côté serveur | manquant côté serveur | ✅ injecte `<th>` et `<td>` |

**Mécanisme** :

- Helper PHP `infrastructure_needsJsFallback($object, $action, $contexts)` dans `ActionsInfrastructure` : retourne `true` quand `infrastructure_shouldInjectOptColumn()` est vrai ET `!isModEnabled('infrasproject')`.
- Hook `addMoreActionsButtons` : quand le fallback est requis, construit une config JSON `{ lines: {id: 0|1}, thLabel, thTooltip }` (état Opt par lineid, libellés traduits) et l'injecte dans la page via `<script>var infrastructureOlFallbackConf = …</script>`.
- `js/infrastructure.lib.js` : au DOMReady, si `infrastructureOlFallbackConf` est défini, cible toutes les tables contenant un `th.linecolmove`, puis :
  - injecte `<th class="infrastructure_ol">` avant `th.linecolmove` **si absent** (sinon n'écrase pas le rendu serveur),
  - pour chaque `<tr id="row-…">` du tbody : ignore les lignes `rel="infrastructure"` (gérées par le tpl), ignore les lignes ayant déjà `td.infrastructure_ol` (rendu par IPP), sinon injecte la cellule checkbox avant `td.linecolmove`,
  - ajuste les `colspan` du formulaire d'ajout de ligne (tpl natif `objectline_create.tpl.php`) : incrémente de 1 chaque `td.linecoledit[colspan]` (mini-header `tr.liste_titre_add_` + ligne d'inputs `tr.liste_titre_create`) et le `td[colspan]` du `tr#trlinefordates` (services / contrats avec dateSelector). Aligné sur le comportement du tpl infrasproject qui incrémente `$colspan` côté serveur.
- La logique AJAX existante (`callAjaxUpdateLineOL` sur `.infrastructure_ol_chkbx`) reste inchangée — la délégation `$(document).on('change', …)` capte les checkboxes qu'elles soient injectées par le serveur ou par le fallback.

**Limites résiduelles** :

- Les lignes spéciales infrastructure (titres / sous-totaux / textes libres) restent gérées par le tpl `infrastructureline_row_document.tpl.php` et ne dépendent ni d'InfraSProject ni d'InfraSPackPlus pour leur cellule Opt.
- Si Dolibarr modifie la classe CSS `linecolmove` dans un futur tpl natif, le sélecteur du fallback (et le `preg_replace` côté sous-hook) ne trouveront plus le point d'insertion ; le rendu reste valide mais sans la cellule. Surveiller à chaque upgrade Dolibarr.

### Structure du changelog (Changelog structure)

```xml
<changelog>
    <Version Number="21.8.1" MonthVersion="2026-09">
        <change type='add'>Added feature description.</change>
        <change type='chg'>Changed feature description.</change>
        <change type='fix'>Fixed bug description.</change>
    </Version>
    <InfraS Downloaded="YYYYMMDD"/>
    <Dolibarr minVersion="21.0.0" maxVersion="24.x.x"/>
    <PHP minVersion="7.4" maxVersion="8.4"/>
</changelog>
```

- Types de changement : `add`, `chg`, `fix` — ordre recommandé dans une version : `fix → chg → add`
- L'attribut `Downloaded` est mis à jour automatiquement lors du téléchargement
- Parsé par `infrastructure_getLocalVersionMinDoli()` et `infrastructure_getChangeLog()` (dans `core/lib/infrastructureAdmin.lib.php`)

`infrastructure_getLocalVersionMinDoli()` retourne :

```php
[
    0 => "21.1.1",           // Version courante
    1 => "21.0.0",           // Version min Dolibarr
    2 => 0,                  // Flag erreur (-1 = KO, 0 = OK)
    3 => <SimpleXMLElement>, // Liste des versions (ou message d'erreur)
    4 => "24.x.x",           // Version max Dolibarr
    5 => "7.4",              // Version min PHP
    6 => "8.4"               // Version max PHP
]
```

### Compatibilité avec InfraSPackPlus (PDF rendering)

Les modèles PDF InfraSPlus (propal, facture, commande, etc.) intègrent nativement le support des structures :

- reconnaissance des lignes spéciales (`special_code = 550090`, `product_type = 9`),
- rendu personnalisé des titres selon `INFRASTRUCTURE_TITLE_STYLE` / `_PDF_TITLE_STYLE`,
- affichage des sous-totaux avec répartition TVA,
- support des modes `hideblock`, `print_as_list`, `print_condensed`,
- `show_table_header_before` pour répéter l'en-tête de tableau,
- page récap optionnelle (`INFRASTRUCTURE_*_ADD_RECAP`).

InfraSPackPlus 18.16.0 ajoute une exclusion explicite des lignes infrastructure du bloc `INFRASPLUS_PDF_SHOW_DISCOUNT_OPT` pour éviter le double affichage du template d'édition.

### Style, couleur et détails PDF des lignes optionnelles (fix/add 21.7.1)

**Marge du sous-total identique au Total HT (fix)** : `INFRASTRUCTURE_DISPLAY_MARGIN_ON_TOTAL` (`infrastructureline_total.tpl.php`) recalculait le coût de revient du bloc en re-fetchant la fiche produit catalogue (`Product::cost_price`) pour chaque ligne, au lieu d'utiliser le prix de revient réel déjà chargé sur chaque ligne (`$l->pa_ht`, le même champ qu'utilise le calcul de marge natif Dolibarr). Dès qu'une ligne du bloc n'était pas rattachée à un produit (prix libre) ou que le produit n'avait pas de coût renseigné, le coût cumulé restait à 0 et la colonne Marge affichait exactement le Total HT. Corrigé par la somme des `pa_ht * qty` des lignes du bloc.

**Bandeau de fond du sous-total sous-dimensionné (fix)** : `TCPDF::getStringHeight()` (utilisé par `pdfAddTotal()` pour dimensionner le fond du sous-total) ne parse pas le HTML et ne compte donc pas un `<br />` littéral comme un retour à la ligne, contrairement à `writeHTMLCell()` qui l'interprète. Quand `INFRASTRUCTURE_PDF_OL_SHOW_DETAILS` ajoute un `<br />` avant le cumul des montants optionnels (libellé sur 2 lignes), le fond restait dimensionné pour 1 ligne. Corrigé en convertissant le `<br />` en saut de ligne réel (`\n`) uniquement pour cet appel de mesure, sans toucher au libellé HTML réellement rendu par `writeHTMLCell()`.

**Nouvelles options PDF pour les lignes optionnelles** : `INFRASTRUCTURE_PDF_OL_SHOW_TOTAL_HT_AFTER_DESC` (ajoute le Total HT de chaque ligne optionnelle sur une nouvelle ligne après sa description) et `INFRASTRUCTURE_PDF_OL_STYLE` / `INFRASTRUCTURE_PDF_OL_COLOR` (style et couleur de texte des lignes optionnelles) sont implémentées via :

### Taux de marge / taux de marque sur la ligne de sous-total écran (fix 21.7.2)

`INFRASTRUCTURE_DISPLAY_MARGIN_ON_TOTAL` n'affichait qu'une seule cellule marge (montant) sur la ligne de sous-total, alors que le calcul de colspan (`infrastructureline_row_document.tpl.php` ~L94-96) réserve déjà, indépendamment de cette constante, une colonne native supplémentaire par option Dolibarr active du module Marges (`DISPLAY_MARGIN_RATES` → colonne Marge %, `DISPLAY_MARK_RATES` → colonne Marque %, cf. `margin/admin/margin.php`). Dès qu'une de ces options était active en plus d'`INFRASTRUCTURE_DISPLAY_MARGIN_ON_TOTAL`, la ligne de sous-total ne comptait qu'une cellule marge pour 2 ou 3 colonnes réservées, désalignant les cellules suivantes (Total HT, etc.) d'une à deux colonnes vers la gauche.

Correctif (`infrastructureline_total.tpl.php`) : deux nouvelles variables `$displayMarginRate` / `$displayMarkRate` (dérivées de `$displayMargin` + `DISPLAY_MARGIN_RATES` / `DISPLAY_MARK_RATES`) et `$marginColsCount` (0 à 3) remplacent le flat `($displayMargin ? 1 : 0)` dans les deux calculs de colspan (`$colsAfterQty` en mode aligné, `$labelColspan` en mode legacy). La cellule marge imprime désormais, à la suite du montant : le taux de marge (`100 * marge / totalCostPrice`, repli `n/a` si `totalCostPrice == 0`) si `DISPLAY_MARGIN_RATES` est actif, puis le taux de marque (`100 * marge / total_line`, repli `n/a` si `total_line == 0`) si `DISPLAY_MARK_RATES` est actif — mêmes formules que `getMarginInfos()` (`margin/lib/margins.lib.php`), appliquées aux totaux cumulés du bloc au lieu d'une ligne unique.

### Retrait de l'affichage systématique de la marge brute sur le sous-total (chg/add 21.8.0)

Depuis l'ajout des taux de marge/marque ci-dessus, la marge brute affichée en permanence dans la colonne montant (native PA HT) n'apportait plus d'information supplémentaire pour juger la rentabilité du bloc — elle a donc été retirée de l'affichage par défaut. La colonne montant reste vide (absorbée par la cellule de remplissage `$colsAfterQty`/`$labelColspan`, cf. ci-dessus) tant que la nouvelle option `INFRASTRUCTURE_DISPLAY_COST_PRICE_ON_TOTAL` n'est pas activée.

**Nouvelle option** `INFRASTRUCTURE_DISPLAY_COST_PRICE_ON_TOTAL` (section « PARAMÈTRES DIVERS » d'`admin/infrastructuresetup.php`, juste après `INFRASTRUCTURE_DISPLAY_MARGIN_ON_TOTAL` dont elle dépend — masquée si celle-ci est inactive, sur le même principe que les options dépendantes d'`INFRASTRUCTURE_MANAGE_OL`) : si active, affiche le prix de revient total cumulé du bloc (`$totalCostPrice`, déjà calculé pour les taux) dans la colonne montant, à la place de l'ancienne marge brute.

**Implémentation** (`infrastructureline_total.tpl.php`) : nouvelle variable `$displayCostPrice` (`$displayMargin && getDolGlobalString('INFRASTRUCTURE_DISPLAY_COST_PRICE_ON_TOTAL')`) qui remplace `$displayMargin` dans le calcul de `$marginColsCount` pour la colonne montant — la colonne n'est donc comptée dans le colspan que si elle sera effectivement renseignée. Le bloc de calcul de `$totalCostPrice`/`$marge` (requête `getLinesFromTitleId()`) n'est désormais exécuté que si `$marginColsCount > 0` (montant, taux de marge ou taux de marque actifs), pour éviter une requête inutile quand aucune des 3 colonnes n'est affichée. `$marge` reste calculée dans tous les cas dès que le bloc s'exécute : elle sert au calcul des taux même quand la colonne montant elle-même est masquée.

- une branche dédiée dans `pdf_writelinedesc()` pour les lignes normales marquées « Opt » (`array_options['options_infrastructure_ol']`), non couvertes par `isModInfrastructureLine()` (réservée aux titres/sous-totaux/textes libres du module) ;
- une méthode privée `applyOlPdfStyle(&$pdf, &$object, $i)` (`class/actions_infrastructure.class.php`) : détecte la ligne optionnelle (avec `fetch_optionals()` de secours si les extrafields ne sont pas encore chargés) et applique `INFRASTRUCTURE_PDF_OL_STYLE`/`INFRASTRUCTURE_PDF_OL_COLOR` sur le `$pdf` courant, si `INFRASTRUCTURE_PDF_OL_SHOW_DETAILS` est actif ;
- un appel à cette méthode, ajouté dans chacun des hooks `pdf_getlineqty`, `pdf_getlinevatrate`, `pdf_getlineupexcltax`, `pdf_getlineupwithtax`, `pdf_getlineunit`, `pdf_getlineremisepercent`, `pdf_getlinetotalexcltax`, `pdf_getlinetotalwithtax`, juste avant leur `return 0` final (qui laisse Dolibarr imprimer la valeur native, désormais avec le style/couleur appliqués) — pour que le style s'applique à **toutes** les colonnes de la ligne, pas seulement à sa description.

**Piège corrigé — colonne Quantité court-circuitée** : la case « Opt » positionne aussi `special_code = 3` sur la ligne (marqueur natif Dolibarr « ligne présentée en option », cf. CLAUDE.md d'InfraSPackPlus). `pdf_getlineqty()` contenait déjà une branche dédiée à ce `special_code` (pour forcer l'affichage de la quantité, que Dolibarr masque nativement sur ces lignes) qui retournait avant d'atteindre l'appel à `applyOlPdfStyle()` placé plus bas dans la méthode — l'appel a donc été dupliqué dans cette branche.

**Colonne « Num » — limite architecturale, contournée côté InfraSPackPlus** : la colonne « Num » (numéro de ligne, `INFRASPLUS_PDF_WITH_NUM_COLUMN`) n'est rendue par aucun hook Dolibarr standard — elle est calculée (`$i + 1`) et dessinée directement dans les modèles PDF d'InfraSPackPlus, avec une réinitialisation systématique de la police/couleur juste avant son rendu. Le module infrastructure ne peut donc pas la styler depuis ses propres hooks ; voir le CLAUDE.md d'InfraSPackPlus (section *Colonne « Num » et lignes optionnelles infrastructure*) pour le correctif appliqué côté modèles PDF (`infraspackplus_applyInfrastructureOlPdfStyle()`).

**Chevauchement du libellé de titre avec la colonne « Num » (fix 21.7.1 / InfraSPackPlus v21.5.8)** : quand InfraSPackPlus est actif avec sa colonne « Num » activée et positionnée en 1ère colonne (`INFRASPLUS_PDF_WITH_NUM_COLUMN` + `INFRASPLUS_PDF_NUMCOL_REF=1`, valeur par défaut), le libellé des titres de niveau 1 chevauchait visuellement le numéro de ligne. Deux causes cumulatives :
1. **`pdfAddTitle()`** (ce module) : le texte du titre démarrait toujours à `$pdf->getMargins()['left']` (marge brute TCPDF) au lieu de `$posx` (position réelle de la colonne Désignation, calculée dynamiquement par InfraSPackPlus et reçue en paramètre, mais jamais utilisée). Corrigé : le texte démarre désormais à `$posx` ; le bandeau de fond reste volontairement pleine largeur marge à marge (comportement inchangé, distinction faite via 2 jeux de variables `$titleBlockX/$titleBlockW` pour le fond et `$titleTextX/$titleTextW` pour le texte — la hauteur du fond, `getStringHeight()`, doit utiliser la même largeur que le texte réellement rendu, sans quoi le retour à la ligne diffère et le bandeau serait sous-dimensionné).
2. **Modèles PDF `pdf_InfraSPlus_*.modules.php`** (InfraSPackPlus) : le contenu de la colonne Num (`$i + 1`) n'était vidé que pour les titres/sous-totaux du module Sous-Total (ATM), jamais pour ceux du module infrastructure — une variable `$isInfraSLine` calculée dans 7 modèles mais jamais branchée dans cette condition (code mort), absente des 3 autres (`CBL`, `OM`, `OF`, ajoutée à cette occasion).

### Compatibilité avec InfraSDiscount (Discount exclusion)

Le module `infrasdiscount` exclut automatiquement les lignes infrastructure de ses calculs via :

```php
infrasdiscount_isInfrastructureLine($line)   // special_code == 550090 && product_type == 9
infrasdiscount_isInfrastructureTitle($line)  // qty ∈ [1..9]
infrasdiscount_isInfrastructureTotal($line)  // qty ∈ [91..99]
```

Les calculs de remise en cascade ignorent ces lignes, ce qui garantit la cohérence des sous-totaux en présence de lignes de remise.
