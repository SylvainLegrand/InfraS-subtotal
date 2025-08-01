# Change Log
All notable changes to this project will be documented in this file.

# [Unreleased]
- NEW : COMPAT 22 - *17/07/2025* - 3.29.0

## Release 3.29
- NEW : - Created conf SUBTOTAL_LIMIT_TVA_ON_CONDENSED_BLOCS to Limit the display of the VAT rate to blocks printed in condensed or list format - *17/07/2025* - 3.28.4

## Release 3.28
- FIX : remove warning - *27/05/2025* - 3.28.4  
- FIX : DA026337 - Fix buttons on supplier object - *02/04/2025* - 3.28.3
- FIX : DA026204 - Hide the price of set items option was not correctly applied during PDF generation - *10/03/2025* - 3.28.2
- FIX : DA026083 - Display details if not in a subtotal bloc - *21/02/2025* - 3.28.1
- NEW : TK2501-3506 - Created conf SUBTOTAL_HIDE_FOLDERS_BY_DEFAULT to hide folders by default - *14/01/2025* - 3.28.0

## Release 3.27
- NEW : Use v20 dropdown for action buttons & fix buttons orders - *01/10/2024* - 3.27.0  
  Use SUBTOTAL_FORCE_EXPLODE_ACTION_BTN hidden conf to disable behavior

## Release 3.26
- FIX : Compat V20 : Document col missing for title for NC - *16/09/2024* - 3.26.1
- NEW : Add more order to invoice massaction option - *04/09/2024* - 3.26.0  
  Allow adding list of shipping ref to title block

# Release 3.25 - 24/07/2024
- FIX : null-coalesce (potential fatal in trigger) + restore useful comment - *2025-03-26* - 3.25.7
- FIX : DA025895 Refactored SHIPPING_CREATE trigger - *2025-03-26* - 3.25.6
- FIX: DA025864: conf `NO_TITLE_SHOW_ON_EXPED_GENERATION` should delete all
  title/free/subtotal lines from the shipment but doesn't - *12/12/2024* - 3.25.5
- FIX : DA025861 Provide an option in the heading style configuration to have no styling at all - *12/12/2024* - 3.25.4
- FIX DA025399 : GETPOST type integer n'existe pas - *22/08/2024* - 3.25.3
- FIX : CKeditor no check version to avoid the error message  - *20/08/2024* - 3.25.2
- FIX : Title summary - *24/07/2024* - 3.25.1
- FIX : Compat v20
  Changed Dolibarr compatibility range to 16 min - 20 max - *11/07/2024* - 3.25.0
- Ajout du hook pdfgeneration dans la liste des hooks accepté par le module - *12/07/2024* - 3.25.0

# Release 3.24 - 08/04/2024
- FIX : Backward compatibility with Dolibarr 15 and 16 for supplier orders: some parameters were passed to `CommandeFournisseur::addline()` but they were introduced in v16 and v17 respectively. - *09/07/2025* - 3.24.8
- FIX : Global $object variable was being reassigned causing CRON job crashes (and possibly more) - *18/04/2024* - 3.24.7
- FIX : Retour montée de version   - *27/09/2024* - 3.24.6
- FIX : DA024845 : Le module sous total amène des erreurs dans les sauts de page lorsque l'on arrive tout juste en bas de page. - *24/07/2024* - 3.24.5
- FIX : Module_number missed in subTotal class. function  addSubTotalLine function  in test order_supplier - *26/06/2024* - 3.24.4
- FIX : display totalht ligne 'non compris' - *12/04/2024* - 3.24.3
- FIX : Title extrafields wasn't working - *12/04/2024* - 3.24.2
- FIX : doublon affichage label description lors de la création de facture depuis un objet (propal/commande ...)  - *03/04/2024* - 3.24.1
- NEW : Ajout des quantités par sous totaux sur l'interface - *27/03/2024* - 3.24.0

# Release 3.23 - 18/12/2023
- FIX : Dans le dictionnaire "Ligne de texte prédéfini" du module Sous total, si je tente d'éditer le contenu d'un texte, cela s'affiche en code HTML au lieu d'un champ d'édition WYSIWYG  - *27/09/2024* - 3.23.11
- FIX : DA024939 - Added static method hasBreakPage to check if a line has a break page or not - *17/05/2024* - 3.23.10
- FIX : DA024587 - Les totaux remisés sur le PDF Sponge sont erronés  - *20/03/2024* - 3.23.9
- FIX : Suite a l'issue #379, la création de facture d'acompte avec un montant variable change les qté des lignes générées par le module donc on utilise un trigger pour remettre les bonnes qtés - *18/03/2024* - 3.23.8
- FIX : Sur le modèle PDF Sponge, retrait des lignes de sous totaux au moment du calcul de l'avancement global - *12/03/2024* - 3.23.7
- FIX : Create invoice deposit line same order with percent : FIX add Line subtotal and free text  - *20/02/2024* - 3.23.6
- FIX : Display subtotal with tax when global PDF_PROPAL_SHOW_PRICE_INCL_TAX is enable - *15/01/2024* - 3.23.5
- FIX : php 8.2 warning - *15/01/2024* - 3.23.4
- FIX : colum in card if global 'MAIN_NO_INPUT_PRICE_WITH_TAX' is enable
- FIX : js error when summary menu is disabled - *06/10/2023* - 3.23.2
- FIX : css break due to incompatible conf - *06/10/2023* - 3.23.1
- FIX : page break issue from PR #271, #292 & #328 - *19/07/2023* - 3.23.0  
  To disable this fix use Hidden conf  SUBTOTAL_DISABLE_FIX_TRANSACTION set to 1  
  This correction is being prepared for the next version, to avoid any possible side-effects that we haven't yet seen.
- FIX Compat v19 et php8.2 - *23/10/2023* - 3.23.0  
  Changed Dolibarr compatibility range to 15 min - 19 max  
  Change PHP compatibility range to 7.0 min - 8.2 max

# Release 3.22 - 19/07/2023

- FIX : DA024364 - Fatal error deuxieme parametre de str_repeat - *19/01/2024* - 3.22.7
- FIX : DA024159 - Suppression des ligne sous total hors bloc expédié dans le bon d'expédition - *13/12/2023* - 3.22.6
- HOTFIX : must be greater than 0 fatal - *22/11/2023* - 3.22.5
- FIX : DA024057 - Anomalie PDF lors de l'activation conf "Remplacer par le détail des TVA si l'option "Cacher le détail des ensembles" est utilisée (expérimental)" - *16/11/2023* - 3.22.4
- FIX : PHP8 warnings - *07/09/2023* - 3.22.3
- FIX : Fatal error au recurring invoices - *28/08/2023* - 3.22.2
- FIX : Add missing subtotal fail cause dolibarr make a reorder of subtotal after title juste added  - *28/07/2023* - 3.22.1
- NEW : Add new option to chose for folder management behavior  - *17/07/2023* - 3.22.0
  Also add a new BTN to open/close a complete block with its children too.
- FIX : Folder management ajax call - *10/07/2023* - 3.21.1
- NEW : Ajout des marges par blocs - *28/06/2023* - 3.21.0
- NEW : Add folder management - *28/06/2023* - 3.20.0

## Release 3.19 - 19/06/2023

- FIX : title group lines move placeholder colspan and adapt to V16+ new line dom - *06/06/2023* - 3.19.1
- NEW : Document summary addition in left menu  - *06/06/2023* - 3.19.0

## Release 3.18 - 26/04/2023

- FIX : DA024015 - lors de la création de facture fournisseur depuis plusieurs commandes fournisseur, les titres qui reprennent la ref des commandes d'origine n'était pas bon - *19-10-2023* - 3.18.4
- FIX : DA023419 - Les arrondis des sous-totaux n'était plus qu'à un chiffre après la virgule - *01/06/2023* - 3.18.3
- FIX : DA023305 compat php8  *02/06/2023* - 3.18.3
- FIX : Include path  *03/05/2023* - 3.18.2
- FIX : Missing translations *26/04/2023* - 3.18.1
- NEW : Ajout options *27/02/2023* - 3.18.0
    - "Cacher les options de titre"
    - "Cacher l'option du saut de page avant"
    - "Cache les options génération du document"
- NEW : Allow module to regenerate invoice document after being generated by cron createRecurringInvoices to fix formatting issue. - *07/03/2023* - 3.17.0
- NEW : add es_ES language - *07/03/2023* - 3.16.5
- FIX : Warning on PHP 8 *20/03/2023* - 3.16.4
- FIX : Display of free line remove button *20/03/2023* - 3.16.3
- FIX : rounding of subtotal in PDF  - *20/02/2023* - 3.16.2
- FIX : La création de factures depuis la liste des commandes créé un décalage titre sous total - *20/02/2023* - 3.16.1
- NEW : add rank input for subtotal lines *24/01/2023* - 3.16.0

## Release 3.16

- NEW : Ajout configuration SUBTOTAL_TITLE_SIZE permettant d'éditer la taille des titres - *07/02/2023* - 3.16.0

## Release 3.15

- FIX : lorsque l'option "Cacher le prix des lignes des ensembles" était cochée, la réduction n'apparaissait plus sur les lignes qui ne font pas partie d'un ensemble - *06/04/2023* - 3.15.5
- FIX : retrait des constantonoff lorsqu'on est en mode edit dans la page de conf - *09/03/2023* - 3.15.4
- FIX : Multiples erreurs de colspan qui créaient des décalages sur les tableaux de lignes - *20/01/2023* - 3.15.3
- FIX : DA022658 - Gestion des non-compris non-fonctionnelle - 3.15.2
- FIX : Fatal error *02/01/2023* - 3.15.1
- NEW : Ajout de massaction de suppression de ligne sur les card *16/11/2022* 3.15.0

## Release 3.14 PI

- FIX : Update info bits on create from hook - *18/11/2022* - 3.14.8 ```PR #273 OpenDsi```  
  le code permettant de mettre à jour l'info bit semble être obsolète depuis la version 10 de Dolibarr (le mieux serait de remonter sur les versions précédentes et retrouver à partir de quelle version de Dolibarr ce code n'est plus utile)
  De plus, il ajoute des sauts de lignes lorsque l'objet copié et l'objet source ne possèdent pas le même nombre de lignes. En effet un autre module peut ajouter une ligne et on a alors un saut de ligne supplémentaire.
- FIX : PHP 8 Compatibility - *19/10/2022* - 3.14.7
- FIX : Remove transaction in  pdf_writelinedesc *10/11/2022* - 3.14.6
- FIX : PHP 8 Compatibility - *13/07/2022* - 3.14.5
- FIX : Admin déplacement de l'option de récap en zone expérimentale *11/07/2022* 3.14.4
- FIX : html tag missing for style *11/07/2022* 3.14.3
- FIX duplicate origin lines on create from proposal *11/07/2022* 3.14.2 [PR #273 OpenDsi](https://github.com/ATM-Consulting/dolibarr_module_subtotal/pull/273)
  when you create an invoice or order from a proposal you got duplicate lines if there are "Ouvrage" (or other external modules with product type "9") lines in proposal
- FIX : Compatibility V16 Dictionnaries *14/06/2022* - 3.14.1
- NEW : Refonte page setup avec class setup de Dolibarr V15 *11/05/2022* 3.14.0
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *10/05/2022* 3.13.0
- NEW : Add total_ht on originproductline tpl if available + add class td identification + add data-id tr identification *07/04/2022* - 3.12.0

## Release 3.11
- FIX : FIX DOUBLE PARENTHESIS - *15/05/2023* - 3.11.13
- FIX : COMPAT V16 family - *02/06/2022)* - 3.11.12
- FIX : TRIGGER UPDATE AND MODIFY - *02/06/2022)* - 3.11.11
- FIX : description - *02/06/2022)* - 3.11.10
- FIX : $pdf->rollbackTransaction without start *29/06/2022* - 3.11.9
- FIX : colspan 4 to 5 to fix view of propal and bill *07/04/2022* - 3.11.8
- FIX : title and subtotal padding *30/03/2022* - 3.11.7
- FIX : title and text offset position *29/03/2022* - 3.11.6
- FIX : background Color position *17/03/2022* - 3.11.5
- FIX : remove useless retrocompatibility file and change module min compatibility version to Dolibarr 7.0 and PHP 5.4  *08/03/2022* - 3.11.4
- FIX : all table class oddeven *08/03/2022* - 3.11.3
- FIX : Module logo and setup table class *07/03/2022* - 3.11.2
- FIX : Advanced setup option not used correctly *07/03/2022* - 3.11.1
- NEW : Add new conf to remove strange behavior on PDF subtotal line. Subtotal label always contain title label, it's possible to disable this behavior. *07/03/2022* - 3.11.0
- NEW : Option to set background color of title and subtotal *04/03/2022* - 3.10.0 [PR #216 gdesnoues](https://github.com/ATM-Consulting/dolibarr_module_subtotal/pull/216)
- NEW : Sum qty in each subtotal line  *10/12/2021* - 3.9.0 [PR #222 OpenDsi](https://github.com/ATM-Consulting/dolibarr_module_subtotal/pull/222)

  Apport de possibilité du choix du modèle de docuement dans la configuration du module.
  Les options sur les sous-totaux ont été mises sur les lignes de sous-totaux pour simplification du code et éviter de parcourir toutes les lignes afin de retrouver le titre parent.
- NEW : Can select sub-total lines in supplier order and invoice  *10/12/2021* - 3.8.0 [PR #226 OpenDsi](https://github.com/ATM-Consulting/dolibarr_module_subtotal/pull/226)

## Version 3.7
- FIX : Missing CKEditor parameters allowing to browse URL *07/04/2025* - 3.7.6
- FIX : typo dans hook ODTSubstitutionLine *30/10/2023* - 3.7.5
- FIX : object was never fetch *07/02/2022* - 3.7.4
- FIX : Fix compatibility 11.0 pdfEvolution *19/01/2022* - 3.7.3
- FIX : Compatibility with version 14 and lower *20/12/2021* - 3.7.2 @jyhere #229
- FIX : Compatibility with version 15 *14/12/2021* - 3.7.1
- NEW : add api subtotal to module. add entryPoint getTotalLine  *17/11/2021* - 3.7.0


## Release 3.6
- FIX : Substitutions ODT ne se font pas pour toutes les actions sur les factures *15/12/2022* 3.6.11
- FIX : Title lines broken on PDF for documents whose lines use the `desc` field instead of the `label` field (such as
  supplier orders and invoices) *22/11/2021* - 3.6.10
- FIX : checkbox to add a subtotal title block per order on invoices using the "Bill orders" feature was broken by
  core changes in Dolibarr *12/11/2021* - 3.6.9
- FIX : Missing columns in invoice creation page (from order) *10/06/2022* - 3.6.8
- FIX : addition of a conf allowing to add the subtotal line or not when creating an expedition from an order *12/07/2021* - 3.6.7
- FIX : Clone icon compatibility *08/06/2021* - 3.6.6
- FIX : Uniformize module descriptor's editor, editor_url and family fields *2021-06-08* - 3.6.5
- FIX : Ajout include de la classe dans actions_subtotal pour éviter des erreurs *21/05/2021* - 3.6.4
- FIX : Fix document line colspan fail if Margin module don't enabled but some conf of this module still actived *21/04/2021* - 3.6.3
- FIX : Dolibarr v13.0 compatibility (token renewal exclusion) *13/04/2021* - 3.6.2
- FIX : Exclude subtotals from the total calculation *07/04/2021* - 3.5.6
- NEW : Ajouter les lignes 'Titre' , 'Total' , 'Libre' aux generations d'expeditions de commandes expédiables (il faudra
  supprimer les lignes de sous-total à la main si le besoin s'en fait sentir) *03/04/2021* - 3.5.5


## Release 3.5

- FIX : Text or title line break PDF *15/04/2021* - 3.5.7
- NEW Ajouter les lignes 'Titre' , 'Total' , 'Libre' aux generations d'expeditions de commandes expédiables (il faudra
  supprimer les lignes de sous-total à la main si le besoin s'en fait sentir) *2021-02-03* - 3.5.5
- NEW : Add more compatibility for new PDF models using new cols system.
  Ceci est un fix avec un fort impact potentiel sur les instances courantes. Il est donc préférable de le placer
  sur une nouvelle release - 3.5

## Release 3.5
- FIX: invoice creation: title/subtotal/free text lines coming from shipments or deliveries not imported with special code (MDLL) - *17/11/2021* - 3.5.8
- FIX : Text or title line break PDF *15/04/2021* - 3.5.7
- NEW Ajouter les lignes 'Titre' , 'total' , 'libre' aux generation d'expedition de commandes expédiables (il faudra supprimer les lignes de sous-total à la main si le besoin s'en fait sentir ) [2021-02-03]

