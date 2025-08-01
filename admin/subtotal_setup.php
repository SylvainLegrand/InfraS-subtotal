<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 SuperAdmin <maxime@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    subtotal/admin/setup.php
 * \ingroup subtotal
 * \brief   subtotal setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/subtotal.lib.php';

// Translations
$langs->loadLangs(array("admin", "subtotal@subtotal"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('subtotalsetup', 'globalsetup'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');

if(!class_exists('FormSetup')){
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}

$formSetup = new FormSetup($db);



/*
// Hôte
$item = $formSetup->newItem('NO_PARAM_JUST_TEXT');
$item->fieldOverride = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
$item->cssClass = 'minwidth500';

// Setup conf SUBTOTAL_MYPARAM1 as a simple string input
$item = $formSetup->newItem('SUBTOTAL_MYPARAM1');

// Setup conf SUBTOTAL_MYPARAM1 as a simple textarea input but we replace the text of field title
$item = $formSetup->newItem('SUBTOTAL_MYPARAM2');
$item->nameText = $item->getNameText().' more html text ';

// Setup conf SUBTOTAL_MYPARAM3
$item = $formSetup->newItem('SUBTOTAL_MYPARAM3');
$item->setAsThirdpartyType();

// Setup conf SUBTOTAL_MYPARAM4 : exemple of quick define write style
$formSetup->newItem('SUBTOTAL_MYPARAM4')->setAsYesNo();

// Setup conf SUBTOTAL_MYPARAM5
$formSetup->newItem('SUBTOTAL_MYPARAM5')->setAsEmailTemplate('thirdparty');

// Setup conf SUBTOTAL_MYPARAM6
$formSetup->newItem('SUBTOTAL_MYPARAM6')->setAsSecureKey()->enabled = 0; // disabled

// Setup conf SUBTOTAL_MYPARAM7
$formSetup->newItem('SUBTOTAL_MYPARAM7')->setAsProduct();
*/

// Activer l'utilisation avancée
if(!in_array($action, array('edit', 'update'))) {
	$item = $formSetup->newItem('SUBTOTAL_USE_NEW_FORMAT');
	$item->setAsYesNo();
	$item->helpText = $langs->transnoentities('SUBTOTAL_USE_NEW_FORMAT_HELP');


	// Sur les lignes de sous total des PDF, ajouter le libellé du titre auquel cette dernière est rattaché.
	$formSetup->newItem('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL')->setAsYesNo();

	// Activer la numérotation automatique sur le PDF à partir de Dolibarr 3.8
	$formSetup->newItem('SUBTOTAL_USE_NUMEROTATION')->setAsYesNo();

	// Autoriser l'ajout d'un titre et sous-total
	$formSetup->newItem('SUBTOTAL_ALLOW_ADD_BLOCK')->setAsYesNo();

	// Autoriser la suppression d'un titre ou sous-total
	$formSetup->newItem('SUBTOTAL_ALLOW_EDIT_BLOCK')->setAsYesNo();

	// Autoriser la duplication d'un bloc
	$formSetup->newItem('SUBTOTAL_ALLOW_REMOVE_BLOCK')->setAsYesNo();

	// Autoriser la duplication d'un bloc
	$formSetup->newItem('SUBTOTAL_ALLOW_DUPLICATE_BLOCK')->setAsYesNo();

	// Autoriser la duplication d'une ligne
	$formSetup->newItem('SUBTOTAL_ALLOW_DUPLICATE_LINE')->setAsYesNo();

	// Permettre l'ajout d'une ligne libre et/ou produit directement sous un titre
	$formSetup->newItem('SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE')->setAsYesNo();

	// L'ajout sous un titre se fera en fin de section
	$formSetup->newItem('SUBTOTAL_ADD_LINE_UNDER_TITLE_AT_END_BLOCK')->setAsYesNo();

	$formSetup->newItem('SUBTOTAL_HIDE_FOLDERS_BY_DEFAULT')->setAsYesNo();
}

// Cacher les options du titre
$formSetup->newItem('SUBTOTAL_HIDE_OPTIONS_TITLE')->setAsYesNo();

// Cacher l'option ajouter un saut de page avant
$formSetup->newItem('SUBTOTAL_HIDE_OPTIONS_BREAK_PAGE_BEFORE')->setAsYesNo();

// Cacher les options génération de document
$formSetup->newItem('SUBTOTAL_HIDE_OPTIONS_BUILD_DOC')->setAsYesNo();

// Texte des titres lors de la facturation via onglet client -> bouton "Facturer commandes"
$item = $formSetup->newItem('SUBTOTAL_TEXT_FOR_TITLE_ORDETSTOINVOICE')->helpText = $langs->transnoentities('SUBTOTAL_TEXT_FOR_TITLE_ORDETSTOINVOICE_info');

// Style des titres (B = gras, U = souligné, I = italique)
$item = $formSetup->newItem('SUBTOTAL_TITLE_STYLE');
$item->fieldAttr['placeholder'] = 'BU';

$item = $formSetup->newItem('SUBTOTAL_TEXT_LINE_STYLE');
$item->fieldAttr['placeholder'] = '';

// Style des titres (B = gras, U = souligné, I = italique)
$item = $formSetup->newItem('SUBTOTAL_TITLE_SIZE');
$item->helpText = $langs->transnoentities('SUBTOTAL_TITLE_SIZE_info');

// Style des sous-totaux (B = gras, U = souligné, I = italique)
$item = $formSetup->newItem('SUBTOTAL_SUBTOTAL_STYLE');
$item->fieldAttr['placeholder'] = 'BU';

//Affichage des marges sur les sous-totaux
$formSetup->newItem('DISPLAY_MARGIN_ON_SUBTOTALS')->setAsYesNo();

// Couleur de fond utilisée sur les PDF pour les titres
$item = $formSetup->newItem('SUBTOTAL_TITLE_BACKGROUNDCOLOR');
$item->fieldValue = getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR','#ffffff');
$item->fieldAttr['type'] = 'color';
$item->fieldOutputOverride ='<input type="color" value="'.$item->fieldValue .'" disabled />';

// Couleur de fond utilisée sur les PDF pour les sous-totaux
$item = $formSetup->newItem('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR');
$item->fieldValue = getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR','#ebebeb');
$item->fieldAttr['type'] = 'color';
$item->fieldOutputOverride ='<input type="color" value="'.$item->fieldValue .'" disabled />';

$item = $formSetup->newItem('SUBTOTAL_DISABLE_SUMMARY')->setAsYesNo();



$item = $formSetup->newItem('SUBTOTAL_BLOC_FOLD_MODE')->setAsSelect(array(
		'default' => $langs->trans('HideSubtitleOnFold'),
		'keepTitle' => $langs->trans('KeepSubtitleDisplayOnFold'),
	));
if(!getDolGlobalInt('SUBTOTAL_BLOC_FOLD_MODE')){
	$result = dolibarr_set_const($item->db, $item->confKey, 'default', 'chaine', 0, '', $item->entity);
	$item->reloadValueFromConf();	// InfraS change
}


if (!getDolGlobalInt('MAIN_MODULE_INFRASPACKPLUS')) {	// InfraS add

// Activer la gestion des blocs "Non Compris" pour exclusion du total
$formSetup->newItem('ManageNonCompris')->setAsTitle();

$itemNC = $formSetup->newItem('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS')->setAsSelect(array(0 => $langs->transnoentities('No'), 1 => $langs->transnoentities('Yes')));
$itemNC->setSaveCallBack(function ($itemNC){
	$result = dolibarr_set_const($itemNC->db, $itemNC->confKey, $itemNC->fieldValue, 'chaine', 0, '', $itemNC->entity);
	if((int) $itemNC->fieldValue > 0) {
		_createExtraComprisNonCompris();
	}
});


// Colonnes à afficher sur lignes marquées "Non Compris"
$item = $formSetup->newItem('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC');
$TField = array(
	'pdf_getlineqty' => $langs->trans('Qty'),
	'pdf_getlinevatrate' => $langs->trans('VAT'),
	'pdf_getlineupexcltax' => $langs->trans('PriceUHT'),
	'pdf_getlinetotalexcltax' => $langs->trans('TotalHT'),
	'pdf_getlinetotalincltax' => $langs->trans('TotalTTC'),
	'pdf_getlineunit' => $langs->trans('Unit'),
	'pdf_getlineremisepercent' => $langs->trans('Discount')
);
$item->setAsMultiSelect($TField);


if(!in_array($action, array('edit', 'update'))) {
	// La gestion des non-compris vide aussi le prix de revient
	$item = $formSetup->newItem('SUBTOTAL_NONCOMPRIS_UPDATE_PA_HT');
	$item->setAsYesNo();
	$item->helpText = $langs->transnoentities('SUBTOTAL_NONCOMPRIS_UPDATE_PA_HT_info');
}	// InfraS add
}	// InfraS add
if(!in_array($action, array('edit', 'update')) || (float)DOL_VERSION < 17) {	// InfraS add
	// Ajouter un titre, ajoutera au-dessus les sous-totaux manquants
	$formSetup->newItem('SUBTOTAL_AUTO_ADD_SUBTOTAL_ON_ADDING_NEW_TITLE')->setAsYesNo();
}


$formSetup->newItem('SetupForExtrafields')->setAsTitle();

if(!in_array($action, array('edit', 'update'))) {
	// Autoriser l'affichage des extrafields sur les titres (les données enregistrées seront alors peuplées sur les lignes du bloc)
	$formSetup->newItem('SUBTOTAL_ALLOW_EXTRAFIELDS_ON_TITLE')->setAsYesNo();
}

// Champs complémentaires disponible sur les titres dans les propositions commerciales clients
$item = $formSetup->newItem('SUBTOTAL_LIST_OF_EXTRAFIELDS_PROPALDET');
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('propaldet');
$item->setAsMultiSelect($extralabels);


// Champs complémentaires disponible sur les titres dans les commandes clients
$item = $formSetup->newItem('SUBTOTAL_LIST_OF_EXTRAFIELDS_COMMANDEDET');
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('commandedet');
$item->setAsMultiSelect($extralabels);

// Champs complémentaires disponible sur les titres dans les factures clients
$item = $formSetup->newItem('SUBTOTAL_LIST_OF_EXTRAFIELDS_FACTUREDET');
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('facturedet');
$item->setAsMultiSelect($extralabels);

/*
 * Configuration
 */
$formSetup->newItem('Setup')->setAsTitle();

// Activer l'affichage de la somme des quantités sur les lignes de sous-totaux pour les modèles de documents :
$item = $formSetup->newItem('SUBTOTAL_DEFAULT_DISPLAY_QTY_FOR_SUBTOTAL_ON_ELEMENTS');
$langs->loadLangs(array('propal', 'orders', 'bills', 'supplier', 'supplier_proposal'));
$TField = array(
	'propal' => $langs->trans('Proposal'),
	'commande' => $langs->trans('Order'),
	'facture' => $langs->trans('Invoice'),
	'supplier_proposal' => $langs->trans('SupplierProposal'),
	'order_supplier' => $langs->trans('SupplierOrder'),
	'invoice_supplier' => $langs->trans('SupplierInvoice'),
);
$item->setAsMultiSelect($TField);
$item->helpText = $langs->transnoentities('SUBTOTAL_DEFAULT_DISPLAY_QTY_FOR_SUBTOTAL_ON_ELEMENTS_info');

// Ne pas reporter les lignes de titre lors de la génération d’expédition
if(!in_array($action, array('edit', 'update'))) {
	$formSetup->newItem('NO_TITLE_SHOW_ON_EXPED_GENERATION')->setAsYesNo();
}
// InfraS add begin
// Afficher le taux de TVA sur les lignes de sous-totaux
$formSetup->newItem('SUBTOTAL_SHOW_TVA_ON_SUBTOTAL_LINES_ON_ELEMENTS')->setAsYesNo();
// Limiter l'affichage du taux de TVA aux lignes de sous-totaux
if (!empty(getDolGlobalInt('SUBTOTAL_SHOW_TVA_ON_SUBTOTAL_LINES_ON_ELEMENTS')) && isModEnabled('infraspackplus')) {
	$formSetup->newItem('SUBTOTAL_LIMIT_TVA_ON_CONDENSED_BLOCS')->setAsYesNo();
}
// InfraS add end

/*
 * Génération d'un récapitulatif par titre
 */

if(!in_array($action, array('edit', 'update'))) {
	$formSetup->newItem('RecapGeneration')->setAsTitle();

	// Conserver le PDF de récapitulation après la fusion
	$formSetup->newItem('SUBTOTAL_KEEP_RECAP_FILE')->setAsYesNo();

	// Activer la génération du récapitulatif sur les propositions commerciales	// InfraS change (moved from line 373)
	$formSetup->newItem('SUBTOTAL_PROPAL_ADD_RECAP')->setAsYesNo();	// InfraS change (moved from line 374)

	// Activer la génération du récapitulatif sur les commandes
	$formSetup->newItem('SUBTOTAL_COMMANDE_ADD_RECAP')->setAsYesNo();

	// Activer la génération du récapitulatif sur les factures
	$formSetup->newItem('SUBTOTAL_INVOICE_ADD_RECAP')->setAsYesNo();
}

/*
 * Paramètrage de l'option "Cacher le prix des lignes des ensembles"
 */
if(!in_array($action, array('edit', 'update'))) {
	$formSetup->newItem('SetupForSubBlocs')->setAsTitle();

	// Par defaut, cocher la case "Cacher le prix des lignes des ensembles" lors de la génération des PDF
	$formSetup->newItem('SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED')->setAsYesNo();

	// Afficher la quantité sur les lignes de produit
	$formSetup->newItem('SUBTOTAL_IF_HIDE_PRICES_SHOW_QTY')->setAsYesNo();
	if (!getDolGlobalInt('MAIN_MODULE_INFRASPACKPLUS')) {	// InfraS add
	// Masquer les totaux
	$formSetup->newItem('SUBTOTAL_HIDE_DOCUMENT_TOTAL')->setAsYesNo();
	}	// InfraS add

	if (isModEnabled('shippableorder')) {
		$formSetup->newItem('SUBTOTAL_SHIPPABLE_ORDER')->setAsYesNo();
	}

	if (isModEnabled('clilacevenements')) {
		// Afficher la quantité sur les lignes de sous-total (uniquement dans le cas d'un produit virtuel ajouté)
		$formSetup->newItem('SUBTOTAL_SHOW_QTY_ON_TITLES')->setAsYesNo();

		// Masquer uniquement les prix pour les produits se trouvant dans un ensemble
		$formSetup->newItem('SUBTOTAL_ONLY_HIDE_SUBPRODUCTS_PRICES')->setAsYesNo();
	}
}


/*
 * ZONE EXPERIMENTAL
 */


if(!in_array($action, array('edit', 'update'))) {
	if (!getDolGlobalInt('MAIN_MODULE_INFRASPACKPLUS')) {    // InfraS add
		$formSetup->newItem('SubtotalExperimentalZone')->setAsTitle();


	// Avoir une seule ligne de titre + total si l'option "Cacher le détail des ensembles" est utilisée (expérimental)
	$item = $formSetup->newItem('SUBTOTAL_ONE_LINE_IF_HIDE_INNERLINES');
	$item->setAsYesNo();
	$item->nameText = $langs->trans("SUBTOTAL_ONE_LINE_IF_HIDE_INNERLINES", $langs->transnoentitiesnoconv('HideInnerLines'));

	// Remplacer par le détail des TVA si l'option "Cacher le détail des ensembles" est utilisée (expérimental)
	$item = $formSetup->newItem('SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES');
	$item->setAsYesNo();
	$item->nameText = $langs->trans("SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES", $langs->transnoentitiesnoconv('HideInnerLines'));

	// Activer la génération du récapitulatif sur les propositions commerciales	// InfraS change (moved from line 313)
	//$formSetup->newItem('SUBTOTAL_PROPAL_ADD_RECAP')->setAsYesNo();	// InfraS change (moved from line 314)
	}	// InfraS add
}

// InfraS add begin
if (isModEnabled('oblyon') && !empty(getDolGlobalString('MAIN_MENU_INVERT')) && !empty(getDolGlobalString('OBLYON_HIDE_LEFTMENU')) ) {
	// Désactiver le sommaire rapide
	dolibarr_set_const($db, 'SUBTOTAL_DISABLE_SUMMARY', 1, 'chaine', 0, '', $conf->entity);
}
// InfraS add end

/*
 * Actions
 */

if ($action == 'update' && !empty($formSetup) && is_object($formSetup) && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
	header('Location:'.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "SubtotalSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = subtotalAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "subtotal@subtotal");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("SubTotalSetupPage").'</span><br><br>';


if ($action == 'edit') {

	print $formSetup->generateOutput(true);
	print '<br>';
} else {
	if (!empty($formSetup->items)) {
		print $formSetup->generateOutput();

		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	}
	else {
		print '<br>'.$langs->trans("NothingToSetup");
	}
}

// Page end
print dol_get_fiche_end();


llxFooter();
$db->close();
