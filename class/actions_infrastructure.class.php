<?php
	/**************************************************
	* Copyright (C) 2025-2026	Sylvain Legrand - <contact@infras.fr>	InfraS - <https://www.infras.fr>
	* Copyright (C) 2025-2026	Fallinah Ranasolonirina	- <contact@infras.fr>	InfraS - <https://www.infras.fr>
	*
	* This program is free software; you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation; either version 3 of the License, or
	* (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with this program. If not, see <https://www.gnu.org/licenses/>.
	*
	* SPDX-License-Identifier: GPL-3.0-or-later
	* This file is part of Dolibarr module Infrastructure
	**************************************************/

	/**************************************************
	* \file			infrastructure/class/actions_infrastructure.class.php
	* \ingroup		infrastructure
	* \brief		Hook actions for Infrastructure module
	*************************************************/

	// Libraries ************************************
	include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
	include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
	dol_include_once('/infrastructure/class/infrastructure.class.php');
	dol_include_once('/infrastructure/core/lib/infrastructure.lib.php');
	dol_include_once('/infrastructure/backport/v19/core/class/commonhookactions.class.php');
	if (isModEnabled('ouvrage')) {
		dol_include_once('/ouvrage/class/ouvrage.class.php');
	}
	/**
	* Class ActionsInfrastructure
	*/
	class ActionsInfrastructure extends \infrastructure\RetroCompatCommonHookActions
	{
		public $db;	// @var DoliDB $db Database handler
		public $module_number;	// @var int Numéro du module (initialisé dans le constructeur via TInfrastructure::getModuleNumber())
		public $error;	// @var string $error
		public $errors = [];	// @var array $errors
		public $results = [];	// @var array Hook results. Propagated to $hookmanager->resArray for later reuse
		public $resprints;	// @var string String displayed by executeHook() immediately after return
		public $allow_move_block_lines;	// @var bool Allow move block lines
		protected $infrastructure_level_cur = 0;	// @var int Infrastructure current level
		protected $infrastructure_show_qty_by_default = false;	// @var bool Show infrastructure qty by default
		protected $infrastructure_sum_qty_enabled = false;	// @var bool Determine if sum on infrastructure qty is enabled
		protected $cachedRedrawnHeaderPages = [];	// @var int[] Pages où infrastructure_drawNativeTableHeaderBefore a été appelé (numéros de page TCPDF)
		protected $cachedNativeTabTop = null;	// @var float|null Position Y (mm) du haut de l'en-tête natif sur la page 1, déduite de posy de la 1ère ligne
		protected $infrastructureSavedCellPaddings = null;	// @var null|array Sauvegarde des cell paddings d'origine avant modification dans pdfAddTotal (restaurés dans pdf_writelinedesc à la prochaine ligne non sous-total)

		/**
		* Constructor
		*
		* @param DoliDB $db Database handler
		*/
		public function __construct($db)
		{
			global $langs;

			$langs->load('infrastructure@infrastructure');
			$this->db						= $db;
			$this->module_number			= TInfrastructure::getModuleNumber();
			$this->allow_move_block_lines	= true;
		}


		/**
		* Print field list select
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int								0 if OK, -1 if KO, 1 to replace standard code
		*/
		public function printFieldListSelect($parameters, &$object, &$action, HookManager $hookmanager)
		{

			global $type_element, $where;

			$contexts = explode(':', $parameters['context']);
			if (in_array('consumptionthirdparty', $contexts) && in_array($type_element, ['propal', 'order', 'invoice', 'supplier_order', 'supplier_invoice', 'supplier_proposal'])) {
				$mod_num = TInfrastructure::$module_number;
				$where	.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty > 9)';		// Not a title (can't use TInfrastructure class methods in sql)
				$where	.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty < 90)';	// Not a infrastructure (can't use TInfrastructure class methods in sql)
				$where	.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty != 50)';	// Not a free line text (can't use TInfrastructure class methods in sql)
			}
			return 0;
		}

		/**
		* Edit dictionary field list
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int								0 if OK, -1 if KO, 1 to replace standard code
		*/
		public function editDictionaryFieldlist($parameters, &$object, &$action, HookManager $hookmanager)
		{

			if ($parameters['tabname'] == $this->db->prefix().'c_infrastructure_free_text') {
				$value = TInfrastructure::getHtmlDictionnary();
				?>
				<script type="text/javascript">
					$(function () {
						if ($('input[name=content]').length > 0) {
							$('input[name=content]').each(function (i, item) {
								var value = '';
								// Le dernier item correspond à l'édition
								if (i == $('input[name=content]').length - 1) {
									value = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
								}
								$(item).replaceWith($('<textarea name="content">' + value + '</textarea>'));
							});
							<?php if (isModEnabled('fckeditor') && getDolGlobalString('FCKEDITOR_ENABLE_DETAILS')) { ?>
								$('textarea[name=content]').each(function(i, item) {
									CKEDITOR.replace(item, {
										toolbar: 'dolibarr_notes',
										customConfig: ckeditorConfig,
										versionCheck: false
									});
								});
							<?php } ?>
						}
					});
				</script>
				<?php
			}
			return 0;
		}

		/**
		* Create dictionary field list
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int								0 if OK, -1 if KO, 1 to replace standard code
		*/
		public function createDictionaryFieldlist($parameters, &$object, &$action, HookManager $hookmanager)
		{
			global $conf;

			if ($parameters['tabname'] == $this->db->prefix().'c_infrastructure_free_text') {
				// Editor wysiwyg
				$toolbarname		= 'dolibarr_notes';
				$disallowAnyContent	= true;
				if (getDolGlobalString('FCKEDITOR_ALLOW_ANY_CONTENT')) {
					$disallowAnyContent	= !getDolGlobalString('FCKEDITOR_ALLOW_ANY_CONTENT'); // Only predefined list of html tags are allowed or all
				}
				if (getDolGlobalString('FCKEDITOR_SKIN')) {
					$skin = getDolGlobalString('FCKEDITOR_SKIN');
				} else {
					$skin = 'moono-lisa'; // default with ckeditor 4.6 : moono-lisa
				}
				if (getDolGlobalString('FCKEDITOR_ENABLE_SCAYT_AUTOSTARTUP')) {
					$scaytautostartup = 'scayt_autoStartup: true,';
				} else {
					$scaytautostartup = '/*scayt is disable*/'; // Disable by default
				}
				$htmlencode_force		= preg_match('/_encoded$/', $toolbarname) ? 'true' : 'false';
				$editor_height			= getDolGlobalString('MAIN_DOLEDITOR_HEIGHT', 100);
				$editor_allowContent	= $disallowAnyContent ? 'false' : 'true';
				$value = TInfrastructure::getHtmlDictionnary();
				?>
				<script type="text/javascript">
					$(function () {
						if ($('input[name=content]').length > 0) {
							$('input[name=content]').each(function (i, item) {
								var value = '';
								// Le dernier item correspond à l'édition
								if (i == $('input[name=content]').length - 1) {
									value = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
								}
								$(item).replaceWith($('<textarea name="content">' + value + '</textarea>'));
							});

							<?php if (isModEnabled("fckeditor") && getDolGlobalString('FCKEDITOR_ENABLE_DETAILS')) { ?>
							$('textarea[name=content]').each(function (i, item) {
								CKEDITOR.replace(item, {
									toolbar: 'dolibarr_notes',
									customConfig: ckeditorConfig,
									versionCheck: false
								});
							});
							<?php } ?>
						}
					});
				</script>
				<?php
			}
			return 0;
		}

		/**
		* Overloading the formObjectOptions function : replacing the parent's function with the one below
		*
		* @param 	array			$parameters  array           meta datas of the hook (context, etc...)
		* @param 	CommonObject	$object      CommonObject    the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		* @param 	string			$action      string          current action (if set). Generally create or edit or null
		* @param 	HookManager 	$hookmanager HookManager     current hook manager
		* @return	int
		*/
		public function formObjectOptions($parameters, &$object, &$action, HookManager $hookmanager)
		{
			global $langs,$db,$user, $conf;

			$langs->load('infrastructure@infrastructure');
			$contexts			= explode(':', $parameters['context']);
			if (in_array('ordercard', $contexts) || in_array('ordersuppliercard', $contexts) || in_array('propalcard', $contexts) || in_array('supplier_proposalcard', $contexts) || in_array('invoicecard', $contexts) || in_array('invoicesuppliercard', $contexts) || in_array('invoicereccard', $contexts) || in_array('expeditioncard', $contexts)) {
				$createRight	= $user->hasRight($object->element, 'creer');
				if ($object->element == 'facturerec') {
					$object->statut = 0; // hack for facture rec
					$createRight = $user->hasRight('facture', 'creer');
				} elseif ($object->element == 'order_supplier') {
					$createRight = $user->hasRight('fournisseur', 'commande', 'creer');
				} elseif ($object->element == 'invoice_supplier') {
					$createRight = $user->hasRight('fournisseur', 'facture', 'creer');
				} elseif ($object->element == 'shipping') {
					$createRight = true; // No rights management for shipments
				}
				if ($object->statut == 0 && $createRight) {
					$idvar		= $object->element == 'facture' ? 'facid' : 'id';
					if (in_array($action, ['add_title_line', 'add_total_line', 'add_subtitle_line', 'add_infrastructure_line', 'add_free_text'])) {
						$level	= GETPOST('level', 'int');
						if ($action == 'add_title_line') {
							$title	= !empty(GETPOST('title', 'alphanohtml')) ? GETPOST('title', 'alphanohtml') : $langs->trans('InfrastructureTitle');
							$qty	= $level < 1 ? 1 : $level ;
						} elseif ($action=='add_free_text') {
							$title	= GETPOST('title', 'restricthtml');
							if (empty($title)) {
								$free_text		= GETPOST('free_text', 'int');
								if (!empty($free_text)) {
									$TFreeText	= infrastructure_getTFreeText();
									if (!empty($TFreeText[$free_text])) {
										$title	= $TFreeText[$free_text]->content;
									}
								}
							}
							$title	= !empty($title) ? $title : $langs->trans('InfrastructureAddLineDescription');
							$qty	= 50;
						} elseif ($action == 'add_subtitle_line') {
							$title	= !empty(GETPOST('title', 'alphanohtml')) ? GETPOST('title', 'alphanohtml') : $langs->trans('InfrastructureSubtitle');
							$qty	= 2;
						} elseif ($action == 'add_infrastructure_line') {
							$title	= $langs->trans('InfrastructureSubInfrastructure');
							$qty	= 98;
						} else {
							$title	= !empty(GETPOST('title', 'alphanohtml')) ? GETPOST('title', 'alphanohtml') : $langs->trans('InfrastructureSubtotal');
							$qty	= $level ? 100 - $level : 99;
						}
						if (getDolGlobalString('INFRASTRUCTURE_AUTO_ADD_TOTAL_ON_ADDING_NEW_TITLE') && $qty < 10) {
							TInfrastructure::addInfrastructureMissing($object, $qty);
						}
						if (getDolGlobalInt('MAIN_VIEW_LINE_NUMBER') == 1) {
							$rang		= GETPOST('rank', 'int') ? (int)GETPOST('rank', 'int') : '-1';
							$newlineid	= TInfrastructure::addInfrastructureLine($object, $title, $qty, $rang);
							print '<div id="newlineid">'.$newlineid.'</div>';
						} else {
							TInfrastructure::addInfrastructureLine($object, $title, $qty);
						}
					} elseif ($action==='ask_deleteallline') {
						$form			= new Form($db);
						$lineid			= GETPOST('lineid', 'int');
						$TIdForGroup	= TInfrastructure::getLinesFromTitleId($object, $lineid, true);
						$nbLines		= count($TIdForGroup);
						$formconfirm	= $form->formconfirm(dol_escape_htmltag($_SERVER["PHP_SELF"]).'?id='.$object->id.'&lineid='.$lineid, $langs->trans('InfrastructureDeleteWithAllLines'), $langs->trans('InfrastructureConfirmDeleteAllThisLines', $nbLines), 'confirm_delete_all_lines', '', 0, 1);
						print $formconfirm;
					}
					if (getDolGlobalString('INFRASTRUCTURE_ALLOW_ADD_LINE_UNDER_TITLE')) {
						infrastructure_showSelectTitleToAdd($object);
					}
					if ($object->element != 'shipping' && $action != 'editline') {
						infrastructure_printNewFormat($object, $conf, $langs, $idvar);
					}
				}
			} elseif ((!empty($parameters['currentcontext']) && $parameters['currentcontext'] == 'orderstoinvoice') || in_array('orderstoinvoice', $contexts) || in_array('orderstoinvoicesupplier', $contexts)) {
				infrastructure_billOrdersAddCheckBoxForTitleBlocks();
			}
			return 0;
		}


		/**
		* Table build to generate new document and to show linked objects (../core/class/html.formfile.class.php)
		*
		* @param	array()			$parameters		Hook metadatas (context, etc...)
		* @param	CommonObject	&$object		The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		* @param	string			&$action		Current action (if set). Generally create or edit or null
		* @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
		* @return	int								< 0 on error, 0 on success, 1 to replace standard code + $this->resprints HTML code to show
		**/
		public function formBuilddocOptions($parameters, &$object, &$action, HookManager $hookmanager)
		{

			global $langs;

			$this->resprints	= '';
			if (!getDolGlobalString('INFRASTRUCTURE_HIDE_OPTIONS_BUILD_DOC') && in_array($object->element, ['propal', 'commande', 'facture', 'facturerec', 'order_supplier', 'invoice_supplier'])) {
				$colspan				= 6;
				$hideInnerLines			= isset($_SESSION['infrastructure_hideInnerLines_'.$parameters['modulepart']][$object->id]) ?  $_SESSION['infrastructure_hideInnerLines_'.$parameters['modulepart']][$object->id] : 0;
				$hideqtys				= isset($_SESSION['infrastructure_hideqtys_'.$parameters['modulepart']][$object->id]) ?  $_SESSION['infrastructure_hideqtys_'.$parameters['modulepart']][$object->id] : 0;
				$hidepricesDefaultConf	= getDolGlobalString('INFRASTRUCTURE_HIDE_PRICE_DEFAULT_CHECKED')?getDolGlobalString('INFRASTRUCTURE_HIDE_PRICE_DEFAULT_CHECKED') :0;
				$hideprices				= !empty($_SESSION['infrastructure_hideprices_'.$parameters['modulepart']][$object->id]) ?  $_SESSION['infrastructure_hideprices_'.$parameters['modulepart']][$object->id] : $hidepricesDefaultConf;
				$titleOptions			= $langs->trans('InfrastructureOptions').'&nbsp;&nbsp;&nbsp;'.img_picto($langs->trans('Setup'), 'setup', 'style="vertical-align: bottom; height: 20px;"');
				$titleStyle				= 'background-color: rgba(148, 148, 148, .065) !important;';
				$this->resprints		.= '	<script type = "text/javascript">
													$(document).ready(function(){
														var noFoldable	= '.getDolGlobalString('INFRASTRUCTURE_MAKE_BUILD_DOC_NO_FOLDABLE').';
														if (noFoldable) {
															$(".infrastructurefoldable").show();
														} else {
															$(".infrastructurefoldable").hide();
															$(".infrastructurefold").click(function (){
																$(".infrastructurefoldable").toggle();
															});
														}
														// Exclusion mutuelle : hideInnerLines vs (hideprices, hideqtys)
														$("#hideInnerLines").on("change", function () {
															if ($(this).is(":checked")) {
																$("#hideprices, #hideqtys").prop("checked", false);
															}
														});
														$("#hideqtys, #hideprices").on("change", function () {
															if ($(this).is(":checked")) {
																$("#hideInnerLines").prop("checked", false);
															}
														});
													});
												</script>';
				$this->resprints		.= '	<tr class = "infrastructurefold cursorpointer infrastructuretrans" style = "'.$titleStyle.'"><td class = "center" colspan = "'.$colspan.'" style = "font-size: 120%;">'.$titleOptions.'</td></tr>
												<tr class = "oddeven infrastructurefoldable">
													<td colspan = "'.$colspan.'" class = "right">
														<label for = "hideInnerLines">'.$langs->trans('InfrastructureHideInnerLines').'</label>
														<input type = "checkbox" id = "hideInnerLines" name = "hideInnerLines" value = "1" '.(!empty($hideInnerLines) ? 'checked = "checked"' : '').' />
													</td>
												</tr>
												<tr class = "oddeven infrastructurefoldable">
													<td colspan = "'.$colspan.'" class = "right">
														<label for = "hideqtys">'.$langs->trans('InfrastructureHideQtys').'</label>
														<input type = "checkbox" id = "hideqtys" name = "hideqtys" value = "1" '.(!empty($hideqtys) ? 'checked = "checked"' : '').' />
													</td>
												</tr>
												<tr class = "oddeven infrastructurefoldable">
													<td colspan = "'.$colspan.'" class = "right">
														<label for = "hideprices">'.$langs->trans('InfrastructureHidePrice').'</label>
														<input type = "checkbox" id = "hideprices" name = "hideprices" value = "1" '.(!empty($hideprices) ? 'checked = "checked"' : '').' />
													</td>
												</tr>';
				if (($object instanceof Propal && getDolGlobalString('INFRASTRUCTURE_PROPAL_ADD_RECAP')) ||
					($object instanceof Commande && getDolGlobalString('INFRASTRUCTURE_COMMANDE_ADD_RECAP')) ||
					($object instanceof Facture && getDolGlobalString('INFRASTRUCTURE_INVOICE_ADD_RECAP')) ||
					($object instanceof FactureRec && getDolGlobalString('INFRASTRUCTURE_INVOICE_ADD_RECAP')))
				{
					$this->resprints	.= '	<tr class = "oddeven infrastructurefoldable">
													<td colspan = "'.$colspan.'" class = "right">
														<label for = "infrastructure_add_recap">'.$langs->trans('InfrastructureAddRecap').'</label>
														<input type = "checkbox" id = "infrastructure_add_recap" name = "infrastructure_add_recap" value = "1" '.(!empty(GETPOST('infrastructure_add_recap', 'int')) ? 'checked = "checked"' : '').'/>
													</td>
												</tr>';
				}
				$this->resprints	.= '		<tr class = "infrastructurebgtrans"><td class = "center infrastructurenopadding" colspan = "'.$colspan.'"><hr class = "quatrevingtpercent"></td></tr>';
			}
			return 0;
		}
		/**
		* ODT substitution line
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int
		*/
		public function ODTSubstitutionLine(&$parameters, &$object, $action, HookManager $hookmanager)
		{
			global $conf;

			if (in_array($action, ['builddoc', 'addline', 'confirm_valid', 'confirm_paiement'])) {
				$line												= &$parameters['line'];
				$object												= &$parameters['object'];
				$substitutionarray									= &$parameters['substitutionarray'];
				$substitutionarray['line_not_modinfrastructure']	= true;
				$substitutionarray['line_modinfrastructure']		= false;
				$substitutionarray['line_modinfrastructure_total']	= false;
				$substitutionarray['line_modinfrastructure_title']	= false;
				if ($line->product_type == 9 && $line->special_code == $this->module_number) {
					$substitutionarray['line_modinfrastructure']	= 1;
					$substitutionarray['line_not_modinfrastructure']= false;
					$substitutionarray['line_price_ht']			= $substitutionarray['line_price_vat']
																= $substitutionarray['line_price_ttc']
																= $substitutionarray['line_vatrate']
																= $substitutionarray['line_qty']
																= $substitutionarray['line_up']
																= '';
					if ($line->qty > 90) {
						$substitutionarray['line_modinfrastructure_total'] = true;
						$TInfo									= infrastructure_get_totalLineFromObject($object, $line, 0, 1);
						$substitutionarray['line_price_ht']		= price($TInfo[0], 0, '', 1, 0, getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
						$substitutionarray['line_price_vat']	= price($TInfo[1], 0, '', 1, 0, getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
						$substitutionarray['line_price_ttc']	= price($TInfo[2], 0, '', 1, 0, getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
					} else {
						$substitutionarray['line_modinfrastructure_title'] = true;
					}
				} else {
					$substitutionarray['line_not_modinfrastructure']	= true;
					$substitutionarray['line_modinfrastructure']		= 0;
				}
			}
			return 0;
		}

		/**
		* Do actions
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return int|void
		*/
		public function doActions($parameters, &$object, $action, HookManager $hookmanager)
		{
			global $db, $conf, $langs, $user, $hideqtys, $hideprices;

			$contextArray	= [];
			if (isset($parameters['context'])) {
				$contextArray = explode(':', $parameters['context']);
			}
			$showBlockExtrafields	= GETPOST('showBlockExtrafields', 'aZ09');
			$idvar					= isset($object->element) && $object->element == 'facture' ? 'facid' : 'id';
			if (in_array($action, ['updateligne', 'updateline'])) {
				$found	= false;
				$lineid	= GETPOST('lineid', 'int');
				foreach ($object->lines as &$line) {
					if ($line->id == $lineid && TInfrastructure::isModInfrastructureLine($line)) {
						$found	= true;
						if (TInfrastructure::isTitle($line) && !empty($showBlockExtrafields)) {
							$extrafieldsline	= new ExtraFields($db);
							$extralabelsline	= $extrafieldsline->fetch_name_optionals_label($object->table_element_line);
							$extrafieldsline->setOptionalsFromPost($extralabelsline, $line);
						}
						infrastructure_updateInfrastructureLine($object, $line);
						infrastructure_updateInfrastructureBloc($object, $line);
						TInfrastructure::generateDoc($object);
						break;
					}
				}
				if ($found) {
					$urlSelf	= preg_replace('#(\.php).*$#', '$1', $_SERVER['PHP_SELF']);
					header('Location: '.$urlSelf.'?'.$idvar.'='.((int) $object->id));
					exit; // Surtout ne pas laisser Dolibarr faire du traitement sur le updateligne sinon ça plante les données de la ligne
				}
			} elseif ($action === 'builddoc') {
				if (in_array('invoicecard', $contextArray)
					|| in_array('propalcard', $contextArray)
					|| in_array('ordercard', $contextArray)
					|| in_array('ordersuppliercard', $contextArray)
					|| in_array('invoicesuppliercard', $contextArray)
					|| in_array('supplier_proposalcard', $contextArray)
				) {
					$TSessNames		= infrastructure_getSessionNames($contextArray);
					$sessname		= $TSessNames['hideInnerLines'];
					$sessname2		= $TSessNames['hideqtys'];
					$sessname3		= $TSessNames['hideprices'];
					$hideInnerLines	= GETPOST('hideInnerLines', 'int');
					if (!array_key_exists($sessname, $_SESSION) || empty($_SESSION[$sessname]) || !is_array($_SESSION[$sessname]) || !isset($_SESSION[$sessname][$object->id])) {
						$_SESSION[$sessname]			= [$object->id => 0]; // prevent old system
					}
					$_SESSION[$sessname][$object->id]	= $hideInnerLines;
					$hideqtys						= GETPOST('hideqtys', 'int');
					if (!array_key_exists($sessname2, $_SESSION) || empty($_SESSION[$sessname2]) || !is_array($_SESSION[$sessname2]) || !isset($_SESSION[$sessname2][$object->id])) {
						$_SESSION[$sessname2]			= [$object->id => 0]; // prevent old system
					}
					$_SESSION[$sessname2][$object->id]	= $hideqtys;
					$hideprices							= GETPOST('hideprices', 'int');
					if (!array_key_exists($sessname3, $_SESSION) || empty($_SESSION[$sessname3]) || !is_array($_SESSION[$sessname3]) || !isset($_SESSION[$sessname3][$object->id])) {
						$_SESSION[$sessname3]			= [$object->id => 0]; // prevent old system
					}
					$_SESSION[$sessname3][$object->id]	= $hideprices;
					foreach ($object->lines as &$line) {
						if ($line->product_type == 9 && $line->special_code == $this->module_number) {
							if ($line->qty >= 90) {
								$line->modinfrastructure_total = 1;
							} else {
								$line->modinfrastructure_title = 1;
							}
							$line->total_ht = infrastructure_get_totalLineFromObject($object, $line, false);
						}
					}
				}
			} elseif ($action === 'confirm_delete_all_lines' && GETPOST('confirm', 'alpha') == 'yes') {
				$error	= 0;
				$Tab	= TInfrastructure::getLinesFromTitleId($object, GETPOST('lineid', 'int'), true);
				foreach ($Tab as $line) {
					$result = 0;
					if (!empty(isModEnabled('ouvrage')) && class_exists('Ouvrage') && Ouvrage::isOuvrage($line)) {
						// Call trigger
						$interface			= new Interfaces($db);
						$result				= $interface->run_triggers('OUVRAGE_DELETE', $line, $user, $langs, $conf);
						if ($result < 0) {
							$error++;
						}
						// End call triggers
					}
					$idLine		= $line->id;
					if ($object->element == 'facture') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'invoice_supplier') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'propal') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'supplier_proposal') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'commande') {
						$result = $object->deleteline($user, $idLine);
					} elseif ($object->element == 'order_supplier') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'facturerec') {
						$result = $object->deleteline($idLine);
					} elseif ($object->element == 'shipping') {
						$result = $object->deleteline($user, $idLine);
					}
					if ($result < 0) {
						$error++;
					}
				}
				if ($error > 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$db->rollback();
				} else {
					$db->commit();
				}
				header('location:?id='.$object->id);
				exit;
			} elseif ($action == 'duplicate') {
				$lineid			= GETPOST('lineid', 'int');
				$nbDuplicate	= TInfrastructure::duplicateLines($object, $lineid, true);
				if ($nbDuplicate > 0) {
					setEventMessage($langs->trans('InfrastructureDuplicateSuccess', $nbDuplicate));
				} elseif ($nbDuplicate == 0) {
					setEventMessage($langs->trans('InfrastructureDuplicateLineidNotFound'), 'warnings');
				} else {
					setEventMessage($langs->trans('InfrastructureDuplicateError'), 'errors');
				}
				header('Location: ?id='.$object->id);
				exit;
			} elseif ((!empty($parameters['currentcontext']) && $parameters['currentcontext'] == 'orderstoinvoice') || in_array('orderstoinvoice', $contextArray) || in_array('orderstoinvoicesupplier', $contextArray) || in_array('orderlist', $contextArray)) {
				infrastructure_billOrdersAddCheckBoxForTitleBlocks();
			} else {
				// when automatic generate is enabled : keep last selected options from last "builddoc" action (ganerate document manually)
				if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
					if (in_array('invoicecard', $contextArray) || in_array('propalcard', $contextArray) || in_array('ordercard', $contextArray) || in_array('ordersuppliercard', $contextArray) || in_array('invoicesuppliercard', $contextArray) || in_array('supplier_proposalcard', $contextArray)) {
						$confirm	= GETPOST('confirm', 'alpha');
						if (in_array($action, ['modif', 'reopen']) || (in_array($action, ['confirm_modif', 'confirm_edit', 'confirm_validate', 'confirm_valid']) && $confirm == 'yes')) {
							$TSessNames	= infrastructure_getSessionNames($contextArray);
							$sessname	= $TSessNames['hideInnerLines'];
							$sessname2	= $TSessNames['hideqtys'];
							$sessname3	= $TSessNames['hideprices'];
							if (GETPOSTISSET('hideInnerLines')) {
								$hideInnerLines = GETPOST('hideInnerLines', 'int');
							} else {
								$hideInnerLines = isset($_SESSION[$sessname][$object->id]) ? $_SESSION[$sessname][$object->id] : 0;
							}
							$_POST['hideInnerLines'] = $hideInnerLines;
							if (GETPOSTISSET('hideqtys')) {
								$hideqtys = GETPOST('hideqtys', 'int');
							} else {
								$hideqtys = isset($_SESSION[$sessname2][$object->id]) ? $_SESSION[$sessname2][$object->id] : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);
							}
							// no need to set POST value (it's a global value used in global card)
							if (GETPOSTISSET('hideprices')) {
								$hideprices	 = GETPOST('hideprices', 'int');
							} else {
								$hidepricesDefaultConf	= getDolGlobalString('INFRASTRUCTURE_HIDE_PRICE_DEFAULT_CHECKED') ? getDolGlobalString('INFRASTRUCTURE_HIDE_PRICE_DEFAULT_CHECKED') : 0;
								$hideprices				= isset($_SESSION[$sessname3][$object->id]) ? $_SESSION[$sessname3][$object->id] : $hidepricesDefaultConf;
							}
							// no need to set POST value (it's a global value used in this module)
						}
					}
				}
			}
			return 0;
		}

		/**
		*	Recalcule le total du document en excluant les lignes optionnelles (special_code = 3).
		*	Remplace update_price() quand des lignes OL existent, pour que leurs montants
		*	réels restent visibles en ligne sans entrer dans le total général.
		*
		*	@param	array			$parameters		Parameters (exclspec, roundingadjust, nodatabaseupdate, seller)
		*	@param	CommonObject	$object			Document parent (Propal, Commande, Facture, ...)
		*	@param	string			$action			Action
		*	@param	HookManager		$hookmanager	Hook manager
		*	@return	int								0 = continuer le code standard, 1 = code standard remplacé
		**/
		public function updateTotalPrice($parameters, &$object, &$action, HookManager $hookmanager)
		{
			if (!getDolGlobalString('INFRASTRUCTURE_MANAGE_OL')) return 0;
			$TAllowed = ['propal', 'commande', 'facture', 'supplier_proposal', 'order_supplier', 'facture_fourn', 'invoice_supplier'];
			if (!in_array($object->element, $TAllowed) || empty($object->table_element_line) || empty($object->fk_element)) return 0;
			if (!empty($parameters['nodatabaseupdate'])) return 0;

			// Vérifier si des lignes OL (special_code = 3) existent sur ce document
			$sql = "SELECT rowid FROM ".$object->db->prefix().$object->db->sanitize($object->table_element_line);
			$sql .= " WHERE ".$object->db->sanitize($object->fk_element)." = ".((int) $object->id);
			$sql .= " AND special_code = 3";
			$rescheck = $object->db->query($sql);
			if (!$rescheck || $object->db->num_rows($rescheck) == 0) {
				if ($rescheck) $object->db->free($rescheck);
				return 0; // Pas de lignes OL : laisser update_price() gérer normalement
			}
			$object->db->free($rescheck);

			// Nom du champ TVA dans la table de lignes (facture_fourn utilise 'tva', les autres 'total_tva')
			$fieldtva_line = in_array($object->element, ['facture_fourn', 'invoice_supplier']) ? 'tva' : 'total_tva';

			// Sommer les totaux en excluant les lignes OL (special_code = 3)
			$sql = "SELECT";
			$sql .= " COALESCE(SUM(total_ht), 0) as total_ht,";
			$sql .= " COALESCE(SUM(".$object->db->sanitize($fieldtva_line)."), 0) as total_tva,";
			$sql .= " COALESCE(SUM(total_ttc), 0) as total_ttc,";
			$sql .= " COALESCE(SUM(total_localtax1), 0) as total_localtax1,";
			$sql .= " COALESCE(SUM(total_localtax2), 0) as total_localtax2,";
			$sql .= " COALESCE(SUM(multicurrency_total_ht), 0) as multicurrency_total_ht,";
			$sql .= " COALESCE(SUM(multicurrency_total_tva), 0) as multicurrency_total_tva,";
			$sql .= " COALESCE(SUM(multicurrency_total_ttc), 0) as multicurrency_total_ttc";
			$sql .= " FROM ".$object->db->prefix().$object->db->sanitize($object->table_element_line);
			$sql .= " WHERE ".$object->db->sanitize($object->fk_element)." = ".((int) $object->id);
			$sql .= " AND special_code != 3";
			$resql = $object->db->query($sql);
			if (!$resql) return 0;
			$obj = $object->db->fetch_object($resql);
			$object->db->free($resql);
			if (!$obj) return 0;

			$object->total_ht			= (float) price2num($obj->total_ht);
			$object->total_tva			= (float) price2num($obj->total_tva);
			$object->total_ttc			= (float) price2num($obj->total_ttc);
			$object->total_localtax1	= (float) price2num($obj->total_localtax1);
			$object->total_localtax2	= (float) price2num($obj->total_localtax2);
			$object->multicurrency_total_ht		= (float) price2num($obj->multicurrency_total_ht);
			$object->multicurrency_total_tva	= (float) price2num($obj->multicurrency_total_tva);
			$object->multicurrency_total_ttc	= (float) price2num($obj->multicurrency_total_ttc);

			// Ajouter le timbre fiscal si présent (identique à update_price)
			if (!empty($object->revenuestamp)) {
				$multicurrency_tx				= !empty($object->multicurrency_tx) ? $object->multicurrency_tx : 1;
				$object->total_ttc				+= $object->revenuestamp;
				$object->multicurrency_total_ttc	+= $object->revenuestamp * $multicurrency_tx;
			}

			// Mettre à jour les totaux du document en base
			$sql = "UPDATE ".$object->db->prefix().$object->db->sanitize($object->table_element)." SET";
			$sql .= " total_ht = ".((float) price2num($object->total_ht, 'MT', 1)).",";
			$sql .= " total_tva = ".((float) price2num($object->total_tva, 'MT', 1)).",";
			$sql .= " localtax1 = ".((float) price2num($object->total_localtax1, 'MT', 1)).",";
			$sql .= " localtax2 = ".((float) price2num($object->total_localtax2, 'MT', 1)).",";
			$sql .= " total_ttc = ".((float) price2num($object->total_ttc, 'MT', 1));
			$sql .= ", multicurrency_total_ht = ".((float) price2num($object->multicurrency_total_ht, 'MT', 1));
			$sql .= ", multicurrency_total_tva = ".((float) price2num($object->multicurrency_total_tva, 'MT', 1));
			$sql .= ", multicurrency_total_ttc = ".((float) price2num($object->multicurrency_total_ttc, 'MT', 1));
			$sql .= " WHERE rowid = ".((int) $object->id);
			$resql = $object->db->query($sql);
			if (!$resql) return 0;

			return 1; // update_price() standard ignorée
		}

		/**
		*	Recalcule le tableau des marges de la fiche (FormMargin::displayMarginInfos) en excluant
		*	les lignes optionnelles (special_code = 3), par cohérence avec updateTotalPrice() qui les
		*	exclut déjà du total du document. Le calcul natif FormMargin::getMarginInfosArray() est
		*	réutilisé tel quel sur une copie de l'objet privée de ces lignes : $parameters['marginInfo']
		*	est passé par référence par le core et modifié en place.
		*
		*	@param	array			$parameters		Parameters (marginInfo par référence)
		*	@param	CommonObject	$object			Document parent (Propal, Commande, Facture, ...)
		*	@param	string			$action			Action
		*	@param	HookManager		$hookmanager	Hook manager
		*	@return	int								0 = continuer le code standard (affichage natif avec marginInfo corrigé)
		**/
		public function displayMarginInfos($parameters, &$object, &$action, HookManager $hookmanager)
		{
			if (!getDolGlobalString('INFRASTRUCTURE_MANAGE_OL')) return 0;
			if (empty($object->lines) || ! is_array($object->lines) || !isset($parameters['marginInfo'])) return 0;
			// Lignes à conserver pour le calcul des marges : tout sauf les lignes OL (special_code = 3)
			$TLines	= array();
			$hasOl	= false;
			foreach ($object->lines as $line) {
				if (!TInfrastructure::isModInfrastructureLine($line) && (int) $line->special_code == 3) {
					$hasOl = true;
					continue;
				}
				$TLines[] = $line;
			}
			if (!$hasOl) return 0; // Pas de lignes OL : laisser le calcul natif inchangé
			$clone			= clone $object;
			$clone->lines	= $TLines;
			dol_include_once('/core/class/html.formmargin.class.php');
			$formmargin					= new FormMargin($this->db);
			$parameters['marginInfo']	= $formmargin->getMarginInfosArray($clone, false);
			return 0;
		}
		/**
		* Change rounding mode
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int
		*/
		public function changeRoundingMode($parameters, &$object, &$action, HookManager $hookmanager)
		{
			return 0;
		}

		/**
		* PDF add total
		*
		* @param	TCPDF|ModelePDFStatic	$pdf		PDF object
		* @param	CommonObject			$object 	Object
		* @param	CommonObjectLine		$line 		Line
		* @param	string					$label		Label
		* @param	string					$description Description
		* @param	float					$posx 		Position X
		* @param	float					$posy 		Position Y
		* @param	float					$w 			Width
		* @param	float					$h 			Height
		* @return	void
		*/
		public function pdfAddTotal(&$pdf, &$object, &$line, $label, $description, $posx, $posy, $w, $h)
		{
			global $conf, $infrastructure_last_title_posy, $langs;

			$infrastructureDefaultTopPadding	= 1;
			$infrastructureDefaultBottomPadding	= 1;
			$infrastructureDefaultLeftPadding	= 0.5;
			$infrastructureDefaultRightPadding	= 0.5;
			$use_multicurrency					= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1 ? 1 : 0;
			empty($pdf->page_largeur) ? $pdf->page_largeur = 0 : '';
			empty($pdf->marge_droite) ? $pdf->marge_droite = 0 : '';
			empty($line->total) ? $line->total = 0 : '';
			empty($pdf->postotalht) ? $pdf->postotalht = 0 : '';
			$bgStyle							= infrastructure_getPdfBackgroundStyle($pdf, 'INFRASTRUCTURE_PDF_TOTAL_BACKGROUND_COLOR', 'INFRASTRUCTURE_PDF_TOTAL_BACKGROUND_CELL_HEIGHT_OFFSET', 'INFRASTRUCTURE_PDF_TOTAL_BACKGROUND_CELL_POS_Y_OFFSET', $line);
			$fillBackground						= $bgStyle['fill'];
			$backgroundColor					= $bgStyle['color'];
			$backgroundCellHeightOffset			= $bgStyle['heightOffset'];
			$backgroundCellPosYOffset			= $bgStyle['posYOffset'];
			infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
			// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
			$pdfModelUseColSystem				= !empty($object->context['infrastructurePdfModelInfo']->cols); // justilise une variable au cas ou le test evolue
			if ($pdfModelUseColSystem) {
				include_once __DIR__.'/staticPdf.model.php';
				$staticPdfModel					= new ModelePDFStatic($object->db);
				$staticPdfModel->marge_droite	= $object->context['infrastructurePdfModelInfo']->marge_droite;
				$staticPdfModel->marge_gauche	= $object->context['infrastructurePdfModelInfo']->marge_gauche;
				$staticPdfModel->page_largeur	= $object->context['infrastructurePdfModelInfo']->page_largeur;
				$staticPdfModel->page_hauteur	= $object->context['infrastructurePdfModelInfo']->page_hauteur;
				$staticPdfModel->cols			= $object->context['infrastructurePdfModelInfo']->cols;
				if (property_exists($object->context['infrastructurePdfModelInfo'], 'defaultTitlesFieldsStyle')) {
					$staticPdfModel->defaultTitlesFieldsStyle	= $object->context['infrastructurePdfModelInfo']->defaultTitlesFieldsStyle;
				}
				if (property_exists($object->context['infrastructurePdfModelInfo'], 'defaultContentsFieldsStyle')) {
					$staticPdfModel->defaultContentsFieldsStyle	= $object->context['infrastructurePdfModelInfo']->defaultContentsFieldsStyle;
				}
				$staticPdfModel->prepareArrayColumnField($object, $langs);
				if (isset($staticPdfModel->cols['totalexcltax']['content']['padding'][0])) {
					$infrastructureDefaultTopPadding		= $staticPdfModel->cols['totalexcltax']['content']['padding'][0];
				}
				if (isset($staticPdfModel->cols['totalexcltax']['content']['padding'][2])) {
					$infrastructureDefaultBottomPadding	= $staticPdfModel->cols['totalexcltax']['content']['padding'][0];
				}

				if (isset($staticPdfModel->cols['totalincltax']['content']['padding'][0])) {
					$infrastructureDefaultTopPadding		= $staticPdfModel->cols['totalincltax']['content']['padding'][0];
				}
				if (isset($staticPdfModel->cols['totalincltax']['content']['padding'][2])) {
					$infrastructureDefaultBottomPadding	= $staticPdfModel->cols['totalincltax']['content']['padding'][0];
				}
			}
			$hideInnerLines	= GETPOST('hideInnerLines', 'int');
			$hidePriceOnInfrastructureLines	= GETPOST('hide_price_on_infrastructure_lines', 'int');
			if ($object->element == 'shipping' || $object->element == 'delivery') {
				$hidePriceOnInfrastructureLines = 1;
			}
			$set_pagebreak_margin	= false;
			if (method_exists('Closure', 'bind')) {
				$pagebreakOriginalValue = $pdf->AcceptPageBreak();
				$sweetsThief = function ($pdf) {
						return $pdf->bMargin ;
				};
				$sweetsThief	= Closure::bind($sweetsThief, null, $pdf);
				$bMargin		= $sweetsThief($pdf);
				$pdf->SetAutoPageBreak(false);
				$set_pagebreak_margin = true;
			}
			if ($line->qty == 99) {
				$pdf->SetFillColor(220, 220, 220);
			} elseif ($line->qty == 98) {
				$pdf->SetFillColor(230, 230, 230);
			} else {
				$pdf->SetFillColor(240, 240, 240);
			}
			$style				= getDolGlobalString('INFRASTRUCTURE_PDF_TOTAL_STYLE');
			$pdf->SetFont('', $style, 9);
			$curentCellPaddinds = $pdf->getCellPaddings();	// save curent cell padding
			// Sauvegarde des paddings d'origine pour restauration dans pdf_writelinedesc (prochaine ligne non-sous-total). Permet de propager le padding aux MultiCell des colonnes voisines (TVA, Total HT, Total TTC) afin d'aligner verticalement leurs valeurs avec le libellé.
			if ($this->infrastructureSavedCellPaddings === null) {
				$this->infrastructureSavedCellPaddings	= $curentCellPaddinds;
			}
			// Padding appliqué systématiquement (comme dans pdfAddTitle ligne 1179) pour que le libellé du sous-total soit centré dans son background avec la même mise en page que le libellé d'un titre.
			$pdf->setCellPaddings($curentCellPaddinds['L'], $infrastructureDefaultTopPadding, $curentCellPaddinds['R'], $infrastructureDefaultBottomPadding);
			$pdf->writeHTMLCell($w, $h, $posx, $posy, $label, 0, 1, false, true, 'R', true);
			$pageAfter			= $pdf->getPage();
			// getStringHeight() ne parse pas le HTML : un '<br />' littéral (ex. cumul des montants optionnels) n'est pas compté comme un retour à la ligne, contrairement à writeHTMLCell() ci-dessus. On le convertit en saut de ligne réel (\n, code 10) uniquement pour ce calcul de hauteur.
			$cell_height		= $pdf->getStringHeight($w, preg_replace('/<br\s*\/?>/i', "\n", $label));	//Print background
			// Étendre le bandeau du sous-total de la marge gauche à la marge droite du PDF (couvre toutes les colonnes : Réf → Total HT/TTC).
			$pdfMarginsForBg	= $pdf->getMargins();
			$totalBgStartX		= isset($pdfMarginsForBg['left']) ? $pdfMarginsForBg['left'] : $posx;
			$totalBgRight		= isset($pdfMarginsForBg['right']) ? $pdfMarginsForBg['right'] : 0;
			$totalBgWidth		= $pdf->getPageWidth() - $totalBgStartX - $totalBgRight;
			// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
			if ($pdfModelUseColSystem) {
				if ($fillBackground) {
					$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
				}
				$pdf->SetXY($object->context['infrastructurePdfModelInfo']->marge_gauche, $posy + $backgroundCellPosYOffset);
				$pdf->MultiCell($object->context['infrastructurePdfModelInfo']->page_largeur - $object->context['infrastructurePdfModelInfo']->marge_gauche - $object->context['infrastructurePdfModelInfo']->marge_droite, $cell_height, '', 0, '', 1);
			} else {
				$pdf->SetXY($totalBgStartX, $posy + $backgroundCellPosYOffset); //-1 to take into account the entire height of the row
				//background color
				if ($fillBackground) {
					$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
					$pdf->SetFont('', '', 9); //remove UBI for the background
					$pdf->MultiCell($totalBgWidth, $cell_height + $backgroundCellHeightOffset, '', 0, '', 1); //+2 same of SetXY()
					$pdf->SetXY($posx, $posy); //reset position
					$pdf->SetFont('', $style, 9); //reset style
				} else {
					$pdf->MultiCell($totalBgWidth, $cell_height, '', 0, '', 1);
				}
			}
			if (!$hidePriceOnInfrastructureLines) {
				$total_to_print		= price($line->total, 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
				if ($use_multicurrency) {
					$total_to_print	= price($line->multicurrency_total_ht,0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
				}
				if (getDolGlobalString('INFRASTRUCTURE_MANAGE_OL')) {
					$TTitle	= infrastructure_getCachedAllTitleFromLine($object, $line);
					foreach ($TTitle as &$line_title) {
						if (!empty($line_title->array_options['options_infrastructure_ol'])) {
							$total_to_print = ''; // TODO Gestion des blocs optionnels, voir si on affiche une annotation du genre "Option"
							break;
						}
					}
				}
				if ($total_to_print !== '') {
					if (GETPOST('hideInnerLines', 'int')) {
						// Le calcul est censé être fait dans beforePDFCreation. Fallback de secours si total à 0
						// alors qu'on a sauvegardé les lignes originales (ex. clone PHP qui aurait perdu la valeur).
						if ((float) $line->total == 0 && !empty($object->context['infrastructureCache']['originalLines'])) {
							$savedLines				= $object->lines;
							$object->lines			= $object->context['infrastructureCache']['originalLines'];
							$savedCache				= $object->context['infrastructureCache'];
							$object->context['infrastructureCache']	= [];
							$TInfo					= infrastructure_get_totalLineFromObject($object, $line, false, 1);
							$object->lines			= $savedLines;
							$object->context['infrastructureCache']	= $savedCache;
							$total_to_print			= price($TInfo[0], 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
							if ($use_multicurrency) {
								$total_to_print		= price($TInfo[6], 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
							}
							$line->total_ht			= $TInfo[0];
							$line->total			= $TInfo[0];
							$line->total_ttc		= $TInfo[2];
							$line->multicurrency_total_ht	= $TInfo[6];
							$line->multicurrency_total_ttc	= $TInfo[7];
						}
					} else {
						$TInfo			= infrastructure_get_totalLineFromObject($object, $line, false, 1);
						$TTotal_tva		= $TInfo[3];
						$total_to_print = price($TInfo[0], 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
						if ($use_multicurrency) {
							$total_to_print = price($TInfo[6],0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
						}
						$line->total_ht	= $TInfo[0];
						$line->total	= $TInfo[0];
						if (!TInfrastructure::isModInfrastructureLine($line)) {
							$line->total_tva = $TInfo[1];
						}
						$line->total_ttc = $TInfo[2];
					}
				}
				$pdf->SetXY($pdf->postotalht, $posy);
				if ($set_pagebreak_margin) {
					$pdf->SetAutoPageBreak($pagebreakOriginalValue, $bMargin);
				}
				if ($pdfModelUseColSystem) {
					// Modèles à colonnes configurables (InfraSPlus) : printStdColumnContent dessine le total directement (les hooks pdf_getline* ne sont pas appelés pour ces modèles).
					$staticPdfModel->printStdColumnContent($pdf, $posy, 'totalexcltax', $total_to_print);
					if (getDolGlobalString('PDF_PROPAL_SHOW_PRICE_INCL_TAX')) {
						$staticPdfModel->printStdColumnContent($pdf, $posy, 'totalincltax', price($line->total_ttc, 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT')));
					}
				}
				// Modèles natifs Dolibarr (azur, crabe, etc.) : le total HT/TTC est rendu via les hooks pdf_getlinetotalexcltax / pdf_getlinetotalwithtax (universels depuis 18.3.1) — pas de dessin direct ici pour éviter le doublon avec ces hooks.
			} else {
				if ($set_pagebreak_margin) {
					$pdf->SetAutoPageBreak($pagebreakOriginalValue, $bMargin);
				}
			}
			// Pas de restauration des paddings ici : on les laisse actifs pour que les hooks pdf_getlinevatrate / pdf_getlinetotalexcltax / pdf_getlinetotalwithtax (appelés ensuite par le modèle PDF pour rendre les colonnes voisines de la ligne sous-total) bénéficient du même padding 1mm haut/bas. Ils seront restaurés au début de la prochaine ligne via pdf_writelinedesc.
			$posy	= $posy + $cell_height;
			$pdf->SetXY($posx, $posy);
			$pdf->setColor('text', 0, 0, 0);
		}

		/**
		* PDF add title
		*
		* @param	TCPDF|ModelePDFStatic	$pdf			PDF object
		* @param	CommonObject			$object 		Object
		* @param	CommonObjectLine		$line			Line
		* @param	string					$label			Label
		* @param	string					$description	Description
		* @param	float					$posx			Horizontal position
		* @param	float					$posy			Vertical position
		* @param	float					$w				Width
		* @param	float					$h				Height
		* @return	void
		*/
		public function pdfAddTitle(&$pdf, &$object, &$line, $label, $description, $posx, $posy, $w, $h)
		{
			global $hidedesc;

			// Show table header before this title (option show_table_header_before)
			if (is_object($line) && empty($line->array_options) && method_exists($line, 'fetch_optionals')) {
				$line->fetch_optionals();
			}
			if (!empty($line->array_options['options_show_table_header_before']) && $line->array_options['options_show_table_header_before'] > 0) {
				$pdfModel	= infrastructure_getCallerNativePdfModel($object);
				if (is_object($pdfModel)) {
					$consumed	= infrastructure_drawNativeTableHeaderBefore($pdf, $pdfModel, $posy);
					if ($consumed > 0) {
						$posy	+= $consumed + 1;
						$currentPage	= (int) $pdf->getPage();
						if (!in_array($currentPage, $this->cachedRedrawnHeaderPages, true)) {
							$this->cachedRedrawnHeaderPages[]	= $currentPage;
						}
					}
				}
			}
			empty($pdf->page_largeur) ? $pdf->page_largeur = 0 : '';
			empty($pdf->marge_droite) ? $pdf->marge_droite = 0 : '';
			// Étendre le BANDEAU DE FOND de la marge gauche à la marge droite du PDF (couvre toutes les colonnes : Réf → Total HT/TTC).
			$pdfMargins			= $pdf->getMargins();
			$titleBlockX		= isset($pdfMargins['left']) ? $pdfMargins['left'] : $posx;
			$titleBlockRight	= isset($pdfMargins['right']) ? $pdfMargins['right'] : 0;
			$titleBlockW		= $pdf->getPageWidth() - $titleBlockX - $titleBlockRight;
			// Le TEXTE du titre, lui, démarre à $posx (position réelle de la colonne Désignation, calculée par le modèle
			// PDF appelant) et non à la marge brute : sans ça, le libellé chevauche la colonne Num/Réf quand celle-ci
			// est positionnée en rang 1 (INFRASPLUS_PDF_WITH_NUM_COLUMN + INFRASPLUS_PDF_NUMCOL_REF=1).
			$titleTextX			= !empty($posx) ? $posx : $titleBlockX;
			$titleTextW			= $titleBlockX + $titleBlockW - $titleTextX;
			// Manage background color
			$fillDescBloc				= false;
			$bgStyle					= infrastructure_getPdfBackgroundStyle($pdf, 'INFRASTRUCTURE_PDF_TITLE_BACKGROUND_COLOR', 'INFRASTRUCTURE_PDF_TITLE_BACKGROUND_CELL_HEIGHT_OFFSET', 'INFRASTRUCTURE_PDF_TITLE_BACKGROUND_CELL_POS_Y_OFFSET', $line);
			$fillBackground				= $bgStyle['fill'];
			$backgroundColor			= $bgStyle['color'];
			$backgroundCellHeightOffset	= $bgStyle['heightOffset'];
			$backgroundCellPosYOffset	= $bgStyle['posYOffset'];
			// User-configured text color override (takes precedence over auto white-on-dark from infrastructure_getPdfBackgroundStyle).
			infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TITLE_COLOR');
			$pdf->SetTextColor(0, 0, 0);
			// Réservation d'espace : pour tout titre infrastructure (avec ou sans totaux stockés via INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL), si le couple « libellé du titre + description optionnelle + (ligne de totaux si applicable) » ne tient pas sur la page courante, on force un AddPage propre AVANT le rendu. Sans cette précaution, le writeHTMLCell du libellé déclenche un auto-page-break TCPDF en plein milieu du titre — le label se retrouve à cheval ou perdu entre les deux pages, une page parasite vide est créée, et le MultiCell du bandeau de fond (appelé après le writeHTMLCell, avec SetXY à $posy + offset) est dessiné sur la page d'arrivée à la position Y de l'ancienne page : on observe alors un bandeau vide sans texte en haut de la page suivante. Pour le mode INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL actif, on ajoute en plus la hauteur de la ligne de totaux redessinée par infrastructure_drawTitleColumnsAtPosY.
			// Compat. modèles natifs Dolibarr (pdf_crabe, pdf_azur, etc.) : ces modèles entourent l'appel à pdf_writelinedesc d'un startTransaction / rollbackTransaction(true) et, en cas de saut de page détecté au 1er essai, ils réduisent temporairement la marge basse via setPageOrientation('', true, $heightforfooter) (~12 mm au lieu des 80-120 mm initiaux pour la zone footer + totaux + freetext + QR) avant un 2e appel. Conséquence : notre AddPage du 1er essai est annulé par le rollback, et au 2e essai notre check $posy + $reservedH > getPageHeight() - getBreakMargin() ne se déclencherait plus (marge basse trop courte) — le titre serait rendu en débordement en bas de page courante puis les lignes suivantes se dessineraient par-dessus le footer. On applique donc un plancher conservatif de 25 mm à la marge basse pour le calcul du pagebreakTrigger, ce qui garantit que notre AddPage explicite se redéclenche correctement au 2e essai hors transaction. De plus, on désactive l'auto-page-break TCPDF pendant le rendu du couple writeHTMLCell + MultiCell pour le rendre atomique (sinon l'auto-page-break interne pourrait toujours fragmenter le titre dans des cas non couverts par notre estimation de hauteur).
			$reservedSizeTitle		= (float) (getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_SIZE') ? getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_SIZE') : 9);
			$reservedLabelH			= max((float) $h, $reservedSizeTitle * 0.6);
			$reservedDescH			= !empty($description) && empty($hidedesc) ? max((float) $h, ($reservedSizeTitle - 1) * 0.5) + 1 : 0;
			$reservedTotalsRowH		= isset($line->infrastructure_title_total_ht) ? 5 : 0;	// heightline 3mm + paddings ~2mm dans infrastructure_drawTitleColumnsAtPosY (option INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL active)
			$reservedH				= $reservedLabelH + $reservedDescH + $reservedTotalsRowH;
			$savedAutoPageBreak		= $pdf->getAutoPageBreak();
			$savedBreakMargin		= (float) $pdf->getBreakMargin();
			$effectiveBreakMargin	= max($savedBreakMargin, 25.0);
			$pagebreakTrigger		= $pdf->getPageHeight() - $effectiveBreakMargin;
			if ($posy + $reservedH > $pagebreakTrigger) {
				$pdf->AddPage('', '', true);
				$newPageMargins		= $pdf->getMargins();
				$posy				= isset($newPageMargins['top']) && $newPageMargins['top'] > 0 ? (float) $newPageMargins['top'] : 10.0;
			}
			$pdf->SetAutoPageBreak(false, 0);
			$infrastructure_last_title_posy	= $posy;
			$pdf->SetXY($titleTextX, $posy);
			$hideInnerLines			= GETPOST('hideInnerLines', 'int');
			$style					= getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_STYLE');
			$size_title = 9;
			if (getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_SIZE')) {
				$size_title = getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_SIZE');
			}
			if ($hideInnerLines) {
				if (getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_STYLE_IF_HIDDEN_LINES')) {
					$style = getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_STYLE_IF_HIDDEN_LINES');
				}
			}
			$pdf->SetFont('', $style, $size_title);
			// save curent cell padding
			$curentCellPaddinds = $pdf->getCellPaddings();
			// set cell padding with column content definition PDF
			$pdf->setCellPaddings($curentCellPaddinds['L'], 1, $curentCellPaddinds['R'], 1);
			$posYBeforeTile = $pdf->GetY();
			if ($label === strip_tags($label) && $label === dol_html_entity_decode($label, ENT_QUOTES)) {
				$pdf->MultiCell($titleTextW, $h, $label, 0, 'L', $fillDescBloc); // Pas de HTML dans la chaine
			} else {
				$pdf->writeHTMLCell($titleTextW, $h, $titleTextX, $posy, $label, 0, 1, $fillDescBloc, true, 'J', true); // et maintenant avec du HTML
			}
			$posYBeforeDesc = $pdf->GetY();
			if ($description && !($hidedesc ?? 0)) {
				$pdf->setColor('text', 0, 0, 0);
				$pdf->SetFont('', '', $size_title - 1);
				$pdf->writeHTMLCell($titleTextW, $h, $titleTextX, $posYBeforeDesc + 1, $description, 0, 1, $fillDescBloc, true, 'J', true);
			}
			//background color
			if ($fillBackground) {
				$posYAfterDesc	= $pdf->GetY();
				// getStringHeight() doit utiliser la même largeur que celle utilisée pour le rendu réel du libellé
				// ($titleTextW, potentiellement plus étroite que le bandeau de fond $titleBlockW) : sinon, en cas de
				// retour à la ligne différent entre les deux largeurs, la hauteur du bandeau de fond serait sous-estimée.
				$cell_height	= $pdf->getStringHeight($titleTextW, $label) + $backgroundCellHeightOffset;
				$bgStartX		= $titleBlockX;
				$bgW			= $titleBlockW;
				// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
				if (!empty($object->context['infrastructurePdfModelInfo']->cols)) {
					$bgStartX	= $object->context['infrastructurePdfModelInfo']->marge_gauche;
					$bgW 		= $object->context['infrastructurePdfModelInfo']->page_largeur - $object->context['infrastructurePdfModelInfo']->marge_gauche - $object->context['infrastructurePdfModelInfo']->marge_droite;
				}
				$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
				$pdf->SetXY($bgStartX, $posy + $backgroundCellPosYOffset); //-2 to take into account  the entire height of the row
				$pdf->MultiCell($bgW, $cell_height, '', 0, '', 1, 1, null, null, true, 0, true); //+2 same of SetXY()
				$posy = $posYAfterDesc;
				$pdf->SetXY($titleBlockX, $posy); //reset position
				$pdf->SetFont('', $style, $size_title); //reset style
				$pdf->SetColor('text', 0, 0, 0); // restore default text color;
			}
			// restore cell padding
			$pdf->setCellPaddings($curentCellPaddinds['L'], $curentCellPaddinds['T'], $curentCellPaddinds['R'], $curentCellPaddinds['B']);
			// Option INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL : les hooks vat/total ont été neutralisés systématiquement pour les titres porteurs de totaux stockés (sans cela, Dolibarr dessinerait les valeurs au $curY d'origine — sans tenir compte du padding top 1mm appliqué au libellé du titre — soit ~1mm trop haut ; et après un saut de page, au mauvais Y sur la mauvaise page). On redessine ici manuellement les colonnes TVA / Total HT à la position Y réelle du titre avec le même décalage vertical que le libellé.
			if (isset($line->infrastructure_title_total_ht)) {
				$pdfModel	= infrastructure_getCallerNativePdfModel($object);
				if (is_object($pdfModel)) {
					infrastructure_drawTitleColumnsAtPosY($pdf, $pdfModel, $object, $line, $infrastructure_last_title_posy);
				}
			}
			// Restauration de l'auto-page-break TCPDF avec sa marge basse d'origine (cf. désactivation en début de méthode pour rendu atomique).
			$pdf->SetAutoPageBreak($savedAutoPageBreak, $savedBreakMargin);
		}

		/**
		* PDF write line desc ref
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_writelinedesc_ref($parameters = [], &$object, &$action = '')
		{
			return $this->pdf_writelinedesc($parameters, $object, $action);
		}

		/**
		* Is mod infrastructure line
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @return	bool
		*/
		public function isModInfrastructureLine(&$parameters, &$object)
		{

			$i		= is_array($parameters) ? $parameters['i'] : (int) $parameters;
			$line	= $object->lines[$i] ?? '';
			if ($object->element == 'shipping' || $object->element == 'delivery') {
				$line = new OrderLine($object->db);
				$line->fetch(!empty($object->lines[$i]->fk_elementdet) ? $object->lines[$i]->fk_elementdet : 0);
			}
			if (is_object($line) && property_exists($line, 'special_code') && $line->special_code == $this->module_number && $line->product_type == 9) {
				return true;
			}
			return false;
		}

		/**
		* Applique le style et la couleur PDF dédiés aux lignes optionnelles (case « Opt », extrafield
		* options_infrastructure_ol) sur la colonne courante, si INFRASTRUCTURE_PDF_OL_SHOW_DETAILS est actif.
		* Appelée depuis chaque hook pdf_getline... et pdf_writelinedesc pour que le style/la couleur s'applique
		* à toutes les colonnes de la ligne, pas seulement à sa description.
		*
		* @param	TCPDF|ModelePDFStatic	$pdf		Instance PDF courante
		* @param	CommonObject			$object		Document en cours de génération
		* @param	int						$i			Index de la ligne dans $object->lines
		* @return	bool								true si le style/la couleur a été appliqué
		*/
		private function applyOlPdfStyle(&$pdf, &$object, $i)
		{
			if (empty($object->lines[$i]) || !getDolGlobalInt('INFRASTRUCTURE_PDF_OL_SHOW_DETAILS')) {
				return false;
			}
			$line	= $object->lines[$i];
			if (empty($line->array_options)) {
				$line->fetch_optionals();
			}
			if (empty($line->array_options['options_infrastructure_ol']) || !is_object($pdf)) {
				return false;
			}
			$pdf->SetFont('', getDolGlobalString('INFRASTRUCTURE_PDF_OL_STYLE'));
			infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_OL_COLOR');
			return true;
		}

		/**
		* Before percent calculation
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	void
		*/
		public function beforePercentCalculation($parameters = [], &$object, &$action = '')
		{
			if ($object->name == 'sponge' && isset($parameters['object']) && !empty($parameters['object']->lines)) {
				foreach ($parameters['object']->lines as $k => $line) {
					if (TInfrastructure::isModInfrastructureLine($line)) {
						unset($parameters['object']->lines[$k]);
					}
				}
			}
		}

		/**
		* PDF get line qty
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineqty($parameters = [], &$object, &$action = '')
		{
			global $hideqtys, $hideprices, $hookmanager, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				if ($this->infrastructure_sum_qty_enabled === true) {
					$line_qty = intval($line->qty);
					if ($line_qty < 50) {
						// it's a title level (init level qty)
						$infrastructure_level = $line_qty;
						$this->infrastructure_level_cur = $infrastructure_level;
						TInfrastructure::setInfrastructureQtyForObject($object, $infrastructure_level, 0);
						// not show qty for title lines
						$this->resprints = '';
						return 1;
					} elseif ($line_qty > 50) {
						// it's a infrastructure level (show level qty and reset)
						$infrastructure_level = 100 - $line_qty;
						$level_qty_total = $object->TInfrastructureQty[$infrastructure_level];
						TInfrastructure::setInfrastructureQtyForObject($object, $infrastructure_level, 0);
						// show quantity sum only if it's a infrastructure line (level)
						$line_show_qty = TInfrastructure::showQtyForObjectLine($line, $this->infrastructure_show_qty_by_default);
						if ($line_show_qty === false) {
							$this->resprints = '';
						} else {
							$this->resprints = $level_qty_total;
							if (is_object($pdf)) {
								infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
							}
						}
						return 1;
					} else {
						// not show qty for text line
						$this->resprints = '';
						return 1;
					}
				} else {
					$this->resprints = ' ';
					return 1;
				}
			} else {
				if (!empty($line) && $line->special_code == 3 && getDolGlobalInt('INFRASTRUCTURE_PDF_OL_SHOW_DETAILS')) {
					// Ligne optionnelle (special_code = 3, aussi posé par la case « Opt » du module infrastructure) : on force
					// l'affichage de la quantité, sans quoi pdf_getlineqty() (core, htdocs/core/lib/pdf.lib.php) la masquerait
					// nativement. Ce retour anticipé court-circuitait l'application du style/couleur OL plus bas dans la méthode.
					$this->applyOlPdfStyle($pdf, $object, $i);
					$this->resprints = $line->qty;
					return 1;
				}
				if ($this->infrastructure_sum_qty_enabled === true) {
					// sum quantities by infrastructure level
					if ($this->infrastructure_level_cur >= 1) {
						for ($infrastructure_level = 1; $infrastructure_level <= $this->infrastructure_level_cur; $infrastructure_level++) {
							TInfrastructure::addInfrastructureQtyForObject($object, $infrastructure_level, $line->qty);
						}
					}
				}
				// hideqtys : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				// Indépendant de hideprices ; les deux options peuvent être actives en même temps.
				if (!empty($hideqtys)) {
					$lineTitle	= (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang): '';
					if (!empty($lineTitle) && infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
						$this->resprints	= ' ';
						$params				= ['parameters' => $parameters, 'currentmethod' => 'pdf_getlineqty', 'currentcontext' => 'infrastructure_hideqtys', 'i' => $i];
						return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
					}
					$this->resprints	= $object->lines[$parameters['i']]->qty;
				}
			}
			if (is_array($parameters)) $i = &$parameters['i'];
			else $i = (int) $parameters;
			/** Attention, ici on peut ce retrouver avec un objet de type stdClass à cause de l'option cacher le détail des ensembles avec la notion d'option (@see beforePDFCreation()) et dû à l'appel de TInfrastructure::hasOlTitle() */
			if (empty($object->lines[$i]->id)) return 0; // hideInnerLines => override $object->lines et Dolibarr ne nous permet pas de mettre à jour la variable qui conditionne la boucle sur les lignes (PR faite pour 6.0)
			if (empty($object->lines[$i]->array_options)) $object->lines[$i]->fetch_optionals();
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line total excl tax
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlinetotalexcltax($parameters = [], &$object, &$action = '')
		{
			global $conf, $hideprices, $hideqtys, $hookmanager, $hidedetails, $langs, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				// VAT lines invisibles injectées par beforePDFCreation (mode hideInnerLines)
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				// Titres porteurs de totaux stockés (option INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL active) : on neutralise systématiquement le hook (resprints vide), pdfAddTitle redessine manuellement les colonnes au bon Y (le padding top 1mm du libellé titre n'est pas pris en compte par le $curY que Dolibarr passe à printStdColumnContent, ce qui produit un décalage vertical sans cette neutralisation).
				if (TInfrastructure::isTitle($line) && isset($line->infrastructure_title_total_ht)) {
					$this->resprints	= '';
					return 1;
				}
				// Titres et textes libres : pas de total à afficher
				if (!TInfrastructure::isTotal($line)) { $this->resprints = ' '; return 1; }
				// Sous-totaux : calcul et affichage du total du bloc (logique unifiée pour les modèles PDF natifs Dolibarr ET InfraSPlus)
				$use_multicurrency				= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1 ? 1 : 0;
				$hidePriceOnInfrastructureLines	= $object->element == 'shipping' || $object->element == 'delivery' ? 1 : GETPOST('hide_price_on_infrastructure_lines', 'int');
				if (!empty($hidePriceOnInfrastructureLines)) {
					$this->resprints	= ' ';
					return 1;
				}
				$total_to_print	= price($object->lines[$i]->total);
				if (getDolGlobalInt('INFRASTRUCTURE_MANAGE_OL')) {
					$TTitle	= infrastructure_getCachedAllTitleFromLine($object, $object->lines[$i]);
					foreach ($TTitle as &$line_title) {
						if (!empty($line_title->array_options['options_infrastructure_ol'])) {
							$total_to_print	= ''; // TODO Gestion des blocs optionnels, voir si on affiche une annotation du genre "Option"
							break;
						}
					}
				}
				if ($total_to_print !== '') {
					if (GETPOST('hideInnerLines', 'int')) {
						// Dans le cas des lignes cachées, le calcul est déjà fait dans la méthode beforePDFCreation et les lignes de sous-totaux sont déjà renseignées
					} else {
						$TInfo						= infrastructure_get_totalLineFromObject($object, $object->lines[$i], false, 1);
						$TTotal_tva					= $TInfo[3];
						// Formatage standard Dolibarr (sans symbole monétaire) pour rester cohérent avec les autres lignes du document.
						$total_to_print				= price($TInfo[0], 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
						if ($use_multicurrency) {
							$total_to_print			= price($TInfo[6], 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
						}
						$object->lines[$i]->total						= $TInfo[0];
						$object->lines[$i]->total_ht					= $TInfo[0];
						$object->lines[$i]->total_tva					= !TInfrastructure::isModInfrastructureLine($object->lines[$i]) ? $TInfo[1] : $object->lines[$i]->total_tva;
						$object->lines[$i]->total_ttc					= $TInfo[2];
						$object->lines[$i]->multicurrency_total_ht		= $TInfo[6];
						$object->lines[$i]->multicurrency_total_ttc		= $TInfo[7];
					}
				}
				// Applique le style et la couleur du sous-total avant le rendu Dolibarr (le SetFont/SetTextColor de pdfAddTotal a été réinitialisé à noir avant que ce hook soit appelé pour la cellule Total HT).
				if (is_object($pdf)) {
					$totalStyle	= getDolGlobalString('INFRASTRUCTURE_PDF_TOTAL_STYLE');
					$pdf->SetFont('', $totalStyle, 9);
					infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
				}
				$this->resprints	= !empty($total_to_print) ? $total_to_print : ' ';
				return 1;
			}
			if (!empty($hideprices)) {
				// hideprices : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang): '';
				if (!empty($lineTitle) && infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
					$this->resprints = ' ';
					$params = ['parameters' => $parameters, 'currentmethod' => 'pdf_getlinetotalexcltax', 'currentcontext' => 'infrastructure_hideprices', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			} elseif (!empty($hidedetails)) {
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang): '';
				if (!($lineTitle && infrastructure_getCachedTitleHasTotal($object, $lineTitle, true))) {
					$this->resprints = price($object->lines[$i]->total_ht, 0, $langs);
					$params = ['parameters' => $parameters, 'currentmethod' => 'pdf_getlinetotalexcltax', 'currentcontext' => 'infrastructure_hidedetails', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* Remplace le retour de la méthode qui l'appelle par un standard 1 ou autre chose celon le hook
		*
		* @param	CommonObject	$object			Object
		* @param	HookManager		$hookmanager	Hook manager
		* @param	string			$action			Action
		* @param	array			$params			Parameters
		* @param	int				$defaultReturn	Default return value
		* @return	int 1, 0, -1
		*/
		private function callHook(&$object, &$hookmanager, $action, $params, $defaultReturn = 1)
		{
			$reshook = $hookmanager->executeHooks('infrastructureHidePrices', $params, $object, $action);
			if ($reshook < 0) {
				$this->error	= $hookmanager->error;
				$this->errors	= $hookmanager->errors;
				return -1;
			} elseif (empty($reshook)) {
				if (property_exists($hookmanager, 'resPrints')) {
					$this->resprints	.= $hookmanager->resPrint;
				}
			} else {
				$this->resprints = $hookmanager->resPrint;
				// override return (use $this->results['overrideReturn'] or $this->resArray['overrideReturn'] in other module action_xxxx.class.php )
				if (isset($this->results['overrideReturn'])) {
					return $this->results['overrideReturn'];
				}
			}
			return $defaultReturn;
		}

		/**
		* PDF get line total with tax
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlinetotalwithtax($parameters = [], &$object, &$action = '')
		{
			global $conf, $langs, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				// VAT lines invisibles injectées par beforePDFCreation (mode hideInnerLines)
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				// Titres porteurs de totaux stockés (option INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL active) : neutralisation systématique du hook, voir le commentaire équivalent dans pdf_getlinetotalexcltax.
				if (TInfrastructure::isTitle($line) && isset($line->infrastructure_title_total_ttc)) {
					$this->resprints	= '';
					return 1;
				}
				// Titres et textes libres : pas de total à afficher
				if (!TInfrastructure::isTotal($line)) { $this->resprints = ' '; return 1; }
				// Sous-totaux : calcul et affichage du total TTC du bloc (logique unifiée pour tous les modèles PDF)
				$hidePriceOnInfrastructureLines	= $object->element == 'shipping' || $object->element == 'delivery' ? 1 : GETPOST('hide_price_on_infrastructure_lines', 'int');
				if (!empty($hidePriceOnInfrastructureLines)) {
					$this->resprints	= ' ';
					return 1;
				}
				$total_to_print	= price($object->lines[$i]->total_ttc);
				if (getDolGlobalInt('INFRASTRUCTURE_MANAGE_OL')) {
					$TTitle	= infrastructure_getCachedAllTitleFromLine($object, $object->lines[$i]);
					foreach ($TTitle as &$line_title) {
						if (!empty($line_title->array_options['options_infrastructure_ol'])) {
							$total_to_print	= ''; // TODO Gestion des blocs optionnels, voir si on affiche une annotation du genre "Option"
							break;
						}
					}
				}
				if ($total_to_print !== '') {
					if (GETPOST('hideInnerLines', 'int')) {
						// Calcul déjà fait dans beforePDFCreation
					} else {
						$TInfo							= infrastructure_get_totalLineFromObject($object, $object->lines[$i], false, 1);
						$TTotal_tva						= $TInfo[3];
						// Formatage standard Dolibarr (sans symbole monétaire) pour rester cohérent avec les autres lignes du document.
						$total_to_print					= price($TInfo[2], 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
						$object->lines[$i]->total		= $TInfo[0];
						$object->lines[$i]->total_ht	= $TInfo[0];
						$object->lines[$i]->total_tva	= !TInfrastructure::isModInfrastructureLine($object->lines[$i]) ? $TInfo[1] : $object->lines[$i]->total_tva;
						$object->lines[$i]->total_ttc	= $TInfo[2];
					}
				}
				// Applique le style et la couleur du sous-total avant le rendu Dolibarr (le SetFont/SetTextColor de pdfAddTotal a été réinitialisé à noir avant que ce hook soit appelé pour la cellule Total TTC).
				if (is_object($pdf)) {
					$totalStyle	= getDolGlobalString('INFRASTRUCTURE_PDF_TOTAL_STYLE');
					$pdf->SetFont('', $totalStyle, 9);
					infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
				}
				$this->resprints	= !empty($total_to_print) ? $total_to_print : ' ';
				return 1;
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line unit
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineunit($parameters = [], &$object, &$action = '')
		{
			global $conf, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				return 1;
			}
			if (is_array($parameters)) {
				$i = &$parameters['i'];
			} else {
				$i = (int) $parameters;
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line up excl tax
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineupexcltax($parameters = [], &$object, &$action = '')
		{
			global $conf, $pdf, $hideprices, $hidedetails, $hookmanager, $langs;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				// On récupère les montants du bloc pour les afficher dans la ligne de sous-total
				if (TInfrastructure::isTotal($line)) {
					$parentTitle = infrastructure_getCachedParentTitle($object, $line->rang);
					if (is_object($parentTitle) && empty($parentTitle->array_options)) {
						$parentTitle->fetch_optionals();
					}
					if (!empty($parentTitle->array_options['options_show_total_ht'])) {
						$TTotal					= TInfrastructure::getTotalBlockFromTitle($object, $parentTitle);
						$useMulticurrency		= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1;
						$valueToDisplay			= $useMulticurrency ? $TTotal['multicurrency_total_unit_subprice'] : $TTotal['total_unit_subprice'];
						infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
						$this->resprints		= price($valueToDisplay, 0, '', 1, 0, getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
					}
				}
				return 1;
			}
			if (!empty($hideprices)) {
				// hideprices : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang): '';
				if (!empty($lineTitle) && infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
					$this->resprints = ' ';
					$params = ['parameters' => $parameters, 'currentmethod' => 'pdf_getlineupexcltax', 'currentcontext' => 'infrastructure_hideprices', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			} elseif (!empty($hidedetails)) {
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang) : '';
				if (!($lineTitle && infrastructure_getCachedTitleHasTotal($object, $lineTitle, true))) {
					$this->resprints = price($object->lines[$i]->subprice, 0, $langs);
					$params = ['parameters' => $parameters, 'currentmethod' => 'pdf_getlineupexcltax', 'currentcontext' => 'infrastructure_hidedetails', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				} //
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line remise percent
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineremisepercent($parameters = [], &$object, &$action = '')
		{
			global $conf, $hideqtys, $hideprices, $hidedetails, $hookmanager, $langs, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				// Affichage de la remise
				if (TInfrastructure::isTotal($line)) {
					if ($parentTitle = infrastructure_getCachedParentTitle($object, $line->rang)) {
						if (empty($parentTitle->array_options)) {
							$parentTitle->fetch_optionals();
						}
						if (!empty($parentTitle->array_options['options_show_reduc'])) {
							$TTotal				= TInfrastructure::getTotalBlockFromTitle($object, $parentTitle);
							$this->resprints	= price((1 - $TTotal['total_ht'] / $TTotal['total_subprice']) * 100, 0, '', 1, 2, 2).'%';
						}
					}
				}
				return 1;
			} elseif (!empty($hideprices)) {
				// hideprices : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				$lineTitle	= infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang);
				if (!empty($lineTitle) && infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
					$this->resprints	= ' ';
					return 1;
				}
			} elseif (!empty($hidedetails)) {
				$lineTitle	= (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang): '';
				if (!($lineTitle && infrastructure_getCachedTitleHasTotal($object, $lineTitle, true))) {
					$this->resprints	= dol_print_reduction($object->lines[$i]->remise_percent, $langs);
					return 1;
				}
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line up with tax
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineupwithtax($parameters = [], &$object, &$action = '')
		{
			global $conf, $hideqtys, $hideprices, $pdf;

			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;

			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				return 1;
			}
			if (is_array($parameters)) {
				$i = &$parameters['i'];
			} else {
				$i = (int) $parameters;
			}
			if (!empty($hideprices)) {
				// hideprices : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang) : '';
				if (empty($lineTitle) || !infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
					$this->applyOlPdfStyle($pdf, $object, $i);
					return 0; // pas dans un bloc avec sous-total en aval → ne rien faire
				}
				$this->resprints = ' ';
				return 1;
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line vat rate
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlinevatrate($parameters = [], &$object, &$action = '')
		{
			global $hideqtys, $hideprices, $hidedetails, $hookmanager, $pdf;

			$i			= intval($parameters['i']);
			$line		= isset($object->lines[$i]) ? $object->lines[$i] : null;		// Dans le cas des notes de frais report ne pas traiter
			$TContext	= explode(':', $parameters['context']);
			if (in_array('expensereportcard', $TContext))	return 0;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				// Titres porteurs d'un taux TVA stocké (option INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL active) : neutralisation systématique du hook, voir le commentaire équivalent dans pdf_getlinetotalexcltax.
				if (TInfrastructure::isTitle($line) && isset($line->infrastructure_common_vat) && $line->infrastructure_common_vat !== false && $line->infrastructure_common_vat !== null) {
					$this->resprints	= '';
					return 1;
				}
				// L'option SHOW_TVA_ON_TOTAL_LINES n'affiche la TVA que sur les lignes sous-total (qty 91-99) — jamais sur les titres ni les textes libres.
				if (!empty(getDolGlobalString('INFRASTRUCTURE_SHOW_TVA_ON_TOTAL_LINES')) && TInfrastructure::isTotal($line)) {
					// Si applyTitlePrintAsListOrCondensed a déjà retiré les lignes filles, on récupère le taux pré-calculé au moment du gel des totaux.
					if (isset($line->infrastructure_common_vat)) {
						$tva_unique	= $line->infrastructure_common_vat;
					} else {
						$tva_unique	= TInfrastructure::getCommonVATRate($object, $object->lines[$i]);
					}
					if ($tva_unique !== false) {
						$shouldShow	= true;
						if (getDolGlobalInt('INFRASTRUCTURE_LIMIT_TVA_ON_CONDENSED_BLOCS')) {
							// L'option LIMIT_TVA_ON_CONDENSED_BLOCS restreint l'affichage aux sous-totaux dont le titre parent a print_as_list ou print_condensed actif (options portées par le TITRE, pas par le sous-total).
							$parentTitle	= infrastructure_getCachedParentTitle($object, $line->rang);
							if (is_object($parentTitle) && empty($parentTitle->array_options) && method_exists($parentTitle, 'fetch_optionals')) {
								$parentTitle->fetch_optionals();
							}
							$hasPrintOption	= is_object($parentTitle) && (
								(!empty($parentTitle->array_options['options_print_as_list']) && $parentTitle->array_options['options_print_as_list'] > 0)
								|| (!empty($parentTitle->array_options['options_print_condensed']) && $parentTitle->array_options['options_print_condensed'] > 0)
							);
							if (!$hasPrintOption) {
								$shouldShow	= false;
							}
						}
						if ($shouldShow) {
							$this->resprints	= vatrate($tva_unique, true);
							if (is_object($pdf)) {
								$totalStyle	= getDolGlobalString('INFRASTRUCTURE_PDF_TOTAL_STYLE');
								$pdf->SetFont('', $totalStyle, 9);
								infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TOTAL_COLOR');
							}
							return 1;
						}
					}
				}
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				return 1;
			}
			if (empty($object->lines[$i])) return 0; // hideInnerLines => override $object->lines et Dolibarr ne nous permet pas de mettre à jour la variable qui conditionne la boucle sur les lignes (PR faite pour 6.0)
			$object->lines[$i]->fetch_optionals();
			// Cache le prix pour les lignes standards dolibarr qui sont dans un ensemble
			if (!empty($hideprices)) {
				// Check if a title exist for this line && if the title have infrastructure
				// hideprices : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
				$lineTitle = infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang);
				if (!empty($lineTitle) && infrastructure_getCachedTitleHasTotal($object, $lineTitle, false)) {
					$this->resprints = ' ';
					$params = ['parameters' => $parameters, 'currentmethod' => 'pdf_getlinevatrate', 'currentcontext' => 'infrastructure_hideprices', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			} elseif (!empty($hidedetails)) {
				$lineTitle = (!empty($object->lines[$i])) ? infrastructure_getCachedParentTitle($object, $object->lines[$i]->rang) : '';
				if (!($lineTitle && infrastructure_getCachedTitleHasTotal($object, $lineTitle, true))) {
					$this->resprints	= vatrate($object->lines[$i]->tva_tx, true);
					$params				= ['parameters' => $parameters, 'currentmethod' => 'pdf_getlinevatrate', 'currentcontext' => 'infrastructure_hidedetails', 'i' => $i];
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			}
			$this->applyOlPdfStyle($pdf, $object, $i);
			return 0;
		}

		/**
		* PDF get line progress
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object 	Object
		* @param	string			$action 	Action
		* @return	int
		*/
		public function pdf_getlineprogress($parameters = [], &$object, &$action)
		{
			$i		= intval($parameters['i']);
			$line	= isset($object->lines[$i]) ? $object->lines[$i] : null;
			if ($this->isModInfrastructureLine($parameters, $object)) {
				if ($line && $line->qty == -99) { $this->resprints = ' '; return 1; }
				$this->resprints = ' ';
				return 1;
			}
			if (is_array($parameters)) {
				$i = &$parameters['i'];
			} else {
				$i = (int) $parameters;
			}
			return 0;
		}

		/**
		* Before PDF creation
		*
		* @param	array			$parameters	Parameters
		* @param	CommonObject	$object		Object
		* @param	string			$action		Action
		* @return	int							> 0 if OK, 0 if no hook executed, < 0 if KO
		*/
		public function beforePDFCreation($parameters = [], &$object, &$action = '')
		{
			/**
			 * @var $pdf    TCPDF
			 */
			global $pdf, $conf, $langs;

			if (TInfrastructure::showQtyForObject($object, 'pdf') === true) {
				$this->infrastructure_sum_qty_enabled		= true;
				$this->infrastructure_show_qty_by_default	= true;
			}
			if (!isset($object->context) || !is_array($object->context)) {
				$object->context	= [];
			}
			$object->context['infrastructureCache']	= [];
			// Réinitialise les caches du mécanisme show_table_header_before (instance ActionsInfrastructure réutilisée entre PDFs successifs). Le cache du modèle PDF natif appelant est porté par $object->context['infrastructureCache'] et donc déjà réinitialisé par la ligne précédente.
			$this->cachedRedrawnHeaderPages		= [];
			$this->cachedNativeTabTop			= null;
			$this->infrastructureSavedCellPaddings	= null;
			infrastructure_warmPDFInfrastructureCache($object);
			$TContext	= explode(':', $parameters['context']);
			if (in_array('pdfgeneration', $TContext)) {
				$object->context['infrastructurePdfModelInfo']			= new stdClass(); // see defineColumnFiel method in this class
				$object->context['infrastructurePdfModelInfo']->cols	= false;
				infrastructure_forceRemisePercentForShowReduc($object);
				infrastructure_applyTitleWithTotal($object);
				infrastructure_applyTitlePrintAsListOrCondensed($object);
				}
				if (in_array('propalcard', $TContext) || in_array('ordercard', $TContext) || in_array('invoicecard', $TContext) || in_array('supplier_proposalcard', $TContext) || in_array('ordersuppliercard', $TContext) || in_array('invoicesuppliercard', $TContext)) {
				$i = 0;
				if (isset($parameters['i'])) {
					$i = $parameters['i'];
				}
				foreach ($parameters as $key => $value) {
					${$key} = $value;
				}
				infrastructure_setDocTVA($pdf, $object);
				infrastructure_addNumerotation($object);
				foreach ($object->lines ?? [] as $k => &$l) {
					if (TInfrastructure::isTotal($l)) {
						$parentTitle = infrastructure_getCachedParentTitle($object, $l->rang);
						if (is_object($parentTitle) && empty($parentTitle->array_options)) $parentTitle->fetch_optionals();
						if (!empty($parentTitle->id) && !empty($parentTitle->array_options['options_show_reduc'])) {
							$l->remise_percent = 100;    // Affichage de la réduction sur la ligne de sous-total
						}
					}
					// Pas de hook sur les colonnes du PDF expédition, on unset les bonnes variables
					if (($object->element == 'shipping' || $object->element == 'delivery') && $this->isModInfrastructureLine($k, $object)) {
						$l->qty = $l->qty_asked;
						unset($l->qty_asked, $l->qty_shipped, $l->volume, $l->weight);
					}
				}
				$hideInnerLines	= GETPOST('hideInnerLines', 'int');
				$hideqtys = GETPOST('hideqtys', 'int');
				if (!empty($hideInnerLines)) { // si c une ligne de titre
					$fk_parent_line	= 0;
					$TLines			= [];
					$original_count = count($object->lines);
						foreach ($object->lines as $k => &$line) {
						// to keep compatibility with supplier order and old versions (rowid was replaced with id in fetch lines method)
						if ($line->id > 0) {
							$line->rowid = $line->id;
						}
						if ($line->product_type == 9 && $line->rowid > 0) {
							$fk_parent_line	= $line->rowid;
							// Fix tk7201 - si on cache le détail, la TVA est renseigné au niveau du sous-total, l'erreur c'est s'il y a plusieurs sous-totaux pour les même lignes, ça va faire la somme
							if (TInfrastructure::isTotal($line)) {
								$TInfo = infrastructure_get_totalLineFromObject($object, $line, false, 1);
								if (TInfrastructure::getNiveau($line) == 1) {
									$line->TTotal_tva = $TInfo[3];
									$line->TTotal_tva_array = $TInfo[5];
								}
								$line->total_ht						= $TInfo[0];
								$line->total_tva					= $TInfo[1];
								$line->total						= $line->total_ht;
								$line->total_ttc					= $TInfo[2];
								$line->multicurrency_total_ht		= $TInfo[6];
								$line->multicurrency_total_ttc		= $TInfo[7];
							}
						}
						if ($hideInnerLines) {
							// hideInnerLines : ne s'applique que sur les lignes de détail d'un bloc/sous-bloc qui possède un sous-total en aval (de même niveau ou de niveau supérieur).
							$hasParentTitle	= infrastructure_getCachedParentTitle($object, $line->rang);
							$inBlockTotal	= !empty($hasParentTitle) && infrastructure_getCachedTitleHasTotal($object, $hasParentTitle, false);
							if (!$inBlockTotal && empty(TInfrastructure::isModInfrastructureLine($line))) {	// pas dans un bloc avec sous-total => on l'affiche
								$TLines[] = $line;
							}
							if ($line->product_type == 9 && $line->rowid > 0) {
								// Inject invisible VAT lines here
								if (!empty($line->TTotal_tva)) {
									foreach ($line->TTotal_tva as $vatrate => $vatamount) {
										$vatLine				= clone $line;
										$vatLine->qty			= -99;
										$vatLine->tva_tx		= $vatrate;
										$vatLine->total_tva		= $vatamount;
										$vatLine->total_ht		= 0;
										$vatLine->total_ttc		= 0;
										$vatLine->TTotal_tva	= null; // Clear to avoid recursion/confusion
										$TLines[]				= $vatLine;
									}
								}
								$lineForDisplay					= clone $line;
								$lineForDisplay->TTotal_tva		= null;
								$lineForDisplay->total_tva		= 0;
								// ajoute la ligne de sous-total
								$TLines[] = $lineForDisplay;
							}
							} elseif (!empty($hideqtys) || !empty($hideprices)) {
							$TLines[] = $line; // Cas où on cache uniquement les quantités ou les prix : toutes les lignes restent affichées, le hook colonne s'occupe de masquer le détail
						}
						if ($line->product_type != 9) { // jusqu'au prochain titre ou total
							//$line->fk_parent_line = $fk_parent_line;
						}
					}

					$nblignes		= count($TLines);
					// Sauvegarde des lignes originales pour permettre un recalcul de secours (cf. pdfAddTotal en mode hideInnerLines).
					$originalLines	= $object->lines;
					$object->lines	= $TLines;
					$object->context['infrastructureCache']				= [];
					$object->context['infrastructureCache']['originalLines']	= $originalLines;
					if ($i > count($object->lines)) {
						$this->resprints = '';
						return 0;
					}
				}
			}
			infrastructure_warmPDFInfrastructureCache($object);
			return 0;
		}


		/**
		* PDF write line desc
		*
		* @param	array			$parameters	Parameters
		* @param	CommonObject	$object		Object
		* @param	string			$action		Action
		* @return	int
		*/
		public function pdf_writelinedesc($parameters = [], &$object, &$action = '')
		{
			/**
			 * @var $pdf    TCPDF
			 */
			global $pdf;
			foreach ($parameters as $key => $value) {
				${$key} = $value;
			}
			// même si le foreach du dessu fait ce qu'il faut, l'IDE n'aime pas
			$outputlangs	= $parameters['outputlangs'];
			$i				= $parameters['i'];
			$posx			= $parameters['posx'];
			$h				= $parameters['h'];
			$w				= $parameters['w'];
			$hideInnerLines = GETPOST('hideInnerLines', 'int');
			$hideqtys = GETPOST('hideqtys', 'int');
			if ($this->cachedNativeTabTop === null && (int) $i === 0 && isset($parameters['posy']) && $parameters['posy'] > 0) {
				$this->cachedNativeTabTop	= ((float) $parameters['posy']) - 7;
			}
			// Restaure les cell paddings d'origine si la ligne courante n'est pas un sous-total infrastructure (la ligne précédente était un sous-total et avait modifié les paddings pour aligner les colonnes voisines).
			if ($this->infrastructureSavedCellPaddings !== null && is_object($pdf)) {
				$lineCheck				= isset($object->lines[$i]) ? $object->lines[$i] : null;
				$isCurrentLineSubTotal	= $this->isModInfrastructureLine($parameters, $object) && $lineCheck && TInfrastructure::isTotal($lineCheck);
				if (!$isCurrentLineSubTotal) {
					$pdf->setCellPaddings(
						$this->infrastructureSavedCellPaddings['L'],
						$this->infrastructureSavedCellPaddings['T'],
						$this->infrastructureSavedCellPaddings['R'],
						$this->infrastructureSavedCellPaddings['B']
					);
					$this->infrastructureSavedCellPaddings	= null;
				}
			}
			if ($this->isModInfrastructureLine($parameters, $object) ) {
				global $hideqtys, $hideprices;
				if(!empty($hideprices) || !empty($hideqtys)) {
					if (empty($object->context['infrastructureCache']['fkParentLineReset'])) {
						foreach ($object->lines as &$line) {
							if ($line->fk_product_type != 9) $line->fk_parent_line = -1;
						}
						unset($line);
						if (!is_array($object->context)) {
							$object->context = [];
						}
						if (!isset($object->context['infrastructureCache'])) {
							$object->context['infrastructureCache'] = [];
						}
						$object->context['infrastructureCache']['fkParentLineReset'] = true;
					}
				}
				$line = &$object->lines[$i];
				// Unset on Dolibarr < 20.0
				if ($object->element == 'delivery' && !empty($object->commande->expeditions[$line->fk_elementdet])) unset($object->commande->expeditions[$line->fk_elementdet]);
				// Unset on Dolibarr >= 20.0
				if ($object->element == 'delivery' && !empty($object->commande->expeditions[$line->fk_elementdet])) unset($object->commande->expeditions[$line->fk_elementdet]);
				$margin = $pdf->getMargins();
				if (!empty($margin) && $line->info_bits > 0) { // PAGE BREAK
					$pdf->addPage();
					$posy = $margin['top'];
				}
				$label			= $line->label;
				$description	= !empty($line->desc) ? $outputlangs->convToOutputCharset($line->desc) : $outputlangs->convToOutputCharset($line->description);
				if (empty($label)) {
					$label = $description;
					$description = '';
				}
				if ($line->qty == -99) {
					return 1;
				} elseif ($line->qty > 90) {
					if (getDolGlobalInt('INFRASTRUCTURE_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL')) {
						$label .= ' '.infrastructure_getTitle($object, $line);
					} elseif (getDolGlobalInt('INFRASTRUCTURE_USE_NUMEROTATION')) {
						$numerotation = infrastructure_getNumerotation($object, $line);
						if ($numerotation !== '') {
							$label .= ' '.$numerotation;
						}
					}
					if (getDolGlobalInt('INFRASTRUCTURE_PDF_OL_SHOW_DETAILS')) {
						$TInfoOl = infrastructure_get_totalLineFromObject($object, $line, false, 1);
						if (!empty($TInfoOl[8])) {
							$outputlangs->load('infrastructure@infrastructure');
							$label .= '<br />('.$outputlangs->transnoentities('InfrastructureOptionalTotalInLabel', price($TInfoOl[8], 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'))).')';
						}
					}
					// FIX DA024845 : Le module sous total amène des erreurs dans les sauts de page lorsque l'on arrive tout juste en bas de page.
					// Quand un modèle InfraSPlus est en charge ($_SESSION['InfraSPackPlus_model']), on délègue la décision de saut de page au modèle PDF appelant. Le modèle dispose d'un pre-check spécifique aux lignes Infrastructure (pdf_InfraSPlus_*.modules.php — `if (!empty($isSubTotal) || !empty($isInfraTotal))`) exécuté AVANT pdf_InfraSPlus_writelinedesc, qui synchronise $curY et le numéro de page avec l'AddPage. Si on faisait ici un AddPage interne SANS que le modèle s'en aperçoive, les valeurs des colonnes voisines (Qté / TVA / Total HT) seraient dessinées sur l'ANCIENNE page à $curY non actualisé, alors que le bandeau + libellé du sous-total seraient dessinés sur la NOUVELLE page — désynchronisation visible par un sous-total dont les valeurs et le bandeau sont sur deux pages différentes.
					$heightForFooter = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10) + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 12 : 22); // Height reserved to output the footer (value include bottom margin)
					if (empty($_SESSION['InfraSPackPlus_model']) && $pdf->getPageHeight() - $posy - $heightForFooter < 8) {
						$pdf->addPage('', '', true);
						$posy = $pdf->GetY();
					}
					$this->pdfAddTotal($pdf, $object, $line, $label, $description, $posx, $posy, $w, $h);
					// On delivery PDF, we don't want quantities to appear and there are no hooks => setting text color to background color;
					if ($object->element == 'delivery') {
						switch ($line->qty) {
							case 99:
								$grey = 220;
								break;
							case 98:
								$grey = 230;
								break;
							default:
								$grey = 240;
						}
						$pdf->SetTextColor($grey, $grey, $grey);
					}
					$posy = $pdf->GetY();
					return 1;
				} elseif ($line->qty < 10) {
					$this->pdfAddTitle($pdf, $object, $line, $label, $description, $posx, $posy, $w, $h);
					if ($object->element == 'delivery') {
						$pdf->SetTextColor(255, 255, 255);
					}
					$posy	= $pdf->GetY();
					return 1;
				} elseif (!empty($margin)) {
					$labelproductservice = pdf_getlinedesc($object, $i, $outputlangs, $parameters['hideref'], $parameters['hidedesc'], $parameters['issupplierline']);
					$labelproductservice = preg_replace('/(<img[^>]*src=")([^"]*)(&amp;)([^"]*")/', '\1\2&\4', $labelproductservice, -1, $nbrep);
					$pdf->writeHTMLCell($parameters['w'], $parameters['h'], $parameters['posx'], $posy, $outputlangs->convToOutputCharset($labelproductservice), 0, 1, false, true, 'J', true);
					return 1;
				}
				return 0;
			} elseif ($this->applyOlPdfStyle($pdf, $object, $parameters['i'])) {
				// Ligne optionnelle (case « Opt », extrafield options_infrastructure_ol) : rendu standard de la description, avec style/couleur dédiés, et ajout optionnel du Total HT juste en dessous.
				$optionalLine			= $object->lines[$parameters['i']];
				$labelproductservice	= pdf_getlinedesc($object, $parameters['i'], $outputlangs, $parameters['hideref'], $parameters['hidedesc'], $parameters['issupplierline']);
				$labelproductservice	= preg_replace('/(<img[^>]*src=")([^"]*)(&amp;)([^"]*")/', '\1\2&\4', $labelproductservice, -1, $nbrep);
				if (getDolGlobalInt('INFRASTRUCTURE_PDF_OL_SHOW_TOTAL_HT_AFTER_DESC')) {
					$labelproductservice	.= '<br />'.$outputlangs->transnoentities('TotalHT').' : '.price($optionalLine->total_ht, 0, '', 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
				}
				$pdf->writeHTMLCell($parameters['w'], $parameters['h'], $parameters['posx'], $posy, $outputlangs->convToOutputCharset($labelproductservice), 0, 1, false, true, 'J', true);
				$pdf->SetFont('', '');
				$pdf->setColor('text', 0, 0, 0);
				return 1;
			} elseif (empty($object->lines[$parameters['i']])) {
				$this->resprints = -1;
			}
			return 0;
		}

		/**
		*	Détermine si la colonne « Opt » (option de ligne) doit être injectée
		*	dans l'en-tête / les lignes du tableau de lignes d'un document.
		*	Conditions : brouillon, constante INFRASTRUCTURE_MANAGE_OL active,
		*	action != editline, version Dolibarr major dans [21, 24], contexte
		*	correspondant à une carte de document éditable.
		*
		*	@param	CommonObject	$object		Objet métier en cours de rendu
		*	@param	string			$action		Action courante
		*	@param	array			$contexts	Contextes du hook
		*	@return	bool						true si l'injection est requise
		**/
		protected function infrastructure_shouldInjectOptColumn($object, $action, $contexts)
		{
			if (empty($object) || ! is_object($object) || empty($object->element)) {
				return false;
			}
			if (! property_exists($object, 'statut') || $object->statut != 0) {
				return false;
			}
			if (! getDolGlobalString('INFRASTRUCTURE_MANAGE_OL')) {
				return false;
			}
			if ($action === 'editline') {
				return false;
			}
			$major	= (int) DOL_VERSION;
			if ($major < 21 || $major > 24) {
				return false;
			}
			$allowedContexts	= ['invoicecard', 'invoicesuppliercard', 'propalcard', 'supplier_proposalcard', 'ordercard', 'ordersuppliercard', 'invoicereccard'];
			foreach ($allowedContexts as $ctx) {
				if (in_array($ctx, $contexts, true)) {
					return true;
				}
			}
			return false;
		}

		/**
		*	Détermine si le fallback JavaScript doit injecter la colonne « Opt » côté
		*	client. Le rendu serveur via les sous-hooks infrasprojectEnrich* fonctionne
		*	dès lors qu'infrasproject est actif (il rend le <thead> et appelle les deux
		*	sous-hooks). Sans infrasproject : le <thead> natif Dolibarr n'a pas la
		*	colonne « Opt », et même quand IPP rend les <tr> avec leur cellule, le
		*	header reste décalé — on bascule alors sur l'injection JS.
		*
		*	@param	CommonObject	$object		Objet métier en cours de rendu
		*	@param	string			$action		Action courante
		*	@param	array			$contexts	Contextes du hook
		*	@return	bool						true si le fallback JS doit s'activer
		**/
		protected function infrastructure_needsJsFallback($object, $action, $contexts)
		{
			if (! $this->infrastructure_shouldInjectOptColumn($object, $action, $contexts)) {
				return false;
			}
			if (isModEnabled('infrasproject')) {
				return false;
			}
			return true;
		}

		/**
		*	Restaure les libellés des lignes titres Infrastructure perdus lors de la conversion
		*	devis fournisseur → commande fournisseur.
		*
		*	@param	array			$parameters		Paramètres du hook (objFrom = SupplierProposal source)
		*	@param	CommandeFournisseur	$object		Nouvelle commande fournisseur
		*	@param	string			$action			Action courante
		*	@param	HookManager		$hookmanager	Gestionnaire de hooks
		*	@return	int								0 si OK, -1 si KO
		**/
		public function createFrom($parameters, &$object, &$action, HookManager $hookmanager)
		{
			$contexts	= explode(':', $parameters['context'] ?? '');
			if (!in_array('ordersuppliercard', $contexts)) {
				return 0;
			}
			if (empty($parameters['objFrom']) || !is_object($parameters['objFrom'])) {
				return 0;
			}
			$objFrom	= $parameters['objFrom'];
			if ($objFrom->element !== 'supplier_proposal') {
				return 0;
			}
			// Les lignes sont chargées dans SupplierProposal::fetch() — toujours présentes ici
			if (empty($objFrom->lines)) {
				return 0;
			}
			// Liste ordonnée des libellés des lignes Infrastructure du devis
			$infraLabels	= [];
			foreach ($objFrom->lines as $srcLine) {
				if (TInfrastructure::isModInfrastructureLine($srcLine)) {
					$infraLabels[]	= (string) $srcLine->label;
				}
			}
			if (empty($infraLabels)) {
				return 0;
			}
			// Charger les lignes de la commande si besoin
			if (empty($object->lines)) {
				$object->fetch_lines();
			}
			// Correspondance par position séquentielle parmi les lignes Infrastructure
			$idx	= 0;
			foreach ($object->lines as $orderLine) {
				if (!TInfrastructure::isModInfrastructureLine($orderLine)) {
					continue;
				}
				if (!isset($infraLabels[$idx])) {
					break;
				}
				$label	= $infraLabels[$idx];
				$idx++;
				if (empty($label)) {
					continue;
				}
				$sql	= 'UPDATE '.$this->db->prefix().'commande_fournisseurdet';
				$sql	.= " SET description = '".$this->db->escape($label)."'";
				$sql	.= ' WHERE rowid = '.((int) $orderLine->id);
				$this->db->query($sql);
			}
			return 0;
		}

		/**
		*	Sous-hook exposé par infrasproject : fournit la cellule <th>Opt</th> à
		*	injecter dans l'en-tête du tableau de lignes (juste avant .linecolmove).
		*	infrasproject capture le rendu de son tpl linetitles/v*.tpl.php, exécute
		*	ce sous-hook et collecte $hookmanager->resPrint via les resprints de chaque
		*	module appelé. Approche permettant à plusieurs modules d'enrichir l'en-tête
		*	sans dupliquer le <thead> (le HookManager Dolibarr ne s'arrête pas au 1er
		*	retour positif d'un hook printObjectLineTitle).
		*
		*	@param	array			$parameters		Paramètres du hook
		*	@param	CommonObject	$object			Objet métier
		*	@param	string			$action			Action courante
		*	@param	HookManager		$hookmanager	Gestionnaire de hooks
		*	@return	int								0 (non bloquant — sortie via $this->resprints)
		**/
		public function infrasprojectEnrichObjectLineTitle($parameters, &$object, &$action, HookManager $hookmanager)
		{
			global $form, $langs;

			$contexts	= explode(':', $parameters['context'] ?? '');
			if (! $this->infrastructure_shouldInjectOptColumn($object, $action, $contexts)) {
				return 0;
			}
			ob_start();
			include dol_buildpath('/infrastructure/core/tpl/lineol/header.tpl.php', 0);
			$this->resprints	= ob_get_clean();
			return 0;
		}

		/**
		*	Sous-hook exposé par infrasproject : fournit la cellule <td>…</td> à
		*	injecter sur chaque ligne du tableau (juste avant .linecolmove). Pour les
		*	lignes spéciales infrastructure (titres / sous-totaux / textes libres), le
		*	tpl infrastructureline_row_document.tpl.php rend déjà sa propre cellule
		*	Opt — on retourne ici une chaîne vide pour éviter la doublure.
		*
		*	@param	array			$parameters		Paramètres du hook
		*	@param	CommonObject	$object			Objet métier
		*	@param	string			$action			Action courante
		*	@param	HookManager		$hookmanager	Gestionnaire de hooks
		*	@return	int								0 (non bloquant — sortie via $this->resprints)
		**/
		public function infrasprojectEnrichObjectLine($parameters, &$object, &$action, HookManager $hookmanager)
		{
			$contexts	= explode(':', $parameters['context'] ?? '');
			if (! $this->infrastructure_shouldInjectOptColumn($object, $action, $contexts)) {
				return 0;
			}
			$line	= $parameters['line'] ?? null;
			if (! is_object($line)) {
				return 0;
			}
			// Lignes spéciales infrastructure : rendues par infrastructureline_row_document.tpl.php
			// qui gère sa propre cellule Opt — ne pas dédoubler.
			if (isset($line->special_code) && $line->special_code == $this->module_number && isset($line->product_type) && $line->product_type == 9) {
				return 0;
			}
			$module_number	= $this->module_number;
			ob_start();
			include dol_buildpath('/infrastructure/core/tpl/lineol/row.tpl.php', 0);
			$this->resprints	= ob_get_clean();
			return 0;
		}

		/**
		* Print object line — rend les lignes spéciales infrastructure (titres,
		* sous-totaux, textes libres) via le tpl dédié, et injecte le lien de
		* duplication sur les lignes standards. Pour les lignes standards, retourne
		* 0 et laisse Dolibarr (ou infrasproject s'il est actif) faire son rendu.
		* La colonne « Opt » est ajoutée par le sous-hook infrasprojectEnrichObjectLine
		* appelé par infrasproject après son propre rendu de ligne.
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param 	string			$action			Action
		* @param 	HookManager		$hookmanager	Hook manager
		* @return int
		*/
		public function printObjectLine($parameters, &$object, &$action, HookManager $hookmanager)
		{
			global $conf, $langs, $user, $db, $bc, $usercandelete, $toselect, $inputalsopricewithtax;

			$lineLabel	= "";
			$num		= &$parameters['num'];
			$line		= &$parameters['line'];
			$i			= &$parameters['i'];
			$var		= &$parameters['var'];
			$contexts	= explode(':', $parameters['context']);
			if ($parameters['currentcontext'] === 'paiementcard') return 0;
				$originline		= null;
				$newToken		= function_exists('newToken') ? newToken() : $_SESSION['newtoken'];
				$createRight	= $user->hasRight($object->element, 'creer');
				if ($object->element == 'facturerec' ) {
					$object->statut = 0; // hack for facture rec
					$createRight = $user->hasRight('facture', 'creer');
				} elseif ($object->element == 'order_supplier' ) {
					$createRight = $user->hasRight('fournisseur', 'commande', 'creer');
				} elseif ($object->element == 'invoice_supplier' ) {
					$createRight = $user->hasRight('fournisseur', 'facture', 'creer');
				} elseif ($object->element == 'commande' && in_array('ordershipmentcard', $contexts)) {
					// H4cK 4n0nYm0u$-style : $line n'est pas un objet instancié mais provient d'un fetch_object d'une requête SQL
					$line->id			= $line->rowid;
					$line->product_type = $line->type;
				} elseif ($object->element == 'shipping' || $object->element == 'delivery') {
					if (empty($line->origin_line_id) && !empty($line->fk_elementdet)) {
						$line->origin_line_id	= $line->fk_elementdet;
					}
					$originline = new OrderLine($db);
					$originline->fetch(!empty($line->origin_line_id) ? $line->origin_line_id : 0);
					foreach (get_object_vars($line) as $property => $value) {
						if (empty($originline->{ $property })) {
							$originline->{ $property } = $value;
						}
					}
					$line	= $originline;
				}
				$idvar		= $object->element=='facture' ? 'facid' : 'id';
				$isOuvrage	= !empty(isModEnabled('ouvrage')) && class_exists('Ouvrage') && Ouvrage::isOuvrage($line) ? 1 : 0;
				if ($line->special_code!=$this->module_number || $line->product_type!=9) {
					if ($object->statut == 0  && $createRight && getDolGlobalString('INFRASTRUCTURE_ALLOW_DUPLICATE_LINE') && $object->element !== 'invoice_supplier') {
						if (empty($line->fk_prev_id)) $line->fk_prev_id = null;
						if (($object->element != 'shipping' && $object->element != 'delivery')&& !(TInfrastructure::isModInfrastructureLine($line)) && ( $line->fk_prev_id === null ) && !($action == "editline" && GETPOST('lineid', 'int') == $line->id)) {
							echo '<a name="duplicate-'.((int) $line->id).'" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?'.$idvar.'='.((int) $object->id).'&action=duplicate&lineid='.((int) $line->id).'&token='.$newToken.'"><i class="'.getDolGlobalString('MAIN_FONTAWESOME_ICON_STYLE').' fa-clone" aria-hidden="true"></i></a>';
							?>
								<script type="text/javascript">
									$(document).ready(function() {
										$("a[name='duplicate-<?php echo $line->id; ?>']").prependTo($('#row-<?php echo $line->id; ?>').find('.linecoledit'));
									});
								</script>
							<?php
						}
					}
					return 0;
				} elseif (in_array('invoicecard', $contexts) || in_array('invoicesuppliercard', $contexts) || in_array('propalcard', $contexts) || in_array('supplier_proposalcard', $contexts) || in_array('ordercard', $contexts) || in_array('ordersuppliercard', $contexts) || in_array('invoicereccard', $contexts)) {
					include dol_buildpath('/infrastructure/core/tpl/infrastructureline_row_document.tpl.php', 0);
					return 1;
				} elseif (($object->element == 'commande' && in_array('ordershipmentcard', $contexts)) || (in_array('expeditioncard', $contexts) && $action == 'create')) {
					include dol_buildpath('/infrastructure/core/tpl/infrastructureline_row_shipment.tpl.php', 0);
					return 1;
				} elseif ($object->element == 'shipping' || $object->element == 'delivery') {
					include dol_buildpath('/infrastructure/core/tpl/infrastructureline_row_shipping.tpl.php', 0);
					return 1;
				}
			return 0;
		}

		/**
		* Print origin object sub line
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param 	string			$action			Action
		* @param 	HookManager		$hookmanager	Hook manager
		* @return 	int
		*/
		public function printOriginObjectSubLine($parameters, &$object, &$action, HookManager $hookmanager)
		{
			global $conf, $restrictlist, $selectedLines;

			$line		= &$parameters['line'];
			$contexts	= explode(':', $parameters['context']);
			if (in_array('ordercard', $contexts) || in_array('invoicecard', $contexts) || in_array('ordersuppliercard', $contexts) || in_array('invoicesuppliercard', $contexts)) {
				if (class_exists('TInfrastructure')) {
					dol_include_once('/infrastructure/class/infrastructure.class.php');
				}
				if (TInfrastructure::isModInfrastructureLine($line)) {
					$object->tpl['infrastructure']	= $line->id;
					if (TInfrastructure::isTitle($line)) {
						$object->tpl['sub-type'] = 'title';
					} elseif (TInfrastructure::isTotal($line)) {
						$object->tpl['sub-type'] = 'total';
					} elseif (TInfrastructure::isFreeText($line)) {
						$object->tpl['sub-type'] = 'freetext';
					}
					$object->tpl['sub-tr-style']	= '';
					$object->tpl['sub-tr-class']	= infrastructure_getLineSpecialClass($line);
					$object->tpl['sub-tr-style']	= infrastructure_getLineSpecialStyle($line);
					$object->tpl['sub-td-style']	= '';
					if ($line->qty > 90) {
						$object->tpl['sub-td-style']	= 'style="text-align:right"';
					}
					$object->tpl["sublabel"]	= '';
					if (TInfrastructure::isTitle($line) || TInfrastructure::isTotal($line)) {
						$object->tpl["sublabel"]	= str_repeat('&nbsp;&nbsp;&nbsp;', max(floatval($line->qty) - 1, 0));
						if (TInfrastructure::isTitle($line)) {
							$object->tpl["sublabel"].= '<i class="'.getDolGlobalString('MAIN_FONTAWESOME_ICON_STYLE').' fa-tenge" aria-hidden="true"></i>'.$line->qty.'&nbsp;&nbsp;';
						}
					}
					// Get display styles and apply them
					$titleStyleItalic		= strpos(getDolGlobalString('INFRASTRUCTURE_TITLE_STYLE'), 'I') === false ? '' : ' font-style: italic;';
					$titleStyleBold			=  strpos(getDolGlobalString('INFRASTRUCTURE_TITLE_STYLE'), 'B') === false ? '' : ' font-weight:bold;';
					$titleStyleUnderline	=  strpos(getDolGlobalString('INFRASTRUCTURE_TITLE_STYLE'), 'U') === false ? '' : ' text-decoration: underline;';
					if (empty($line->label)) {
						if ($line->qty >= 91 && $line->qty <= 99 && getDolGlobalInt('INFRASTRUCTURE_CONCAT_TITLE_LABEL_IN_TOTAL_LABEL')) {
							$object->tpl["sublabel"].=  $line->description.' '.infrastructure_getTitle($object, $line);
						} else {
							$object->tpl["sublabel"]	= ($object->tpl["sublabel"] ?? '').$line->description;
						}
					} else {
						if (getDolGlobalString('PRODUIT_DESC_IN_FORM') && !empty($line->description)) {
							$object->tpl["sublabel"]	.= '<span class="infrastructure_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$line->label.'</span><br><div class="infrastructure_desc">'.dol_htmlentitiesbr($line->description).'</div>';
						} else {
							$object->tpl["sublabel"]	.= '<span class="infrastructure_label classfortooltip" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" title="'.$line->description.'">'.$line->label.'</span>';
						}
					}
					if ($line->qty>90) {
						$total						= infrastructure_get_totalLineFromObject($object, $line, false);
						$object->tpl["sublabel"]	.= ' : <b>'.$total.'</b>';
					}
					$object->printOriginLine($line, '', $restrictlist, '/core/tpl', $selectedLines);
					unset($object->tpl["sublabel"]);
					unset($object->tpl['sub-td-style']);
					unset($object->tpl['sub-tr-style']);
					unset($object->tpl['sub-type']);
					unset($object->tpl['infrastructure']);
					return 1;
				}
			}
			return 0;
		}

		/**
		* For compatibility with dolibarr <= v14
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int
		*/
		public function printOriginObjectLine($parameters, $object, &$action, HookManager $hookmanager)
		{
			return $this->printOriginObjectSubLine($parameters, $object, $action, $hookmanager);
		}

		/**
		* Add more actions buttons
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$object			Object
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return int
		*/
		public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
			global $langs, $db, $conf;

			if ($object->statut == 0 && getDolGlobalString('INFRASTRUCTURE_MANAGE_OL') && $action != 'editline') {
				if ($object->element == 'invoice_supplier' || $object->element == 'order_supplier') {
					foreach ($object->lines as $line) {
						// fetch optionals attributes and labels — requis pour que le tpl row.tpl.php
						// accède à $line->array_options['options_infrastructure_ol'] lors du rendu serveur
						$extrafields	= new ExtraFields($this->db);
						$extralabels	= $extrafields->fetch_name_optionals_label($object->table_element_line, true);
						$line->fetch_optionals($line->id, $extralabels);
					}
				}
				// Le rendu du <th>Opt</th> et des <td> est effectué côté serveur via les
				// sous-hooks infrasprojectEnrich* quand infrasproject est actif (et IPP
				// reprend les <tr> en mode view via le même sous-hook). En l'absence
				// d'infrasproject, le <thead> n'est pas enrichi côté serveur : on bascule
				// alors sur un fallback JavaScript qui injecte la colonne manquante au
				// DOMReady, en respectant les cellules déjà rendues par d'éventuels autres
				// modules (sous-hook IPP ou tpl infrastructureline_row_document.tpl.php).
				$fallbackContexts	= explode(':', $parameters['context'] ?? '');
				if ($this->infrastructure_needsJsFallback($object, $action, $fallbackContexts)) {
					$olMap			= [];
					if (! empty($object->lines) && is_array($object->lines)) {
						foreach ($object->lines as $fbLine) {
							$isInfraSLine	= (isset($fbLine->special_code) && $fbLine->special_code == $this->module_number && isset($fbLine->product_type) && $fbLine->product_type == 9);
							if ($isInfraSLine) {
								continue;
							}
							$olMap[(int) $fbLine->id]	= ! empty($fbLine->array_options['options_infrastructure_ol']) ? 1 : 0;
						}
					}
					$fallbackConfig	= ['lines'		=> $olMap,
											'thLabel'	=> $langs->trans('infrastructure_ol_title'),
											'thTooltip'	=> $langs->trans('infrastructure_ol_title_help')
									];
					print '<script type="text/javascript">var infrastructureOlFallbackConf = '.json_encode($fallbackConfig).';</script>';
				}
				print '<script type="text/javascript" src="'.dol_buildpath('infrastructure/js/infrastructure.lib.js', 1).'"></script>';
				?>
				<script type="text/javascript">
					$(function () {
						function callAjaxUpdateLineOL(set, lineid, infrastructure_ol) {
							$.ajax({
								url: '<?php echo dol_buildpath('/infrastructure/script/interface.php', 1); ?>'
								, type: 'POST'
								, data: {
									json: 1
									, set: set
									, element: '<?php echo dol_escape_js($object->element); ?>'
									, elementid: <?php echo (int)$object->id; ?>
									, lineid: lineid
									, infrastructure_ol: infrastructure_ol
									, token: '<?php echo newToken(); ?>'
								}
							}).done(function (response) {
								setTimeout(function() {
									window.location.href = window.location.pathname + '?id=<?php echo $object->id; ?>&page_y=' + window.pageYOffset;
								}, 200);
							}).fail(function(jqXHR, textStatus, errorThrown) {
								console.error('Infrastructure OL update failed:', textStatus, errorThrown);
							});
						}
						// Délégation sur document : robuste si des lignes sont injectées plus tard
						$(document).on('change', '.infrastructure_ol_chkbx', function (event) {
							var lineid = $(this).data('lineid');
							var infrastructure_ol = 0 | $(this).is(':checked'); // Renvoi 0 ou 1
							callAjaxUpdateLineOL('updateLineOL', lineid, infrastructure_ol);
						});
					});
				</script>
				<?php
			}
			infrastructure_ajaxBlockOrderJs($object);
			// Pass Oblyon sticky flags to summary menu JS for scroll offset compensation
			$isOblyon	= isModEnabled('oblyon') && isset($conf->theme) && $conf->theme == 'oblyon';
			$jsConfig	= ['langs'						=> ['InfrastructureSummaryTitle' => $langs->trans('InfrastructureQuickSummary')],
								'isOblyon'					=> $isOblyon ? 1 : 0,
								'fixArearefCard'			=> $isOblyon ? getDolGlobalInt('FIX_AREAREF_CARD') : 0,
								'fixStickyTabsCard'			=> $isOblyon ? getDolGlobalInt('FIX_STICKY_TABS_CARD') : 0
						];
			print '<script type="text/javascript"> if (typeof infrastructureSummaryJsConf === undefined) { var infrastructureSummaryJsConf = {}; } infrastructureSummaryJsConf = '.json_encode($jsConfig).'; </script>'; // used also for infrastructure.lib.js
			if (!getDolGlobalString('INFRASTRUCTURE_DISABLE_SUMMARY')) {
				$jsConfig	= ['langs'						=> ['InfrastructureSummaryTitle' => $langs->trans('InfrastructureQuickSummary')]];
				print '<link rel="stylesheet" type="text/css" href="'.dol_buildpath('infrastructure/css/summary-menu.css.php', 1).'">';
				print '<script type="text/javascript" src="'.dol_buildpath('infrastructure/js/summary-menu.js', 1).'"></script>';
			}
			return 0;
		}

		/**
		* After PDF creation
		*
		* @param	array		$parameters		Parameters
		* @param	TCPDF		$pdf			PDF
		* @param	string		$action			Action
		* @param	HookManager	$hookmanager	Hook manager
		* @return	int
		*/
		public function afterPDFCreation($parameters, &$pdf, &$action, HookManager $hookmanager)
		{
			$object = $parameters['object'];

			if ((getDolGlobalString('INFRASTRUCTURE_PROPAL_ADD_RECAP') && $object->element == 'propal') || (getDolGlobalString('INFRASTRUCTURE_COMMANDE_ADD_RECAP') && $object->element == 'commande') || (getDolGlobalString('INFRASTRUCTURE_INVOICE_ADD_RECAP') && $object->element == 'facture')) {
				// Délégation à InfraSPackPlus quand un modèle InfraSPlus est utilisé : le récap est alors rendu INTÉGRÉ dans le PDF (page récap dessinée par pdf_InfraSPlus_subtotal_recap avant la finalisation du document) au lieu d'être généré ici dans un fichier _recap.pdf séparé puis fusionné. Évite le double rendu et conserve un seul flux de génération. La détection se fait via $_SESSION['InfraSPackPlus_model'] posé dans actions_infraspackplus::beforePDFCreation et nettoyé dans son afterPDFCreation.
				if (GETPOST('infrastructure_add_recap', 'int') && empty($parameters['fromInfraS']) && empty($_SESSION['InfraSPackPlus_model'])) {
					TInfrastructure::addRecapPage($parameters, $pdf);
				}
			}
			return 0;
		}

		/**
		* Overloading the getlinetotalremise function : replacing the parent's function with the one below
		*
		* @param	array		$parameters		Meta datas of the hook (context, etc...)
		* @param	CommonObject$object			The object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		* @param	string		$action			Current action (if set). Generally create or edit or null
		* @param	HookManager	$hookmanager	Current hook manager
		* @return	int
		*/
		public function getlinetotalremise($parameters, &$object, &$action, HookManager $hookmanager)
		{
			// Si c'est une ligne de sous-total, la méthode pdfGetLineTotalDiscountAmount ne doit rien renvoyer
			if (!empty($object->lines[$parameters['i']]) && TInfrastructure::isModInfrastructureLine($object->lines[$parameters['i']])) {
				$this->resprints	= '';
				$this->results		= [];
				return 1;
			}
			return 0;
		}

		/**
		* Overloading the defineColumnField function
		*
		* @param	array								$parameters		Hook metadatas (context, etc...)
		* @param	CommonDocGenerator|ModelePDFStatic	$pdfDoc			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		* @param	string								$action			Current action (if set). Generally create or edit or null
		* @param	HookManager 						$hookmanager	Hook manager propagated to allow calling another hook
		* @return	int									< 0 on error, 0 on success, 1 to replace standard code
		*/
		public function defineColumnField($parameters, &$pdfDoc, &$action, HookManager $hookmanager)
		{
			// If this model is column field compatible it will add info to change infrastructure behavior
			$parameters['object']->context['infrastructurePdfModelInfo']->cols = $pdfDoc->cols;
			$parameters['object']->context['infrastructurePdfModelInfo']->cols = $pdfDoc->cols;
			// HACK Pour passer les paramettres du model dans les hooks sans infos
			$parameters['object']->context['infrastructurePdfModelInfo']->marge_droite 	= $pdfDoc->marge_droite;
			$parameters['object']->context['infrastructurePdfModelInfo']->marge_gauche 	= $pdfDoc->marge_gauche;
			$parameters['object']->context['infrastructurePdfModelInfo']->page_largeur 	= $pdfDoc->page_largeur;
			$parameters['object']->context['infrastructurePdfModelInfo']->page_hauteur 	= $pdfDoc->page_hauteur;
			$parameters['object']->context['infrastructurePdfModelInfo']->format			= $pdfDoc->format;
			if (property_exists($pdfDoc, 'context') && array_key_exists('infrastructurePdfModelInfo', $pdfDoc->context) && is_object($pdfDoc->context['infrastructurePdfModelInfo'])) {
				$parameters['object']->context['infrastructurePdfModelInfo']->defaultTitlesFieldsStyle	= $pdfDoc->context['infrastructurePdfModelInfo']->defaultTitlesFieldsStyle;
				$parameters['object']->context['infrastructurePdfModelInfo']->defaultContentsFieldsStyle	= $pdfDoc->context['infrastructurePdfModelInfo']->defaultContentsFieldsStyle;
			}
			return 0;
		}

		/**
		* Re-generate the document after creation of recurring invoice by cron
		*
		* @param	array				$parameters		Hook metadatas (context, etc...)
		* @param	CommonDocGenerator	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
		* @param	string				$action			Current action (if set). Generally create or edit or null
		* @param	HookManager			$hookmanager	Hook manager propagated to allow calling another hook
		* @return	int									< 0 on error, 0 on success, 1 to replace standard code
		*/
		public function afterCreationOfRecurringInvoice($parameters, &$object, &$action, HookManager $hookmanager)
		{
			$TSub	= new TInfrastructure;
			$TSub->generateDoc($object);
			return 0;
		}

		/**
		* Print common footer
		*
		* @param	array			$parameters		Parameters
		* @param	CommonObject	$objectHook		Object hook
		* @param	string			$action			Action
		* @param	HookManager		$hookmanager	Hook manager
		* @return	int
		*/
		public function printCommonFooter(&$parameters, &$objectHook, &$action, HookManager $hookmanager)
		{
			global $langs, $db, $conf;

			$contextArray = explode(':', $parameters['context']);
			/**Gestion des dossiers qui permettent de réduire un bloc**/
			if (in_array('invoicecard', $contextArray)
					|| in_array('invoicesuppliercard', $contextArray)
					|| in_array('propalcard', $contextArray)
					|| in_array('ordercard', $contextArray)
					|| in_array('ordersuppliercard', $contextArray)
					|| in_array('invoicereccard', $contextArray)
					|| in_array('ordershipmentcard', $contextArray)
					|| (in_array('expeditioncard', $contextArray) && $action == 'create')
				) {
				$id					= !empty(GETPOSTINT('id')) ? GETPOSTINT('id') : GETPOSTINT('facid');	//On récupère les informations de l'objet actuel
				if (in_array('expeditioncard', $contextArray) && $action == 'create' && empty($id)) {
					// Page de création d'expédition (expedition/card.php) : l'id de la commande d'origine
					// peut transiter par origin_id ou object_id selon le point d'entrée.
					$id	= GETPOSTINT('origin_id') ? GETPOSTINT('origin_id') : GETPOSTINT('object_id');
				}
				$TCurrentContexts	= explode('card', $parameters['currentcontext']);	//On détermine l'élement concernée en fonction du contexte
				/**
				 *  TODO John le 11/08/2023 : Je trouve bizarre d'utiliser le contexte pour déterminer la class de l'objet alors
				 *    que l'objet est passé en paramètres ça doit être due à de vielle versions de Dolibarr ou une compat avec un module externe...
				 *    Cette methode de chargement d'objet a causée une fatale car la classe de l'objet correspondant au contexte n'était pas chargé ce qui n'est pas logique...
				 *    La logique voudrait que l'on utilise $object->element
				 *    Cependant si on regarde plus loin $object qui est passé en référence dans les paramètres de cette méthode est remplacé quelques lignes plus bas.
				 */
				if ($TCurrentContexts[0] == 'order') {
					$element = 'Commande';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';}
				} elseif ($TCurrentContexts[0] == 'invoice') {
					$element = 'Facture';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';}
				} elseif ($TCurrentContexts[0] == 'invoicesupplier') {
					$element = 'FactureFournisseur';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';}
				}  elseif ($TCurrentContexts[0] == 'ordersupplier') {
					$element = 'CommandeFournisseur';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';}
				} elseif ($TCurrentContexts[0] == 'invoicerec') {
					$element = 'FactureRec';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';}
				} elseif ($TCurrentContexts[0] == 'ordershipment') {
					// Onglet "Expédier" d'une commande (expedition/shipment.php) : le document porteur de hideblock est la commande.
					$element = 'Commande';
					if (!class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';}
				} elseif ($TCurrentContexts[0] == 'expedition' && $action == 'create') {
					// Page de création d'expédition (expedition/card.php?action=create) : $object y est en réalité
					// le document d'origine (quasi toujours la commande), pas l'Expedition elle-même.
					$element = GETPOST('origin', 'alpha') ? ucfirst(GETPOST('origin', 'alpha')) : 'Commande';
					if ($element == 'Commande' && !class_exists($element)) { include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';}
				} else $element = $TCurrentContexts[0];
				if (!class_exists($element)) {
					// Pour éviter la fatale sur une page d'un module externe qui utiliserait un nom de context de Dolibarr mais qui
					$this->error = $langs->trans('InfrastructureErrorClassXNotExists', $element);
					return -1;
				}
				$object			= new $element($db);
				$object->fetch($id);
				$TLines			= TInfrastructure::getAllTitleFromDocument($object);	//On récupère tous les titres sous-total
				$TBlocksToHide	= [];	//On définit quels sont les blocs à cacher en fonction des données existantes (hideblock)
				$hideMode		= getDolGlobalString('INFRASTRUCTURE_BLOC_FOLD_MODE', 'default');
				$hideMode		= in_array($hideMode, ['default', 'keepTitle', 'hideAll']) ? $hideMode : 'default';
				if (!empty($TLines)) {
					foreach ($TLines as $line) {
						if (array_key_exists('options_hideblock', $line->array_options) && $line->array_options['options_hideblock']) $TBlocksToHide[] = $line->id;
					}
				}
				$jsConf		= ['linesToHide'			=> $TBlocksToHide,
									'hideFoldersByDefault'	=> getDolGlobalInt('INFRASTRUCTURE_HIDE_FOLDERS_BY_DEFAULT'),
									'closeMode'				=> $hideMode, // default, keepTitle, hideAll
									'interfaceUrl'			=> dol_buildpath('/infrastructure/script/interface.php', 1),
									'token'					=> newToken(),
									'element'				=> $element,
									'element_id'			=> $id,
									'img_folder_closed' 	=> img_picto('', 'folder'),
									'img_folder_open'		=> img_picto('', 'folder-open'),
									'langs'					=> ['Infrastructure_HideAll'		=> $langs->transnoentities('Infrastructure_HideAll'),
																	'Infrastructure_ShowAll'		=> $langs->transnoentities('Infrastructure_ShowAll'),
																	'Infrastructure_Hide'			=> $langs->transnoentities('Infrastructure_Hide'),
																	'Infrastructure_Show'			=> $langs->transnoentities('Infrastructure_Show'),
																	'Infrastructure_ForceHideAll'	=> $langs->transnoentities('Infrastructure_ForceHideAll'),
																	'Infrastructure_ForceShowAll'	=> $langs->transnoentities('Infrastructure_ForceShowAll')
																]
															];
				$colorBloc	= getDolGlobalString('INFRASTRUCTURE_TITLE_COLOR_BLOC', 'be3535');
				$color		= getDolGlobalString('INFRASTRUCTURE_TITLE_COLOR', '000000');
				print '<script type="text/javascript" src="'.dol_buildpath('infrastructure/js/infrastructure.lib.js', 1).'"></script>';
				?>
					<style>
						.fold-infrastructure-container{
							-webkit-user-select: none; /* Safari */
							-ms-user-select: none; /* IE 10 and IE 11 */
							user-select: none; /* Standard syntax */
						}

				.toggle-all-folder-status, .fold-infrastructure-btn {
					cursor: pointer;
				}

				.fold-infrastructure-btn[data-toggle-all-children="0"] {
					color: <?php echo $color; ?>;
				}

				.fold-infrastructure-btn[data-toggle-all-children="1"] {
					color: <?php echo $colorBloc; ?>;
				}

				.toggle-all-folder-status:hover, .fold-infrastructure-btn:hover {
					color: var(--colortextlink, rgb(10, 20, 100));
				}

				.fold-infrastructure-btn[data-toggle-all-children="1"]:hover {
					color: <?php echo $colorBloc; ?>;
				}
			</style>
			<script type="text/javascript">
				// TODO : mettre ça dans une classe js
				$(document).ready(function () {
					// Table contenant les lignes infrastructure : recherche générique par ancêtre plutôt que #tablelines
					// en dur, car ce sélecteur n'existe pas sur toutes les pages (ex. création d'expédition depuis commande).
					var $infrastructureTable = $('tr[data-isinfrastructure]').first().closest('table');
					// Utilisation d'une sorte de namespace en JS
					infrastructureFolders = {};
					(function (o) {
						o.config = <?php print json_encode($jsConf); ?> ;
						/**
						 * Dolibarr token
						 * @type {string}
						 */
						o.newToken = o.config.token || '';
						/**
						 *
						 * @param {int} titleId
						 */
						o.countHiddenLinesForTitle = function (titleId) {
							let $titleLine = $('#row-' + titleId);
							let childrenList = getInfrastructureTitleChilds($titleLine, true); // renvoi la liste des id des enfants
							let totalHiddenLines = 0;
							if (childrenList.length > 0) {
								childrenList.forEach((childLineId) => {
									let $childLine = $('#' + childLineId);
									if (!$childLine.is(":visible")) {
										totalHiddenLines++;
									}
								});
							}
							return totalHiddenLines;
						}
						/**
						 * Mise à jour des titres parents pour l'affichage du nombre de lignes cachées
						 * @param {jQuery}  $childTilteLine la ligne de titre enfant
						 */
						o.updateHiddenLinesCountInfoForParentTitles = function ($childTilteLine) {
							let parentTitles = o.getTitleParents($childTilteLine);
							if (parentTitles.length > 0) {
								parentTitles.forEach((parentTitleLineId) => {
									let $titleCollapseInfos = $('.fold-infrastructure-info[data-title-line-target="' + parentTitleLineId + '"]');
									if ($titleCollapseInfos.length > 0) {
										let totalHiddenLines = o.countHiddenLinesForTitle(parentTitleLineId);
										$titleCollapseInfos.html('(' + totalHiddenLines + ')');
										if (totalHiddenLines == 0) {
											$titleCollapseInfos.html('');
										}
									}
								});
							}
						}
						/**
						 * @param {jQuery}  $childLine
						 * @param {int} titleId
						 */
						o.addTitleParentId = function ($childLine, titleId) {
							// Ajoute l'id parent si se n'est pas déja fait
							let parentTitleIds = $childLine.attr('data-parent-titles');
							if (parentTitleIds != null) {
								let parentTitleIdsList = parentTitleIds.split(",");
								if (!parentTitleIdsList.includes(titleId)) {
									$childLine.attr('data-parent-titles', parentTitleIds + ',' + titleId);
								}
							} else {
								$childLine.attr('data-parent-titles', titleId);
							}
						}
						/**
						 * @param {jQuery}  $childLine
						 * @param {int} titleId
						 * @return []
						 */
						o.getTitleParents = function ($childLine) {
							let result = [];
							let parentTitleIds = $childLine.attr('data-parent-titles');
							if (parentTitleIds != null) {
								return parentTitleIds.split(",");
							}
							return result;
						}
						/**
						 *
						 * @param {int}     titleId
						 * @param {string}  toggleStatus    open, closed
						 * @param {boolean} forceHideAll    En mode 'hideAll', force le masquage de tout le contenu (sous-titres et sous-totaux compris) lors du clic sur le bouton "plier tout".
						 * @param {boolean} ignoreCloseMode Si true, ignore le closeMode (keepTitle/hideAll) et applique le pliage en mode 'default'. Utilisé à l'init pour ne pas appliquer la logique mode-spécifique au reload.
						 */
						o.toggleChildFolderStatusDisplay = function (titleId, toggleStatus = 'open', forceHideAll = false, ignoreCloseMode = false) {
							let $titleLine			= $('#row-' + titleId);
							let $collapseBtn		= $('.fold-infrastructure-btn[data-title-line-target="' + titleId + '"]');
							let $collapseSimpleBtn	= $('.fold-infrastructure-btn[data-title-line-target="' + titleId + '"][data-toggle-all-children="0"]');
							let $collapseAllBtn		= $('.fold-infrastructure-btn[data-title-line-target="' + titleId + '"][data-toggle-all-children="1"]');
							let $collapseInfos		= $('.fold-infrastructure-info[data-title-line-target="' + titleId + '"]');
							// keepTitleVisible : on garde les titres et sous-totaux enfants visibles lorsqu'on plie.
							// Vrai pour les modes 'keepTitle' et 'hideAll', sauf si forceHideAll est passé (clic sur le bouton 2 en mode 'hideAll')
							// ou si ignoreCloseMode est passé (init au chargement de la page, le mode ne doit s'appliquer qu'au clic).
							let keepTitleVisible	= !forceHideAll && !ignoreCloseMode && (o.config.closeMode == 'keepTitle' || o.config.closeMode == 'hideAll');
							if ($titleLine.length > 0) {
								$titleLine.attr('data-folder-status', toggleStatus);
								let haveTitle		= false;
								let childrenList	= getInfrastructureTitleChilds($titleLine, true); // renvoi la liste des id des enfants
								let totalHiddenLines= 0;
								if (childrenList.length > 0) {
									let doNotDisplayLines = []; // Dans le cas de l'ouverture il faut vérifier que les titres enfants ne sont pas fermés avant d'ouvrir
									let doNotHiddeLines = []; // En mode keepTitle/hideAll : Dans le cas de la fermeture il faut vérifier que les titres enfants ne sont pas ouvert avant de fermer
									childrenList.forEach((childLineId) => {
										let $childLine = $('#' + childLineId);
										if ($childLine.attr('data-isinfrastructure') == "title") {
											// Ajoute l'id parent si se n'est pas déja fait
											o.addTitleParentId($childLine, titleId);
											haveTitle = true;
											// Dans le cas de l'ouverture il faut vérifier que les titres enfants ne sont pas fermés avant d'ouvrir
											let grandChildrenList = getInfrastructureTitleChilds($childLine, true); // renvoi la liste des id des enfants
											if ($childLine.attr('data-folder-status') == "closed") {
												doNotDisplayLines = doNotDisplayLines.concat(grandChildrenList);
											} else if (keepTitleVisible && $childLine.attr('data-folder-status') == "open") {
												doNotHiddeLines = doNotDisplayLines.concat(grandChildrenList);
											}
										}
										if (toggleStatus == 'closed') {
											if (keepTitleVisible && ($childLine.attr('data-isinfrastructure') == "title" || $childLine.attr('data-isinfrastructure') == "infrastructure")) {
												$childLine.show();
											} else if (!doNotHiddeLines.includes(childLineId)) {
												$childLine.hide();
											}
										} else {
											if (!doNotDisplayLines.includes(childLineId)) {
												$childLine.show();
											}
										}
										if (!$childLine.is(":visible")) {
											totalHiddenLines++;
										}
									});
								}
								$collapseInfos.html('(' + totalHiddenLines + ')');
								if (totalHiddenLines == 0) {
									$collapseInfos.html('');
								}
								// Mise à jour des parents pour l'affichage du nombre de lignes cachées
								o.updateHiddenLinesCountInfoForParentTitles($titleLine);
								if (toggleStatus == 'closed') {
									$collapseBtn.html(o.config.img_folder_closed);
									$collapseSimpleBtn.attr('title', o.config.langs.Infrastructure_Show);
									$collapseAllBtn.attr('title', o.config.langs.Infrastructure_ForceShowAll);
								} else {
									$collapseBtn.html(o.config.img_folder_open);
									$collapseSimpleBtn.attr('title', o.config.langs.Infrastructure_Hide);
									$collapseAllBtn.attr('title', o.config.langs.Infrastructure_ForceHideAll);
								}
								// Si pas de titre pas besoin d'afficher le bouton dossier rouge
								if (haveTitle) {
									$collapseAllBtn.show();
								} else {
									$collapseAllBtn.hide();
								}
							}
						}
						// initialisation des lignes affichées ou non
						// Au chargement de la page, on n'applique PAS la logique mode-spécifique (keepTitle/hideAll) :
						// le mode INFRASTRUCTURE_BLOC_FOLD_MODE ne doit s'appliquer qu'au clic utilisateur.
						$('tr[data-isinfrastructure="title"]').each(function () {
							let lineId = $(this).attr('data-id');
							if (lineId != null) {
								if (o.config.linesToHide.includes(lineId)) {
									o.toggleChildFolderStatusDisplay(lineId, 'closed', false, true);
								} else {
									if (o.config.hideFoldersByDefault == 1) {
										o.toggleChildFolderStatusDisplay(lineId, 'closed', false, true);
									} else {
										o.toggleChildFolderStatusDisplay(lineId, 'open', false, true);
									}
								}
							}
						});
						// Lors du clic sur un dossier, on cache ou faire apparaitre les lignes contenues dans le bloc concerné
						$(document).on("click", ".fold-infrastructure-btn", function (event) {
							event.preventDefault();
							let targetTitleLineId = $(this).attr('data-title-line-target');
							if (targetTitleLineId != undefined) {
								// folderManage_click(targetTitleLineId);
								let titleRow = $('#row-' + targetTitleLineId);
								let newStatus = titleRow.attr('data-folder-status') == 'closed' ? 'open' : 'closed'
								let isToggleAllBtn = $(this).attr('data-toggle-all-children') == '1';
								// En mode 'hideAll', le bouton "plier tout" force le masquage de tout le contenu (titres et sous-totaux enfants compris).
								// Le bouton "plier sans toucher aux titres enfants" garde lui le comportement keepTitle.
								let forceHideAll = isToggleAllBtn && o.config.closeMode == 'hideAll';
								let sendData = {
									element: o.config.element,
									element_id: o.config.element_id,
									titleStatusList: [{
										'id': targetTitleLineId,
										'status': newStatus !== 'closed' ? 0 : 1,
									}]
								};
								/**
								 * Pour les boutons de type "block" bouton pour ouvrir / fermer tous les blocs enfants (ex dossier rouge)
								 **/
								if (isToggleAllBtn) {
									let childrenList = getInfrastructureTitleChilds(titleRow, true); // renvoi la liste des id des enfants
									if (childrenList.length > 0) {
										childrenList.forEach((childLineId) => {
											let $childLine = $('#' + childLineId);
											if ($childLine.attr('data-isinfrastructure') == "title") {
												sendData.titleStatusList.push({
													'id': $childLine.attr('data-id'),
													'status': newStatus !== 'closed' ? 0 : 1,
												});
												o.toggleChildFolderStatusDisplay($childLine.attr('data-id'), newStatus, forceHideAll);
											}
										});
									}
								}
								o.toggleChildFolderStatusDisplay(targetTitleLineId, newStatus, forceHideAll); // devrait être dans le callback ajax success mais pour plus d'ergonomie et rapidité de feedback je le sort
								o.callInterface('set', 'update_hideblock_data', sendData, function (response) {
									// TODO gérer un retour en cas d'érreur
									// o.toggleChildFolderStatusDisplay(targetTitleLineId, newStatus);
								})
							}
						});
						//Fonction qui permet d'ajouter l'option "Cacher les lignes" ou "Afficher les lignes"
						$infrastructureTable.find('>tbody:first').prepend(
							'<tr>' +
							'	<td colspan="100%" style="  text-align:right ">' +
							'		<span id="hide_all"  class="toggle-all-folder-status" data-folder-status="closed" >' + o.config.img_folder_open + '&nbsp;' + o.config.langs.Infrastructure_HideAll + '</span>' +
							'		&nbsp;' +
							'		<span id="show_all" class="toggle-all-folder-status" data-folder-status="open"  >' + o.config.img_folder_closed + '&nbsp;' + o.config.langs.Infrastructure_ShowAll + '</span>' +
							'	</td>' +
							'</tr>'
						);
						// Lors du clic sur un dossier, on cache ou faire apparaitre les lignes contenues dans le bloc concerné
						$(document).on("click", ".toggle-all-folder-status", function (event) {
							event.preventDefault();
							newStatus = $(this).attr('data-folder-status');
							$(this).fadeOut();
							let sendData = {
								element: o.config.element,
								element_id: o.config.element_id,
								titleStatusList: []
							};
							$infrastructureTable.find('tr[data-isinfrastructure=title]').each(function (index) {
								sendData.titleStatusList.push({
									'id': $(this).attr('data-id'),
									'status': newStatus !== 'closed' ? 0 : 1,
								});

								//TODO manage response feedback to rollback display on error
								o.toggleChildFolderStatusDisplay($(this).attr('data-id'), newStatus);
							});
							o.callInterface('set', 'update_hideblock_data', sendData, function (response) {
								// $('#tablelines tr[data-isinfrastructure=title]').each(function( index ) {
								// 	//TODO manage response feedback
								// });
							});
							$(this).fadeIn();
						});
						o.checkListOfLinesIdHaveTitle = function (childrenList) {
							if (!Array.isArray(childrenList)) {
								return false;
							}
							childrenList.forEach((childLineId) => {
								let $childLine = $('#' + childLineId);
								if ($childLine.length > 0 && $childLine.attr('data-isinfrastructure') == "title") {
									return true;
								}
							});
							return false;
						}
						/**
						*
						* @param {string} typeAction
						* @param {string} action
						* @param sendData
						* @param callBackFunction
						*/
						o.callInterface = function (typeAction = 'get', action, sendData = {}, callBackFunction) {

							let ajaxData = {
								'data': sendData,
								'token': o.newToken,
							};
							if (typeAction == 'set') {
								ajaxData.set = action;
							} else {
								ajaxData.get = action;
							}
							$.ajax({
								method: 'POST',
								url: o.config.interfaceUrl,
								dataType: 'json',
								data: ajaxData,
								success: function (response) {
									if (typeof callBackFunction === 'function') {
										callBackFunction(response);
									} else {
										console.error('Callback function invalide for callKanbanInterface');
									}
									if (response.newToken != undefined) {
										o.newToken = response.newToken;
									}
									if (response.msg.length > 0) {
										o.setEventMessage(response.msg, response.result > 0 ? true : false, response.result == 0 ? true : false);
									}
								},
								error: function (err) {
									if (err.responseText.length > 0) {
										// detect login page in case of just disconnected
										let loginPage = $(err.responseText).find('[name="actionlogin"]');
										if (loginPage != undefined && loginPage.val() == 'login') {
											o.setEventMessage(o.langs.errorAjaxCallDisconnected, false);
											setTimeout(function () {
												location.reload();
											}, 2000);
										} else {
											o.setEventMessage(o.langs.errorAjaxCall, false);
										}
									} else {
										o.setEventMessage(o.langs.errorAjaxCall, false);
									}
								}
							});
						}
						/**
						 *
						 * @param {string} msg
						 * @param {boolean} status
						 * @param {boolean} sticky
						 */
						o.setEventMessage = function (msg, status = true, sticky = false) {
							let jnotifyConf = {
								delay: 1500					// the default time to show each notification (in milliseconds)
								, type: 'error'
								, sticky: sticky			// determines if the message should be considered "sticky" (user must manually close notification)
								, closeLabel: "&times;"		// the HTML to use for the "Close" link
								, showClose: true			// determines if the "Close" link should be shown if notification is also sticky
								, fadeSpeed: 150			// the speed to fade messages out (in milliseconds)
								, slideSpeed: 250			// the speed used to slide messages out (in milliseconds)
							}
							if (msg.length > 0) {
								if (status) {
									jnotifyConf.type = '';
									$.jnotify(msg, jnotifyConf);
								} else {
									$.jnotify(msg, jnotifyConf);
								}
							} else {
								$.jnotify('ErrorMessageEmpty', jnotifyConf);
							}
						}
					})(infrastructureFolders);
				});
			</script>
			<?php
			}
			return 0;
		}
		/**
		* Print field list where
		*
		* @param	array			$parameters Parameters
		* @param	CommonObject	$object Object
		* @param	string			$action Action
		* @param	HookManager		$hookmanager Hook manager
		* @return	int
		*/
		public function printFieldListWhere(&$parameters, &$object, &$action, HookManager $hookmanager)
		{
			$contexts = explode(':', $parameters['context']);
			if (in_array('checkmarginlist', $contexts)) {
				$this->resprints = ' AND  d.special_code != 550090';
			}
			return 0; // succès
		}

		/**
		*	Rafraîchit les cellules sous-total infrastructure après une modification quickcustomerprice
		*
		*	@param	array		$parameters		Métadonnées du hook
		*	@param	object		&$object		Objet en cours d'édition
		*	@param	string		&$action		Action courante
		*	@param	HookManager	$hookmanager	Gestionnaire de hooks
		*	@return	int						0 = continuer
		**/
		public function addJSCallbacks($parameters, &$object, &$action, $hookmanager)
		{
			$TContexts	= ['propalcard', 'ordercard', 'invoicecard', 'ordersuppliercard', 'invoicesuppliercard', 'supplier_proposalcard', 'invoicereccard'];
			if (!in_array($parameters['currentcontext'], $TContexts)) {
				return 0;
			}

			$interfaceUrl	= dol_buildpath('/infrastructure/script/interface.php', 1);
			$element		= dol_escape_js($object->element);
			$objectid		= (int) $object->id;

			ob_start();
			?>
			<script type="text/javascript">
			priceCallbacks.push(function(lineid, data) {
				$('#tablelines td[rel="infrastructure_total"]').each(function() {
					var $cell		= $(this);
					var rowLineid	= $cell.closest('tr').attr('id').replace('row-', '');
					$.ajax({
						url: <?php echo json_encode($interfaceUrl); ?>
						, data: { get: 'getTotalLine', element: <?php echo json_encode($element); ?>, elementid: <?php echo $objectid; ?>, lineid: rowLineid }
						, dataType: 'json'
					}).done(function(responseData) {
						if (responseData && responseData.total !== undefined) {
							$cell.html(responseData.total);
						}
					});
				});
			});
			</script>
			<?php
			$this->resprints = ob_get_clean();
			return 0;
		}
	}
