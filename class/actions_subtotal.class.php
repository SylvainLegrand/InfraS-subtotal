<?php
/**
* SPDX-License-Identifier: GPL-3.0-or-later
* This file is part of Dolibarr module Subtotal
*/


dol_include_once('/subtotal/class/subtotal.class.php');
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';
if (!empty(isModEnabled('ouvrage')))	dol_include_once('/ouvrage/class/ouvrage.class.php');	// InfraS add

class ActionsSubtotal extends \subtotal\RetroCompatCommonHookActions
{

	/**
	 * @var string $error
	 */
	public $error;

	/**
	 * @var array $errors
	 */
	public $errors = array();

    /**
     * @var int Subtotal current level
     */
    protected $subtotal_level_cur = 0;

    /**
     * @var bool Show subtotal qty by default
     */
    protected $subtotal_show_qty_by_default = false;

    /**
     * @var bool Determine if sum on subtotal qty is enabled
     */
    protected $subtotal_sum_qty_enabled = false;


	function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$langs->load('subtotal@subtotal');

		$this->allow_move_block_lines = true;
	}

	function printFieldListSelect($parameters, &$object, &$action, $hookmanager) {

		global $type_element, $where;

		$contexts = explode(':',$parameters['context']);

		if(in_array('consumptionthirdparty',$contexts) && in_array($type_element, array('propal', 'order', 'invoice', 'supplier_order', 'supplier_invoice', 'supplier_proposal'))) {
			$mod_num = TSubtotal::$module_number;

			// Not a title (can't use TSubtotal class methods in sql)
			$where.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty > 9)';
			// Not a subtotal (can't use TSubtotal class methods in sql)
			$where.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty < 90)';
			// Not a free line text (can't use TSubtotal class methods in sql)
			$where.= ' AND (d.special_code != '.$mod_num.' OR d.product_type != 9 OR d.qty != 50)';

		}

		return 0;
	}


	function editDictionaryFieldlist($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		$dictionnariesTablePrefix = '';
		if (intval(DOL_VERSION)< 16) $dictionnariesTablePrefix =  MAIN_DB_PREFIX;

		if ($parameters['tabname'] == $dictionnariesTablePrefix.'c_subtotal_free_text')
		{
            $value = TSubtotal::getHtmlDictionnary();


			?>
			<script type="text/javascript">
				$(function() {
						if ($('input[name=content]').length > 0)
						{
							$('input[name=content]').each(function(i, item) {
								var value = '';
								// Le dernier item correspond à l'édition
								if (i == $('input[name=content]').length - 1) {
									value = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
								}
								$(item).replaceWith($('<textarea name="content">'+value+'</textarea>'));
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
	function createDictionaryFieldlist($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs;	// InfraS change

		$dictionnariesTablePrefix = '';
		if (intval(DOL_VERSION)< 16) $dictionnariesTablePrefix =  MAIN_DB_PREFIX;

		if ($parameters['tabname'] == $dictionnariesTablePrefix.'c_subtotal_free_text')
		{
			// Editor wysiwyg	// InfraS add begin
			$toolbarname = 'dolibarr_notes';
			$disallowAnyContent = true;
			if (isset($conf->global->FCKEDITOR_ALLOW_ANY_CONTENT)) {
				$disallowAnyContent = empty($conf->global->FCKEDITOR_ALLOW_ANY_CONTENT); // Only predefined list of html tags are allowed or all
			}
			if (!empty($conf->global->FCKEDITOR_SKIN)) {
				$skin = $conf->global->FCKEDITOR_SKIN;
			} else {
				$skin = 'moono-lisa'; // default with ckeditor 4.6 : moono-lisa
			}
			if (!empty($conf->global->FCKEDITOR_ENABLE_SCAYT_AUTOSTARTUP)) {
				$scaytautostartup = 'scayt_autoStartup: true,';
			} else {
				$scaytautostartup = '/*scayt is disable*/'; // Disable by default
			}
			$htmlencode_force = preg_match('/_encoded$/', $toolbarname) ? 'true' : 'false';
			$editor_height = empty($conf->global->MAIN_DOLEDITOR_HEIGHT) ? 100 : $conf->global->MAIN_DOLEDITOR_HEIGHT;
			$editor_allowContent = $disallowAnyContent ? 'false' : 'true';
			// InfraS add end
            $value = TSubtotal::getHtmlDictionnary();

			?>
			<script type="text/javascript">
				$(function() {
					if ($('input[name=content]').length > 0)
					{
						$('input[name=content]').each(function(i, item) {
							var value = '';
							// Le dernier item correspond à l'édition
							if (i == $('input[name=content]').length - 1) {
								value = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
							}
							$(item).replaceWith($('<textarea name="content">'+value+'</textarea>'));
						});

						<?php if (isModEnabled("fckeditor") && getDolGlobalString('FCKEDITOR_ENABLE_DETAILS')) { ?>
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


	/** Overloading the formObjectOptions function : replacing the parent's function with the one below
	 * @param      $parameters  array           meta datas of the hook (context, etc...)
	 * @param      $object      CommonObject    the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param      $action      string          current action (if set). Generally create or edit or null
	 * @param      $hookmanager HookManager     current hook manager
	 * @return     void
	 */

    var $module_number = 104777;

    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
      	global $langs,$db,$user, $conf;

		$langs->load('subtotal@subtotal');

		$contexts = explode(':',$parameters['context']);

		if(in_array('ordercard',$contexts) || in_array('ordersuppliercard',$contexts) || in_array('propalcard',$contexts) || in_array('supplier_proposalcard',$contexts) || in_array('invoicecard',$contexts) || in_array('invoicesuppliercard',$contexts) || in_array('invoicereccard',$contexts) || in_array('expeditioncard',$contexts)) {

			$createRight = $user->hasRight($object->element, 'creer');
			if($object->element == 'facturerec' )
			{
				$object->statut = 0; // hack for facture rec
				$createRight = $user->hasRight('facture', 'creer');
			} elseif($object->element == 'order_supplier' )
			{
			    $createRight = $user->hasRight('fournisseur', 'commande', 'creer');
			} elseif($object->element == 'invoice_supplier' )
			{
			    $createRight = $user->hasRight('fournisseur', 'facture', 'creer');
			}
			elseif($object->element == 'shipping')
			{
				$createRight = true; // No rights management for shipments
			}

			if ($object->statut == 0  && $createRight) {


				if($object->element=='facture')$idvar = 'facid';
				else $idvar='id';

				if(in_array($action, array('add_title_line', 'add_total_line', 'add_subtitle_line', 'add_subtotal_line', 'add_free_text')) )
				{
					$level = GETPOST('level', 'int'); //New avec SUBTOTAL_USE_NEW_FORMAT

					if($action=='add_title_line') {
						$title = GETPOST('title', 'none');
						if(empty($title)) $title = $langs->trans('title');
						$qty = $level<1 ? 1 : $level ;
					}
					else if($action=='add_free_text') {
						$title = GETPOST('title', 'restricthtml');

						if (empty($title)) {
							$free_text = GETPOST('free_text', 'int');
							if (!empty($free_text)) {
								$TFreeText = getTFreeText();
								if (!empty($TFreeText[$free_text])) {
									$title = $TFreeText[$free_text]->content;
								}
							}
						}
						if(empty($title)) $title = $langs->trans('subtotalAddLineDescription');
						$qty = 50;
					}
					else if($action=='add_subtitle_line') {
						$title = GETPOST('title', 'none');
						if(empty($title)) $title = $langs->trans('subtitle');
						$qty = 2;
					}
					else if($action=='add_subtotal_line') {
						$title = $langs->trans('SubSubTotal');
						$qty = 98;
					}
					else {
						$title = GETPOST('title', 'none') ? GETPOST('title', 'none') : $langs->trans('SubTotal');
						$qty = $level ? 100-$level : 99;
					}
					dol_include_once('/subtotal/class/subtotal.class.php');

					if (getDolGlobalString('SUBTOTAL_AUTO_ADD_SUBTOTAL_ON_ADDING_NEW_TITLE') && $qty < 10) TSubtotal::addSubtotalMissing($object, $qty);

	    			if (getDolGlobalInt('MAIN_VIEW_LINE_NUMBER') == 1) {
						$rang = GETPOST('rank', 'int') ? (int) GETPOST('rank', 'int') : '-1';
						$newlineid = TSubtotal::addSubTotalLine($object, $title, $qty, $rang);
						echo '<div id="newlineid">'.$newlineid.'</div>';
					} else {
						TSubtotal::addSubTotalLine($object, $title, $qty);
					}
				}
				else if($action==='ask_deleteallline') {
						$form=new Form($db);

						$lineid = GETPOST('lineid','int');
						$TIdForGroup = TSubtotal::getLinesFromTitleId($object, $lineid, true);

						$nbLines = count($TIdForGroup);

						$formconfirm=$form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('deleteWithAllLines'), $langs->trans('ConfirmDeleteAllThisLines',$nbLines), 'confirm_delete_all_lines','',0,1);
						print $formconfirm;
				}

				if (getDolGlobalString('SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE'))
				{
					$this->showSelectTitleToAdd($object);
				}


				if($object->element != 'shipping' && $action!='editline') {
					// New format is for 3.8
					$this->printNewFormat($object, $conf, $langs, $idvar);
				}
			}
		}
		elseif ((!empty($parameters['currentcontext']) && $parameters['currentcontext'] == 'orderstoinvoice') || in_array('orderstoinvoice',$contexts) || in_array('orderstoinvoicesupplier',$contexts))
		{
			$this->_billOrdersAddCheckBoxForTitleBlocks();
		}

		return 0;
	}

	function printNewFormat(&$object, &$conf, &$langs, $idvar)
	{
		if (!getDolGlobalString('SUBTOTAL_ALLOW_ADD_BLOCK')) return false;

		$jsData = array(
			'conf' => array(
				'SUBTOTAL_USE_NEW_FORMAT' => getDolGlobalInt('SUBTOTAL_USE_NEW_FORMAT'),
				'MAIN_VIEW_LINE_NUMBER' => getDolGlobalInt('MAIN_VIEW_LINE_NUMBER'),
				'token' => newToken(),
				'groupBtn' => intval(DOL_VERSION) < 20.0 || getDolGlobalInt('SUBTOTAL_FORCE_EXPLODE_ACTION_BTN') ? 0 : 1
			),
			'langs' => array(
				'Level' => $langs->trans('Level'),
				'Position' => $langs->transnoentities('Position'),
				'AddTitle' => $langs->trans('AddTitle'),
				'AddSubTotal' => $langs->trans('AddSubTotal'),
				'AddFreeText' => $langs->trans('AddFreeText'),
			)
		);


		$jsData['buttons'] = dolGetButtonAction('', $langs->trans('SubtotalsAndTitlesActionBtnLabel'), 'default', [
				['attr' => [ 'rel' => 'add_title_line'], 'id' => 'add_title_line', 'urlraw' =>'#', 'label' => $langs->trans('AddTitle'), 'perm' => 1],
				['attr' => [ 'rel' => 'add_total_line'], 'id' => 'add_total_line', 'urlraw' =>'#', 'label' => $langs->trans('AddSubTotal'), 'perm' => 1],
				['attr' => [ 'rel' => 'add_free_text'], 'id' => 'add_free_text', 'urlraw' =>'#', 'label' => $langs->trans('AddFreeText'), 'perm' => 1],
			], 'subtotal-actions-buttons-dropdown');

		if(empty($jsData['conf']['groupBtn'])) {
			$jsData['buttons'] = '<div class="inline-block divButAction"><a id="add_title_line" rel="add_title_line" href="javascript:;" class="butAction">'.$langs->trans('AddTitle').'</a></div>';
			$jsData['buttons'].= '<div class="inline-block divButAction"><a id="add_total_line" rel="add_total_line" href="javascript:;" class="butAction">'.$langs->trans('AddSubTotal').'</a></div>';
			$jsData['buttons'].= '<div class="inline-block divButAction"><a id="add_free_text" rel="add_free_text" href="javascript:;" class="butAction">'.$langs->trans('AddFreeText').'</a></div>';
		}




		?>
			<!-- SubTotal action printNewFormat -->
		 	<script type="text/javascript">
				$(document).ready(function() {
					let jsSubTotalData = <?php print json_encode($jsData); ?>;

					if (jsSubTotalData.conf.groupBtn == 0) {

						let targetContainer;

						if ($("div.fiche div.tabsAction > .butAction").length) {
							targetContainer = $("div.fiche div.tabsAction");
						} else {
							targetContainer = $("div.fiche div.tabsAction > .divButAction").length
								? $("div.fiche div.tabsAction")
								: $("div.fiche div.tabsAction");
						}
						targetContainer.append('<br />');
						targetContainer.append(jsSubTotalData.buttons);

					} else {

						let elementsButon;

						elementsButon = $("div.fiche div.tabsAction > .butAction").length
							? $("div.fiche div.tabsAction > .butAction")
							: $("div.fiche div.tabsAction > .divButAction");

						$(jsSubTotalData.buttons).insertBefore(elementsButon.first());
					}

					function updateAllMessageForms(){
				         for (instance in CKEDITOR.instances) {
				             CKEDITOR.instances[instance].updateElement();
				         }
				    }

					function promptSubTotal(action, titleDialog, label, url_to, url_ajax, params, use_textarea, show_free_text, show_under_title) {
					     $( "#dialog-prompt-subtotal" ).remove();

						 var dialog_html = '<div id="dialog-prompt-subtotal" '+(action == 'addSubtotal' ? 'class="center"' : '')+' >';
						 dialog_html += '<input id="token" name="token" type="hidden" value="' + jsSubTotalData.conf.token + '" />';


						 if (typeof show_under_title != 'undefined' && show_under_title)
						 {
							 var selectUnderTitle = <?php echo json_encode(getHtmlSelectTitle($object, true)); ?>;
							 dialog_html += selectUnderTitle + '<br /><br />';
						 }

						if (action == 'addTitle' || action == 'addFreeTxt')
						{
							if (typeof show_free_text != 'undefined' && show_free_text)
							{
							   var selectFreeText = <?php echo json_encode(getHtmlSelectFreeText()); ?>;
							   dialog_html += selectFreeText + ' <?php echo $langs->transnoentities('subtotalFreeTextOrDesc'); ?><br />';
							}

							if (typeof use_textarea != 'undefined' && use_textarea) dialog_html += '<textarea id="sub-total-title" rows="<?php echo ROWS_8; ?>" cols="80" placeholder="'+label+'"></textarea>';
							else dialog_html += '<input id="sub-total-title" size="30" value="" placeholder="'+label+'" />';
						}

						if (action == 'addSubtotal'){
							dialog_html += '<input id="sub-total-title" size="30" value="" placeholder="'+label+'" />';
						}

						if(jsSubTotalData.conf.MAIN_VIEW_LINE_NUMBER) {
							dialog_html += '&emsp;<input style="max-width: 80px;" id="subtotal_line_position" name="subtotal_line_position" type="number" min="0" step="1" size="1" text-align="right" placeholder="' + jsSubTotalData.langs.Position + '" />';
						}

						if (action == 'addTitle' || action == 'addSubtotal')
						{
							if(jsSubTotalData.conf.SUBTOTAL_USE_NEW_FORMAT){
  								dialog_html += '&emsp;<select name="subtotal_line_level">';
								for (var i=1;i<10;i++)
								{
									dialog_html += '<option value="'+i+'">'+ jsSubTotalData.langs.Level +' '+i+'</option>';
								}
								dialog_html += "</select>";
							}
							else{
								dialog_html += '<input type="hidden" name="subtotal_line_level" value="'+i+'" />';
							}
						}

						 dialog_html += '</div>';

						$('body').append(dialog_html);

						<?php
						$editorTool = getDolGlobalString('FCKEDITOR_EDITORNAME', 'ckeditor');
						$editorConf = empty(getDolGlobalString('FCKEDITOR_ENABLE_DETAILS')) ? false : getDolGlobalString('FCKEDITOR_ENABLE_DETAILS');
						if($editorConf && in_array($editorTool,array('textarea','ckeditor'))){
						?>
						if (action == 'addTitle' || action == 'addFreeTxt')
						{
							if (typeof use_textarea != 'undefined' && use_textarea && typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" )
							{
								CKEDITOR.replace('sub-total-title', {
									toolbar: 'dolibarr_details',
									versionCheck: false,
									toolbarStartupExpanded: false,

									// Intégration du filemanager via les variables JS de Dolibarr
									filebrowserBrowseUrl: ckeditorFilebrowserBrowseUrl,
									filebrowserImageBrowseUrl: ckeditorFilebrowserImageBrowseUrl,
									// filebrowserUploadUrl: DOL_URL_ROOT + '/includes/fckeditor/editor/filemanagerdol/connectors/php/upload.php?Type=File',
									// filebrowserImageUploadUrl: DOL_URL_ROOT + '/includes/fckeditor/editor/filemanagerdol/connectors/php/upload.php?Type=Image',

									// Dimensions des fenêtres popup
									filebrowserWindowWidth: '900',
									filebrowserWindowHeight: '500',
									filebrowserImageWindowWidth: '900',
									filebrowserImageWindowHeight: '500'
								});
							}
						}
						<?php } ?>

					     $( "#dialog-prompt-subtotal" ).dialog({
	                        resizable: false,
							height: 'auto',
							width: 'auto',
	                        modal: true,
	                        title: titleDialog,
	                        buttons: {
	                            "Ok": function() {
	                            	if (typeof use_textarea != 'undefined' && use_textarea && typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" ){ updateAllMessageForms(); }
									params.rank = 0;
									if($(this).find('#subtotal_line_position').length > 0){
										params.rank = $(this).find('#subtotal_line_position').val();
									}

									params.title = (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && "sub-total-title" in CKEDITOR.instances ? CKEDITOR.instances["sub-total-title"].getData() : $(this).find('#sub-total-title').val());
									params.under_title = $(this).find('select[name=under_title]').val();
									params.free_text = $(this).find('select[name=free_text]').val();
									params.level = $(this).find('select[name=subtotal_line_level]').val();
									params.token = $(this).find('input[name=token]').val();

									let microtime = new Date();
									url_to+="&microtime="+ microtime.getTime(); // to avoid # ancor blocking refresh by adding same rank as curent

									$.ajax({
										url: url_ajax
										,type: 'POST'
										,data: params
										,dataType: "html"
									}).done(function(response) {
										if(jsSubTotalData.conf.MAIN_VIEW_LINE_NUMBER == 1) {
											newlineid = $($.parseHTML(response)).find("#newlineid").text();
											url_to = url_to + "&gotoline=" + params.rank + "#row-" + newlineid;
										}
										else {
											url_to = url_to + "&gotoline=" + params.rank + "#tableaddline";
										}
										document.location.href=url_to;
									});

                                    $( this ).dialog( "close" );
	                            },
	                            "<?php echo $langs->trans('Cancel') ?>": function() {
	                                $( this ).dialog( "close" );
	                            }
	                        }
	                     });
					}

					$('a[rel=add_title_line]').click(function(e)
					{
						e.preventDefault();
						promptSubTotal('addTitle'
							 , "<?php echo $langs->trans('YourTitleLabel') ?>"
							 , "<?php echo $langs->trans('title'); ?>"
							 , '?<?php echo $idvar ?>=<?php echo $object->id; ?>'
							 , '<?php echo $_SERVER['PHP_SELF']; ?>'
							 , {<?php echo $idvar; ?>: <?php echo (int) $object->id; ?>, action:'add_title_line'}
						);
					});

					$('a[rel=add_total_line]').click(function(e)
					{
						e.preventDefault();
						promptSubTotal('addSubtotal'
							, '<?php echo $langs->trans('YourSubtotalLabel') ?>'
							, '<?php echo $langs->trans('subtotal'); ?>'
							, '?<?php echo $idvar ?>=<?php echo $object->id; ?>'
							, '<?php echo $_SERVER['PHP_SELF']; ?>'
							, {<?php echo $idvar; ?>: <?php echo (int) $object->id; ?>, action:'add_total_line'}
							/*,false,false, <?php echo getDolGlobalString('SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE') ? 'true' : 'false'; ?>*/
						);
					});

					$('a[rel=add_free_text]').click(function(e)
					{
						e.preventDefault();
						promptSubTotal('addFreeTxt'
							, "<?php echo $langs->transnoentitiesnoconv('YourTextLabel') ?>"
							, "<?php echo $langs->trans('subtotalAddLineDescription'); ?>"
							, '?<?php echo $idvar ?>=<?php echo $object->id; ?>'
							, '<?php echo $_SERVER['PHP_SELF']; ?>'
							, {<?php echo $idvar; ?>: <?php echo (int) $object->id; ?>, action:'add_free_text'}
							, true
							, true
							, <?php echo getDolGlobalString('SUBTOTAL_ALLOW_ADD_LINE_UNDER_TITLE') ? 'true' : 'false'; ?>
						);
					});
				});
		 	</script>
		 <?php
	}

	function showSelectTitleToAdd(&$object)
	{
		global $langs;

		dol_include_once('/subtotal/class/subtotal.class.php');
		dol_include_once('/subtotal/lib/subtotal.lib.php');
		$TTitle = TSubtotal::getAllTitleFromDocument($object);

		?>
		<script type="text/javascript">
			$(function() {
				var add_button = $("#addline");

				if (add_button.length > 0)
				{
					add_button.closest('tr').prev('tr.liste_titre').children('td:last').addClass('center').text("<?php echo $langs->trans('subtotal_title_to_add_under_title'); ?>");
					var select_title = $(<?php echo json_encode(getHtmlSelectTitle($object)); ?>);

					add_button.before(select_title);
				}
			});
		</script>
		<?php
	}


	function formBuilddocOptions($parameters, &$object) {
	/* Réponse besoin client */

		global $conf, $langs, $bc;

		$action = GETPOST('action', 'none');
		$contextArray = explode(':',$parameters['context']);
		if (
				!getDolGlobalString('SUBTOTAL_HIDE_OPTIONS_BUILD_DOC')
				&& (in_array('invoicecard',$contextArray)
		        || in_array('invoicesuppliercard',$contextArray)
				|| in_array('propalcard',$contextArray)
				|| in_array('ordercard',$contextArray)
		        || in_array('ordersuppliercard',$contextArray)
				|| in_array('invoicereccard',$contextArray))
			)
	        {
	            $hideInnerLines	= isset( $_SESSION['subtotal_hideInnerLines_'.$parameters['modulepart']][$object->id] ) ?  $_SESSION['subtotal_hideInnerLines_'.$parameters['modulepart']][$object->id] : 0;
	            $hidesubdetails	= isset( $_SESSION['subtotal_hidesubdetails_'.$parameters['modulepart']][$object->id] ) ?  $_SESSION['subtotal_hidesubdetails_'.$parameters['modulepart']][$object->id] : 0;	// InfraS change
				$hidepricesDefaultConf = getDolGlobalString('SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED')?getDolGlobalString('SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED') :0;
				$hideprices= !empty( $_SESSION['subtotal_hideprices_'.$parameters['modulepart']][$object->id] ) ?  $_SESSION['subtotal_hideprices_'.$parameters['modulepart']][$object->id] : $hidepricesDefaultConf;
				// InfraS change begin
				$titleOptions	= $langs->trans('Subtotal_Options').'&nbsp;&nbsp;&nbsp;'.img_picto($langs->trans('Setup'), 'setup', 'style="vertical-align: bottom; height: 20px;"');
				$titleStyle		= 'background: transparent !important; background-color: rgba(148, 148, 148, .065) !important; cursor: pointer;';
				$out = '';
		     	$out.= '		<tr class = "subtotalfold" style = "'.$titleStyle.'"><td colspan = "6" align = "center" style = "font-size: 120%;">'.$titleOptions.'</td></tr>
								<tr class = "oddeven subtotalfoldable">
									<td colspan = "6" class = "right">
										<label for = "hideInnerLines">'.$langs->trans('HideInnerLines').'</label>
										<input type = "checkbox" onclick="if($(this).is(\':checked\')) { $(\'#hidesubdetails\').prop(\'checked\', \'checked\')  }" id = "hideInnerLines" name = "hideInnerLines" value = "1" '.(!empty($hideInnerLines) ? 'checked = "checked"' : '').' />
									</td>
								</tr>
								<tr class = "oddeven subtotalfoldable">
									<td colspan = "6" class = "right">
										<label for = "hidesubdetails">'.$langs->trans('SubTotalhidedetails').'</label>
										<input type = "checkbox" id = "hidesubdetails" name = "hidesubdetails" value = "1" '.(!empty($hidesubdetails) ? 'checked = "checked"' : '').' />
									</td>
								</tr>
								<tr class = "oddeven subtotalfoldable">
									<td colspan = "6" class = "right">
										<label for = "hideprices">'.$langs->trans('SubTotalhidePrice').'</label>
										<input type = "checkbox" id = "hideprices" name = "hideprices" value = "1" '.(!empty($hideprices) ? 'checked = "checked"' : '').' />
									</td>
								</tr>';
				if (
					(in_array('propalcard',             $contextArray) && getDolGlobalString('SUBTOTAL_PROPAL_ADD_RECAP'))
					|| (in_array('ordercard',           $contextArray) && getDolGlobalString('SUBTOTAL_COMMANDE_ADD_RECAP'))
				    || (in_array('ordersuppliercard',   $contextArray) && getDolGlobalString('SUBTOTAL_COMMANDE_ADD_RECAP'))
					|| (in_array('invoicecard',         $contextArray) && getDolGlobalString('SUBTOTAL_INVOICE_ADD_RECAP'))
				    || (in_array('invoicesuppliercard', $contextArray) && getDolGlobalString('SUBTOTAL_INVOICE_ADD_RECAP'))
					|| (in_array('invoicereccard',      $contextArray) && getDolGlobalString('SUBTOTAL_INVOICE_ADD_RECAP'))
				)
				{
					$out.= '	<tr class = "oddeven subtotalfoldable">
									<td colspan = "6" class = "right">
										<label for = "subtotal_add_recap">'.$langs->trans('subtotal_add_recap').'</label>
										<input type = "checkbox" id = "subtotal_add_recap" name = "subtotal_add_recap" value = "1" '.(!empty(GETPOST('subtotal_add_recap', 'none')) ? 'checked = "checked"' : '').' />
									</td>
								</tr>';
				}
				$out.= '		<script type = "text/javascript">
									$(document).ready(function(){
										$(".subtotalfoldable").hide();
									});
									$(".subtotalfold").click(function (){
										$(".subtotalfoldable").toggle();
									});
								</script>';
				$this->resprints = $out;
			}
        return 0;
		// InfraS change end
	}

    function formEditProductOptions($parameters, &$object, &$action, $hookmanager)
    {

    	if (in_array('invoicecard',explode(':',$parameters['context'])))
        {

        }

        return 0;
    }

	function ODTSubstitutionLine(&$parameters, &$object, $action, $hookmanager) {
		global $conf;

		if($action === 'builddoc' || $action === 'addline' || $action === 'confirm_valid' || $action === 'confirm_paiement') {

			$line = &$parameters['line'];
			$object = &$parameters['object'];
			$substitutionarray = &$parameters['substitutionarray'];

            $substitutionarray['line_not_modsubtotal'] = true;
            $substitutionarray['line_modsubtotal'] = false;
            $substitutionarray['line_modsubtotal_total'] = false;
            $substitutionarray['line_modsubtotal_title'] = false;

			if($line->product_type == 9 && $line->special_code == $this->module_number) {
				$substitutionarray['line_modsubtotal'] = 1;
                $substitutionarray['line_not_modsubtotal'] = false;

				$substitutionarray['line_price_ht']
					 = $substitutionarray['line_price_vat']
					 = $substitutionarray['line_price_ttc']
					 = $substitutionarray['line_vatrate']
					 = $substitutionarray['line_qty']
					 = $substitutionarray['line_up']
					 = '';

				if($line->qty>90) {
					$substitutionarray['line_modsubtotal_total'] = true;

					//list($total, $total_tva, $total_ttc, $TTotal_tva) = $this->getTotalLineFromObject($object, $line, '', 1);
					$TInfo = $this->getTotalLineFromObject($object, $line, '', 1);

					$substitutionarray['line_price_ht'] = price($TInfo[0],0,'',1,0,getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
					$substitutionarray['line_price_vat'] = price($TInfo[1],0,'',1,0,getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
					$substitutionarray['line_price_ttc'] = price($TInfo[2],0,'',1,0,getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
				} else {
					$substitutionarray['line_modsubtotal_title'] = true;
				}


			}
			else{
				$substitutionarray['line_not_modsubtotal'] = true;
				$substitutionarray['line_modsubtotal'] = 0;
			}

		}

		return 0;
	}

	/**
	 * @param array $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int|void
	 */
	function doActions($parameters, &$object, $action, $hookmanager)
	{
		global $db, $conf, $langs,$user;
		$contextArray = array();
		if (isset($parameters['context'])) $contextArray = explode(':', $parameters['context']);

		dol_include_once('/subtotal/class/subtotal.class.php');
		dol_include_once('/subtotal/lib/subtotal.lib.php');
		require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

		$showBlockExtrafields = GETPOST('showBlockExtrafields', 'none');

		if(isset($object->element) && $object->element=='facture') $idvar = 'facid';
		else $idvar = 'id';

		if ($action == 'updateligne' || $action == 'updateline')
		{
			$found = false;
			$lineid = GETPOST('lineid', 'int');
			foreach ($object->lines as &$line)
			{

				if ($line->id == $lineid && TSubtotal::isModSubtotalLine($line))
				{
					$found = true;
					if(TSubtotal::isTitle($line) && !empty($showBlockExtrafields)) {
						$extrafieldsline = new ExtraFields($db);
						$extralabelsline = $extrafieldsline->fetch_name_optionals_label($object->table_element_line);
						$extrafieldsline->setOptionalsFromPost($extralabelsline, $line);
					}
					_updateSubtotalLine($object, $line);
					_updateSubtotalBloc($object, $line);

					TSubtotal::generateDoc($object);
					break;
				}
			}

			if ($found)
			{
				header('Location: '.$_SERVER['PHP_SELF'].'?'.$idvar.'='.$object->id);
				exit; // Surtout ne pas laisser Dolibarr faire du traitement sur le updateligne sinon ça plante les données de la ligne
			}
		}
		else if($action === 'builddoc') {

			if (
				in_array('invoicecard',              $contextArray)
				|| in_array('propalcard',            $contextArray)
				|| in_array('ordercard',             $contextArray)
			    || in_array('ordersuppliercard',     $contextArray)
			    || in_array('invoicesuppliercard',   $contextArray)
			    || in_array('supplier_proposalcard', $contextArray)
			)
	        {
				if(in_array('invoicecard',$contextArray)) {
					$sessname = 'subtotal_hideInnerLines_facture';
					$sessname2 = 'subtotal_hidesubdetails_facture';	// InfraS change
					$sessname3 = 'subtotal_hideprices_facture';
				}
				elseif(in_array('invoicesuppliercard',$contextArray)) {
				    $sessname = 'subtotal_hideInnerLines_facture_fournisseur';
				    $sessname2 = 'subtotal_hidesubdetails_facture_fournisseur';	// InfraS change
				    $sessname3 = 'subtotal_hideprices_facture_fournisseur';
				}
				elseif(in_array('propalcard',$contextArray)) {
					$sessname = 'subtotal_hideInnerLines_propal';
					$sessname2 = 'subtotal_hidesubdetails_propal';	// InfraS change
					$sessname3 = 'subtotal_hideprices_propal';
				}
				elseif(in_array('supplier_proposalcard',$contextArray)) {
				    $sessname = 'subtotal_hideInnerLines_supplier_proposal';
				    $sessname2 = 'subtotal_hidesubdetails_supplier_proposal';	// InfraS change
				    $sessname3 = 'subtotal_hideprices_supplier_proposal';
				}
				elseif(in_array('ordercard',$contextArray)) {
					$sessname = 'subtotal_hideInnerLines_commande';
					$sessname2 = 'subtotal_hidesubdetails_commande';	// InfraS change
					$sessname3 = 'subtotal_hideprices_commande';
				}
				elseif(in_array('ordersuppliercard',$contextArray)) {
				    $sessname = 'subtotal_hideInnerLines_commande_fournisseur';
				    $sessname2 = 'subtotal_hidesubdetails_commande_fournisseur';	// InfraS change
				    $sessname3 = 'subtotal_hideprices_commande_fournisseur';
				}
				else {
					$sessname = 'subtotal_hideInnerLines_unknown';
					$sessname2 = 'subtotal_hidesubdetails_unknown';	// InfraS change
					$sessname3 = 'subtotal_hideprices_unknown';
				}

				global $hidesubdetails; // same name as in global card (proposal, order, invoice, ...)	// InfraS add
				global $hideprices;

				$hideInnerLines = GETPOST('hideInnerLines', 'int');
				if (!array_key_exists($sessname, $_SESSION) || empty($_SESSION[$sessname]) || !is_array($_SESSION[$sessname]) || !isset($_SESSION[$sessname][$object->id]) || !is_array($_SESSION[$sessname][$object->id]))
                    $_SESSION[$sessname] = array($object->id => 0); // prevent old system
				$_SESSION[$sessname][$object->id] = $hideInnerLines;

				$hidesubdetails= GETPOST('hidesubdetails', 'int');	// InfraS change
				if (!array_key_exists($sessname, $_SESSION) || empty($_SESSION[$sessname]) || !is_array($_SESSION[$sessname2]) || !isset($_SESSION[$sessname2][$object->id]) || !is_array($_SESSION[$sessname2][$object->id]))
					$_SESSION[$sessname2] = array($object->id => 0); // prevent old system
				$_SESSION[$sessname2][$object->id] = $hidesubdetails;	// InfraS change

				$hideprices= GETPOST('hideprices', 'int');
				if (!array_key_exists($sessname, $_SESSION) || empty($_SESSION[$sessname]) || !is_array($_SESSION[$sessname3]) || !isset($_SESSION[$sessname3][$object->id]) || !is_array($_SESSION[$sessname3][$object->id]))
					$_SESSION[$sessname3] = array($object->id => 0); // prevent old system
				$_SESSION[$sessname3][$object->id] = $hideprices;

				foreach($object->lines as &$line) {
					if ($line->product_type == 9 && $line->special_code == $this->module_number) {

                        if($line->qty>=90) {
                            $line->modsubtotal_total = 1;
                        }
                        else{
                            $line->modsubtotal_title = 1;
                        }

						$line->total_ht = $this->getTotalLineFromObject($object, $line, '');
					}
	        	}
	        }

		}
		else if($action === 'confirm_delete_all_lines' && GETPOST('confirm', 'none')=='yes') {
			$error = 0;
			$Tab = TSubtotal::getLinesFromTitleId($object, GETPOST('lineid', 'int'), true);
			foreach($Tab as $line) {
                $result = 0;
				// InfraS add begin
				if (!empty(isModEnabled('ouvrage')) && Ouvrage::isOuvrage($line)) {
					// Call trigger
					include_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';
					$interface			= new Interfaces($db);
					$result				= $interface->run_triggers('OUVRAGE_DELETE', $line, $user, $langs, $conf);
					if ($result < 0)	$error++;
					// End call triggers
				}
				// InfraS add end
				$idLine = $line->id;
				/**
				 * @var $object Facture
				 */
				if($object->element=='facture') $result = $object->deleteline($idLine);
				/**
				 * @var $object Facture fournisseur
				 */
				else if($object->element=='invoice_supplier')
				{
					$result = $object->deleteline($idLine);
				}
				/**
				 * @var $object Propal
				 */
				else if($object->element=='propal') $result = $object->deleteline($idLine);
				/**
				 * @var $object Propal Fournisseur
				 */
				else if($object->element=='supplier_proposal') $result = $object->deleteline($idLine);
				/**
				 * @var $object Commande
				 */
				else if($object->element=='commande')
				{
					$result = $object->deleteline($user, $idLine);
				}
				/**
				 * @var $object Commande fournisseur
				 */
				else if($object->element=='order_supplier')
				{
                    			$result = $object->deleteline($idLine);
				}
				/**
				 * @var $object Facturerec
				 */
				else if($object->element=='facturerec') $result = $object->deleteline($idLine);
				/**
				 * @var $object Expedition
				 */
				else if($object->element=='shipping') $result = $object->deleteline($user, $idLine);

                if ($result < 0) $error++;
			}

            if ($error > 0) {
                setEventMessages($object->error, $object->errors, 'errors');
                $db->rollback();
            } else {
                $db->commit();
            }

			header('location:?id='.$object->id);
			exit;

		}
		else if ($action == 'duplicate')
		{
			$lineid = GETPOST('lineid', 'int');
			$nbDuplicate = TSubtotal::duplicateLines($object, $lineid, true);

			if ($nbDuplicate > 0) setEventMessage($langs->trans('subtotal_duplicate_success', $nbDuplicate));
			elseif ($nbDuplicate == 0) setEventMessage($langs->trans('subtotal_duplicate_lineid_not_found'), 'warnings');
			else setEventMessage($langs->trans('subtotal_duplicate_error'), 'errors');

			header('Location: ?id='.$object->id);
			exit;
		}

		elseif ((!empty($parameters['currentcontext']) && $parameters['currentcontext'] == 'orderstoinvoice')
				|| in_array('orderstoinvoice',         $contextArray)
				|| in_array('orderstoinvoicesupplier', $contextArray)
				|| in_array('orderlist',               $contextArray)
		) {
			$this->_billOrdersAddCheckBoxForTitleBlocks();
		}
        else {
            // when automatic generate is enabled : keep last selected options from last "builddoc" action (ganerate document manually)
            if (!getDolGlobalString('MAIN_DISABLE_PDF_AUTOUPDATE')) {
                if (in_array('invoicecard', $contextArray)
                    || in_array('propalcard', $contextArray)
                    || in_array('ordercard', $contextArray)
                    || in_array('ordersuppliercard', $contextArray)
                    || in_array('invoicesuppliercard', $contextArray)
                    || in_array('supplier_proposalcard', $contextArray)
                ) {
                    $confirm = GETPOST('confirm', 'alpha');

                    if ($action == 'modif'
                        || ($action == 'confirm_modif' && $confirm == 'yes')
                        || ($action == 'confirm_edit' && $confirm == 'yes')
                        || $action == 'reopen'
                        || (($action == 'confirm_validate' || $action == 'confirm_valid') && $confirm == 'yes')
                    ) {
                        if (in_array('invoicecard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_facture';
                            $sessname2 = 'subtotal_hidesubdetails_facture';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_facture';
                        } elseif (in_array('invoicesuppliercard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_facture_fournisseur';
                            $sessname2 = 'subtotal_hidesubdetails_facture_fournisseur';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_facture_fournisseur';
                        } elseif (in_array('propalcard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_propal';
                            $sessname2 = 'subtotal_hidesubdetails_propal';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_propal';
                        } elseif (in_array('supplier_proposalcard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_supplier_proposal';
                            $sessname2 = 'subtotal_hidesubdetails_supplier_proposal';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_supplier_proposal';
                        } elseif (in_array('ordercard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_commande';
                            $sessname2 = 'subtotal_hidesubdetails_commande';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_commande';
                        } elseif (in_array('ordersuppliercard', $contextArray)) {
                            $sessname = 'subtotal_hideInnerLines_commande_fournisseur';
                            $sessname2 = 'subtotal_hidesubdetails_commande_fournisseur';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_commande_fournisseur';
                        } else {
                            $sessname = 'subtotal_hideInnerLines_unknown';
                            $sessname2 = 'subtotal_hidesubdetails_unknown';	// InfraS change
                            $sessname3 = 'subtotal_hideprices_unknown';
                        }

                        global $hidesubdetails; // same name as in global card (proposal, order, invoice, ...)	// InfraS change
                        global $hideprices; // used as global value in this module

                        if (GETPOSTISSET('hideInnerLines')) {
                            $hideInnerLines = GETPOST('hideInnerLines', 'int');
                        } else {
                            $hideInnerLines = isset($_SESSION[$sessname][$object->id]) ? $_SESSION[$sessname][$object->id] : 0;
                        }
                        $_POST['hideInnerLines'] = $hideInnerLines;

                        if (GETPOSTISSET('hidesubdetails')) {	// InfraS change
                            $hidesubdetails = GETPOST('hidesubdetails', 'int');	// InfraS change
                        } else {
                            $hidesubdetails = isset($_SESSION[$sessname2][$object->id]) ? $_SESSION[$sessname2][$object->id] : (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);	// InfraS change
                        }
                        // no need to set POST value (it's a global value used in global card)

                        if (GETPOSTISSET('hideprices')) {
                            $hideprices = GETPOST('hideprices', 'int');
                        } else {
                            $hidepricesDefaultConf = getDolGlobalString('SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED') ? getDolGlobalString('SUBTOTAL_HIDE_PRICE_DEFAULT_CHECKED')  : 0;
                            $hideprices = isset($_SESSION[$sessname3][$object->id]) ? $_SESSION[$sessname3][$object->id] : $hidepricesDefaultConf;
                        }
                        // no need to set POST value (it's a global value used in this module)
                    }
                }
            }
        }

        return 0;
	}

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {
		return 0;
	}

	function changeRoundingMode($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;
		if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && !empty($object->table_element_line) && in_array($object->element, array('commande', 'facture', 'propal')))
		{
			if ($object->element == 'commande')
				$obj = new OrderLine($object->db);
			if ($object->element == 'propal')
				$obj = new PropaleLigne($object->db);
			if ($object->element == 'facture')
				$obj = new FactureLigne($object->db);
			if (!empty($parameters['fk_element']))
			{

				if($obj->fetch($parameters['fk_element'])){
					$obj->id= $obj->rowid;
					if (empty($obj->array_options))
						$obj->fetch_optionals();
					if (!empty($obj->array_options['options_subtotal_nc']))
						return 1;
				}
			}
		}

		return 0;
	}

	function getArrayOfLineForAGroup(&$object, $lineid) {
		$qty_line = 0;
        $qty_end_line = 0;
		$found = false;
		$Tab= array();

		foreach($object->lines as $l) {
		    $lid = (!empty($l->rowid) ? $l->rowid : $l->id);

			if($lid == $lineid && $l->qty > 0 && $l->qty < 10) {
				$found = true;
				$qty_line = $l->qty;
                $qty_end_line = 100 - $qty_line;
			}

			if($found) {
                if ($l->special_code == $this->module_number && $lid != $lineid && ($l->qty <= $qty_line || $l->qty >= $qty_end_line)) {
                    if ($l->qty == $qty_end_line) $Tab[] = $lid;
                    break;
                }
                else $Tab[] = $lid;
			}
		}

		return $Tab;
	}


    //@TODO change all call to this method with the method in lib !!!!
	/**
	 * @param $object
	 * @param $line
	 * @param false $use_level
	 * @param int $return_all
	 * @return array|float|int
	 */
	function getTotalLineFromObject(&$object, &$line, $use_level=false, $return_all=0) {
		global $conf, $db;	// InfraS change

		$rang = $line->rang;
		$qty_line = $line->qty;
		$lvl = 0;
        if (TSubtotal::isSubtotal($line)) $lvl = TSubtotal::getNiveau($line);

		$title_break = TSubtotal::getParentTitleOfLine($object, $rang, $lvl);

		$total = 0;
		$total_tva = 0;
		$total_ttc = 0;
        $total_qty = 0;
		$TTotal_tva = array();
		$TTotal_tva_array = array();	// InfraS add
		$multicurrency_total_ht = 0;	// InfraS add
		$multicurrency_total_ttc = 0;	// InfraS add


		$sign=1;
		if (isset($object->type) && $object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) $sign=-1;

		if (GETPOST('action', 'none') == 'builddoc') $builddoc = true;
		else $builddoc = false;

		dol_include_once('/subtotal/class/subtotal.class.php');

		$TLineReverse = array_reverse($object->lines);

		// InfraS add begin
		$listOuvrages	= array();
		if (!empty(isModEnabled('ouvrage'))) {
			// first loop to record all ouvrages
			foreach($TLineReverse as $l) {
				$isOuvrage	= Ouvrage::isOuvrage($l) ? 1 : 0;	// ouvrage ??
				if (!empty($title_break) && $title_break->id == $l->id) break;	// We go back from the end to the beginning, so when we find the associated title we stop
				elseif (!empty($isOuvrage)) {	// it's a ouvrage
					$listOuvrages[$l->id]	= $l->qty;	// record the quantity linked to the ID
				}
			}
		}
		// InfraS add end

		foreach($TLineReverse as $l)
		{
			$l->total_ttc = doubleval($l->total_ttc);
			$l->total_ht = doubleval($l->total_ht);
			$l->multicurrency_total_ht = doubleval($l->multicurrency_total_ht);	// InfraS add
			$l->multicurrency_total_ttc = doubleval($l->multicurrency_total_ttc);	// InfraS add
			$isOuvrage	= !empty(isModEnabled('ouvrage')) && Ouvrage::isOuvrage($l) ? 1 : 0;	// InfraS add

			//print $l->rang.'>='.$rang.' '.$total.'<br/>';
            if ($l->rang>=$rang) continue;
            if (!empty($title_break) && $title_break->id == $l->id) break;
            elseif (!TSubtotal::isModSubtotalLine($l) && empty($isOuvrage))	// InfraS change
            {
				$totalQty	= !empty($listOuvrages) && !empty($l->fk_parent_line) && array_key_exists($l->fk_parent_line, $listOuvrages) ? $listOuvrages[$l->fk_parent_line] : 1;	// InfraS change
				$total_qty += $l->qty;
               // TODO retirer le test avec $builddoc quand Dolibarr affichera le total progression sur la card et pas seulement dans le PDF
                if ($builddoc && $object->element == 'facture' && $object->type==Facture::TYPE_SITUATION)
                {
					$sitFacTotLineAvt	= isset($conf->global->INFRASPLUS_PDF_SITFAC_TOTLINE_AVT) ? $conf->global->INFRASPLUS_PDF_SITFAC_TOTLINE_AVT : 0;	// InfraS add
                    if ($l->situation_percent > 0 && !empty($l->total_ht) && empty($sitFacTotLineAvt))	// InfraS change
                    {
                        $prev_progress = 0;
                        $progress = 1;
                        if (method_exists($l, 'get_prev_progress'))
                        {
                            $prev_progress = $l->get_prev_progress($object->id);
                            $progress = ($l->situation_percent - $prev_progress) / 100;
                        }

                        $result = ($sign * ($l->total_ht / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS change
                        $total+= $result;
                        // TODO check si les 3 lignes du dessous sont corrects
                        if ($l->situation_percent != 0)	$total_tva += ($sign * ($l->total_tva / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS change
                        if ($l->situation_percent != 0)	$TTotal_tva[$l->tva_tx] += ($sign * ($l->total_tva / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS change
                        if ($l->total_ttc != 0)	$total_ttc += ($sign * ($l->total_ttc / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS change
						if ($l->multicurrency_total_ht != 0)	$multicurrency_total_ht += ($sign * ($l->multicurrency_total_ht / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS add
						if ($l->multicurrency_total_ttc != 0)	$multicurrency_total_ttc += ($sign * ($l->multicurrency_total_ttc / ($l->situation_percent / 100)) * $progress) * $totalQty;	// InfraS add
                    }
					else {	// InfraS add begin
						if ($l->product_type != 9) {
										$total += $l->total_ht * $totalQty;
										$total_tva += $l->total_tva * $totalQty;
										$TTotal_tva[$l->tva_tx] += $l->total_tva * $totalQty;
										$total_ttc += $l->total_ttc * $totalQty;
										$multicurrency_total_ht += $l->multicurrency_total_ht * $totalQty;	// InfraS add
										$multicurrency_total_ttc += $l->multicurrency_total_ttc * $totalQty;	// InfraS add
						}
					}
					// InfraS add end
                }
                else
                {
					if ($l->product_type != 9) {
									$total += $l->total_ht * $totalQty;	// InfraS change
									$total_tva += $l->total_tva * $totalQty;	// InfraS change
									$multicurrency_total_ht += $l->multicurrency_total_ht * $totalQty;	// InfraS add

									if(! isset($TTotal_tva[$l->tva_tx])) {
										$TTotal_tva[$l->tva_tx] = 0;
									}
									$TTotal_tva[$l->tva_tx] += $l->total_tva * $totalQty;	// InfraS change

									$total_ttc += $l->total_ttc * $totalQty;	// InfraS change
									$multicurrency_total_ttc += $l->multicurrency_total_ttc * $totalQty;	// InfraS add
									// InfraS add begin
									$vatrate = (string) $l->tva_tx;
									if (($l->info_bits & 0x01) == 0x01) {
										$vatrate .= '*';
									}
									$vatcode = $l->vat_src_code;
									if (empty($TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'])) {
										$TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'] = 0;
									}
									$TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')] = array('vatrate' => $vatrate, 'vatcode' => $vatcode, 'amount' => $TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'] + $l->total_tva, 'base' => $total);
									// InfraS add end
					}
                }
            }
		}
		if (!$return_all) return $total;
		else return array($total, $total_tva, $total_ttc, $TTotal_tva, $total_qty, $TTotal_tva_array, $multicurrency_total_ht, $multicurrency_total_ttc);	// InfraS change
	}

	/**
	 * @param $pdf          TCPDF               PDF object
	 * @param $object       CommonObject        dolibarr object
	 * @param $line         CommonObjectLine    dolibarr object line
	 * @param $label        string
	 * @param $description  string
	 * @param $posx         float               horizontal position
	 * @param $posy         float               vertical position
	 * @param $w            float               width
	 * @param $h            float               height
	 */
	function pdf_add_total(&$pdf,&$object, &$line, $label, $description,$posx, $posy, $w, $h) {
		global $conf,$subtotal_last_title_posy,$langs;

		$subtotalDefaultTopPadding = 1;
		$subtotalDefaultBottomPadding = 1;
		$subtotalDefaultLeftPadding = 0.5;
		$subtotalDefaultRightPadding = 0.5;
        $backgroundCellHeightOffset = 0;
        $backgroundCellPosYOffset = 0;
		empty($pdf->page_largeur) ? $pdf->page_largeur = 0 : '';
		empty($pdf->marge_droite) ? $pdf->marge_droite = 0 : '';
		empty($line->total) ? $line->total = 0 : '' ;
		empty($pdf->postotalht) ? $pdf->postotalht = 0 : '' ;
		$use_multicurrency	= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1 ? 1 : 0;	// InfraS add

		$fillBackground = false;
		if(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR')
			&& function_exists('colorValidateHex')
			&& colorValidateHex(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR'))
			&& function_exists('colorStringToArray')
			&& function_exists('colorIsLight')
		){
			$fillBackground = true;


			// add here special PDF compatibility modifications
			// mais avant d'ajouter une exeption ici verifier si il ne faut pas plutôt effectuer un fix sur le PDF
			// ex : les "Anciens PDF n'utilisent pas le padding pour les texts contrairement au "nouveaux PDF" c'est pourquoi les nouveaux PDF disposent d'un affichage mieux positionné

			if(getDolGlobalString('SUBTOTAL_BACKGROUND_CELL_HEIGHT_OFFSET')){
				$backgroundCellHeightOffset = doubleval(getDolGlobalString('SUBTOTAL_BACKGROUND_CELL_HEIGHT_OFFSET'));
			}
			if(getDolGlobalString('SUBTOTAL_BACKGROUND_CELL_POS_Y_OFFSET')){
				$backgroundCellPosYOffset = doubleval(getDolGlobalString('SUBTOTAL_BACKGROUND_CELL_POS_Y_OFFSET'));
			}

			$backgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR') ,array(233, 233, 233));

			//background color
			if (!colorIsLight(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR'))) {
				$pdf->setColor('text', 255,255,255);
			}
		}

		// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
		$pdfModelUseColSystem = !empty($object->context['subtotalPdfModelInfo']->cols); // justilise une variable au cas ou le test evolue
		if($pdfModelUseColSystem){

			include_once __DIR__ . '/staticPdf.model.php';
			$staticPdfModel = new ModelePDFStatic($object->db);
			$staticPdfModel->marge_droite 	= $object->context['subtotalPdfModelInfo']->marge_droite;
			$staticPdfModel->marge_gauche 	= $object->context['subtotalPdfModelInfo']->marge_gauche;
			$staticPdfModel->page_largeur 	= $object->context['subtotalPdfModelInfo']->page_largeur;
			$staticPdfModel->page_hauteur 	= $object->context['subtotalPdfModelInfo']->page_hauteur;
			$staticPdfModel->cols 			= $object->context['subtotalPdfModelInfo']->cols;
            if ( property_exists($object->context['subtotalPdfModelInfo'], 'defaultTitlesFieldsStyle')){
				$staticPdfModel->defaultTitlesFieldsStyle 	= $object->context['subtotalPdfModelInfo']->defaultTitlesFieldsStyle;
			}
			if (property_exists($object->context['subtotalPdfModelInfo'], 'defaultContentsFieldsStyle')){
				$staticPdfModel->defaultContentsFieldsStyle = $object->context['subtotalPdfModelInfo']->defaultContentsFieldsStyle;
			}

			$staticPdfModel->prepareArrayColumnField($object, $langs);

			if(isset($staticPdfModel->cols['totalexcltax']['content']['padding'][0])){
				$subtotalDefaultTopPadding = $staticPdfModel->cols['totalexcltax']['content']['padding'][0];
			}
			if(isset($staticPdfModel->cols['totalexcltax']['content']['padding'][2])){
				$subtotalDefaultBottomPadding = $staticPdfModel->cols['totalexcltax']['content']['padding'][0];
			}

			if(isset($staticPdfModel->cols['totalincltax']['content']['padding'][0])){
				$subtotalDefaultTopPadding = $staticPdfModel->cols['totalincltax']['content']['padding'][0];
			}
			if(isset($staticPdfModel->cols['totalincltax']['content']['padding'][2])){
				$subtotalDefaultBottomPadding = $staticPdfModel->cols['totalincltax']['content']['padding'][0];
			}
		}


		$hideInnerLines = GETPOST('hideInnerLines', 'int');
		if (getDolGlobalString('SUBTOTAL_ONE_LINE_IF_HIDE_INNERLINES') && $hideInnerLines && !empty($subtotal_last_title_posy))
		{
			$posy = $subtotal_last_title_posy;
			$subtotal_last_title_posy = null;
		}

		$hidePriceOnSubtotalLines = GETPOST('hide_price_on_subtotal_lines', 'int');

		if($object->element == 'shipping' || $object->element == 'delivery')
		{
			$hidePriceOnSubtotalLines = 1;
		}

		$set_pagebreak_margin = false;
		if(method_exists('Closure','bind')) {
			$pageBreakOriginalValue = $pdf->AcceptPageBreak();
			$sweetsThief = function ($pdf) {
		    		return $pdf->bMargin ;
			};
			$sweetsThief = Closure::bind($sweetsThief, null, $pdf);

			$bMargin  = $sweetsThief($pdf);

			$pdf->SetAutoPageBreak( false );

			$set_pagebreak_margin = true;
		}


		if($line->qty==99)
			$pdf->SetFillColor(220,220,220);
		elseif ($line->qty==98)
			$pdf->SetFillColor(230,230,230);
		else
			$pdf->SetFillColor(240,240,240);

		$style = 'B';
		if (getDolGlobalString('SUBTOTAL_SUBTOTAL_STYLE')) $style = getDolGlobalString('SUBTOTAL_SUBTOTAL_STYLE');

		$pdf->SetFont('', $style, 9);


		// save curent cell padding
		$curentCellPaddinds = $pdf->getCellPaddings();
		// set cell padding with column content definition for old PDF compatibility
		$pdf->setCellPaddings($curentCellPaddinds['L'],$subtotalDefaultTopPadding, $curentCellPaddinds['R'],$subtotalDefaultBottomPadding);

		$pdf->writeHTMLCell($w, $h, $posx, $posy, $label, 0, 1, false, true, 'R', true);

//		var_dump($bMargin);
		$pageAfter = $pdf->getPage();

		//Print background
		$cell_height = $pdf->getStringHeight($w, $label);

		// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
		if($pdfModelUseColSystem){
			if ($fillBackground) {
				$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
			}
			$pdf->SetXY($object->context['subtotalPdfModelInfo']->marge_droite, $posy+$backgroundCellPosYOffset);
			$pdf->MultiCell($object->context['subtotalPdfModelInfo']->page_largeur - $object->context['subtotalPdfModelInfo']->marge_gauche - $object->context['subtotalPdfModelInfo']->marge_droite, $cell_height, '', 0, '', 1);
		}
		else{
			$pdf->SetXY($posx, $posy+$backgroundCellPosYOffset); //-1 to take into account the entire height of the row

			//background color
			if ($fillBackground)
			{
				$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
				$pdf->SetFont('', '', 9); //remove UBI for the background
				$pdf->MultiCell($pdf->page_largeur - $pdf->marge_droite, $cell_height+$backgroundCellHeightOffset, '', 0, '', 1); //+2 same of SetXY()
				$pdf->SetXY($posx, $posy); //reset position
				$pdf->SetFont('', $style, 9); //reset style
			}
			else {
				$pdf->MultiCell($pdf->page_largeur - $pdf->marge_droite, $cell_height, '', 0, '', 1);
			}
		}

		if (!$hidePriceOnSubtotalLines) {
			$total_to_print = price($line->total,0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			// InfraS add begin
			if ($use_multicurrency) {
				$total_to_print = price($line->multicurrency_total_ht,0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			}
			// InfraS add end
			if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS'))
			{
				$TTitle = TSubtotal::getAllTitleFromLine($line);
				foreach ($TTitle as &$line_title)
				{
					if (!empty($line_title->array_options['options_subtotal_nc']))
					{
						$total_to_print = ''; // TODO Gestion "Compris/Non compris", voir si on affiche une annotation du genre "NC"
						break;
					}
				}
			}

			if($total_to_print !== '') {

				if (GETPOST('hideInnerLines', 'int'))
				{
					// Dans le cas des lignes cachés, le calcul est déjà fait dans la méthode beforePDFCreation et les lignes de sous-totaux sont déjà renseignés
//					$line->TTotal_tva
//					$line->total_ht
//					$line->total_tva
//					$line->total
//					$line->total_ttc
				}
				else
				{
					//					list($total, $total_tva, $total_ttc, $TTotal_tva) = $this->getTotalLineFromObject($object, $line, '', 1);

					$TInfo = $this->getTotalLineFromObject($object, $line, '', 1);
					$TTotal_tva = $TInfo[3];
					$total_to_print = price($TInfo[0],0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
					// InfraS add begin
					if ($use_multicurrency) {
						$total_to_print = price($TInfo[6],0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
					}
					// InfraS add end

                    $line->total_ht = $TInfo[0];
					$line->total = $TInfo[0];
					if (!TSubtotal::isModSubtotalLine($line)) $line->total_tva = $TInfo[1];
					$line->total_ttc = $TInfo[2];
				}
			}

			$pdf->SetXY($pdf->postotalht, $posy);
			if($set_pagebreak_margin) $pdf->SetAutoPageBreak( $pageBreakOriginalValue , $bMargin);

			if($pdfModelUseColSystem){
				$staticPdfModel->printStdColumnContent($pdf, $posy, 'totalexcltax', $total_to_print);
				if(!empty($conf->global->PDF_PROPAL_SHOW_PRICE_INCL_TAX))
				{
					$staticPdfModel->printStdColumnContent($pdf, $posy, 'totalincltax', price($line->total_ttc,0,'',1,0,getDolGlobalInt('MAIN_MAX_DECIMALS_TOT')));
				}
			}
			else{
				$pdf->MultiCell($pdf->page_largeur-$pdf->marge_droite-$pdf->postotalht, 3, $total_to_print, 0, 'R', 0);
			}
		}
		else{
			if($set_pagebreak_margin) $pdf->SetAutoPageBreak( $pageBreakOriginalValue , $bMargin);
		}


		// restore cell padding
		$pdf->setCellPaddings($curentCellPaddinds['L'], $curentCellPaddinds['T'], $curentCellPaddinds['R'], $curentCellPaddinds['B']);

		$posy = $posy + $cell_height;
		$pdf->SetXY($posx, $posy);
		$pdf->setColor('text', 0,0,0);

	}

	/**
	 * @param $pdf          TCPDF               PDF object
	 * @param $object       CommonObject        dolibarr object
	 * @param $line         CommonObjectLine    dolibarr object line
	 * @param $label        string
	 * @param $description  string
	 * @param $posx         float               horizontal position
	 * @param $posy         float               vertical position
	 * @param $w            float               width
	 * @param $h            float               height
	 */
	function pdf_add_title(&$pdf,&$object, &$line, $label, $description,$posx, $posy, $w, $h) {

		global $db,$conf,$subtotal_last_title_posy, $hidedesc;

		empty($pdf->page_largeur) ? $pdf->page_largeur = 0 : '';
		empty($pdf->marge_droite) ? $pdf->marge_droite = 0 : '';

		// Manage background color
		$fillDescBloc = false;
		$fillBackground = false;
		if(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR')
			&& function_exists('colorValidateHex')
			&& colorValidateHex(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR'))
			&& function_exists('colorStringToArray')
		) {
			$fillBackground = true;
			$backgroundColor = colorStringToArray( getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR'),array(233, 233, 233));

			if(function_exists('colorIsLight') && !colorIsLight( getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR'))){
				$pdf->setColor('text', 255,255,255);
			}

			$backgroundCellHeightOffset = 0;
			$backgroundCellPosYOffset = 0;

			// add here special PDF compatibility modifications
			// mais avant d'ajouter une exeption ici verifier si il ne faut pas plutôt effectuer un fix sur le PDF
			// ex : les "Anciens PDF n'utilisent pas le padding pour les texts contrairement au "nouveaux PDF" c'est pourquoi les nouveaux PDF disposent d'un affichage mieux positionné


			if(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUND_CELL_HEIGHT_OFFSET')){
				$backgroundCellHeightOffset = doubleval(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUND_CELL_HEIGHT_OFFSET'));
			}

			if(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUND_CELL_POS_Y_OFFSET')){
				$backgroundCellPosYOffset = doubleval(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUND_CELL_POS_Y_OFFSET'));
			}
		}


//		$pdf->SetTextColor('text', 0, 0, 0);
		$subtotal_last_title_posy = $posy;
		$pdf->SetXY ($posx, $posy);

		$hideInnerLines = GETPOST('hideInnerLines', 'int');

		$style = ($line->qty==1) ? 'BU' : 'BUI';
		if (getDolGlobalString('SUBTOTAL_TITLE_STYLE')) $style = getDolGlobalString('SUBTOTAL_TITLE_STYLE');
		$size_title = 9;
		if (getDolGlobalString('SUBTOTAL_TITLE_SIZE')) $size_title = getDolGlobalString('SUBTOTAL_TITLE_SIZE');

		if($hideInnerLines) {
			if($line->qty==1){
				$pdf->SetFont('', $style, $size_title);
			}else{
				if (getDolGlobalString('SUBTOTAL_STYLE_TITRES_SI_LIGNES_CACHEES')) $style = getDolGlobalString('SUBTOTAL_STYLE_TITRES_SI_LIGNES_CACHEES');
				$pdf->SetFont('', $style, $size_title);
			}
		}
		else {
			if($line->qty==1)$pdf->SetFont('', $style, $size_title); //TODO if super utile
			else $pdf->SetFont('', $style, $size_title);
		}

		// save curent cell padding
		$curentCellPaddinds = $pdf->getCellPaddings();

		// set cell padding with column content definition PDF
		$pdf->setCellPaddings($curentCellPaddinds['L'],1, $curentCellPaddinds['R'],1);


		$posYBeforeTile = $pdf->GetY();
		if ($label === strip_tags($label) && $label === dol_html_entity_decode($label, ENT_QUOTES)) $pdf->MultiCell($w, $h, $label, 0, 'L', $fillDescBloc); // Pas de HTML dans la chaine
		else $pdf->writeHTMLCell($w, $h, $posx, $posy, $label, 0, 1, $fillDescBloc, true, 'J',true); // et maintenant avec du HTML


		$posYBeforeDesc = $pdf->GetY();
		if($description && !($hidedesc??0)) {
			$pdf->setColor('text', 0,0,0);
			$pdf->SetFont('', '', $size_title-1);
			$pdf->writeHTMLCell($w, $h, $posx, $posYBeforeDesc+1, $description, 0, 1, $fillDescBloc, true, 'J',true);
		}

		//background color
		if ($fillBackground)
		{
			$posYAfterDesc = $pdf->GetY();
			$cell_height = $pdf->getStringHeight($w, $label) + $backgroundCellHeightOffset;
			$bgStartX = $posx;
			$bgW = $pdf->page_largeur - $pdf->marge_droite;// historiquement ce sont ces valeurs, mais elles sont la plupart du temps vide

			// POUR LES PDF DE TYPE PDF_EVOLUTION (ceux avec les colonnes configurables)
			if(!empty($object->context['subtotalPdfModelInfo']->cols)){
				$bgStartX = $object->context['subtotalPdfModelInfo']->marge_droite;
				$bgW = $object->context['subtotalPdfModelInfo']->page_largeur - $object->context['subtotalPdfModelInfo']->marge_gauche - $object->context['subtotalPdfModelInfo']->marge_droite;
			}

			$pdf->SetFillColor($backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
			$pdf->SetXY($bgStartX, $posy + $backgroundCellPosYOffset); //-2 to take into account  the entire height of the row
			$pdf->MultiCell($bgW, $cell_height, '', 0, '', 1, 1,'','',true,0, true); //+2 same of SetXY()
			$posy = $posYAfterDesc;
			$pdf->SetXY($posx, $posy); //reset position
			$pdf->SetFont('', $style, $size_title); //reset style
			$pdf->SetColor('text', 0, 0, 0); // restore default text color;
		}

		// restore cell padding
		$pdf->setCellPaddings($curentCellPaddinds['L'], $curentCellPaddinds['T'], $curentCellPaddinds['R'], $curentCellPaddinds['B']);
	}

	function pdf_writelinedesc_ref($parameters=array(), &$object, &$action='') {
	// ultimate PDF hook O_o

		return $this->pdf_writelinedesc($parameters,$object,$action);

	}

	function isModSubtotalLine(&$parameters, &$object) {

		if(is_array($parameters)) {
			$i = & $parameters['i'];
		}
		else {
			$i = (int)$parameters;
		}

		$line = $object->lines[$i] ??'';

		if($object->element == 'shipping' || $object->element == 'delivery')
		{
			dol_include_once('/commande/class/commande.class.php');
			$line = new OrderLine($object->db);
			$line->fetch($object->lines[$i]->fk_elementdet ?? $object->lines[$i]->fk_elementdet);
		}


		if(is_object($line) && property_exists($line, 'special_code') && $line->special_code == $this->module_number && $line->product_type == 9) {
			return true;
		}

		return false;

	}

    /**
     * @param array $parameters
     * @param Object $object
     * @param string $action
     * @return void
     */
    function beforePercentCalculation ($parameters=array(), &$object, &$action='') {
        if($object->name == 'sponge' && isset($parameters['object']) && !empty($parameters['object']->lines)) {
            foreach ($parameters['object']->lines as $k => $line) {
                if(TSubtotal::isModSubtotalLine($line)) {
                    unset($parameters['object']->lines[$k]);
                }
            }
        }
    }

    /**
     * @param array $parameters
     * @param Object $object
     * @param string $action
     * @return int
     */
	function pdf_getlineqty($parameters=array(), &$object, &$action='') {
		global $conf, $hidesubdetails, $hideprices, $hookmanager;	// InfraS change

        $i = intval($parameters['i']);
        $line = isset($object->lines[$i]) ? $object->lines[$i] : null ;

		if($this->isModSubtotalLine($parameters,$object) ){
            if ($this->subtotal_sum_qty_enabled === true) {
                $line_qty = intval($line->qty);

                if ($line_qty < 50) {
                    // it's a title level (init level qty)
                    $subtotal_level = $line_qty;
                    $this->subtotal_level_cur = $subtotal_level;
                    TSubtotal::setSubtotalQtyForObject($object, $subtotal_level, 0);

                    // not show qty for title lines
                    $this->resprints = '';

                    return 1;
                } elseif ($line_qty > 50) {
                    // it's a subtotal level (show level qty and reset)
                    $subtotal_level = 100 - $line_qty;
                    $level_qty_total = $object->TSubtotalQty[$subtotal_level];
                    TSubtotal::setSubtotalQtyForObject($object, $subtotal_level, 0);

                    // show quantity sum only if it's a subtotal line (level)
                    $line_show_qty = TSubtotal::showQtyForObjectLine($line, $this->subtotal_show_qty_by_default);
                    if ($line_show_qty === false) {
                        $this->resprints = '';
                    } else {
                        $this->resprints = $level_qty_total;
                    }

                    return 1;
                } else {
                    // not show qty for text line
                    $this->resprints = '';
                    return 1;
                }
            }
            else {
                $this->resprints = ' ';

                    return 1;
            }
		} else {
            if ($this->subtotal_sum_qty_enabled === true) {

                // sum quantities by subtotal level
                if ($this->subtotal_level_cur >= 1) {
                    for ($subtotal_level = 1; $subtotal_level <= $this->subtotal_level_cur; $subtotal_level++) {
                        TSubtotal::addSubtotalQtyForObject($object, $subtotal_level, $line->qty);
                    }
                }
            }
            if (!empty($hideprices) && !empty($object->lines[$parameters['i']]) && property_exists($object->lines[$parameters['i']], 'qty')) {
                $this->resprints = $object->lines[$parameters['i']]->qty;
                return 1;
            } elseif (getDolGlobalString('SUBTOTAL_IF_HIDE_PRICES_SHOW_QTY')) {
                $hideInnerLines = GETPOST('hideInnerLines', 'int');
                //$hidesubdetails = GETPOST('hidesubdetails', 'int');	// InfraS change
                if (empty($hideInnerLines) && !empty($hidesubdetails)) {	// InfraS change
                    $this->resprints = $object->lines[$parameters['i']]->qty;
                }
            }
			// InfraS add begin
			// Cache la quantité pour les lignes standards dolibarr qui sont dans un ensemble
			else if (!empty($hidesubdetails))
			{
				// Check if a title exist for this line && if the title have subtotal
				$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
				if (!($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))) {
					$this->resprints = $object->lines[$parameters['i']]->qty;
				} else {
					$this->resprints = ' ';
					// currentcontext à modifier celon l'appel
					$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlineqty', 'currentcontext'=>'subtotal_hidesubdetails', 'i' => $i);
					return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
				}
			}
			// InfraS add end
        }

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		/** Attention, ici on peut ce retrouver avec un objet de type stdClass à cause de l'option cacher le détail des ensembles avec la notion de Non Compris (@see beforePDFCreation()) et dû à l'appel de TSubtotal::hasNcTitle() */
		if (empty($object->lines[$i]->id)) return 0; // hideInnerLines => override $object->lines et Dolibarr ne nous permet pas de mettre à jour la variable qui conditionne la boucle sur les lignes (PR faite pour 6.0)

		if(empty($object->lines[$i]->array_options)) $object->lines[$i]->fetch_optionals();

		if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		{
			if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';
				return 1;
			}
		}

		return 0;
	}

	function pdf_getlinetotalexcltax($parameters=array(), &$object, &$action='') {
	    global $conf, $hidesubdetails, $hideprices, $hookmanager, $langs;	// InfraS change

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		if($this->isModSubtotalLine($parameters,$object) ){

			// InfraS add begin
			$use_multicurrency	= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1 ? 1 : 0;
			if (!empty($parameters['infrasplus'])) {
				$hidePriceOnSubtotalLines = $object->element == 'shipping' || $object->element == 'delivery' ? 1 : GETPOST('hide_price_on_subtotal_lines', 'int');
				if (empty($hidePriceOnSubtotalLines)) {
					$total_to_print = price($object->lines[$i]->total);
					if (!empty($conf->global->SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS)) {
						$TTitle = TSubtotal::getAllTitleFromLine($object->lines[$i]);
						foreach ($TTitle as &$line_title) {
							if (!empty($line_title->array_options['options_subtotal_nc'])) {
								$total_to_print = ''; // TODO Gestion "Compris/Non compris", voir si on affiche une annotation du genre "NC"
								break;
							}
						}
					}
					if($total_to_print !== '') {
						if (GETPOST('hideInnerLines', 'int')) {
							// Dans le cas des lignes cachés, le calcul est déjà fait dans la méthode beforePDFCreation et les lignes de sous-totaux sont déjà renseignés
						}
						else {
							dol_include_once('/infraspackplus/core/lib/infraspackplus.pdf.lib.php');
							$TInfo							= $this->getTotalLineFromObject($object, $object->lines[$i], '', 1);
							$TTotal_tva						= $TInfo[3];
							$total_to_print					= pdf_InfraSPlus_price($object, $TInfo[0], $langs);
							if ($use_multicurrency) {
								$total_to_print					= pdf_InfraSPlus_price($object, $TInfo[6], $langs);
							}
							$object->lines[$i]->total		= $TInfo[0];
							$object->lines[$i]->total_ht	= $TInfo[0];
							$object->lines[$i]->total_tva	= !TSubtotal::isModSubtotalLine($object->lines[$i]) ? $TInfo[1] : $object->lines[$i]->total_tva;
							$object->lines[$i]->total_ttc	= $TInfo[2];
							$object->lines[$i]->multicurrency_total_ht	= $TInfo[6];
							$object->lines[$i]->multicurrency_total_ttc	= $TInfo[7];
						}
					}
					$this->resprints	= !empty($total_to_print) ? $total_to_print : ' ';
					return 1;
				}
			}
			// InfraS add end
			$this->resprints = ' ';


            return 1;

		}
		elseif (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS'))
		{
			if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC') )))
			{
				if (!empty($object->lines[$i]->array_options['options_subtotal_nc']))
				{
					$this->resprints = ' ';
					return 1;
				}

				$TTitle = TSubtotal::getAllTitleFromLine($object->lines[$i]);
				foreach ($TTitle as &$line_title)
				{
					if (!empty($line_title->array_options['options_subtotal_nc']))
					{
						$this->resprints = ' ';
						return 1;
					}
				}
			} elseif(in_array('pdf_getlinetotalexcltax', explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))) &&
					floatval($object->lines[$i]->total_ht) == 0
			){
				// On affiche le véritable total ht de la ligne sans le comptabilisé
				$this->resprints = price($object->lines[$i]->qty * $object->lines[$i]->subprice);
				return 1;
			}
		}
        // If commenté car : Affichage du total HT des lignes produit en doublon TICKET DA024057
//		if (GETPOST('hideInnerLines', 'int') && !empty(getDolGlobalString('SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES'))){
//		    $this->resprints = price($object->lines[$i]->total_ht,0,'',1,0,getDolGlobalString('MAIN_MAX_DECIMALS_TOT'));
//		}

		// Si la gestion C/NC est active et que je suis sur un ligne dont l'extrafield est coché
		if (
			getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') &&
			(!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i]))
		)
		{
			// alors je dois vérifier si la méthode fait partie de la conf qui l'exclue
			if (!in_array(__FUNCTION__, explode(',',  getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';

				// currentcontext à modifier celon l'appel
				$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinetotalexcltax', 'currentcontext'=>'subtotal_hide_nc', 'i' => $i);
				return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
			}
		}
		// Cache le prix pour les lignes standards dolibarr qui sont dans un ensemble
		else if (!empty($hideprices) || !empty($hidesubdetails))	// InfraS change
		{
			// Check if a title exist for this line && if the title have subtotal
			$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
			if ($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))
			{

				$this->resprints = ' ';

				// currentcontext à modifier celon l'appel
				$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinetotalexcltax', 'currentcontext'=>'subtotal_hideprices', 'i' => $i);
				return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
			}
		}
		else if (!empty($hidedetails))
		{
			$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
			if (!($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))) {
				$this->resprints = price($object->lines[$i]->total_ht,0,$langs);
				$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinetotalexcltax', 'currentcontext' => 'subtotal_hidedetails', 'i' => $i);

				return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
			}
		}

		return 0;
	}

	/**
	 * Remplace le retour de la méthode qui l'appelle par un standard 1 ou autre chose celon le hook
	 * @return int 1, 0, -1
	 */
	private function callHook(&$object, &$hookmanager, $action, $params, $defaultReturn = 1)
	{
		$reshook=$hookmanager->executeHooks('subtotalHidePrices',$params, $object, $action);
		if ($reshook < 0)
		{
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}
		elseif (empty($reshook))
		{
            if (property_exists($hookmanager, 'resprints')) $this->resprints .= $hookmanager->resprints;
		}
		else
		{
			$this->resprints = $hookmanager->resprints;

			// override return (use $this->results['overrideReturn'] or $this->resArray['overrideReturn'] in other module action_xxxx.class.php )
			if(isset($this->results['overrideReturn']))
			{
				return $this->results['overrideReturn'];
			}
		}

		return $defaultReturn;
	}

	function pdf_getlinetotalwithtax($parameters=array(), &$object, &$action='') {
		global $conf, $hidesubdetails, $hideprices, $hookmanager, $langs;	// InfraS change

		if(is_array($parameters)) $i = & $parameters['i'];	// InfraS add
		else $i = (int)$parameters;	// InfraS add

		if($this->isModSubtotalLine($parameters,$object) ){

			// InfraS add begin
			if (!empty($parameters['infrasplus'])) {
				$hidePriceOnSubtotalLines = $object->element == 'shipping' || $object->element == 'delivery' ? 1 : GETPOST('hide_price_on_subtotal_lines', 'int');
				if (empty($hidePriceOnSubtotalLines)) {
					$total_to_print = price($object->lines[$i]->total_ttc);
					if (!empty($conf->global->SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS)) {
						$TTitle = TSubtotal::getAllTitleFromLine($object->lines[$i]);
						foreach ($TTitle as &$line_title) {
							if (!empty($line_title->array_options['options_subtotal_nc'])) {
								$total_to_print = ''; // TODO Gestion "Compris/Non compris", voir si on affiche une annotation du genre "NC"
								break;
							}
						}
					}
					if($total_to_print !== '') {
						if (GETPOST('hideInnerLines', 'int')) {
							// Dans le cas des lignes cachés, le calcul est déjà fait dans la méthode beforePDFCreation et les lignes de sous-totaux sont déjà renseignés
						}
						else {
							dol_include_once('/infraspackplus/core/lib/infraspackplus.pdf.lib.php');
							$TInfo							= $this->getTotalLineFromObject($object, $object->lines[$i], '', 1);
							$TTotal_tva						= $TInfo[3];
							$total_to_print					= pdf_InfraSPlus_price($object, $TInfo[2], $langs);
							$object->lines[$i]->total		= $TInfo[0];
							$object->lines[$i]->total_ht	= $TInfo[0];
							$object->lines[$i]->total_tva	= !TSubtotal::isModSubtotalLine($object->lines[$i]) ? $TInfo[1] : $object->lines[$i]->total_tva;
							$object->lines[$i]->total_ttc	= $TInfo[2];
						}
					}
					$this->resprints	= !empty($total_to_print) ? $total_to_print : ' ';
					return 1;
				}
			}
			// InfraS add end
			$this->resprints = ' ';


            return 1;

		}

	//	if(is_array($parameters)) $i = & $parameters['i'];	// InfraS change (moved up)
	//	else $i = (int)$parameters;	// InfraS change (moved up)

		if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		{
			if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';
				return 1;
			}
		}

		return 0;
	}

	function pdf_getlineunit($parameters=array(), &$object, &$action='') {
		global $conf;

		if($this->isModSubtotalLine($parameters,$object) ){
			$this->resprints = ' ';

            return 1;

		}

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		{
			if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';
				return 1;
			}
		}

		return 0;
	}

	function pdf_getlineupexcltax($parameters=array(), &$object, &$action='') {
	    global $conf, $hidesubdetails, $hideprices, $hookmanager, $langs;	// InfraS change

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		if($this->isModSubtotalLine($parameters,$object) ) {
			$this->resprints = ' ';

            $line = $object->lines[$i];

            // On récupère les montants du bloc pour les afficher dans la ligne de sous-total
            if(TSubtotal::isSubtotal($line)) {
                $parentTitle = TSubtotal::getParentTitleOfLine($object, $line->rang);

                if(is_object($parentTitle) && empty($parentTitle->array_options)) $parentTitle->fetch_optionals();
                if(! empty($parentTitle->array_options['options_show_total_ht'])) {
                    $TTotal = TSubtotal::getTotalBlockFromTitle($object, $parentTitle);
                    $this->resprints = price($TTotal['total_unit_subprice'],0,'',1,0,getDolGlobalString('MAIN_MAX_DECIMALS_TOT') );
                }
            }


            return 1;

		}

		// Si la gestion C/NC est active et que je suis sur un ligne dont l'extrafield est coché
		if (
		getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') &&
		(!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i]))
		)
		{
		    // alors je dois vérifier si la méthode fait partie de la conf qui l'exclue
		    if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
		    {
		        $this->resprints = ' ';

		        // currentcontext à modifier celon l'appel
		        $params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlineupexcltax', 'currentcontext'=>'subtotal_hide_nc', 'i' => $i);
		        return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)

		    }
		}
		// Cache le prix pour les lignes standards dolibarr qui sont dans un ensemble
		else if (!empty($hideprices) || !empty($hidesubdetails))	// InfraS change
		{

		    // Check if a title exist for this line && if the title have subtotal
		    $lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
		    if ($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))
		    {

		        $this->resprints = ' ';

		        // currentcontext à modifier celon l'appel
		        $params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlineupexcltax', 'currentcontext'=>'subtotal_hideprices', 'i' => $i);
		        return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
		    }
		} elseif (!empty($hidedetails))
		{
			$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
			if (!($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))) {
				$this->resprints = price($object->lines[$i]->subprice,0,$langs);
				$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlineupexcltax', 'currentcontext' => 'subtotal_hidedetails', 'i' => $i);

				return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
			}
		}

		return 0;
	}

	function pdf_getlineremisepercent($parameters=array(), &$object, &$action='') {
	    global $conf, $hidesubdetails, $hideprices, $hookmanager, $langs;	// InfraS change

        if(is_array($parameters)) $i = & $parameters['i'];
        else $i = (int) $parameters;

		if($this->isModSubtotalLine($parameters,$object) ) {
			$this->resprints = ' ';

            $line = $object->lines[$i];

            // Affichage de la remise
            if(TSubtotal::isSubtotal($line)) {
                if ($parentTitle = TSubtotal::getParentTitleOfLine($object, $line->rang)) {

					if(empty($parentTitle->array_options)) $parentTitle->fetch_optionals();
					if(! empty($parentTitle->array_options['options_show_reduc'])) {
						$TTotal = TSubtotal::getTotalBlockFromTitle($object, $parentTitle);
						$this->resprints = price((1-$TTotal['total_ht'] / $TTotal['total_subprice'])*100, 0, '', 1, 2, 2).'%';
					}
				}
            }


            return 1;

		}
		elseif (!empty($hideprices) || !empty($hidesubdetails)	// InfraS change
		        || (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		        )
		    {
		        if (!empty($hideprices) || !in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
		        {
					// Check if a title exist for this line && if the title have subtotal
					$lineTitle = TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang);
					if ($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true)) {
						$this->resprints = ' ';
						return 1;
					}
		        }
		    }
        elseif (!empty($hidedetails))
		{
			$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
			if (!($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))) {
				$this->resprints = dol_print_reduction($object->lines[$i]->remise_percent, $langs);
				return 1;
			}
		}

		return 0;
	}

	function pdf_getlineupwithtax($parameters=array(), &$object, &$action='') {
		global $conf, $hidesubdetails, $hideprices;	// InfraS change

		if($this->isModSubtotalLine($parameters,$object) ){
			$this->resprints = ' ';


            return 1;

		}

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		if (!empty($hideprices) || !empty($hidesubdetails)	// InfraS change
				|| (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		)
		{
			if (!empty($hideprices) || !in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';
				return 1;
			}
		}

		return 0;
	}

	function pdf_getlinevatrate($parameters=array(), &$object, &$action='') {
	    global $conf, $hidesubdetails, $hideprices, $hookmanager;	// InfraS change

//		// Dans le cas des notes de frais report ne pas traiter
//		// TODO : peut être faire l'inverse : limiter à certains elements plutot que le faire pour tous ... à voir si un autre PB du genre apparait.
		$TContext	= explode(':', $parameters['context']);	// InfraS add
		if (in_array('expensereportcard', $TContext))	return 0;	// InfraS add
		// InfraS change begin
		// Move up from line 2175
		if (is_array($parameters)) {
			$i = & $parameters['i'];
		} else {
			$i = (int)$parameters;
		}

		if($this->isModSubtotalLine($parameters,$object)){
			// Vérifie le taux de TVA des lignes comprises entre un Titre et un Sous-total de même niveau.
			$tva_unique = TSubtotal::getCommonVATRate($object, $object->lines[$i]);
			// Si un taux unique est trouvé, on l'affiche dans la colonne TVA
			if (!empty(getDolGlobalString('SUBTOTAL_SHOW_TVA_ON_SUBTOTAL_LINES_ON_ELEMENTS')) && $tva_unique !== false
				&& (!getDolGlobalInt('SUBTOTAL_LIMIT_TVA_ON_CONDENSED_BLOCS') || (getDolGlobalInt('SUBTOTAL_LIMIT_TVA_ON_CONDENSED_BLOCS')
				&& ((!empty($object->lines[$i]->array_options['options_print_as_list']) && $object->lines[$i]->array_options['options_print_as_list'] > 0)
				|| (!empty($object->lines[$i]->array_options['options_print_condensed']) && $object->lines[$i]->array_options['options_print_condensed'] > 0))))) {
				$this->resprints = vatrate($tva_unique, true);
			} else {
				$this->resprints = '';
			}
			return 1;
		}
		// InfraS change end
//		if(is_array($parameters)) $i = & $parameters['i']; // InfraS move up
//		else $i = (int)$parameters; // InfraS move up

		if (empty($object->lines[$i])) return 0; // hideInnerLines => override $object->lines et Dolibarr ne nous permet pas de mettre à jour la variable qui conditionne la boucle sur les lignes (PR faite pour 6.0)

		$object->lines[$i]->fetch_optionals();
		// Si la gestion C/NC est active et que je suis sur un ligne dont l'extrafield est coché
		if (
		getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') &&
		(!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i]))
		)
		{
		    // alors je dois vérifier si la méthode fait partie de la conf qui l'exclue
		    if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
		    {
		        $this->resprints = ' ';

		        // currentcontext à modifier celon l'appel
		        $params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinevatrate', 'currentcontext'=>'subtotal_hide_nc', 'i' => $i);
		        return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
		    }
		}
		// Cache le prix pour les lignes standards dolibarr qui sont dans un ensemble
		else if (!empty($hideprices) || !empty($hidesubdetails))	// InfraS change
		{

		    // Check if a title exist for this line && if the title have subtotal
		    $lineTitle = TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang);
		    if ($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))
		    {

		        $this->resprints = ' ';

		        // currentcontext à modifier celon l'appel
		        $params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinevatrate', 'currentcontext'=>'subtotal_hideprices', 'i' => $i);
		        return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
		    }
		}
        elseif (!empty($hidedetails))
		{
			$lineTitle = (!empty($object->lines[$i])) ? TSubtotal::getParentTitleOfLine($object, $object->lines[$i]->rang): '';
			if (!($lineTitle && TSubtotal::titleHasTotalLine($object, $lineTitle, true))) {
				$this->resprints = vatrate($object->lines[$i]->tva_tx, true);
				$params = array('parameters' => $parameters, 'currentmethod' => 'pdf_getlinevatrate', 'currentcontext'=>'subtotal_hidedetails', 'i' => $i);
				return $this->callHook($object, $hookmanager, $action, $params); // return 1 (qui est la valeur par défaut) OU -1 si erreur OU overrideReturn (contient -1 ou 0 ou 1)
			}
		}

		return 0;
	}

	function pdf_getlineprogress($parameters=array(), &$object, &$action) {
		global $conf;

		if($this->isModSubtotalLine($parameters,$object) ){
			$this->resprints = ' ';
            return 1;

		}

		if(is_array($parameters)) $i = & $parameters['i'];
		else $i = (int)$parameters;

		if (getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && (!empty($object->lines[$i]->array_options['options_subtotal_nc']) || TSubtotal::hasNcTitle($object->lines[$i])) )
		{
			if (!in_array(__FUNCTION__, explode(',', getDolGlobalString('SUBTOTAL_TFIELD_TO_KEEP_WITH_NC'))))
			{
				$this->resprints = ' ';
				return 1;
			}
		}

		return 0;
	}

	function add_numerotation(&$object) {
		global $conf;

		if(getDolGlobalString('SUBTOTAL_USE_NUMEROTATION')) {

			$TLineTitle = $TTitle = $TLineSubtotal = array();
			$prevlevel = 0;
			dol_include_once('/subtotal/class/subtotal.class.php');

			foreach($object->lines as $k=>&$line)
			{
				if ($line->id > 0 && $this->isModSubtotalLine($k, $object) && $line->qty <= 10)
				{
					$TLineTitle[] = &$line;
				}
				else if ($line->id > 0 && TSubtotal::isSubtotal($line) && !$conf->global->SUBTOTAL_USE_NEW_FORMAT)		// InfraS change
				{
					$TLineSubtotal[] = &$line;
				}

			}

			if (!empty($TLineTitle))
			{
				$TTitleNumeroted = $this->formatNumerotation($TLineTitle);

				$TTitle = $this->getTitlesFlatArray($TTitleNumeroted);

				if (!empty($TLineSubtotal))
				{
					foreach ($TLineSubtotal as &$stLine)
					{
						$parentTitle = TSubtotal::getParentTitleOfLine($object, $stLine->rang);
						if (!empty($parentTitle) && array_key_exists($parentTitle->id, $TTitle))
						{
							$stLine->label = $TTitle[$parentTitle->id]['numerotation'] . ' ' . $stLine->label;
						}
					}
				}
			}
		}

	}

	private function getTitlesFlatArray($TTitleNumeroted = array(), &$resArray = array())
	{
		if (is_array($TTitleNumeroted) && !empty($TTitleNumeroted))
		{
			foreach ($TTitleNumeroted as $tn)
			{
				$resArray[$tn['line']->id] = $tn;
				if (array_key_exists('children', $tn))
				{
					$this->getTitlesFlatArray($tn['children'], $resArray);
				}

			}
		}

		return $resArray;
	}

	/**
	 * TODO ne gère pas encore la numération des lignes "Totaux"
	 * @param CommonObjectLine[] $TLineTitle
	 * @param string             $line_reference
	 * @param int                $level
	 * @param int                $prefix_num
	 * @return array
	 */
	private function formatNumerotation(&$TLineTitle, $line_reference='', $level=1, $prefix_num=0)
	{
		$TTitle = array();

		$i=1;
		$j=0;
		$TLineElementsWithoutLabel = array(
			// liste de lignes n'utilisant pas le champ `label` mais le champ `description` (`desc`)
			'facture_fourn_det',
			'commande_fournisseurdet',
		);
		foreach ($TLineTitle as $k => &$line)
		{
			if (!empty($line_reference) && $line->rang <= $line_reference->rang) continue;
			if (!empty($line_reference) && $line->qty <= $line_reference->qty) break;

			if ($line->qty == $level)
			{
				$TTitle[$j]['numerotation'] = ($prefix_num == 0) ? $i : $prefix_num.'.'.$i;
				//var_dump('Prefix == '.$prefix_num.' // '.$line->desc.' ==> numerotation == '.$TTitle[$j]['numerotation'].'   ###    '.$line->qty .'=='. $level);
				if (empty($line->label) && (
					in_array($line->element, $TLineElementsWithoutLabel)
					)
				) {
					$line->label = !empty($line->desc) ? $line->desc : $line->description;
					$line->desc = $line->description = '';
				}

				$line->label = $TTitle[$j]['numerotation'].' '.$line->label;
				$TTitle[$j]['line'] = &$line;

				$deep_level = $line->qty;
				do {
					$deep_level++;
					$TTitle[$j]['children'] = $this->formatNumerotation($TLineTitle, $line, $deep_level, $TTitle[$j]['numerotation']);
				} while (empty($TTitle[$j]['children']) && $deep_level <= 10); // Exemple si un bloc Titre lvl 1 contient pas de sous lvl 2 mais directement un sous lvl 5
				// Rappel on peux avoir jusqu'a 10 niveau de titre

				$i++;
				$j++;
			}
		}

		return $TTitle;
	}

	function setDocTVA(&$pdf, &$object) {

		$hidesubdetails = GETPOST('hidesubdetails', 'int');	// InfraS change

		if(empty($hidesubdetails)) return false;	// InfraS change

		// TODO can't add VAT to document without lines... :-/

		return true;
	}

	function beforePDFCreation($parameters=array(), &$object, &$action)
	{
		/**
		 * @var $pdf    TCPDF
		 */
		global $pdf,$conf, $langs;

		if (TSubtotal::showQtyForObject($object) === true) {
			$this->subtotal_sum_qty_enabled = true;
			$this->subtotal_show_qty_by_default = true;
		}

		// InfraS change begin
		$TContext	= explode(':', $parameters['context']);
		if (in_array('pdfgeneration', $TContext)) {
			// for compatibility dolibarr < 15
			if(!empty($object->context)){ $object->context = array(); }
			$object->context['subtotalPdfModelInfo'] = new stdClass(); // see defineColumnFiel method in this class
			$object->context['subtotalPdfModelInfo']->cols = false;
		}
		if (in_array('propalcard', $TContext) || in_array('ordercard', $TContext) || in_array('invoicecard', $TContext) || in_array('supplier_proposalcard', $TContext) || in_array('ordersuppliercard', $TContext) || in_array('invoicesuppliercard', $TContext)) {
			// var_dump($object->lines);
			dol_include_once('/subtotal/class/subtotal.class.php');

			$i = 0;
			if(isset($parameters['i'])) {
				$i = $parameters['i'];
			}

			foreach($parameters as $key=>$value) {
				${$key} = $value;
			}

			$this->setDocTVA($pdf, $object);

			$this->add_numerotation($object);

			foreach($object->lines as $k => &$l) {
				if(TSubtotal::isSubtotal($l)) {
					$parentTitle = TSubtotal::getParentTitleOfLine($object, $l->rang);
					if(is_object($parentTitle) && empty($parentTitle->array_options)) $parentTitle->fetch_optionals();
					if(! empty($parentTitle->id) && ! empty($parentTitle->array_options['options_show_reduc'])) {
						$l->remise_percent = 100;    // Affichage de la réduction sur la ligne de sous-total
					}
				}


				// Pas de hook sur les colonnes du PDF expédition, on unset les bonnes variables
				if(($object->element == 'shipping' || $object->element == 'delivery') && $this->isModSubtotalLine($k, $object))
				{
					$l->qty = $l->qty_asked;
					unset($l->qty_asked, $l->qty_shipped, $l->volume, $l->weight);
				}
			}

			$hideInnerLines = GETPOST('hideInnerLines', 'int');
			$hidesubdetails = GETPOST('hidesubdetails', 'int');	// InfraS change

			if (!empty($hideInnerLines)) { // si c une ligne de titre	// InfraS change
				$fk_parent_line=0;
				$TLines =array();

				$original_count=count($object->lines);
				$TTvas = array(); // tableau de tva

				foreach($object->lines as $k=>&$line)
				{
					// to keep compatibility with supplier order and old versions (rowid was replaced with id in fetch lines method)
					if ($line->id > 0) {
						$line->rowid = $line->id;
					}

					if($line->product_type==9 && $line->rowid>0)
					{
						$fk_parent_line = $line->rowid;

						// Fix tk7201 - si on cache le détail, la TVA est renseigné au niveau du sous-total, l'erreur c'est s'il y a plusieurs sous-totaux pour les même lignes, ça va faire la somme
						if(TSubtotal::isSubtotal($line))
						{
							/*$total = $this->getTotalLineFromObject($object, $line, '');

							$line->total_ht = $total;
							$line->total = $total;
							*/
							//list($total, $total_tva, $total_ttc, $TTotal_tva) = $this->getTotalLineFromObject($object, $line, '', 1);

							$TInfo = $this->getTotalLineFromObject($object, $line, '', 1);

							if (TSubtotal::getNiveau($line) == 1) {	// InfraS change
								// InfraS add begin
								$line->TTotal_tva = $TInfo[3];
								$line->TTotal_tva_array = $TInfo[5];
							}
							// InfraS add end
							$line->total_ht = $TInfo[0];
							$line->total_tva = $TInfo[1];
							$line->total = $line->total_ht;
							$line->total_ttc = $TInfo[2];

	//                        $TTitle = TSubtotal::getParentTitleOfLine($object, $line->rang);
	//                        $parentTitle = array_shift($TTitle);
	//                        if(! empty($parentTitle->id) && ! empty($parentTitle->array_option['options_show_total_ht'])) {
	//                            exit('la?');
	//                            $line->remise_percent = 100;    // Affichage de la réduction sur la ligne de sous-total
	//                            $line->update();
	//                        }
						}
	//                    if(TSub)

					}

					if ($hideInnerLines)
					{
						// InfraS add begin
						$hasParentTitle = TSubtotal::getParentTitleOfLine($object, $line->rang);
						if (empty($hasParentTitle) && empty(TSubtotal::isModSubtotalLine($line))) {	// cette ligne n'est pas dans un titre => on l'affiche
							$TLines[] = $line;
						}
						// InfraS add end
					    if(getDolGlobalString('SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES'))
						{
							if($line->tva_tx != '0.000' && $line->product_type!=9){

								// on remplit le tableau de tva pour substituer les lignes cachées
								if (!empty($TTvas[$line->tva_tx]['total_tva'])) $TTvas[$line->tva_tx]['total_tva'] += $line->total_tva;
								if (!empty($TTvas[$line->tva_tx]['total_ht'])) $TTvas[$line->tva_tx]['total_ht'] += $line->total_ht;
								if (!empty($TTvas[$line->tva_tx]['total_ttc'])) $TTvas[$line->tva_tx]['total_ttc'] += $line->total_ttc;
							}
							if($line->product_type==9 && $line->rowid>0)
							{
								//Cas où je doit cacher les produits et afficher uniquement les sous-totaux avec les titres
								// génère des lignes d'affichage des montants HT soumis à tva
								$nbtva = count($TTvas);
								if(!empty($nbtva)){
									foreach ($TTvas as $tx =>$val){
										$copyL = clone $line; // la variable $coyyL était nommé $l, j' l'ai renommé car probleme de référence d'instance dans le clone
										$copyL->product_type = 1;
										$copyL->special_code = '';
										$copyL->qty = 1;
										$copyL->desc = $langs->trans('AmountBeforeTaxesSubjectToVATX%', $langs->transnoentitiesnoconv('VAT'), price($tx));
										$copyL->tva_tx = $tx;
										$copyL->total_ht = $val['total_ht'];
										$copyL->total_tva = $val['total_tva'];
										$copyL->total = $line->total_ht;
										$copyL->total_ttc = $val['total_ttc'];
										$TLines[] = $copyL;
										array_shift($TTvas);
								   }
								}

								// ajoute la ligne de sous-total
								$TLines[] = $line;
							}
						} else {

							if($line->product_type==9 && $line->rowid>0)
							{
								// ajoute la ligne de sous-total
								$TLines[] = $line;
							}
						}


					}
					elseif (!empty($hidesubdetails))	// InfraS change
					{
						$TLines[] = $line; //Cas où je cache uniquement les prix des produits
					}

					if ($line->product_type != 9) { // jusqu'au prochain titre ou total
					//$line->fk_parent_line = $fk_parent_line;

					}

					/*if($hideTotal) {
						$line->total = 0;
						$line->subprice= 0;
					}*/

				}

				// cas incongru où il y aurait des produits en dessous du dernier sous-total
				$nbtva = count($TTvas);
				if(!empty($nbtva) && !empty($hideInnerLines) && getDolGlobalString('SUBTOTAL_REPLACE_WITH_VAT_IF_HIDE_INNERLINES'))	// InfraS change
				{
					foreach ($TTvas as $tx =>$val){
						$l = clone $line;
						$l->product_type = 1;
						$l->special_code = '';
						$l->qty = 1;
						$l->desc = $langs->trans('AmountBeforeTaxesSubjectToVATX%', $langs->transnoentitiesnoconv('VAT'), price($tx));
						$l->tva_tx = $tx;
						$l->total_ht = $val['total_ht'];
						$l->total_tva = $val['total_tva'];
						$l->total = $line->total_ht;
						$l->total_ttc = $val['total_ttc'];
						$TLines[] = $l;
						array_shift($TTvas);
					}
				}

				global $nblignes;
				$nblignes=count($TLines);

				$object->lines = $TLines;

				if($i>count($object->lines)) {
					$this->resprints = '';
					return 0;
				}
			}
		}
		// InfraS change end
		return 0;
	}

	function pdf_writelinedesc($parameters=array(), &$object, &$action)
	{
		/**
		 * @var $pdf    TCPDF
		 */
		global $pdf,$conf;

		foreach($parameters as $key=>$value) {
			${$key} = $value;
		}

		// même si le foreach du dessu fait ce qu'il faut, l'IDE n'aime pas
		$outputlangs = $parameters['outputlangs'];
		$i = $parameters['i'];
		$posx = $parameters['posx'];
		$h = $parameters['h'];
		$w = $parameters['w'];

		$hideInnerLines = GETPOST('hideInnerLines', 'int');
		$hidesubdetails = GETPOST('hidesubdetails', 'int');	// InfraS change

		if($this->isModSubtotalLine($parameters,$object) ){

				global $hidesubdetails, $hideprices;	// InfraS change
				if(!empty($hideprices) || !empty($hidesubdetails)) {	// InfraS change
					foreach($object->lines as &$line) {
						if($line->fk_product_type!=9) $line->fk_parent_line = -1;
					}
				}

				$line = &$object->lines[$i];

				// Unset on Dolibarr < 20.0
				if($object->element == 'delivery' && ! empty($object->commande->expeditions[$line->fk_elementdet])) unset($object->commande->expeditions[$line->fk_elementdet]);
				// Unset on Dolibarr >= 20.0
				if($object->element == 'delivery' && ! empty($object->commande->expeditions[$line->fk_elementdet])) unset($object->commande->expeditions[$line->fk_elementdet]);

				$margin = $pdf->getMargins();
				if(!empty($margin) && $line->info_bits>0) { // PAGE BREAK
					$pdf->addPage();
					$posy = $margin['top'];
				}

				$label = $line->label;
				$description= !empty($line->desc) ? $outputlangs->convToOutputCharset($line->desc) : $outputlangs->convToOutputCharset($line->description);

				if(empty($label)) {
					$label = $description;
					$description='';
				}

				if($line->qty>90) {
				if (getDolGlobalString('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL')) {
                        $label .= ' '.$this->getTitle($object, $line);
                    }
					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						/**
						 * TCPDF::startTransaction() committe la transaction en cours s'il y en a une,
						 * ce qui peut être problématique. Comme TCPDF::rollbackTransaction() ne fait rien
						 * si aucune transaction n'est en cours, on peut y faire appel sans problème pour revenir
						 * à l'état d'origine.
						 */
						$pdf->rollbackTransaction(true);
						$pdf->startTransaction();

						$pageBefore = $pdf->getPage();
					}


					// FIX DA024845 : Le module sous total amène des erreurs dans les sauts de page lorsque l'on arrive tout juste en bas de page.
					$heightForFooter = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10) + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 12 : 22); // Height reserved to output the footer (value include bottom margin)
					if($pdf->getPageHeight() - $posy - $heightForFooter < 8){
						$pdf->addPage('', '', true);
						$posy = $pdf->GetY();
					}


					$this->pdf_add_total($pdf,$object, $line, $label, $description,$posx, $posy, $w, $h);

					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						$pageAfter = $pdf->getPage();

						if($pageAfter > $pageBefore) {
							//print "ST $pageAfter>$pageBefore<br>";
							$pdf->rollbackTransaction(true);
							$pdf->addPage('', '', true);
							$posy = $pdf->GetY();
							$this->pdf_add_total($pdf, $object, $line, $label, $description, $posx, $posy, $w, $h);
							$posy = $pdf->GetY();
							//print 'add ST'.$pdf->getPage().'<br />';

						}
						else    // No pagebreak
						{
							$pdf->commitTransaction();
						}
					}

					// On delivery PDF, we don't want quantities to appear and there are no hooks => setting text color to background color;
					if($object->element == 'delivery')
					{
						switch($line->qty)
						{
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
				}
				else if ($line->qty < 10) {
					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						/**
						 * TCPDF::startTransaction() committe la transaction en cours s'il y en a une,
						 * ce qui peut être problématique. Comme TCPDF::rollbackTransaction() ne fait rien
						 * si aucune transaction n'est en cours, on peut y faire appel sans problème pour revenir
						 * à l'état d'origine.
						 */
						$pdf->rollbackTransaction(true);
						$pdf->startTransaction();

						$pageBefore = $pdf->getPage();
					}

					$this->pdf_add_title($pdf,$object, $line, $label, $description,$posx, $posy, $w, $h);

					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						$pageAfter = $pdf->getPage();

						if($pageAfter > $pageBefore) {
							//print "ST $pageAfter>$pageBefore<br>";
							$pdf->rollbackTransaction(true);
							$pdf->addPage('', '', true);
							$posy = $pdf->GetY();
							$this->pdf_add_title($pdf, $object, $line, $label, $description, $posx, $posy, $w, $h);
							$posy = $pdf->GetY();
							//print 'add ST'.$pdf->getPage().'<br />';

						}
						else    // No pagebreak
						{
							$pdf->commitTransaction();
						}
					}

					if($object->element == 'delivery')
					{
						$pdf->SetTextColor(255,255,255);
					}

					$posy = $pdf->GetY();
					return 1;
				} elseif(!empty($margin)) {

					$labelproductservice = pdf_getlinedesc($object, $i, $outputlangs, $parameters['hideref'], $parameters['hidedesc'], $parameters['issupplierline']);

					$labelproductservice = preg_replace('/(<img[^>]*src=")([^"]*)(&amp;)([^"]*")/', '\1\2&\4', $labelproductservice, -1, $nbrep);

					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						/**
						 * TCPDF::startTransaction() committe la transaction en cours s'il y en a une,
						 * ce qui peut être problématique. Comme TCPDF::rollbackTransaction() ne fait rien
						 * si aucune transaction n'est en cours, on peut y faire appel sans problème pour revenir
						 * à l'état d'origine.
						 */
						$pdf->rollbackTransaction(true);
						$pdf->startTransaction();

						$pageBefore = $pdf->getPage();
					}

					$pdf->writeHTMLCell($parameters['w'], $parameters['h'], $parameters['posx'], $posy, $outputlangs->convToOutputCharset($labelproductservice), 0, 1, false, true, 'J', true);

					if(!empty(getDolGlobalString('SUBTOTAL_DISABLE_FIX_TRANSACTION'))) {
						$pageAfter = $pdf->getPage();

						if($pageAfter > $pageBefore) {
							//print "ST $pageAfter>$pageBefore<br>";
							$pdf->rollbackTransaction(true);
							$pdf->addPage('', '', true);
							$posy = $pdf->GetY();
							$pdf->writeHTMLCell($parameters['w'], $parameters['h'], $parameters['posx'], $posy, $outputlangs->convToOutputCharset($labelproductservice), 0, 1, false, true, 'J', true);
							$posy = $pdf->GetY();
							//print 'add ST'.$pdf->getPage().'<br />';

						}
						else    // No pagebreak
						{
							$pdf->commitTransaction();
						}
					}

					return 1;
				}

			return 0;
		}
		elseif (empty($object->lines[$parameters['i']]))
		{
			$this->resprints = -1;
		}

        return 0;
	}

	/**
	 * Permet de récupérer le titre lié au sous-total
	 *
	 * @return string
	 */
	function getTitle(&$object, &$currentLine)
	{
		$res = '';

		foreach ($object->lines as $line)
		{
			if ($line->id == $currentLine->id) break;

			$qty_search = 100 - $currentLine->qty;

			if ($line->product_type == 9 && $line->special_code == $this->module_number && $line->qty == $qty_search)
			{
				$res = ($line->label) ? $line->label : (($line->description) ? $line->description : $line->desc);
			}
		}

		return $res;
	}

	/**
	 * @param $parameters   array
	 * @param $object       CommonObject
	 * @param $action       string
	 * @param $hookmanager  HookManager
	 * @return int
	 */
	function printObjectLine ($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user, $db, $bc, $usercandelete, $toselect, $inputalsopricewithtax;	// InfraS change

		$lineLabel = "";
		$num = &$parameters['num'];
		$line = &$parameters['line'];
		$i = &$parameters['i'];
		$fa = getDolGlobalString('MAIN_FONTAWESOME_ICON_STYLE', 'fa');	// InfraS add

		$var = &$parameters['var'];

		$contexts = explode(':',$parameters['context']);
		if($parameters['currentcontext'] === 'paiementcard') return 0;
		$originline = null;

        $newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];

		$createRight = $user->hasRight($object->element, 'creer');
		if($object->element == 'facturerec' )
		{
			$object->statut = 0; // hack for facture rec
			$createRight = $user->hasRight('facture', 'creer');
		}
		elseif($object->element == 'order_supplier' )
		{
		    $createRight = $user->hasRight('fournisseur', 'commande', 'creer');
		}
		elseif($object->element == 'invoice_supplier' )
		{
		    $createRight = $user->hasRight('fournisseur', 'facture', 'creer');
		}
		elseif($object->element == 'commande' && in_array('ordershipmentcard', $contexts))
		{
			// H4cK 4n0nYm0u$-style : $line n'est pas un objet instancié mais provient d'un fetch_object d'une requête SQL
			$line->id = $line->rowid;
			$line->product_type = $line->type;
		}
		elseif($object->element == 'shipping' || $object->element == 'delivery')
		{
			if(empty($line->origin_line_id) && (! empty($line->fk_elementdet || ! empty($line->fk_elementdet))))
			{
				$line->origin_line_id = $line->fk_elementdet ?? $line->fk_elementdet;
			}

			$originline = new OrderLine($db);
			$originline->fetch($line->fk_elementdet ?? $line->fk_elementdet);

			foreach(get_object_vars($line) as $property => $value)
			{
				if(empty($originline->{ $property }))
				{
					$originline->{ $property } = $value;
				}
			}

			$line = $originline;
		}
 		if($object->element=='facture')$idvar = 'facid';
        else $idvar='id';
		$isOuvrage	= !empty(isModEnabled('ouvrage')) && Ouvrage::isOuvrage($line) ? 1 : 0;	// InfraS add
		if($line->special_code!=$this->module_number || $line->product_type!=9) {
			if ($object->statut == 0  && $createRight && getDolGlobalString('SUBTOTAL_ALLOW_DUPLICATE_LINE') && $object->element !== 'invoice_supplier' && empty($isOuvrage))	// InfraS change
            {
                if(empty($line->fk_prev_id)) $line->fk_prev_id = null;
                if(($object->element != 'shipping' && $object->element != 'delivery')&& !(TSubtotal::isModSubtotalLine($line)) && ( $line->fk_prev_id === null ) && !($action == "editline" && GETPOST('lineid', 'int') == $line->id)) {
                    echo '<a name="duplicate-'.$line->id.'" href="' . $_SERVER['PHP_SELF'] . '?' . $idvar . '=' . $object->id . '&action=duplicate&lineid=' . $line->id . '&token='.$newToken.'"><i class="'.$fa.' fa-clone" aria-hidden="true"></i></a>'; // InfraS change

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
		}
		else if (in_array('invoicecard',$contexts) || in_array('invoicesuppliercard',$contexts) || in_array('propalcard',$contexts) || in_array('supplier_proposalcard',$contexts) || in_array('ordercard',$contexts) || in_array('ordersuppliercard',$contexts) || in_array('invoicereccard',$contexts))
        {

			if(empty($line->description)) $line->description = $line->desc;

            $TNonAffectedByMarge = array('order_supplier', 'invoice_supplier', 'supplier_proposal');
            $affectedByMarge = in_array($object->element, $TNonAffectedByMarge) ? 0 : 1;
			$colspan = 5;
			if($object->element == 'order_supplier')  $colspan = 6;
			if($object->element == 'invoice_supplier') $colspan = 4;	// InfraS change
			if($object->element == 'supplier_proposal') $colspan = 3;

			if(DOL_VERSION > 16.0 && empty(getDolGlobalString('MAIN_NO_INPUT_PRICE_WITH_TAX'))) $colspan++; // Ajout de la colonne PU TTC
			elseif(!empty($inputalsopricewithtax))	 $colspan++;	// InfraS add

			if($object->element == 'facturerec' ) $colspan = 5;

			if(isModEnabled('multicurrency') && ($object->multicurrency_code != $conf->currency)) {
				$colspan++; // Colonne PU Devise
			}
			if($object->element == 'commande' && $object->statut < 3 && isModEnabled('shippableorder')) $colspan++;
			$margins_hidden_by_module = !isModEnabled('affmarges') ? false : !($_SESSION['marginsdisplayed']);
			if(isModEnabled('margin') && !$margins_hidden_by_module) $colspan++;
			if(isModEnabled('margin') && getDolGlobalString('DISPLAY_MARGIN_RATES') && !$margins_hidden_by_module && $affectedByMarge > 0) $colspan++;
			if(isModEnabled('margin') && getDolGlobalString('DISPLAY_MARK_RATES') && !$margins_hidden_by_module && $affectedByMarge > 0) $colspan++;
			if($object->element == 'facture' && getDolGlobalString('INVOICE_USE_SITUATION') && $object->type == Facture::TYPE_SITUATION) $colspan++;
			if(getDolGlobalString('PRODUCT_USE_UNITS')) $colspan++;
			// Compatibility module showprice
			if(isModEnabled('showprice')) $colspan++;
			/* Titre */


			// HTML 5 data for js
            $data = $this->_getHtmlData($parameters, $object, $action, $hookmanager);

            // Prepare CSS class
            $class													= '';
            if (!empty(getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT')))		$class	.= ' newSubtotal';
            if ($line->qty == 1)									$class	.= ' subtitleLevel1';	// Title level 1
            elseif ($line->qty == 2)								$class	.= ' subtitleLevel2';	// Title level 2
            elseif ($line->qty > 2 && $line->qty < 10)				$class	.= ' subtitleLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 99)								$class	.= ' subtotalLevel1';	// Sub-total level 1
            elseif ($line->qty == 98)								$class	.= ' subtotalLevel2';	// Sub-total level 2
            elseif ($line->qty > 90 && $line->qty < 98)				$class	.= ' subtotalLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 50)								$class	.= ' subtotalText';      // Free text
			?>
			<!-- actions_subtotal.class.php line <?php echo __LINE__; ?> -->
			<tr class="oddeven <?php echo $class; ?>" <?php echo $data; ?> rel="subtotal" id="row-<?php echo $line->id ?>" style="<?php
					if (!empty(getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT')))
					{
						// InfraS change begin
						if ($line->qty <= 99 && $line->qty >= 91) {
							$subtotalBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (20 - (109 - $line->qty)) / 10;
							print 'background:rgba('.implode(',', $subtotalBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty >= 1 && $line->qty <= 9) {
							$titleBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (11 - $line->qty) / 10;
							print 'background:rgba('.implode(',', $titleBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty == 50) {	// Free text
							print '';
						}
						// InfraS change end
					}
					else
					{
						if($line->qty==99) print 'background:#ddffdd';          // Sub-total level 1
						else if($line->qty==98) print 'background:#ddddff;';    // Sub-total level 2
						else if($line->qty==2) print 'background:#eeeeff; ';    // Title level 2
						else if($line->qty==50) print '';                       // Free text
						else print 'background:#eeffee;' ;                      // Title level 1 and 3 to 9
					}

			?>;">

				<?php if(getDolGlobalString('MAIN_VIEW_LINE_NUMBER')) { ?>
				<td class="linecolnum"><?php echo $i + 1; ?></td>
				<?php } ?>

				<?php
				if ($object->element == 'order_supplier') {
					$colspan--;
				}
				if ($object->element == 'supplier_proposal') {
					$colspan += 2;
				}
				if ($object->element == 'invoice_supplier') {
					$colspan -= 2;
				}
                $line_show_qty = false;

                if(TSubtotal::isSubtotal($line)) {

                    /* Total */
                    $TSubtotalDatas = $this->getTotalLineFromObject($object, $line, '', 1);
                    $total_line = $TSubtotalDatas[0];
					$multicurrency_total_line = $TSubtotalDatas[6];	// InfraS add
                    $total_qty = $TSubtotalDatas[4];
                    if ($show_qty_bu_deault = TSubtotal::showQtyForObject($object)) {
                        $line_show_qty = TSubtotal::showQtyForObjectLine($line, $show_qty_bu_deault);

                    }
                }

				?>

				<?php
					if($action=='editline' && GETPOST('lineid', 'int') == $line->id && TSubtotal::isModSubtotalLine($line) ) {

                        echo '<td colspan="'.$colspan.'" style="'.(TSubtotal::isFreeText($line) ? '' : 'font-weight:bold;').(($line->qty>90)?'text-align:right':'').'">';
						$params=array('line'=>$line);
						$reshook=$hookmanager->executeHooks('formEditProductOptions',$params,$object,$action);

						echo '<div id="line_'.$line->id.'"></div>'; // Imitation Dolibarr
						echo '<input type="hidden" value="'.$line->id.'" name="lineid">';
						echo '<input id="product_type" type="hidden" value="'.$line->product_type.'" name="type">';
						echo '<input id="product_id" type="hidden" value="'.$line->fk_product.'" name="type">';
						echo '<input id="special_code" type="hidden" value="'.$line->special_code.'" name="type">';

						$isFreeText=false;
						if (TSubtotal::isTitle($line))
						{
							$qty_displayed = $line->qty;
							print img_picto('', 'subsubtotal@subtotal').'<span style="font-size:9px;margin-left:-3px;color:#0075DE;">'.$qty_displayed.'</span>&nbsp;&nbsp;';

						}
						else if (TSubtotal::isSubtotal($line))
						{
							$qty_displayed = 100 - $line->qty;
							print img_picto('', 'subsubtotal2@subtotal').'<span style="font-size:9px;margin-left:-1px;color:#0075DE;">'.$qty_displayed.'</span>&nbsp;&nbsp;';
						}
						else
						{
							$isFreeText = true;
						}

						if ($object->element == 'order_supplier' || $object->element == 'invoice_supplier') {
						    $line->label = !empty($line->description) ? $line->description : $line->desc;
						    $line->description = '';
						}
						$newlabel = $line->label;
						if($line->label=='' && !$isFreeText) {
							if(TSubtotal::isSubtotal($line)) {
								$newlabel = $line->description.' '.$this->getTitle($object, $line);
								$line->description='';
							}
						}

						$readonlyForSituation = '';
                        if(empty($line->fk_prev_id)) $line->fk_prev_id = null;
						if (!empty($line->fk_prev_id) && $line->fk_prev_id != null) $readonlyForSituation = 'readonly';

						if (!$isFreeText) echo '<input type="text" name="line-title" id-line="'.$line->id.'" value="'.$newlabel.'" size="80" '.$readonlyForSituation.'/>&nbsp;';

						if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT') && (TSubtotal::isTitle($line) || TSubtotal::isSubtotal($line)) )
						{
							$select = '<select name="subtotal_level">';
							for ($j=1; $j<10; $j++)
							{
								if (!empty($readonlyForSituation)) {
									if ($qty_displayed == $j) $select .= '<option selected="selected" value="'.$j.'">'.$langs->trans('Level').' '.$j.'</option>';
								} else $select .= '<option '.($qty_displayed == $j ? 'selected="selected"' : '').' value="'.$j.'">'.$langs->trans('Level').' '.$j.'</option>';
							}
							$select .= '</select>&nbsp;';

							echo $select;
						}


						echo '<div class="subtotal_underline" style="margin-left:24px; line-height: 25px;">';
                        if (!getDolGlobalString('SUBTOTAL_HIDE_OPTIONS_BREAK_PAGE_BEFORE')){
							echo '<div>';
							echo '<input style="vertical-align:sub;"  type="checkbox" name="line-pagebreak" id="subtotal-pagebreak" value="8" '.(($line->info_bits > 0) ? 'checked="checked"' : '') .' />&nbsp;';
							echo '<label for="subtotal-pagebreak">'.$langs->trans('AddBreakPageBefore').'</label>';
							echo '</div>';
						}
                        if (TSubtotal::isTitle($line)&& !getDolGlobalString('SUBTOTAL_HIDE_OPTIONS_TITLE'))
                        {
							// InfraS add begin
							if (!empty(isModEnabled('infraspackplus')) && in_array($object->element, array('propal', 'commande', 'facture'))) {
								echo '<div>';
								echo '<input style="vertical-align:sub;"  type="checkbox" name="line-showTableHeaderBefore" id="subtotal-showTableHeaderBefore" value="10" '.((!empty($line->array_options['options_show_table_header_before']) && $line->array_options['options_show_table_header_before'] > 0) ? 'checked="checked"' : '') .' />&nbsp;';
								echo '<label for="subtotal-showTableHeaderBefore">'.$langs->trans('ShowTableHeaderBefore').'</label>';
								echo '</div>';
								echo '<div>';
								echo '<input style="vertical-align:sub;"  type="checkbox" name="line-printAsList" id="subtotal-printAsList" value="20" '.((!empty($line->array_options['options_print_as_list']) && $line->array_options['options_print_as_list'] > 0) ? 'checked="checked"' : '') .' />&nbsp;';
								echo '<label for="subtotal-printAsList">'.$langs->trans('PrintAsList').'</label>';
								echo '</div>';
								echo '<div>';
								echo '<input style="vertical-align:sub;"  type="checkbox" name="line-printCondensed" id="subtotal-printCondensed" value="30" '.((!empty($line->array_options['options_print_condensed']) && $line->array_options['options_print_condensed'] > 0) ? 'checked="checked"' : '') .' />&nbsp;';
								echo '<label for="subtotal-printCondensed">'.$langs->trans('PrintCondensed').'</label>';
								echo '</div>';
							}
							// InfraS add end
                            $form = new Form($db);
                            echo '<div>';
                            echo '<label for="subtotal_tva_tx">'.$form->textwithpicto($langs->trans('subtotal_apply_default_tva'), $langs->trans('subtotal_apply_default_tva_help')).'</label>';
                            echo '<select id="subtotal_tva_tx" name="subtotal_tva_tx" class="flat"><option selected="selected" value="">-</option>';
                            if (empty($readonlyForSituation)) echo str_replace('selected', '', $form->load_tva('subtotal_tva_tx', '', $parameters['seller'], $parameters['buyer'], 0, 0, '', true));
                            echo '</select>';
                            echo '</div>';

                            if (getDolGlobalString('INVOICE_USE_SITUATION') && $object->element == 'facture' && $object->type == Facture::TYPE_SITUATION)
                            {
                                echo '<div>';
                                echo '<label for="subtotal_progress">'.$langs->trans('subtotal_apply_progress').'</label> <input id="subtotal_progress" name="subtotal_progress" value="" size="1" />%';
                                echo '</div>';
                            }
                            echo '<div>';
                            echo '<input style="vertical-align:sub;"  type="checkbox" name="line-showTotalHT" id="subtotal-showTotalHT" value="9" '.((!empty($line->array_options['options_show_total_ht']) && $line->array_options['options_show_total_ht'] > 0) ? 'checked="checked"' : '') .' />&nbsp;';
                            echo '<label for="subtotal-showTotalHT">'.$langs->trans('ShowTotalHTOnSubtotalBlock').'</label>';
                            echo '</div>';

                            echo '<div>';
                            echo '<input style="vertical-align:sub;"  type="checkbox" name="line-showReduc" id="subtotal-showReduc" value="1" '.((!empty($line->array_options['options_show_reduc']) && $line->array_options['options_show_reduc'] > 0) ? 'checked="checked"' : '') .' />&nbsp;';
                            echo '<label for="subtotal-showReduc">'.$langs->trans('ShowReducOnSubtotalBlock').'</label>';
                            echo '</div>';
                        }
                        else if ($isFreeText) echo TSubtotal::getFreeTextHtml($line, (bool) $readonlyForSituation);

                        if (TSubtotal::isSubtotal($line) && $show_qty_bu_deault = TSubtotal::showQtyForObject($object)) {
                            $line_show_qty = TSubtotal::showQtyForObjectLine($line, $show_qty_bu_deault);
                            echo '<div>';
                            echo '<input style="vertical-align:sub;"  type="checkbox" name="line-showQty" id="subtotal-showQty" value="1" ' . ($line_show_qty ? 'checked="checked"' : '') . ' />&nbsp;';
                            echo '<label for="subtotal-showQty">' . $langs->trans('SubtotalLineShowQty') . '</label>';
                            echo '</div>';
                        }

						echo '</div>';

						if (TSubtotal::isTitle($line))
						{
							// WYSIWYG editor
							require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
							$nbrows = ROWS_2;
							$cked_enabled = (getDolGlobalString('FCKEDITOR_ENABLE_DETAILS') ? getDolGlobalString('FCKEDITOR_ENABLE_DETAILS'): 0);
							if (getDolGlobalString('MAIN_INPUT_DESC_HEIGHT')) {
								$nbrows = getDolGlobalString('MAIN_INPUT_DESC_HEIGHT');
							}
							$toolbarname = 'dolibarr_details';
							if (getDolGlobalString('FCKEDITOR_ENABLE_DETAILS_FULL')) {
								$toolbarname = 'dolibarr_notes';
							}
							$doleditor = new DolEditor('line-description', $line->description, '', 100, $toolbarname, '',
								false, true, $cked_enabled, $nbrows, '98%', (bool) $readonlyForSituation);
							$doleditor->Create();

							$TKey = null;
                            getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_PROPALDET', '');
                            getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_COMMANDEDET', '');
                            getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_FACTUREDET', '');
							if ($line->element == 'propaldet') $TKey = explode(',', getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_PROPALDET'));
							elseif ($line->element == 'commandedet') $TKey = explode(',', getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_COMMANDEDET'));
							elseif ($line->element == 'facturedet') $TKey = explode(',', getDolGlobalString('SUBTOTAL_LIST_OF_EXTRAFIELDS_FACTUREDET'));
							// TODO ajouter la partie fournisseur

							if (!empty($TKey))
							{
								$extrafields = new ExtraFields($this->db);
								$extrafields->fetch_name_optionals_label($object->table_element_line);
                                if(!empty($extrafields->attributes[$line->element]['param'])) {
									foreach($extrafields->attributes[$line->element]['param'] as $code => $val) {
										if(in_array($code, $TKey) && $extrafields->attributes[$line->element]['list'][$code] > 0) {
											echo '<div class="sub-'.$code.'">';
											echo '<label class="">'.$extrafields->attributes[$line->element]['label'][$code].'</label>';
                                            if(floatval(DOL_VERSION) >= 17) echo $extrafields->showInputField($code, $line->array_options['options_'.$code], '', '', 'subtotal_','',0,$object->table_element_line);
                                            else echo $extrafields->showInputField($code, $line->array_options['options_'.$code], '', '', 'subtotal_');
											echo '</div>';
										}
									}
								}
							}
						}

					}
					else {

                        if ($line_show_qty) {
                            $colspan -= 2;

                            $style = getDolGlobalString('SUBTOTAL_TITLE_STYLE', '');
                            $titleStyleItalic = strpos($style, 'I') === false ? '' : ' font-style: italic;';
                            $titleStyleBold = strpos($style, 'B') === false ? '' : ' font-weight:bold;';
                            $titleStyleUnderline = strpos($style, 'U') === false ? '' : ' text-decoration: underline;';

                            $style = 'text-align:right;';
                            echo '<td colspan="' . $colspan . '" style="' . $style . $titleStyleBold . '">';
                            echo '<span class="subtotal_label" style="' . $titleStyleItalic . $titleStyleBold . $titleStyleUnderline . '">' . $langs->trans('Qty') . ' : </span>&nbsp;&nbsp;' . price($total_qty, 0, '', 0, 0);
                            echo '</td>';
                            $colspan = 2;
                        }
				    if(TSubtotal::isSubtotal($line) && getDolGlobalString('DISPLAY_MARGIN_ON_SUBTOTALS')) {
						$colspan --;

				        $style = getDolGlobalString('SUBTOTAL_TITLE_STYLE', '');
						$titleStyleItalic = strpos($style, 'I') === false ? '' : ' font-style: italic;';
						$titleStyleBold =  strpos($style, 'B') === false ? '' : ' font-weight:bold;';
						$titleStyleUnderline =  strpos($style, 'U') === false ? '' : ' text-decoration: underline;';


						// $total_line = $this->getTotalLineFromObject($object, $line, '');	// InfraS change $total_line is already calculated in the previous block

						//Marge :
						$style = $line->qty>90 ? 'text-align:right;font-weight:bold;' : '';
						echo '<td nowrap="nowrap" colspan="'.$colspan.'" style="'.$style.'">';
						echo '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'">Marge :</span>';


                        $parentTitleLine = TSubtotal::getParentTitleOfLine($object, $line->rang);
                        $productLines = TSubtotal::getLinesFromTitleId($object, $parentTitleLine->id);

						$totalCostPrice = 0;
                        if(!empty($productLines)){
							foreach ($productLines as $l) {
								$product = new Product($db);
								$res = $product->fetch($l->fk_product);
                                if($res) {
                                    $totalCostPrice += $product->cost_price * $l->qty;
								}
						    }
						}

                        $marge = $total_line - $totalCostPrice;

						echo '&nbsp;&nbsp;'.price($marge);
						echo '</td>';
					}




						//SousTotal :
                        $style = TSubtotal::isFreeText($line) ? '' : 'font-weight:bold;';
                        $style.= $line->qty>90 ? 'text-align:right' : '';

                        echo '<td '. (!TSubtotal::isSubtotal($line) || !getDolGlobalString('DISPLAY_MARGIN_ON_SUBTOTALS') ? ' colspan="'.$colspan.'"' : '' ).' style="' .$style.'">';
						 if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
						 {
							if(TSubtotal::isTitle($line))
							{
								echo str_repeat('&nbsp;&nbsp;&nbsp;', max(floatval($line->qty) - 1, 0));

								if (TSubtotal::isTitle($line)) print img_picto('', 'subtotal@subtotal').'<span style="font-size:9px;margin-left:-3px;">'.$line->qty.'</span>&nbsp;&nbsp;';
								else print img_picto('', 'subtotal2@subtotal').'<span style="font-size:9px;margin-left:-1px;">'.(100-$line->qty).'</span>&nbsp;&nbsp;';
							}
						 }
						 else
						 {
							if($line->qty<=1) print img_picto('', 'subtotal@subtotal');
							else if($line->qty==2) print img_picto('', 'subsubtotal@subtotal').'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
						 }


						 // Get display styles and apply them
                         $style = '';
						 $style  =  TSubtotal::isFreeText($line) ? getDolGlobalString('SUBTOTAL_TEXT_LINE_STYLE', '') : getDolGlobalString('SUBTOTAL_TITLE_STYLE', '');
						 $titleStyleItalic = strpos($style, 'I') === false ? '' : ' font-style: italic;';
						 $titleStyleBold =  strpos($style, 'B') === false ? '' : ' font-weight:bold;';
						 $titleStyleUnderline =  strpos($style, 'U') === false ? '' : ' text-decoration: underline;';

						 if (empty($line->label)) {
							if ($line->qty >= 91 && $line->qty <= 99 && getDolGlobalString('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL')) print  $line->description.' '.'<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$this->getTitle($object, $line).'</span>';
							else print  '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$line->description.'</span>';
						 }
						 else {

							if (getDolGlobalString('PRODUIT_DESC_IN_FORM') && !empty($line->description)) {
								// on ne veut pas afficher le label et la description si elles sont identiques
								 $lineLabel = $line->description != $line->label ? $line->label.'</span><br><div class="subtotal_desc">'.dol_htmlentitiesbr($line->description) : $line->label ;
								print '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >' . $lineLabel . '</div>';
							}
							else{
								print '<span class="subtotal_label classfortooltip" style=" '.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" title="'.$line->description.'">'.$line->label.'</span>';
							}

						 }
						if (TSubtotal::isTitle($line)) {
							//Folder for expand
							$titleAttr = (array_key_exists('options_hideblock', $line->array_options) && $line->array_options['options_hideblock'] == 1) ? $langs->trans("Subtotal_Show") : $langs->trans("Subtotal_Hide");

							print '<span class="fold-subtotal-container" >';

							// bouton pour ouvrir/fermer le bloc
							print ' <span title="'.dol_escape_htmltag($titleAttr).'" class="fold-subtotal-btn" data-toggle-all-children="0" data-title-line-target="' . $line->id . '" id="collapse-' . $line->id . '" >';
							print ((array_key_exists('options_hideblock', $line->array_options) && $line->array_options['options_hideblock'] == 1) ? img_picto('', 'folder') : img_picto('', 'folder-open'));
							print '</span>';

							// Bouton pour ouvrir/fermer aussi les enfants
							print ' <span title="'.dol_escape_htmltag($titleAttr).'" class="fold-subtotal-btn" data-toggle-all-children="1" data-title-line-target="' . $line->id . '" id="collapse-children-' . $line->id . '" >';
							print ((array_key_exists('options_hideblock', $line->array_options) && $line->array_options['options_hideblock'] == 1) ? img_picto('', 'folder') : img_picto('', 'folder-open'));
							print '</span>';

							// un span pour contenir des infos comme le nombre de lignes cachées etc...
							print ' <span class="fold-subtotal-info" title="'.dol_escape_htmltag($langs->trans('NumberOfHiddenLines')).'" data-title-line-target="' . $line->id . '" ></span>';

							print '</span>';
						}


						 if($line->qty>90) print ' : ';
						 if($line->info_bits > 0) echo img_picto($langs->trans('Pagebreak'), 'pagebreak@subtotal');
					}
			?>

			<?php
				if($line->qty>90) {

					/* Total */
					echo '<td class="linecolht nowrap" align="right" style="font-weight:bold;" rel="subtotal_total">'.price($total_line).'</td>';
					if (isModEnabled('multicurrency') && ($object->multicurrency_code != $conf->currency)) {
						echo '<td class="linecoltotalht_currency right bold">'.price($multicurrency_total_line).'</td>';	// InfraS change
					}
				} else {
					echo '<td class="linecolht movetitleblock">&nbsp;</td>';
					if (isModEnabled('multicurrency') && ($object->multicurrency_code != $conf->currency)) {
						echo '<td class="linecoltotalht_currency">&nbsp;</td>';
					}
				}
			?>

			<td class="center nowrap linecoledit">	<!-- InfraS change -->
				<?php
				if ($action != 'selectlines') {

					if($action=='editline' && GETPOST('lineid', 'int') == $line->id && TSubtotal::isModSubtotalLine($line) ) {
						?>
						<input id="savelinebutton" class="button" type="submit" name="save" value="<?php echo $langs->trans('Save') ?>" />
						<br />
						<input class="button" type="button" name="cancelEditlinetitle" value="<?php echo $langs->trans('Cancel') ?>" />
						<script type="text/javascript">
							$(document).ready(function() {
								$('input[name=cancelEditlinetitle]').click(function () {
									document.location.href="<?php echo '?'.$idvar.'='.$object->id ?>";
								});
							});

						</script>
						<?php

					}
					else{
						if ($object->statut == 0  && $createRight && getDolGlobalString('SUBTOTAL_ALLOW_DUPLICATE_BLOCK') && $object->element !== 'invoice_supplier')
						{
                            if(empty($line->fk_prev_id)) $line->fk_prev_id = null;
							if(TSubtotal::isTitle($line) && ( $line->fk_prev_id === null )) {
								echo '<a class="subtotal-line-action-btn" title="'.$langs->trans('CloneLSubtotalBlock').'" href="'.$_SERVER['PHP_SELF'].'?'.$idvar.'='.$object->id.'&action=duplicate&lineid='.$line->id.'&token='.$newToken.'" >';

                                echo '<i class="'.$fa.' fa-clone" aria-hidden="true"></i>'; // InfraS change

								echo '</a>';
							}
						}

						if ($object->statut == 0  && $createRight && getDolGlobalString('SUBTOTAL_ALLOW_EDIT_BLOCK'))
						{
							echo '<a class="subtotal-line-action-btn"  href="'.$_SERVER['PHP_SELF'].'?'.$idvar.'='.$object->id.'&action=editline&token='.$newToken.'&lineid='.$line->id.'#row-'.$line->id.'">'.img_edit().'</a>';
						}
					}

				}

				?>
			</td>

			<td class="center nowrap linecoldelete">	<!-- InfraS change -->
				<?php

				if ($action != 'editline' && $action != 'selectlines') {
						if ($object->statut == 0  && $createRight && !empty(getDolGlobalString('SUBTOTAL_ALLOW_REMOVE_BLOCK')))
						{
                            if(empty($line->fk_prev_id)) $line->fk_prev_id = null;
							if (!isset($line->fk_prev_id) || $line->fk_prev_id === null)
							{
								echo '<a class="subtotal-line-action-btn"  href="'.$_SERVER['PHP_SELF'].'?'.$idvar.'='.$object->id.'&action=ask_deleteline&lineid='.$line->id.'&token='.$newToken.'">'.img_delete().'</a>';
							}

							if(TSubtotal::isTitle($line) && (!isset($line->fk_prev_id) || (isset($line->fk_prev_id) && ($line->fk_prev_id === null))) )
							{

                                $img_delete = img_delete($langs->trans('deleteWithAllLines'), ' style="color:#be3535 !important;" class="pictodelete pictodeleteallline"');

								echo '<a class="subtotal-line-action-btn"  href="'.$_SERVER['PHP_SELF'].'?'.$idvar.'='.$object->id.'&action=ask_deleteallline&lineid='.$line->id.'&token='.$newToken.'">'.$img_delete.'</a>';
							}
						}
					}
				?>
			</td>

			<?php
			if ($object->statut == 0  && $createRight && !empty(getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS')) && TSubtotal::isTitle($line) && $action != 'editline' && $action != 'selectlines')
			{
				echo '<td class="subtotal_nc">';
				echo '<input id="subtotal_nc-'.$line->id.'" class="subtotal_nc_chkbx" data-lineid="'.$line->id.'" type="checkbox" name="subtotal_nc" value="1" '.(!empty($line->array_options['options_subtotal_nc']) ? 'checked="checked"' : '').' />';
				echo '</td>';
			}

			if ($num > 1 && empty($conf->browser->phone)) { ?>
			<td align="center" class="linecolmove tdlineupdown">
			</td>
			<?php } else { ?>
			<td align="center"<?php echo ((empty($conf->browser->phone) && ($object->statut == 0  && $createRight ))?' class="tdlineupdown"':''); ?>></td>
			<?php } ?>


				<?php
				$Telement = array('propal','commande','facture','supplier_proposal','order_supplier','invoice_supplier');

				if (!empty(getDolGlobalString('MASSACTION_CARD_ENABLE_SELECTLINES')) && $object->status == $object::STATUS_DRAFT && $usercandelete && in_array($object->element,$Telement)|| $action == 'selectlines' ) { // dolibarr 8

					if ($action !== 'editline' && GETPOST('lineid', 'int') !== $line->id) {
						$checked = '';

						if (!empty($toselect) && in_array($line->id,$toselect)){
							$checked = 'checked';
						}

						if ($action != 'editline') {
							?>
							<td class="linecolcheck center"><input type="checkbox" class="linecheckbox"  name="line_checkbox[<?php print $i + 1; ?>]" value="<?php print $line->id; ?>"></td>
							<?php
						}
					}
				}
				?>

			</tr>
			<?php


			// Affichage des extrafields à la Dolibarr (car sinon non affiché sur les titres)
			if(TSubtotal::isTitle($line) && getDolGlobalString('SUBTOTAL_ALLOW_EXTRAFIELDS_ON_TITLE')) {

				require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

				// Extrafields
				$extrafieldsline = new ExtraFields($db);
				$extralabelsline = $extrafieldsline->fetch_name_optionals_label($object->table_element_line);

				$colspan+=3; $mode = 'view';
				if($action === 'editline' && $line->rowid == GETPOST('lineid', 'int')) $mode = 'edit';

				$ex_element = $line->element;
				$line->element = 'tr_extrafield_title '.$line->element; // Pour pouvoir manipuler ces tr
				print $line->showOptionals($extrafieldsline, $mode, array('style'=>' style="background:#eeffee;" ','colspan'=>$colspan));
				$isExtraSelected = false;
				foreach($line->array_options as $option) {
					if(!empty($option) && $option != "-1") {
						$isExtraSelected = true;
						break;
					}
				}

				if($mode === 'edit') {
					?>
					<script>
						$(document).ready(function(){

							var all_tr_extrafields = $("tr.tr_extrafield_title");
							<?php
							// Si un extrafield est rempli alors on affiche directement les extrafields
							if(!$isExtraSelected) {
								echo 'all_tr_extrafields.hide();';
								echo 'var trad = "'.$langs->trans('showExtrafields').'";';
								echo 'var extra = 0;';
							} else {
								echo 'all_tr_extrafields.show();';
								echo 'var trad = "'.$langs->trans('hideExtrafields').'";';
								echo 'var extra = 1;';
							}
							?>

							$("div .subtotal_underline").append(
									'<a id="printBlocExtrafields" onclick="return false;" href="#">' + trad + '</a>'
									+ '<input type="hidden" name="showBlockExtrafields" id="showBlockExtrafields" value="'+ extra +'" />');

							$(document).on('click', "#printBlocExtrafields", function() {
								var btnShowBlock = $("#showBlockExtrafields");
								var val = btnShowBlock.val();
								if(val == '0') {
									btnShowBlock.val('1');
									$("#printBlocExtrafields").html("<?php print $langs->trans('hideExtrafields'); ?>");
									$(all_tr_extrafields).show();
								} else {
									btnShowBlock.val('0');
									$("#printBlocExtrafields").html("<?php print $langs->trans('showExtrafields'); ?>");
									$(all_tr_extrafields).hide();
								}
							});
						});
					</script>
					<?php
				}
				$line->element = $ex_element;

			}

			print '<!-- END OF actions_subtotal.class.php line '.__LINE__.' -->';
			return 1;

		}
		elseif(($object->element == 'commande' && in_array('ordershipmentcard', $contexts)) || (in_array('expeditioncard', $contexts) && $action == 'create'))
		{
			$colspan = 4;

			// HTML 5 data for js
			$data = $this->_getHtmlData($parameters, $object, $action, $hookmanager);

            $class													= '';
            if (!empty(getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT')))		$class	.= ' newSubtotal';
            if ($line->qty == 1)									$class	.= ' subtitleLevel1';	// Title level 1
            elseif ($line->qty == 2)								$class	.= ' subtitleLevel2';	// Title level 2
            elseif ($line->qty > 2 && $line->qty < 10)				$class	.= ' subtitleLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 99)								$class	.= ' subtotalLevel1';	// Sub-total level 1
            elseif ($line->qty == 98)								$class	.= ' subtotalLevel2';	// Sub-total level 2
            elseif ($line->qty > 90 && $line->qty < 98)				$class	.= ' subtotalLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 50)								$class	.= ' subtotalText';      // Free text
?>

			<!-- actions_subtotal.class.php line <?php echo __LINE__; ?> -->
			<tr class="oddeven" <?php echo $data; ?> rel="subtotal" id="row-<?php echo $line->id ?>" style="<?php
					if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
					{
						// InfraS change begin
						if ($line->qty <= 99 && $line->qty >= 91) {
							$subtotalBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (20 - (109 - $line->qty)) / 10;
							print 'background:rgba('.implode(',', $subtotalBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty >= 1 && $line->qty <= 9) {
							$titleBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (11 - $line->qty) / 10;
							print 'background:rgba('.implode(',', $titleBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty == 50) {	// Free text
							print '';
						}
						// InfraS change end
					}
					else
					{
                        if($line->qty==99) print 'background:#ddffdd';          // Sub-total level 1
                        else if($line->qty==98) print 'background:#ddddff;';	// Sub-total level 2
                        else if($line->qty==2) print 'background:#eeeeff; ';    // Title level 2
                        else if($line->qty==50) print '';                       // Free text
                        else print 'background:#eeffee;';                       // Title level 1 and 3 to 9
					}

			?>;">

				<td style="<?php TSubtotal::isFreeText($line) ? '' : 'font-weight:bold;'; ?>  <?php echo ($line->qty>90)?'text-align:right':'' ?> "><?php


						 if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
						 {
							if(TSubtotal::isTitle($line) || TSubtotal::isSubtotal($line))
							{
								echo str_repeat('&nbsp;&nbsp;&nbsp;', max(floatval($line->qty) - 1, 0));

								if (TSubtotal::isTitle($line)) print img_picto('', 'subtotal@subtotal').'<span style="font-size:9px;margin-left:-3px;">'.$line->qty.'</span>&nbsp;&nbsp;';
								else print img_picto('', 'subtotal2@subtotal').'<span style="font-size:9px;margin-left:-1px;">'.(100-$line->qty).'</span>&nbsp;&nbsp;';
							}
						 }
						 else
						 {
							if($line->qty<=1) print img_picto('', 'subtotal@subtotal');
							else if($line->qty==2) print img_picto('', 'subsubtotal@subtotal').'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
						 }


						 // Get display styles and apply them
						 $titleStyleItalic = strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'I') === false ? '' : ' font-style: italic;';
						 $titleStyleBold =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'B') === false ? '' : ' font-weight:bold;';
						 $titleStyleUnderline =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'U') === false ? '' : ' text-decoration: underline;';

						 if (empty($line->label)) {
							if ($line->qty >= 91 && $line->qty <= 99 && getDolGlobalString('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL') ) print  $line->description.' '.$this->getTitle($object, $line);
							else print  $line->description;
						 }
						 else {

							if (getDolGlobalString('PRODUIT_DESC_IN_FORM') && !empty($line->description)) {
								print '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$line->label.'</span><br><div class="subtotal_desc">'.dol_htmlentitiesbr($line->description).'</div>';
							}
							else{
								print '<span class="subtotal_label classfortooltip" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" title="'.$line->description.'">'.$line->label.'</span>';
							}

						 }
						//if($line->qty>90) print ' : ';
						if(!empty($line->info_bits) && $line->info_bits > 0) echo img_picto($langs->trans('Pagebreak'), 'pagebreak@subtotal');

			?>
				</td>
				 <td colspan="<?php echo $colspan; ?>">
<?php
						if(in_array('expeditioncard', $contexts) && $action == 'create')
						{
							$fk_entrepot = GETPOST('entrepot_id', 'int');
?>

						<input type="hidden" name="idl<?php echo $i; ?>" value="<?php echo $line->id; ?>" />
						<input type="hidden" name="qtyasked<?php echo $i; ?>" value="<?php echo $line->qty; ?>" />
						<input type="hidden" name="qdelivered<?php echo $i; ?>" value="0" />
						<input type="hidden" name="qtyl<?php echo $i; ?>" value="<?php echo $line->qty; ?>" />
						<input type="hidden" name="entl<?php echo $i; ?>" value="<?php echo $fk_entrepot; ?>" />
<?php
						}
?>
					 </td>
			</tr>
			<!-- END OF actions_subtotal.class.php line <?php echo __LINE__; ?> -->
<?php
			return 1;
		}
		elseif ($object->element == 'shipping' || $object->element == 'delivery')
		{
			global $form;

			$alreadysent = $parameters['alreadysent'];

			$shipment_static = new Expedition($db);
			$warehousestatic = new Entrepot($db);
			$extrafieldsline = new ExtraFields($db);
			$extralabelslines=$extrafieldsline->fetch_name_optionals_label($object->table_element_line);

			$colspan = 4;
			if($object->origin && $object->origin_id > 0) $colspan++;
			if(isModEnabled('stock')) $colspan++;
			if(isModEnabled('productbatch')) $colspan++;
			if($object->statut == 0) $colspan++;
			if($object->statut == 0 && !getDolGlobalString('SUBTOTAL_ALLOW_REMOVE_BLOCK')) $colspan++;

			if($object->element == 'delivery') $colspan = 2;

			print '<!-- origin line id = '.$line->origin_line_id.' -->'; // id of order line

			// HTML 5 data for js
			$data = $this->_getHtmlData($parameters, $object, $action, $hookmanager);

            $class													= '';
            if (!empty(getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT')))		$class	.= ' newSubtotal ';
            if ($line->qty == 1)									$class	.= ' subtitleLevel1';	// Title level 1
            elseif ($line->qty == 2)								$class	.= ' subtitleLevel2';	// Title level 2
            elseif ($line->qty > 2 && $line->qty < 10)				$class	.= ' subtitleLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 99)								$class	.= ' subtotalLevel1';	// Sub-total level 1
            elseif ($line->qty == 98)								$class	.= ' subtotalLevel2';	// Sub-total level 2
            elseif ($line->qty > 90 && $line->qty < 98)				$class	.= ' subtotalLevel3to9';	// Sub-total level 3 to 9
            elseif ($line->qty == 50)								$class	.= ' subtotalText';      // Free text
			?>
			<!-- actions_subtotal.class.php line <?php echo __LINE__; ?> -->
			<tr class="oddeven" <?php echo $data; ?> rel="subtotal" id="row-<?php echo $line->id ?>" style="<?php
					if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
					{
						// InfraS change begin
						if ($line->qty <= 99 && $line->qty >= 91) {
							$subtotalBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (20 - (109 - $line->qty)) / 10;
							print 'background:rgba('.implode(',', $subtotalBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty >= 1 && $line->qty <= 9) {
							$titleBackgroundColor = colorStringToArray(getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#adadcf'));
							$opacity = (11 - $line->qty) / 10;
							print 'background:rgba('.implode(',', $titleBackgroundColor).', '.$opacity.');';
						} elseif ($line->qty == 50) {	// Free text
							print '';
						}
						// InfraS change end
					}
					else
					{
                        if($line->qty==99) print 'background:#ddffdd';          // Sub-total level 1
                        else if($line->qty==98) print 'background:#ddddff;';	// Sub-total level 2
                        else if($line->qty==2) print 'background:#eeeeff; ';	// Title level 2
                        else if($line->qty==50) print '';                       // Free text
                        else print 'background:#eeffee;';                       // Title level 1, Sub-total level 1 and 3 to 9
					}

			?>;">

			<?php
			// #
			if (getDolGlobalString('MAIN_VIEW_LINE_NUMBER'))
			{
				print '<td align="center">'.($i+1).'</td>';
			}
			?>

			<td style="<?php TSubtotal::isFreeText($line) ? '' : 'font-weight:bold;'; ?>  <?php echo ($line->qty>90)?'text-align:right':'' ?> "><?php


			if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
			{
				if(TSubtotal::isTitle($line) || TSubtotal::isSubtotal($line))
				{
					echo str_repeat('&nbsp;&nbsp;&nbsp;', max(floatval($line->qty) - 1, 0));

					if (TSubtotal::isTitle($line)) print img_picto('', 'subtotal@subtotal').'<span style="font-size:9px;margin-left:-3px;">'.$line->qty.'</span>&nbsp;&nbsp;';
					else print img_picto('', 'subtotal2@subtotal').'<span style="font-size:9px;margin-left:-1px;">'.(100-$line->qty).'</span>&nbsp;&nbsp;';
				}
			}
			else
			{
				if($line->qty<=1) print img_picto('', 'subtotal@subtotal');
				else if($line->qty==2) print img_picto('', 'subsubtotal@subtotal').'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
			}


			// Get display styles and apply them
			$titleStyleItalic = strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'I') === false ? '' : ' font-style: italic;';
			$titleStyleBold =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'B') === false ? '' : ' font-weight:bold;';
			$titleStyleUnderline =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'U') === false ? '' : ' text-decoration: underline;';

			if (empty($line->label)) {
				if ($line->qty >= 91 && $line->qty <= 99 && getDolGlobalString('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL')) print  $line->description.' '.$this->getTitle($object, $line);
				else print  $line->description;
			}
			else {
				if (getDolGlobalString('PRODUIT_DESC_IN_FORM') && !empty($line->description)) {
					print '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$line->label.'</span><br><div class="subtotal_desc">'.dol_htmlentitiesbr($line->description).'</div>';
				}
				else{
					print '<span class="subtotal_label classfortooltip " style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" title="'.$line->description.'">'.$line->label.'</span>';
				}
			}
			//if($line->qty>90) print ' : ';
			if($line->info_bits > 0) echo img_picto($langs->trans('Pagebreak'), 'pagebreak@subtotal');

			?>
				</td>
				<td colspan="<?php echo $colspan; ?>">&nbsp;</td>
			<?php

			if ($object->element == 'shipping' && $object->statut == 0 && getDolGlobalString('SUBTOTAL_ALLOW_REMOVE_BLOCK'))
			{
				print '<td class="linecoldelete nowrap" width="10">';
				$lineid = $line->id;
				if($line->element === 'commandedet') {
					foreach($object->lines as $shipmentLine) {
						if((!empty($shipmentLine->fk_elementdet)) && $shipmentLine->fk_origin == 'orderline' && $shipmentLine->fk_elementdet == $line->id) {
							$lineid = $shipmentLine->id;
						} elseif((!empty($shipmentLine->fk_elementdet)) && $shipmentLine->fk_origin == 'orderline' && $shipmentLine->fk_elementdet == $line->id) {
							$lineid = $shipmentLine->id;
						}
					}
				}
                if(empty($line->fk_prev_id)) $line->fk_prev_id = null;
				if ($line->fk_prev_id === null)
				{
					echo '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=deleteline&amp;lineid='.$lineid.'&token='.$newToken.'">'.img_delete().'</a>';
				}

				if(TSubtotal::isTitle($line) && ($line->fk_prev_id === null) )
				{
                    $img_delete = img_delete($langs->trans('deleteWithAllLines'), ' style="color:#be3535 !important;" class="pictodelete pictodeleteallline"');

					echo '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;action=ask_deleteallline&amp;lineid='.$lineid.'&token='.$newToken.'">'.$img_delete.'</a>';
				}

				print '</td>';
			}

			print "</tr>\r\n";
			print "<!-- END OF actions_subtotal.class.php -->\r\n";

			// Display lines extrafields
			if ($object->element == 'shipping' && getDolGlobalString('SUBTOTAL_ALLOW_EXTRAFIELDS_ON_TITLE') && is_array($extralabelslines) && count($extralabelslines)>0) {
				$line = new ExpeditionLigne($db);
				$line->fetch_optionals($line->id);
				print '<tr class="oddeven">';
				print $line->showOptionals($extrafieldsline, 'view', array('style'=>$bc[$var], 'colspan'=>$colspan),$i);
			}

			return 1;
		}

		return 0;

	}


	function printOriginObjectSubLine($parameters, &$object, &$action, $hookmanager)
	{
        global $conf, $restrictlist, $selectedLines;

		$line = &$parameters['line'];

		$contexts = explode(':',$parameters['context']);

        if (in_array('ordercard',$contexts)
            || in_array('invoicecard',$contexts)
            || in_array('ordersuppliercard',$contexts)
            || in_array('invoicesuppliercard',$contexts)
        )
		{
			/** @var Commande $object */

			if(class_exists('TSubtotal')){ dol_include_once('/subtotal/class/subtotal.class.php'); }


			if (TSubtotal::isModSubtotalLine($line))
			{

				$object->tpl['subtotal'] = $line->id;
				if (TSubtotal::isTitle($line)) $object->tpl['sub-type'] = 'title';
				else if (TSubtotal::isSubtotal($line)) $object->tpl['sub-type'] = 'total';

				$object->tpl['sub-tr-style'] = '';
				if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
				{
					// InfraS change begin
					if($line->qty==99) print 'background:'.getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#adadcf').';';          // Sub-total level 1
					else if($line->qty==98) print 'background:'.getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#ddddff').';';    // Sub-total level 2
					else if($line->qty<=97 && $line->qty>=91) print 'background:'.getDolGlobalString('SUBTOTAL_SUBTOTAL_BACKGROUNDCOLOR', '#eeeeff').';';  // Sub-total level 3 to 9
					else if($line->qty==1) print 'background:'.getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#adadcf').';';     // Title level 1
					else if($line->qty==2) print 'background:'.getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#ddddff').';';     // Title level 2
					else if($line->qty==50) print '';                       // Free text
					else print 'background:'.getDolGlobalString('SUBTOTAL_TITLE_BACKGROUNDCOLOR', '#eeeeff').';';                       // Title level 3 to 9

					// InfraS change end
				}
				else
				{
                    if($line->qty==99) $object->tpl['sub-tr-style'].= 'background:#ddffdd';         // Sub-total level 1
                    else if($line->qty==98) $object->tpl['sub-tr-style'].= 'background:#ddddff;';	// Sub-total level 2
                    else if($line->qty==2) $object->tpl['sub-tr-style'].= 'background:#eeeeff; ';	// Title level 2
                    else if($line->qty==50) $object->tpl['sub-tr-style'].= '';                      // Free text
                    else $object->tpl['sub-tr-style'].= 'background:#eeffee;';                      // Title level 1, Sub-total level 1 and 3 to 9
				}


				$object->tpl['sub-td-style'] = '';
				if ($line->qty>90) $object->tpl['sub-td-style'] = 'style="text-align:right"';


				if (getDolGlobalString('SUBTOTAL_USE_NEW_FORMAT'))
				{
					if(TSubtotal::isTitle($line) || TSubtotal::isSubtotal($line))
					{
						$object->tpl["sublabel"] = str_repeat('&nbsp;&nbsp;&nbsp;', max(floatval($line->qty) - 1, 0));

						if (TSubtotal::isTitle($line)) $object->tpl["sublabel"].= img_picto('', 'subtotal@subtotal').'<span style="font-size:9px;margin-left:-3px;">'.$line->qty.'</span>&nbsp;&nbsp;';
						else $object->tpl["sublabel"].= img_picto('', 'subtotal2@subtotal').'<span style="font-size:9px;margin-left:-1px;">'.(100-$line->qty).'</span>&nbsp;&nbsp;';
					}
				}
				else
				{
					$object->tpl["sublabel"] = '';
					if($line->qty<=1) $object->tpl["sublabel"] = img_picto('', 'subtotal@subtotal');
					else if($line->qty==2) $object->tpl["sublabel"] = img_picto('', 'subsubtotal@subtotal').'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
				}
				// Get display styles and apply them
				$titleStyleItalic = strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'I') === false ? '' : ' font-style: italic;';
				$titleStyleBold =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'B') === false ? '' : ' font-weight:bold;';
				$titleStyleUnderline =  strpos(getDolGlobalString('SUBTOTAL_TITLE_STYLE'), 'U') === false ? '' : ' text-decoration: underline;';

				if (empty($line->label)) {
					if ($line->qty >= 91 && $line->qty <= 99 && getDolGlobalString('CONCAT_TITLE_LABEL_IN_SUBTOTAL_LABEL')) $object->tpl["sublabel"].=  $line->description.' '.$this->getTitle($object, $line);
					else $object->tpl["sublabel"].=  $line->description;
				}
				else {

					if (getDolGlobalString('PRODUIT_DESC_IN_FORM') && !empty($line->description)) {
						$object->tpl["sublabel"].= '<span class="subtotal_label" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" >'.$line->label.'</span><br><div class="subtotal_desc">'.dol_htmlentitiesbr($line->description).'</div>';
					}
					else{
						$object->tpl["sublabel"].= '<span class="subtotal_label classfortooltip" style="'.$titleStyleItalic.$titleStyleBold.$titleStyleUnderline.'" title="'.$line->description.'">'.$line->label.'</span>';
					}

				}
				if($line->qty>90)
				{
					$total = $this->getTotalLineFromObject($object, $line, '');
					$object->tpl["sublabel"].= ' : <b>'.$total.'</b>';
				}

                $object->printOriginLine($line, '', $restrictlist, '/core/tpl', $selectedLines);

                unset($object->tpl["sublabel"]);
                unset($object->tpl['sub-td-style']);
                unset($object->tpl['sub-tr-style']);
                unset($object->tpl['sub-type']);
                unset($object->tpl['subtotal']);

                return 1;
			}
		}

        return 0;
	}

    /**
     * For compatibility with dolibarr <= v14
     *
     * @param array $parameters
     * @param CommonObject $object
     * @param string $action
     * @param HookManager $hookmanager
     * @return int
     */
    public function printOriginObjectLine($parameters, $object, &$action, $hookmanager){
        return $this->printOriginObjectSubLine($parameters, $object, $action, $hookmanager);
    }

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
		global $langs, $db;

		if ($object->statut == 0 && getDolGlobalString('SUBTOTAL_MANAGE_COMPRIS_NONCOMPRIS') && $action != 'editline')
		{

		    if($object->element == 'invoice_supplier' || $object->element == 'order_supplier')
		    {
		        foreach ($object->lines as $line)
		        {
		            // fetch optionals attributes and labels
		            require_once(DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php');
		            $extrafields=new ExtraFields($this->db);
		            $extralabels=$extrafields->fetch_name_optionals_label($object->table_element_line,true);
		            $line->fetch_optionals($line->id,$extralabels);
		        }
		    }

			$TSubNc = array();
			foreach ($object->lines as &$l)
			{
				$TSubNc[$l->id] = (int) ($l->array_options['options_subtotal_nc']??0);
			}

			print '<script type="text/javascript" src="'.dol_buildpath('subtotal/js/subtotal.lib.js', 1).'"></script>';

			$form = new Form($db);
			?>

			<script type="text/javascript">
				$(function() {
					var subtotal_TSubNc = <?php echo json_encode($TSubNc); ?>;
					$("#tablelines tr").each(function(i, item) {
						if ($(item).children('.subtotal_nc').length == 0)
						{
							var id = $(item).attr('id');

							if ((typeof id != 'undefined' && id.indexOf('row-') == 0) || $(item).hasClass('liste_titre'))
							{
								let tableNCColSelector = 'td';
								if($(item).hasClass('liste_titre') && $(item).children('th:last-child').length > 0 &&  $(item).children('td:last-child').length == 0){
									tableNCColSelector = 'th'; // In Dolibarr V20.0 title use th instead of td
								}

								$(item).children(`${tableNCColSelector}:last-child`).before(`<${tableNCColSelector} class="subtotal_nc"></${tableNCColSelector}>`);

								if ($(item).attr('rel') != 'subtotal' && typeof $(item).attr('id') != 'undefined')
								{
									var idSplit = $(item).attr('id').split('-');
									$(item).children(`${tableNCColSelector}.subtotal_nc`).append($('<input type="checkbox" id="subtotal_nc-'+idSplit[1]+'" class="subtotal_nc_chkbx" data-lineid="'+idSplit[1]+'" value="1" '+(typeof subtotal_TSubNc[idSplit[1]] != 'undefined' && subtotal_TSubNc[idSplit[1]] == 1 ? 'checked="checked"' : '')+' />'));
								}
							}
							else
							{
								$(item).append('<td class="subtotal_nc"></td>');
							}
						}
					});

					$('#tablelines tr.liste_titre:first .subtotal_nc').html(<?php echo json_encode($form->textwithtooltip($langs->trans('subtotal_nc_title'), $langs->trans('subtotal_nc_title_help'))); ?>);

					function callAjaxUpdateLineNC(set, lineid, subtotal_nc)
					{
						$.ajax({
							url: '<?php echo dol_buildpath('/subtotal/script/interface.php', 1); ?>'
							,type: 'POST'
							,data: {
								json:1
								,set: set
								,element: '<?php echo $object->element; ?>'
								,elementid: <?php echo (int) $object->id; ?>
								,lineid: lineid
								,subtotal_nc: subtotal_nc
							}
						}).done(function(response) {
							window.location.href = window.location.pathname + '?id=<?php echo $object->id; ?>&page_y=' + window.pageYOffset;
						});
					}

					$(".subtotal_nc_chkbx").change(function(event) {
						var lineid = $(this).data('lineid');
						var subtotal_nc = 0 | $(this).is(':checked'); // Renvoi 0 ou 1

						callAjaxUpdateLineNC('updateLineNC', lineid, subtotal_nc);
					});

				});

			</script>
			<?php
		}

		$this->_ajax_block_order_js($object);

		$jsConfig = array(
			'langs' => array(
				'SubtotalSummaryTitle' => $langs->trans('QuickSummary')
			),
			'useOldSplittedTrForLine' => intval(DOL_VERSION) < 16 ? 1 : 0
		);

		print '<script type="text/javascript"> if (typeof subtotalSummaryJsConf === undefined) { var subtotalSummaryJsConf = {}; } subtotalSummaryJsConf = '.json_encode($jsConfig).'; </script>'; // used also for subtotal.lib.js

		if(!getDolGlobalString('SUBTOTAL_DISABLE_SUMMARY')){
			$jsConfig = array(
				'langs' => array(
					'SubtotalSummaryTitle' => $langs->trans('QuickSummary')
				),
				'useOldSplittedTrForLine' => intval(DOL_VERSION) < 16 ? 1 : 0
			);
			print '<link rel="stylesheet" type="text/css" href="'.dol_buildpath('subtotal/css/summary-menu.css', 1).'">';
			print '<script type="text/javascript" src="'.dol_buildpath('subtotal/js/summary-menu.js', 1).'"></script>';
		}

		return 0;
	}

	function afterPDFCreation($parameters, &$pdf, &$action, $hookmanager)
	{
		global $conf;

		$object = $parameters['object'];

		if ((getDolGlobalString('SUBTOTAL_PROPAL_ADD_RECAP') && $object->element == 'propal') || (getDolGlobalString('SUBTOTAL_COMMANDE_ADD_RECAP') && $object->element == 'commande') || (getDolGlobalString('SUBTOTAL_INVOICE_ADD_RECAP') && $object->element == 'facture'))
		{
			if (GETPOST('subtotal_add_recap', 'none') && empty($parameters['fromInfraS'])) {	// InfraS change
				dol_include_once('/subtotal/class/subtotal.class.php');
				TSubtotal::addRecapPage($parameters, $pdf);
			}
		}

		return 0;
	}

	/** Overloading the getlinetotalremise function : replacing the parent's function with the one below
	 * @param      $parameters  array           meta datas of the hook (context, etc...)
	 * @param      $object      CommonObject    the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param      $action      string          current action (if set). Generally create or edit or null
	 * @param      $hookmanager HookManager     current hook manager
	 * @return     void
	 */
	function getlinetotalremise($parameters, &$object, &$action, $hookmanager)
	{
        // Si c'est une ligne de sous-total, la méthode pdfGetLineTotalDiscountAmount ne doit rien renvoyer
        if (!empty($object->lines[$parameters['i']]) && TSubtotal::isModSubtotalLine($object->lines[$parameters['i']])) {
            $this->resprints = '';
            $this->results = [];

            return 1;
        }

        return 0;
    }

	// HTML 5 data for js
	private function _getHtmlData($parameters, &$object, &$action, $hookmanager)
	{
		dol_include_once('/subtotal/class/subtotal.class.php');

	    $line = &$parameters['line'];

	    $ThtmlData['data-id']           = $line->id;
	    $ThtmlData['data-product_type'] = $line->product_type;
	    $ThtmlData['data-qty']          = 0; //$line->qty;
	    $ThtmlData['data-level']        = TSubtotal::getNiveau($line);

	    if(TSubtotal::isTitle($line)){
	        $ThtmlData['data-issubtotal'] = 'title';

			$ThtmlData['data-folder-status'] = 'open';
			if(!empty($line->array_options['options_hideblock'])){
				$ThtmlData['data-folder-status'] = 'closed';
			}
		}elseif(TSubtotal::isSubtotal($line)){
	        $ThtmlData['data-issubtotal'] = 'subtotal';
	    }
	    else{
	        $ThtmlData['data-issubtotal'] = 'freetext';
	    }

	    // Change or add data  from hooks
	    $parameters = array_replace($parameters , array(  'ThtmlData' => $ThtmlData )  );

	    // hook
	    $reshook = $hookmanager->executeHooks('subtotalLineHtmlData',$parameters,$object,$action); // Note that $action and $object may have been modified by hook
	    if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	    if ($reshook>0)
	    {
	        $ThtmlData = $this->results;
	    }

	    return $this->implodeHtmlData($ThtmlData);

	}


	function implodeHtmlData($ThtmlData = array())
	{
	    $data = '';
	    foreach($ThtmlData as $k => $h )
	    {
	        if(is_array($h))
	        {
	            $h = json_encode($h);
	        }

	        $data .= $k . '="'.dol_htmlentities($h, ENT_QUOTES).'" ';
	    }

	    return $data;
	}

	function _ajax_block_order_js($object)
	{
	    global $conf,$tagidfortablednd,$filepath,$langs;

	    /*
	     * this part of js is base on dolibarr htdocs/core/tpl/ajaxrow.tpl.php
	     * for compatibility reasons we don't use tableDnD but jquery sortable
	     */

	    $id=$object->id;
	    $nboflines=(isset($object->lines)?count($object->lines):0);
	    $forcereloadpage=!getDolGlobalString('MAIN_FORCE_RELOAD_PAGE')?0:1;

	    $id=$object->id;
	    $fk_element=$object->fk_element;
	    $table_element_line=$object->table_element_line;
	    $nboflines=(isset($object->lines)?count($object->lines):(empty($nboflines)?0:$nboflines));
	    $tagidfortablednd=(empty($tagidfortablednd)?'tablelines':$tagidfortablednd);
	    $filepath=(empty($filepath)?'':$filepath);


	    if (GETPOST('action','aZ09') != 'editline' && $nboflines > 1)
	    {

			$jsConf = array(
				'useOldSplittedTrForLine' => intval(DOL_VERSION) < 16 ? 1 : 0,
			);

			print '<script type="text/javascript" src="'.dol_buildpath('subtotal/js/subtotal.lib.js', 1).'"></script>';
	        ?>


			<script type="text/javascript">
			$(document).ready(function(){

				let subTotalConf = <?php print json_encode($jsConf); ?>;
				// target some elements
				var titleRow = $('tr[data-issubtotal="title"]');
				var lastTitleCol = titleRow.find('td:last-child');
				var moveBlockCol= titleRow.find('td.linecolht');


				moveBlockCol.disableSelection(); // prevent selection
<?php if ($object->statut == 0) { ?>
				// apply some graphical stuff
				moveBlockCol.css("background-image",'url(<?php echo dol_buildpath('subtotal/img/grip_all.png',2);  ?>)');
				moveBlockCol.css("background-repeat","no-repeat");
				moveBlockCol.css("background-position","center center");
				moveBlockCol.css("cursor","move");
				titleRow.attr('title', '<?php echo html_entity_decode($langs->trans('MoveTitleBlock')); ?>');


 				$( "#<?php echo $tagidfortablednd; ?>" ).sortable({
			    	  cursor: "move",
			    	  handle: ".movetitleblock",
			    	  items: 'tr:not(.nodrag,.nodrop,.noblockdrop)',
			    	  delay: 150, //Needed to prevent accidental drag when trying to select
			    	  opacity: 0.8,
			    	  axis: "y", // limit y axis
			    	  placeholder: "ui-state-highlight",
			    	  start: function( event, ui ) {

						  let colCount = 0;
						  let uiChildren = ui.item.children();
						  colCount = uiChildren.length;

						  if (uiChildren.length > 0) {
							  uiChildren.each(function( index ) {
								  let colspan = $( this ).attr('colspan');
								  if(colspan != null && colspan != '' &&  parseFloat(colspan) > 1){
								    colCount+= parseFloat(colspan);
								  }
							  });
						  }

   						  ui.placeholder.html('<td colspan="'+colCount+'">&nbsp;</td>');

			    		  var TcurrentChilds = getSubtotalTitleChilds(ui.item);
			    		  ui.item.data('childrens',TcurrentChilds); // store data

			    		  for (var key in TcurrentChilds) {
			    			  $('#'+ TcurrentChilds[key]).addClass('noblockdrop');//'#row-'+
			    			  $('#'+ TcurrentChilds[key]).fadeOut();//'#row-'+
			    		  }

			    		  $(this).sortable("refresh");	// "refresh" of source sortable is required to make "disable" work!

			    	    },
				    	stop: function (event, ui) {
							// call we element is droped
				    	  	$('.noblockdrop').removeClass('noblockdrop');

				    	  	var TcurrentChilds = ui.item.data('childrens'); // reload child list from data and not attr to prevent load error

							for (var i =TcurrentChilds.length ; i >= 0; i--) {
				    			  $('#'+ TcurrentChilds[i]).insertAfter(ui.item); //'#row-'+
				    			  $('#'+ TcurrentChilds[i]).fadeIn(); //'#row-'+
							}
							console.log('onstop');
							console.log(cleanSerialize($(this).sortable('serialize')));

							$.ajax({
			    	            data: {
									objet_id: <?php print $object->id; ?>,
							    	roworder: cleanSerialize($(this).sortable('serialize')),
									table_element_line: "<?php echo $table_element_line; ?>",
									fk_element: "<?php echo $fk_element; ?>",
									element_id: "<?php echo $id; ?>",
									filepath: "<?php echo urlencode($filepath); ?>",
									token: "<?php echo currentToken(); ?>"
								},
			    	            type: 'POST',
			    	            url: '<?php echo DOL_URL_ROOT; ?>/core/ajax/row.php',
			    	            success: function(data) {
			    	                console.log(data);
			    	            },
			    	        });

			    	  },
			    	  update: function (event, ui) {

			    	        // POST to server using $.post or $.ajax
				    	  	$('.noblockdrop').removeClass('noblockdrop');
							//console.log('onupdate');
			    	        //console.log(cleanSerialize($(this).sortable('serialize')));
			    	    }
			    });
 				<?php } ?>

			});
			</script>
			<style type="text/css" >

            tr.ui-state-highlight td{
            	border: 1px solid #dad55e;
            	background: #fffa90;
            	color: #777620;
            }

			.subtotal-line-action-btn {
				margin-right: 5px;
			}
			</style>
		<?php

		}



	}

/**
     * @param $parameters
     * @param $object
     * @param $action
     * @param $hookmanager
     */
	function handleExpeditionTitleAndTotal($parameters, &$object, &$action, $hookmanager){
        global $conf;
        //var_dump($parameters['line']);
	    dol_include_once('subtotal/class/subtotal.class.php');
        $currentcontext = explode(':', $parameters['context']);

	    if ( in_array('shippableorderlist',$currentcontext)) {

            //var_dump($parameters['line']);
            if(TSubtotal::isModSubtotalLine($parameters['line'])) {

                $confOld = getDolGlobalString('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT');
                getDolGlobalString('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT', 0);
                $res =  $parameters['shipping']->addline($parameters['TEnt_comm'][$object->order->id], $parameters['line']->id, $parameters['line']->qty);
                getDolGlobalString('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT', $confOld);
            }

        }

    }

	/**
	 * Overloading the defineColumnField function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonDocGenerator object      $pdfDoc         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function defineColumnField($parameters, &$pdfDoc, &$action, $hookmanager)
	{

		// If this model is column field compatible it will add info to change subtotal behavior
		$parameters['object']->context['subtotalPdfModelInfo']->cols = $pdfDoc->cols;

			$parameters['object']->context['subtotalPdfModelInfo']->cols = $pdfDoc->cols;
			// HACK Pour passer les paramettres du model dans les hooks sans infos
			$parameters['object']->context['subtotalPdfModelInfo']->marge_droite 	= $pdfDoc->marge_droite;
			$parameters['object']->context['subtotalPdfModelInfo']->marge_gauche 	= $pdfDoc->marge_gauche;
			$parameters['object']->context['subtotalPdfModelInfo']->page_largeur 	= $pdfDoc->page_largeur;
			$parameters['object']->context['subtotalPdfModelInfo']->page_hauteur 	= $pdfDoc->page_hauteur;
			$parameters['object']->context['subtotalPdfModelInfo']->format 		= $pdfDoc->format;
		    if (property_exists($pdfDoc, 'context') && array_key_exists('subtotalPdfModelInfo', $pdfDoc->context) && is_object($pdfDoc->context['subtotalPdfModelInfo'])) {
                $parameters['object']->context['subtotalPdfModelInfo']->defaultTitlesFieldsStyle = $pdfDoc->context['subtotalPdfModelInfo']->defaultTitlesFieldsStyle;
                $parameters['object']->context['subtotalPdfModelInfo']->defaultContentsFieldsStyle = $pdfDoc->context['subtotalPdfModelInfo']->defaultContentsFieldsStyle;
		    }
		return 0;
	}

	/**
	 * Add a checkbox on the bill orders forms (either the old orderstoinvoice or the new mass
	 * action) to create a title block per invoiced order when creating one invoice per client.
	 */
	private function _billOrdersAddCheckBoxForTitleBlocks()
	{
		global $delayedhtmlcontent, $langs, $conf;

		ob_start();
		$jsConf = array(
			'langs'=>  array(
				'AddTitleBlocFromOrdersToInvoice' => $langs->trans('subtotal_add_title_bloc_from_orderstoinvoice'),
				'AddShippingListToTile' => $langs->trans('AddShippingListToTile'),
				'SubtotalOptions' => $langs->trans('SubtotalOptions'),
				'UseHiddenConfToAutoCheck' => $langs->trans('UseHiddenConfToAutoCheck'),
			),
			'isModShippingEnable' => !empty($conf->expedition->enabled),
			'SUBTOTAL_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC' => getDolGlobalInt('SUBTOTAL_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC')
		);
		?>
			<script type="text/javascript">
				$(function() {
					let jsConf = <?php print json_encode($jsConf); ?>;

					let tr = '<tr><td>'+jsConf.langs.SubtotalOptions+'</td><td>';
					tr+= '<label><input type="checkbox" value="1" name="subtotal_add_title_bloc_from_orderstoinvoice" checked="checked" /> '+jsConf.langs.AddTitleBlocFromOrdersToInvoice+'</label>';
					if(jsConf.isModShippingEnable){
						tr+= '<br/><label><input type="checkbox" value="1" name="subtotal_add_shipping_list_to_title_desc" /> '+jsConf.langs.AddShippingListToTile+' <i class="fa fa-question-circle" title="'+jsConf.langs.UseHiddenConfToAutoCheck+' SUBTOTAL_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC"></label>';
					}
					tr+= '<td></tr>';

					let $noteTextArea = $("textarea[name=note]");
					if ($noteTextArea.length === 1) {
						$noteTextArea.closest($('tr')).after(tr);
						return;
					}
					let $inpCreateBills = $("#validate_invoices");
					if ($inpCreateBills.length === 1) {
						$inpCreateBills.closest($('tr')).after(tr);
					}
				});
			</script>
		<?php
		$delayedhtmlcontent .= ob_get_clean();
	}

	/**
	 * Re-generate the document after creation of recurring invoice by cron
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonDocGenerator object      $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function afterCreationOfRecurringInvoice($parameters, &$object, &$action, $hookmanager){
        require_once __DIR__ . '/subtotal.class.php';
        $TSub = new TSubtotal;
        $TSub->generateDoc($object);
        return 0;
    }

	public function printCommonFooter(&$parameters, &$objectHook, &$action, $hookmanager)
	{
			global $langs, $db, $conf;

			$contextArray = explode(':',$parameters['context']);

			/**Gestion des dossiers qui permettent de réduire un bloc**/
			if (
			 in_array('invoicecard',$contextArray)
				|| in_array('invoicesuppliercard',$contextArray)
				|| in_array('propalcard',$contextArray)
				|| in_array('ordercard',$contextArray)
				|| in_array('ordersuppliercard',$contextArray)
				|| in_array('invoicereccard',$contextArray)
			)
			{
				//On récupère les informations de l'objet actuel
				$id = GETPOST('id', 'int');
				if(empty($id)) $id = GETPOST('facid', 'int');

				//On détermine l'élement concernée en fonction du contexte
				$TCurrentContexts = explode('card', $parameters['currentcontext']);
				/**
				 *  TODO John le 11/08/2023 : Je trouve bizarre d'utiliser le contexte pour déterminer la class de l'objet alors
				 *    que l'objet est passé en paramètres ça doit être due à de vielle versions de Dolibarr ou une compat avec un module externe...
				 *    Cette methode de chargement d'objet a causée une fatale car la classe de l'objet correspondant au contexte n'était pas chargé ce qui n'est pas logique...
				 *    La logique voudrait que l'on utilise $object->element
				 *    Cependant si on regarde plus loin $object qui est passé en référence dans les paramètres de cette méthode est remplacé quelques lignes plus bas.
				 */
				if($TCurrentContexts[0] == 'order'){
					$element = 'Commande';
					if(!class_exists($element)){ require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';}
				}
				elseif($TCurrentContexts[0] == 'invoice'){
					$element = 'Facture';
					if(!class_exists($element)){ require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';}
				}
				elseif($TCurrentContexts[0] == 'invoicesupplier'){
					$element = 'FactureFournisseur';
					if(!class_exists($element)){ require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';}
				}
				elseif($TCurrentContexts[0] == 'ordersupplier'){
					$element = 'CommandeFournisseur';
					if(!class_exists($element)){ require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';}
				}
				elseif($TCurrentContexts[0] == 'invoicerec'){
					$element = 'FactureRec';
					if(!class_exists($element)){ require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';}
				}
				else $element = $TCurrentContexts[0];


				if(!class_exists($element)){
					// Pour éviter la fatale sur une page d'un module externe qui utiliserait un nom de context de Dolibarr mais qui
					$this->error = $langs->trans('ErrorClassXNotExists', $element);
					return -1;
				}

				$object = new $element($db);
				$object->fetch($id);

				//On récupère tous les titres sous-total
				$TLines = TSubtotal::getAllTitleFromDocument($object);

				//On définit quels sont les blocs à cacher en fonction des données existantes (hideblock)
			$TBlocksToHide = array();
			if(!empty($TLines)) {
					foreach ($TLines as $line) {
						if (array_key_exists('options_hideblock', $line->array_options) && $line->array_options['options_hideblock']) $TBlocksToHide[] = $line->id;
					}
				}


				$hideMode = getDolGlobalString('SUBTOTAL_BLOC_FOLD_MODE', 'default');
				if(!in_array($hideMode, array('default', 'keepTitle'))){
					$hideMode = 'default';
				}


				$jsConf = array(
					'linesToHide' => $TBlocksToHide,
					'hideFoldersByDefault' => getDolGlobalInt('SUBTOTAL_HIDE_FOLDERS_BY_DEFAULT'),
					'closeMode' => $hideMode, // default, keepTitle
					'interfaceUrl'=> dol_buildpath('/subtotal/script/interface.php', 1),
					'element' => $element,
					'element_id' => $id,
					'img_folder_closed' => img_picto('', 'folder'),
					'img_folder_open' => img_picto('', 'folder-open'),
					'langs' => array(
						'Subtotal_HideAll' => $langs->transnoentities("Subtotal_HideAll"),
						'Subtotal_ShowAll' => $langs->transnoentities("Subtotal_ShowAll"),
						'Subtotal_Hide' => $langs->transnoentities("Subtotal_Hide"),
						'Subtotal_Show' => $langs->transnoentities("Subtotal_Show"),
						'Subtotal_ForceHideAll' => $langs->transnoentities("Subtotal_ForceHideAll"),
						'Subtotal_ForceShowAll' => $langs->transnoentities("Subtotal_ForceShowAll")
					)
				);

				print '<script type="text/javascript" src="'.dol_buildpath('subtotal/js/subtotal.lib.js', 1).'"></script>';

				?>
				<style>
					.fold-subtotal-container{
						-webkit-user-select: none; /* Safari */
						-ms-user-select: none; /* IE 10 and IE 11 */
						user-select: none; /* Standard syntax */
					}

					.toggle-all-folder-status, .fold-subtotal-btn{
						cursor: pointer;
					}
					.fold-subtotal-btn[data-toggle-all-children="1"]{
						color: rgb(190, 53, 53);
					}
					.toggle-all-folder-status:hover, .fold-subtotal-btn:hover{
						color: var(--colortextlink, rgb(10, 20, 100));
					}
					.fold-subtotal-btn[data-toggle-all-children="1"]:hover{
						color: rgb(138, 28, 28);
					}
				</style>
				<script type="text/javascript">
					// TODO : mettre ça dans une classe js
					$(document).ready(function(){
						// Utilisation d'une sorte de namespace en JS
						subtotalFolders = {};
						(function(o) {
							o.config = <?php print json_encode($jsConf); ?> ;

							/**
							 * Dolibarr token
							 * @type {string}
							 */
							o.newToken = '';

							/**
							 *
							 * @param {int} titleId
							 */
							o.countHiddenLinesForTitle = function(titleId){
								let $titleLine = $('#row-' + titleId);
								let childrenList = getSubtotalTitleChilds($titleLine, true); // renvoi la liste des id des enfants
								let totalHiddenLines = 0;
								if(childrenList.length > 0) {
									childrenList.forEach((childLineId) => {
										let $childLine = $('#'+childLineId);
										if(!$childLine.is(":visible")){
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
							o.updateHiddenLinesCountInfoForParentTitles = function($childTilteLine){
								let parentTitles = o.getTitleParents($childTilteLine);
								if(parentTitles.length>0){
									parentTitles.forEach((parentTitleLineId) => {
										let $titleCollapseInfos = $('.fold-subtotal-info[data-title-line-target="' + parentTitleLineId + '"]');
										if($titleCollapseInfos.length>0){
											let totalHiddenLines = o.countHiddenLinesForTitle(parentTitleLineId);
											$titleCollapseInfos.html('('+ totalHiddenLines +')');
											if(totalHiddenLines == 0){
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
							o.addTitleParentId = function($childLine, titleId){
								// Ajoute l'id parent si se n'est pas déja fait
								let parentTitleIds = $childLine.attr('data-parent-titles');
								if(parentTitleIds != null){
									let parentTitleIdsList = parentTitleIds.split(",");
									if(!parentTitleIdsList.includes(titleId)){
										$childLine.attr('data-parent-titles', parentTitleIds + ',' + titleId);
									}
								}else{
									$childLine.attr('data-parent-titles', titleId);
								}
							}

							/**
							 * @param {jQuery}  $childLine
							 * @param {int} titleId
							 * @return []
							 */
							o.getTitleParents = function($childLine){
								let result = [];
								let parentTitleIds = $childLine.attr('data-parent-titles');
								if(parentTitleIds != null){
									return parentTitleIds.split(",");
								}

								return result;
							}

							/**
							 *
							 * @param {int} titleId
							 * @param toggleStatus : open, closed
							 */
							o.toggleChildFolderStatusDisplay = function(titleId, toggleStatus = 'open'){
								let $titleLine = $('#row-' + titleId);
								let $collapseBtn = $('.fold-subtotal-btn[data-title-line-target="' + titleId + '"]');
								let $collapseSimpleBtn = $('.fold-subtotal-btn[data-title-line-target="' + titleId + '"][data-toggle-all-children="0"]');
								let $collapseAllBtn = $('.fold-subtotal-btn[data-title-line-target="' + titleId + '"][data-toggle-all-children="1"]');
								let $collapseInfos = $('.fold-subtotal-info[data-title-line-target="' + titleId + '"]');

								if($titleLine.length>0){
									$titleLine.attr('data-folder-status', toggleStatus);
									let haveTitle = false;
									let childrenList = getSubtotalTitleChilds($titleLine, true); // renvoi la liste des id des enfants
									let totalHiddenLines = 0;

									if(childrenList.length > 0) {

										let doNotDisplayLines = []; // Dans le cas de l'ouverture il faut vérifier que les titres enfants ne sont pas fermés avant d'ouvrir
										let doNotHiddeLines = []; // En mode keepTitle: Dans le cas de la fermeture il faut vérifier que les titres enfants ne sont pas ouvert avant de fermer

										childrenList.forEach((childLineId) => {
											let $childLine = $('#'+childLineId);

											if ($childLine.attr('data-issubtotal') == "title"){

												// Ajoute l'id parent si se n'est pas déja fait
												o.addTitleParentId($childLine, titleId);

												haveTitle = true;
												// Dans le cas de l'ouverture il faut vérifier que les titres enfants ne sont pas fermés avant d'ouvrir
												let grandChildrenList = getSubtotalTitleChilds($childLine, true); // renvoi la liste des id des enfants

												if($childLine.attr('data-folder-status') == "closed"){
													doNotDisplayLines = doNotDisplayLines.concat(grandChildrenList);
												}
												else if(o.config.closeMode == 'keepTitle' && $childLine.attr('data-folder-status') == "open"){
													doNotHiddeLines = doNotDisplayLines.concat(grandChildrenList);
												}
											}

											if (toggleStatus == 'closed') {
												if(o.config.closeMode == 'keepTitle' && ($childLine.attr('data-issubtotal') == "title" || $childLine.attr('data-issubtotal') == "subtotal"  )){
													$childLine.show();
												}else if(!doNotHiddeLines.includes(childLineId)){
													$childLine.hide();
												}
											} else {
												if(!doNotDisplayLines.includes(childLineId)){
													$childLine.show();
												}
											}

											if(!$childLine.is(":visible")){
												totalHiddenLines++;
											}
										});
									}

									$collapseInfos.html('('+ totalHiddenLines +')');
									if(totalHiddenLines == 0){
										$collapseInfos.html('');
									}

									// Mise à jour des parents pour l'affichage du nombre de lignes cachées
									o.updateHiddenLinesCountInfoForParentTitles($titleLine);

									if(toggleStatus == 'closed') {
										$collapseBtn.html(o.config.img_folder_closed);
										$collapseSimpleBtn.attr('title', o.config.langs.Subtotal_Show);
										$collapseAllBtn.attr('title', o.config.langs.Subtotal_ForceShowAll);
									}else{
										$collapseBtn.html(o.config.img_folder_open);
										$collapseSimpleBtn.attr('title', o.config.langs.Subtotal_Hide);
										$collapseAllBtn.attr('title', o.config.langs.Subtotal_ForceHideAll);
									}

									// Si pas de titre pas besoin d'afficher le bouton dossier rouge
									if(haveTitle){
										$collapseAllBtn.show();
									}else{
										$collapseAllBtn.hide();
									}
								}
							}

							// initialisation des lignes affichées ou non
							$('tr[data-issubtotal="title"]').each(function() {
								let lineId = $( this ).attr('data-id');
								if(lineId != null){
									if(o.config.linesToHide.includes(lineId)){
										o.toggleChildFolderStatusDisplay(lineId, 'closed');
									}else{
										if (o.config.hideFoldersByDefault == 1) {
											o.toggleChildFolderStatusDisplay(lineId, 'closed');
										} else {
											o.toggleChildFolderStatusDisplay(lineId, 'open');
										}
									}
								}
							});

							// Lors du clic sur un dossier, on cache ou faire apparaitre les lignes contenues dans le bloc concerné
							$(document).on("click",".fold-subtotal-btn",function(event) {
								event.preventDefault();
								let targetTitleLineId = $(this).attr('data-title-line-target');
								if(targetTitleLineId != undefined){
									// folderManage_click(targetTitleLineId);
									let titleRow = $('#row-' + targetTitleLineId);
									let newStatus = titleRow.attr('data-folder-status') == 'closed' ? 'open' : 'closed'
									let sendData = {
										element : o.config.element,
										element_id : o.config.element_id,
										titleStatusList : [{
											'id': targetTitleLineId,
											'status': newStatus !== 'closed' ? 0 : 1,
										}]
									};

									/**
									 * Pour les boutons de type "block" bouton pour ouvrir / fermer tous les blocs enfants (ex dossier rouge)
									 **/
									if($(this).attr('data-toggle-all-children') == '1'){ //o.config.closeMode == 'keepTitle'
										let childrenList = getSubtotalTitleChilds(titleRow, true); // renvoi la liste des id des enfants
										if(childrenList.length > 0) {
											childrenList.forEach((childLineId) => {
												let $childLine = $('#'+childLineId);
												if ($childLine.attr('data-issubtotal') == "title"){
													sendData.titleStatusList.push({
														'id': $childLine.attr('data-id'),
														'status': newStatus !== 'closed' ? 0 : 1,
													});
													o.toggleChildFolderStatusDisplay($childLine.attr('data-id'), newStatus);
												}
											});
										}
									}

									o.toggleChildFolderStatusDisplay(targetTitleLineId, newStatus); // devrait être dans le callback ajax success mais pour plus d'ergonomie et rapidité de feedback je le sort
									o.callInterface('set' , 'update_hideblock_data', sendData, function(response){
										// TODO gérer un retour en cas d'érreur
										// o.toggleChildFolderStatusDisplay(targetTitleLineId, newStatus);
									})
								}
							});


							//Fonction qui permet d'ajouter l'option "Cacher les lignes" ou "Afficher les lignes"
							$('#tablelines>tbody:first').prepend(
								'<tr>' +
								'	<td colspan="100%" style="  text-align:right ">' +
								'		<span id="hide_all"  class="toggle-all-folder-status" data-folder-status="closed" >'+o.config.img_folder_open+'&nbsp;'+o.config.langs.Subtotal_HideAll+'</span>' +
								'		&nbsp;' +
								'		<span id="show_all" class="toggle-all-folder-status" data-folder-status="open"  >'+o.config.img_folder_closed + '&nbsp;'+o.config.langs.Subtotal_ShowAll+'</span>' +
								'	</td>' +
								'</tr>'
							);


							// Lors du clic sur un dossier, on cache ou faire apparaitre les lignes contenues dans le bloc concerné
							$(document).on("click",".toggle-all-folder-status",function(event) {
								event.preventDefault();
								newStatus = $( this ).attr('data-folder-status');
								$( this ).fadeOut();

								let sendData = {
									element : o.config.element,
									element_id : o.config.element_id,
									titleStatusList : []
								};

								$('#tablelines tr[data-issubtotal=title]').each(function( index ) {
									sendData.titleStatusList.push({
										'id': $( this ).attr('data-id'),
										'status': newStatus !== 'closed' ? 0 : 1,
									});

									//TODO manage response feedback to rollback display on error
									o.toggleChildFolderStatusDisplay($( this ).attr('data-id'), newStatus);
								});

								o.callInterface('set' , 'update_hideblock_data', sendData, function(response){
									// $('#tablelines tr[data-issubtotal=title]').each(function( index ) {
									// 	//TODO manage response feedback
									// });
								});


								$( this ).fadeIn();
							});


							o.checkListOfLinesIdHaveTitle = function(childrenList){
								if(!Array.isArray(childrenList)){
									return false;
								}

								childrenList.forEach((childLineId) => {
									let $childLine = $('#' + childLineId);
									if ($childLine.length > 0 && $childLine.attr('data-issubtotal') == "title") {
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
							o.callInterface = function ( typeAction = 'get' , action, sendData = {}, callBackFunction){

								let ajaxData = {
									'data': sendData,
									'token': o.newToken,
								};

								if(typeAction == 'set'){
									ajaxData.set = action;
								}else{
									ajaxData.get = action;
								}

								$.ajax({
									method: 'POST',
									url: o.config.interfaceUrl,
									dataType: 'json',
									data: ajaxData,
									success: function (response) {

										if (typeof callBackFunction === 'function'){
											callBackFunction(response);
										} else {
											console.error('Callback function invalide for callKanbanInterface');
										}

										if(response.newToken != undefined){
											o.newToken = response.newToken;
										}

										if(response.msg.length > 0) {
											o.setEventMessage(response.msg, response.result > 0 ? true : false, response.result == 0 ? true : false );
										}
									},
									error: function (err) {

										if(err.responseText.length > 0){

											// detect login page in case of just disconnected
											let loginPage = $(err.responseText).find('[name="actionlogin"]');
											if(loginPage != undefined && loginPage.val() == 'login'){
												o.setEventMessage(o.langs.errorAjaxCallDisconnected, false);

												setTimeout(function (){
													location.reload();
												}, 2000);

											}else{
												o.setEventMessage(o.langs.errorAjaxCall, false);
											}
										}
										else{
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
							o.setEventMessage = function (msg, status = true, sticky = false){

								let jnotifyConf = {
									delay: 1500                               // the default time to show each notification (in milliseconds)
									, type : 'error'
									, sticky: sticky                             // determines if the message should be considered "sticky" (user must manually close notification)
									, closeLabel: "&times;"                     // the HTML to use for the "Close" link
									, showClose: true                           // determines if the "Close" link should be shown if notification is also sticky
									, fadeSpeed: 150                           // the speed to fade messages out (in milliseconds)
									, slideSpeed: 250                           // the speed used to slide messages out (in milliseconds)
								}


								if(msg.length > 0){
									if(status){
										jnotifyConf.type = '';
										$.jnotify(msg, jnotifyConf);
									}
									else{
										$.jnotify(msg, jnotifyConf);
									}
								}
								else{
									$.jnotify('ErrorMessageEmpty', jnotifyConf);
								}
							}

						})(subtotalFolders);

					});
				</script>

				<?php
			}
        return 0;
	}
}
