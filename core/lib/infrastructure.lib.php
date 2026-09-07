<?php
	/**************************************************
	* Copyright (C) 2025-2026	Sylvain Legrand - <contact@infras.fr>	InfraS - <https://www.infras.fr>
	* Copyright (C) 2025-2026	Fallinah Ranasolonirina	- <contact@infras.fr>	InfraS - <https://www.infras.fr>
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
	**************************************************/

	/***************************************************
	*	\file		./infrastructure/core/lib/infrastructure.lib.php
	*	\ingroup	infrastructure
	*	\brief		This file is an example module library
	*				Put some comments here
	**************************************************/

	// Libraries ****************************
	include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
	dol_include_once('/infrastructure/class/infrastructure.class.php');
	if (isModEnabled('ouvrage')) {
		dol_include_once('/ouvrage/class/ouvrage.class.php');
	}

	/**
	*	Compute the CSS class string (with leading space) to apply on a special infrastructure line.
	*	Centralise la logique de classification des lignes spéciales pour les modes/styles dépendant du niveau et du type
	*	(titre 1-9, sous-total 91-99, texte libre 50).
	*
	*	@param	CommonObjectLine	$line	Ligne spéciale du module
	*	@return	string						Classes CSS à concaténer (ex. " newInfrastructure subtitleLevel2")
	*/
	function infrastructure_getLineSpecialClass($line)
	{
		$class	= ' newInfrastructure';
		if (TInfrastructure::isTitle($line)) {
			$class	.= ' subtitleLevel'.$line->qty;					// Sub-title level 1 to 9
		} elseif (TInfrastructure::isTotal($line)) {
			$class	.= ' infrastructureLevel'.(100 - $line->qty);	// Sub-total level 99 (1) to 91 (9)
		} elseif (TInfrastructure::isFreeText($line)) {
			$class	.= ' infrastructureText';						// Free text
		}
		return $class;
	}

	/**
	*	Compute the inline CSS style (background-color + color) to apply on a special infrastructure line.
	*	Centralise la logique des nuances de couleurs par niveau pour les titres (qty 1-9), les sous-totaux (qty 91-99)
	*	et les textes libres (qty == 50). À compléter si on veut plus de nuances de couleurs avec les niveaux 4 à 9.
	*
	*	@param	CommonObjectLine	$line	Ligne spéciale du module
	*	@return	string						Style CSS inline (vide si la ligne ne correspond à aucun cas géré)
	*/
	function infrastructure_getLineSpecialStyle($line)
	{
		$brightness	= getDolGlobalInt('INFRASTRUCTURE_TITLE_AND_TOTAL_BRIGHTNESS_PERCENTAGE', 10);
		if ($line->qty >= 91 && $line->qty <= 99) {
			$bg		= getDolGlobalString('INFRASTRUCTURE_TOTAL_BACKGROUND_COLOR', '#adadcf');
			$color	= getDolGlobalString('INFRASTRUCTURE_TOTAL_COLOR', '000000');
			$offset	= $line->qty < 99 ? (99 - $line->qty) * $brightness : 1;
			return 'background: none; background-color:'.colorLighten($bg, $offset).' !important; color:'.$color.' !important;';
		} elseif ($line->qty >= 1 && $line->qty <= 9) {
			$bg		= getDolGlobalString('INFRASTRUCTURE_TITLE_BACKGROUND_COLOR', '#adadcf');
			$color	= getDolGlobalString('INFRASTRUCTURE_TITLE_COLOR', '000000');
			$offset	= $line->qty > 1 ? ($line->qty - 1) * $brightness : 1;
			return 'background: none; background-color:'.colorLighten($bg, $offset).' !important; color:'.$color.' !important;';
		}
		return '';
	}

	/**
	* Add numerotation to title and infrastructure lines of an object
	*
	* @param	CommonObject	$object	Object
	* @return	void
	*/
	function infrastructure_addNumerotation(&$object)
	{
		if (getDolGlobalInt('INFRASTRUCTURE_USE_NUMEROTATION')) {
			$TLineTitle	= [];
			foreach ($object->lines as &$line) {
				if ($line->id > 0 && TInfrastructure::isModInfrastructureLine($line) && $line->qty <= 10) {
					$TLineTitle[] = &$line;
				}
			}
			if (!empty($TLineTitle)) {
				infrastructure_formatNumerotation($TLineTitle);
			}
		}
	}

	/**
	* List extrafields with "list"(Visible field) attribute > 0 for a given element type (propaldet, commandedet, ...)
	*
	* @param	string		$elementtype	Element type (propaldet, commandedet, facturedet, ...)
	* @return	array						List of extrafields [fieldname => label]
	*/
	function infrastructure_getVisibleExtrafields($elementtype) {
		global $db;

		$extrafields	= new ExtraFields($db);
		$all			= $extrafields->fetch_name_optionals_label($elementtype);
		$list			= [];
		foreach ($all as $key => $label) {
			if ((int) ($extrafields->attributes[$elementtype]['list'][$key] ?? 0) > 0) {
				$list[$key]	= $label;
			}
		}
		return $list;
	}

	/**
	* Ajax block order JS
	*
	* @param CommonObject$object Object
	* @return void
	*/
	function infrastructure_ajaxBlockOrderJs($object)
	{
		global $conf, $tagidfortablednd, $filepath, $langs;

		$id					= $object->id;
		$nboflines			= (isset($object->lines) ? count($object->lines) : 0);
		$forcereloadpage	= !getDolGlobalString('MAIN_FORCE_RELOAD_PAGE') ? 0 : 1;
		$fk_element			= $object->fk_element;
		$table_element_line	= $object->table_element_line;
		$nboflines			= (isset($object->lines) ? count($object->lines) : (empty($nboflines) ? 0 : $nboflines));
		$tagidfortablednd	= (empty($tagidfortablednd) ? 'tablelines' : $tagidfortablednd);
		$filepath			= (empty($filepath) ? '' : $filepath);
		$color				= getDolGlobalString('INFRASTRUCTURE_TITLE_COLOR_BLOC', 'be3535');
		if (GETPOST('action', 'aZ09') != 'editline' && $nboflines > 1) {
			print '<script type="text/javascript" src="'.dol_buildpath('infrastructure/js/infrastructure.lib.js', 1).'"></script>';
			?>
			<script type="text/javascript">
				$(document).ready(function () {
					// target some elements
					var titleRow = $('tr[data-isinfrastructure="title"]');
					var lastTitleCol = titleRow.find('td:last-child');
					var moveBlockCol = titleRow.find('td.linecolht');
					moveBlockCol.disableSelection(); // prevent selection
					<?php if ($object->statut == 0) { ?>
						// apply some graphical stuff
						moveBlockCol.html('<i class="fa fa-grip-horizontal" style="color:<?php echo $color; ?> !important;"></i>');
						moveBlockCol.css("text-align","center");
						moveBlockCol.css("cursor","move");
						titleRow.attr('title', '<?php echo html_entity_decode($langs->trans('InfrastructureMoveTitleBlock')); ?>');
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
									var TcurrentChilds = getInfrastructureTitleChilds(ui.item);
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
			<style type="text/css">
				tr.ui-state-highlight td{
					border: 1px solid #dad55e;
					background: #fffa90;
					color: #777620;
				}
				.infrastructure-line-action-btn {
					margin-right: 5px;
				}
			</style>
			<?php
		}
	}

	/**
	* Add a checkbox on the bill orders forms (either the old orderstoinvoice or the new mass
	* action) to create a title block per invoiced order when creating one invoice per client.
	*
	* @return void
	*/
	function infrastructure_billOrdersAddCheckBoxForTitleBlocks()
	{
		global $delayedhtmlcontent, $langs, $conf;

		ob_start();
		$jsConf = ['langs' => ['AddTitleBlocFromOrdersToInvoice'		=> $langs->trans('InfrastructureAddTitleBlocFromOrderstoinvoice'),
								'AddShippingListToTile'					=> $langs->trans('InfrastructureAddShippingListToTile'),
								'InfrastructureOptions'					=> $langs->trans('InfrastructureOptions'),
								'UseHiddenConfToAutoCheck'				=> $langs->trans('InfrastructureUseHiddenConfToAutoCheck'),
							],
						'isModShippingEnable' 										=> isModEnabled('expedition'),
						'INFRASTRUCTURE_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC'	=> getDolGlobalInt('INFRASTRUCTURE_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC')
					];
		?>
		<script type="text/javascript">
			$(function () {
				let jsConf = <?php print json_encode($jsConf); ?>;

				let tr = '<tr><td>' + jsConf.langs.InfrastructureOptions + '</td><td>';
				tr += '<label><input type="checkbox" value="1" name="infrastructure_add_title_bloc_from_orderstoinvoice" checked="checked" /> ' + jsConf.langs.AddTitleBlocFromOrdersToInvoice + '</label>';
				if (jsConf.isModShippingEnable) {
					let shippingChecked = jsConf.INFRASTRUCTURE_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC ? ' checked="checked"' : '';
					tr += '<br/><label><input type="checkbox" value="1" name="infrastructure_add_shipping_list_to_title_desc"' + shippingChecked + ' /> ' + jsConf.langs.AddShippingListToTile + ' <i class="fa fa-question-circle" title="' + jsConf.langs.UseHiddenConfToAutoCheck + ' INFRASTRUCTURE_DEFAULT_CHECK_SHIPPING_LIST_FOR_TITLE_DESC"></label>';
				}
				tr += '<td></tr>';
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
	* Create extrafield "infrastructure_ol" on document lines.
	*
	* @return  void
	*/
	function infrastructure_createOlExtrafields() {

		global $db;

		$extra = new ExtraFields($db); // propaldet, commandedet, facturedet
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'propaldet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'commandedet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'facturedet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'supplier_proposaldet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'commande_fournisseurdet', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
		$extra->addExtraField('infrastructure_ol', 'Optionnel', 'varchar', 0, 255, 'facture_fourn_det', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 0, '', 0, 1);
	}

	/**
	* Update a document line with infrastructure module specific behavior.
	*
	* @param	CommonObject		$object			Parent object (invoice, order, proposal, supplier, ...)
	* @param	CommonObjectLine	$line			Line to update
	* @param	int|bool			$infrastructure_ol	Flag to mark line as "optionnal dans le sous-total"
	* @param	int					$notrigger		Disable triggers if set to 1
	* @return	int									<0 if KO, >0 if OK
	*/
	function infrastructure_doUpdate(&$object, &$line, $infrastructure_ol, $notrigger = 0)
	{
		global $user;

		if (TInfrastructure::isFreeText($line) || TInfrastructure::isTotal($line)) return 1;
		dol_syslog('infrastructure_doUpdate: line_id='.$line->id.' element='.$line->element.' infrastructure_ol='.var_export($infrastructure_ol, true).' isTitle='.(TInfrastructure::isTitle($line) ? 1 : 0), LOG_DEBUG);
		// Update extrafield et special_code
		if(! empty($infrastructure_ol)) {
			if (!TInfrastructure::isModInfrastructureLine($line)) {
				$line->special_code = 3;
			}
			$line->array_options['options_infrastructure_ol'] = 1;
			if ($line->element == 'propaldet') {
				$res = $line->update($notrigger);
			} else {
				$res = $line->update($user, $notrigger);
			}
			dol_syslog('infrastructure_doUpdate: COCHE line_id='.$line->id.' update res='.$res.' array_options='.var_export($line->array_options, true), LOG_DEBUG);
			if ($res > 0) {
				$resExtra = $line->insertExtraFields();
				dol_syslog('infrastructure_doUpdate: COCHE insertExtraFields res='.$resExtra, LOG_DEBUG);
			}
		} else {
			if(in_array($object->element, ['invoice_supplier', 'order_supplier', 'supplier_proposal'])) {
				if(empty($line->label)) {
					$line->label = $line->description; // supplier lines don't have the field label
				}
				$extrafields	= new ExtraFields($object->db);
				$extralabels	= $extrafields->fetch_name_optionals_label($object->table_element_line,true);
				$line->fetch_optionals($line->id,$extralabels);
			}
			$line->array_options['options_infrastructure_ol'] = 0;
			if (!TInfrastructure::isModInfrastructureLine($line)) {
				$line->special_code = 0;
			}
			$res = TInfrastructure::doUpdateLine($object, $line->id, $line->desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->product_type, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $line->situation_percent, $line->fk_unit, $notrigger);
			dol_syslog('infrastructure_doUpdate: DECOCHE line_id='.$line->id.' doUpdateLine res='.$res, LOG_DEBUG);
			if ($res > 0) {
				$resExtra = $line->insertExtraFields();
				dol_syslog('infrastructure_doUpdate: DECOCHE insertExtraFields res='.$resExtra, LOG_DEBUG);
			}
		}
		return $res;
	}

	/**
	* Return HTML select list of predefined free texts.
	*
	* @param	bool	$withEmpty	Add an empty option in the select list
	* @return	string				HTML code of the select input with JS handler
	*/
	function infrastructure_getHtmlSelectFreeText($withEmpty=true)
	{
		global $langs;

		$TFreeText	= infrastructure_getTFreeText();
		$html		= '<label for="free_text">'.$langs->trans('InfrastructureLabelForFreeText').'</label>';
		$html		.= '<select onChange="infrastructure_getTFreeText($(this));" name="free_text" class="minwidth200">';
		if ($withEmpty) {
			$html.= '<option value=""></option>';
		}
		$TFreeTextContents = [];
		foreach ($TFreeText as $id => $tab) {
			$html					.= '<option value="'.dol_escape_htmltag($id).'">'.dol_escape_htmltag($tab->label).'</option>';
			$TFreeTextContents[$id] = $tab->content;
		}
		$html .= '</select>';
		$html .= '<script type="text/javascript">';
		$html .= 'function infrastructure_getTFreeText(select) {';
		$html .= ' var TFreeText = '.json_encode($TFreeTextContents).';';
		$html .= ' var id = select.val();';
		$html .= ' if (id in TFreeText) {';
		$html .= '  var content = TFreeText[id];';
		$html .= '  if (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && "sub-total-title" in CKEDITOR.instances) {';
		$html .= '   var editor = CKEDITOR.instances["sub-total-title"];';
		$html .= '   editor.setData(content);';
		$html .= '  } else {';
		$html .= '   $("#sub-total-title").val(content);';
		$html .= '  }';
		$html .= ' }';
		$html .= '}';
		$html .= '</script>';
		return $html;
	}

	/**
	* Return HTML select list of infrastructure titles available in the document.
	*
	* @param   CommonObject   $object     Object containing lines (invoice, propal, order, ...)
	* @param   bool           $showLabel  Add HTML label before select field
	* @return  string                     HTML code of the select input
	*/
	function infrastructure_getHtmlSelectTitle(&$object, $showLabel=false)
	{
		global $langs;

		$TTitle	= TInfrastructure::getAllTitleFromDocument($object);
		$html	= '';
		if ($showLabel) {
			$html	.= '<label for="under_title">'.$langs->trans('InfrastructureLabelForUnderTitle').'</label>';
		}
		$html	.= '<select onChange="$(\'select[name=under_title]\').val(this.value);" name="under_title" class="under_title minwidth200"><option value="-1"></option>';
		$nbsp	= '&nbsp;';
		foreach ($TTitle as &$line) {
			$str = '';
			if($line->qty > 1) {
				$str = str_repeat($nbsp, (floatval($line->qty) - 1) * 3);
			}
			$html .= '<option value="'.dol_escape_htmltag($line->id).'">'.dol_escape_htmltag($str.(!empty($line->label) ? $line->label : dol_trunc($line->desc, 30))).'</option>';
		}
		$html .= '</select>';
		return $html;
	}

	/**
	* Retrieve all active predefined free texts for current entity.
	*
	* @return	array	List of free text objects indexed by rowid
	*/
	function infrastructure_getTFreeText()
	{
		global $db,$conf;

		$TFreeText	= [];
		$sql		= 'SELECT rowid, label, content, active, entity FROM '.MAIN_DB_PREFIX.'c_infrastructure_free_text WHERE active = 1 AND entity = '.((int) $conf->entity).' ORDER BY label';
		$resql		= $db->query($sql);
		if ($resql) {
			while ($row = $db->fetch_object($resql)) {
				$TFreeText[$row->rowid] = $row;
			}
		}
		return $TFreeText;
	}

	/**
	* Add an extrafield if it does not already exist
	*
	* @param	string 		$attrname		Name of the extrafield attribute (without "options_" prefix)
	* @param	string 		$label			Label of the extrafield to display
	* @param	string 		$type			Type of the extrafield (varchar, int, date, ...)
	* @param	int			$pos			Position of the extrafield in the list of extrafields of the element
	* @param	string 		$size			Size of the extrafield (only for varchar)
	* @param	string 		$element_type	Type of element on which extrafield must be added (propaldet, commandedet, ...)
	* @param	int			$unique			Whether the extrafield must be unique or not
	* @param	int			$required		Whether the extrafield is required or not
	* @param	string		$default		Default value of the extrafield
	* @param	mixed		$param			Additional parameters of the extrafield (associative array, only for select and multiselect types, with "options" key containing options of the select)
	* @param	int			$alwayseditable	Whether the extrafield must be always editable or not (even if the line is closed/locked)
	* @param	string		$perms			Permissions for the extrafield (a combination of 'r', 'w', 'd' for read, write and delete permissions)
	* @param	string		$list			Whether the extrafield must be displayed in the list view or not (1 or 0)
	* @param	string		$help			Help text for the extrafield
	* @param	string		$computed		Code to compute the value of the extrafield (only for computed fields)
	* @param	string		$entity			Entity for which the extrafield must be added
	* @param	string		$langfile		Lang file to use for the extrafield label and options (without ".lang" suffix)
	* @param	string		$enabled		Whether the extrafield must be enabled or not (1 or 0)
	* @param	int			$totalizable	Whether the extrafield must be totalizable or not (1 or 0)
	* @param	int			$printable		Whether the extrafield must be printable or not (1 or 0)
	* @param	array		$moreparams		More parameters (reserved for future use, can be empty)
	* @param	string		$aiprompt		Prompt to use for AI generation (only for AI generated fields)
	* @return	int							1 if extrafield has been added, 0 if extrafield already exists, <0 if error occurred
	*/
	function infrastructure_addExtraField($attrname, $label, $type, $pos, $size, $element_type, $unique = 0, $required = 0, $default = '', $param = '', $alwayseditable = 0, $perms = '', $list = '-1', $help = '', $computed = '', $entity = '', $langfile = '', $enabled = '1', $totalizable = 0, $printable = 0, $moreparams = [], $aiprompt = '') {

		global $db;

		$extra		= new ExtraFields($db);
		$extra->fetch_name_optionals_label($element_type);
		$existing	= (!empty($extra->attributes[$element_type]['label']) && is_array($extra->attributes[$element_type]['label'])) ? array_keys($extra->attributes[$element_type]['label']) : [];
		if (in_array($attrname, $existing, true)) {
			return 0; // déjà existant
		}
		return $extra->addExtraField($attrname, $label, $type, $pos, $size, $element_type, $unique, $required, $default, $param, $alwayseditable, $perms, $list, $help, $computed, $entity, $langfile, $enabled, $totalizable, $printable, $moreparams, $aiprompt);
	}

	/**
	* Get title
	*
	* @param CommonObject $object Object
	* @param CommonObjectLine $currentLine Current line
	* @return string
	*/
	function infrastructure_getTitle(&$object, &$currentLine)
	{
		$res	= '';
		foreach ($object->lines as $line) {
			if ($line->id == $currentLine->id) {break;}
			$qty_search	= 100 - $currentLine->qty;
			if ($line->product_type == 9 && $line->special_code == TInfrastructure::getModuleNumber() && $line->qty == $qty_search) {
				$res	= ($line->label) ? $line->label : (($line->description) ? $line->description : $line->desc);
			}
		}
		return $res;
	}

	/**
	* Get the numerotation prefix (ex. "1.2") of the parent title of a sub-total line.
	* Requires infrastructure_addNumerotation() to have already prefixed the parent title label.
	*
	* @param CommonObject $object Object
	* @param CommonObjectLine $currentLine Current line (sous-total)
	* @return string
	*/
	function infrastructure_getNumerotation(&$object, &$currentLine)
	{
		$titleLabel	= infrastructure_getTitle($object, $currentLine);
		if ($titleLabel !== '' && preg_match('/^([0-9]+(?:\.[0-9]+)*)\s/', $titleLabel, $matches)) {
			return $matches[1];
		}
		return '';
	}

	/**
	* Print new format
	*
	* @param	CommonObject	$object		Object
	* @param	Conf			$conf		Conf
	* @param	Translate		$langs		Langs
	* @param	string			$idvar		Id var
	* @return	bool|void
	*/
	function infrastructure_printNewFormat(&$object, &$conf, &$langs, $idvar)
	{
		if (!getDolGlobalString('INFRASTRUCTURE_ALLOW_ADD_BLOCK')) {return false;}

		$jsData = ['conf' => ['MAIN_VIEW_LINE_NUMBER'		=> getDolGlobalInt('MAIN_VIEW_LINE_NUMBER'),
										'token'						=> newToken(),
										'groupBtn'					=> intval(DOL_VERSION) < 20.0 || getDolGlobalInt('INFRASTRUCTURE_FORCE_EXPLODE_ACTION_BTN') ? 0 : 1
									],
						'langs' => ['Level'					=> $langs->trans('InfrastructureLevel'),
										'Position'					=> $langs->transnoentities('Position'),
										'AddTitle'					=> $langs->trans('InfrastructureAddTitle'),
										'AddInfrastructure'				=> $langs->trans('InfrastructureAddInfrastructure'),
										'AddFreeText'				=> $langs->trans('InfrastructureAddFreeText'),
									]
					];
		$jsData['buttons'] = dolGetButtonAction('', $langs->trans('InfrastructuresAndTitlesActionBtnLabel'), 'default', [
			['attr' => ['rel' => 'add_title_line'], 'id' => 'add_title_line', 'urlraw' => '#', 'label' => $langs->trans('InfrastructureAddTitle'), 'perm' => 1],
			['attr' => ['rel' => 'add_total_line'], 'id' => 'add_total_line', 'urlraw' => '#', 'label' => $langs->trans('InfrastructureAddInfrastructure'), 'perm' => 1],
			['attr' => ['rel' => 'add_free_text'], 'id' => 'add_free_text', 'urlraw' => '#', 'label' => $langs->trans('InfrastructureAddFreeText'), 'perm' => 1],
		], 'infrastructure-actions-buttons-dropdown');
		if (empty($jsData['conf']['groupBtn'])) {
			$jsData['buttons'] = '<div class="inline-block divButAction"><a id="add_title_line" rel="add_title_line" href="javascript:;" class="butAction">'.$langs->trans('InfrastructureAddTitle').'</a></div>';
			$jsData['buttons'] .= '<div class="inline-block divButAction"><a id="add_total_line" rel="add_total_line" href="javascript:;" class="butAction">'.$langs->trans('InfrastructureAddInfrastructure').'</a></div>';
			$jsData['buttons'] .= '<div class="inline-block divButAction"><a id="add_free_text" rel="add_free_text" href="javascript:;" class="butAction">'.$langs->trans('InfrastructureAddFreeText').'</a></div>';
		}
		?>
			<!-- Infrastructure action printNewFormat -->
			<script type="text/javascript">
				$(document).ready(function() {
					let jsInfrastructureData = <?php print json_encode($jsData); ?>;
					if (jsInfrastructureData.conf.groupBtn == 0) {
						let targetContainer;
						if ($("div.fiche div.tabsAction > .butAction").length) {
							targetContainer = $("div.fiche div.tabsAction");
						} else {
							targetContainer = $("div.fiche div.tabsAction > .divButAction").length
								? $("div.fiche div.tabsAction")
								: $("div.fiche div.tabsAction");
						}
						targetContainer.append('<br />');
						targetContainer.append(jsInfrastructureData.buttons);
					} else {
						let elementsButon;
						elementsButon = $("div.fiche div.tabsAction > .butAction").length
							? $("div.fiche div.tabsAction > .butAction")
							: $("div.fiche div.tabsAction > .divButAction");

						$(jsInfrastructureData.buttons).insertBefore(elementsButon.first());
					}
					function updateAllMessageForms(){
						for (instance in CKEDITOR.instances) {
							CKEDITOR.instances[instance].updateElement();
						}
					}
					function promptInfrastructure(action, titleDialog, label, url_to, url_ajax, params, use_textarea, show_free_text, show_under_title) {
						$( "#dialog-prompt-infrastructure" ).remove();
							var dialog_html = '<div id="dialog-prompt-infrastructure" ' + (action == 'addInfrastructure' ? 'class="center"' : '') + ' >';
							dialog_html += '<input id="token" name="token" type="hidden" value="' + jsInfrastructureData.conf.token + '" />';
							if (typeof show_under_title != 'undefined' && show_under_title) {
								var selectUnderTitle = <?php echo json_encode(infrastructure_getHtmlSelectTitle($object, true)); ?>;
								dialog_html += selectUnderTitle + '<br /><br />';
							}
							if (action == 'addTitle' || action == 'addFreeTxt') {
								if (typeof show_free_text != 'undefined' && show_free_text) {
									var selectFreeText = <?php echo json_encode(infrastructure_getHtmlSelectFreeText()); ?>;
									dialog_html += selectFreeText + ' <?php echo $langs->transnoentities('InfrastructureFreeTextOrDesc'); ?><br />';
								}
								if (typeof use_textarea != 'undefined' && use_textarea) dialog_html += '<textarea id="sub-total-title" rows="<?php echo ROWS_8; ?>" cols="80" placeholder="' + label + '"></textarea>';
								else dialog_html += '<input id="sub-total-title" size="30" value="" placeholder="' + label + '" />';
							}
							if (action == 'addInfrastructure') {
								dialog_html += '<input id="sub-total-title" size="30" value="" placeholder="' + label + '" />';
							}

							if (jsInfrastructureData.conf.MAIN_VIEW_LINE_NUMBER) {
								dialog_html += '&emsp;<input style="max-width: 80px;" id="infrastructure_line_position" name="infrastructure_line_position" type="number" min="0" step="1" size="1" text-align="right" placeholder="' + jsInfrastructureData.langs.Position + '" />';
							}
							if (action == 'addTitle' || action == 'addInfrastructure') {
								dialog_html += '&emsp;<select name="infrastructure_line_level">';
								for (var i=1;i<10;i++){
									dialog_html += '<option value="' + i + '">' + jsInfrastructureData.langs.Level + ' ' + i + '</option>';
								}
								dialog_html += "</select>";
							}
							dialog_html += '</div>';
							$('body').append(dialog_html);
								<?php
								$editorTool = getDolGlobalString('FCKEDITOR_EDITORNAME', 'ckeditor');
								$editorConf = empty(getDolGlobalString('FCKEDITOR_ENABLE_DETAILS')) ? false : getDolGlobalString('FCKEDITOR_ENABLE_DETAILS');
								if ($editorConf && in_array($editorTool, ['textarea','ckeditor'])) {
									?>
								if (action == 'addTitle' || action == 'addFreeTxt') {
									if (typeof use_textarea != 'undefined' && use_textarea && typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined") {
										CKEDITOR.replace('sub-total-title', {
											customConfig: ckeditorConfig,
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
							$( "#dialog-prompt-infrastructure" ).dialog({
								resizable: false,
								height: 'auto',
								width: 'auto',
								modal: true,
								title: titleDialog,
								buttons: {
									"Ok": function() {
										if (typeof use_textarea != 'undefined' && use_textarea && typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" ){ updateAllMessageForms(); }
										params.rank = 0;
										if($(this).find('#infrastructure_line_position').length > 0){
											params.rank = $(this).find('#infrastructure_line_position').val();
										}

									params.title = (typeof CKEDITOR == "object" && typeof CKEDITOR.instances != "undefined" && "sub-total-title" in CKEDITOR.instances ? CKEDITOR.instances["sub-total-title"].getData() : $(this).find('#sub-total-title').val());
									params.under_title = $(this).find('select[name=under_title]').val();
									params.free_text = $(this).find('select[name=free_text]').val();
									params.level = $(this).find('select[name=infrastructure_line_level]').val();
									params.token = $(this).find('input[name=token]').val();

									let microtime = new Date();
									url_to += "&microtime=" + microtime.getTime(); // to avoid # ancor blocking refresh by adding same rank as curent

									$.ajax({
										url: url_ajax
										, type: 'POST'
										, data: params
										, dataType: "html"
									}).done(function (response) {
										if (jsInfrastructureData.conf.MAIN_VIEW_LINE_NUMBER == 1) {
											newlineid = $($.parseHTML(response)).find("#newlineid").text();
											url_to = url_to + "&gotoline=" + params.rank + "#row-" + newlineid;
										} else {
											url_to = url_to + "&gotoline=" + params.rank + "#tableaddline";
										}
										document.location.href = url_to;
									});

										$( this ).dialog( "close" );
									},
									"<?php echo $langs->trans('Cancel') ?>": function() {
										$( this ).dialog( "close" );
									}
								}
							});
					}
					$('a[rel=add_title_line]').click(function (e) {
						e.preventDefault();
						promptInfrastructure('addTitle'
							, "<?php echo $langs->trans('InfrastructureYourTitleLabel') ?>"
							, "<?php echo $langs->trans('InfrastructureTitle'); ?>"
							, '?<?php echo $idvar ?>=<?php echo $object->id; ?>'
							, '<?php echo $_SERVER['PHP_SELF']; ?>'
							, {<?php echo $idvar; ?>: <?php echo (int)$object->id; ?>,
						action:'add_title_line'
					}
					)
						;
					});
					$('a[rel=add_total_line]').click(function (e) {
						e.preventDefault();
						promptInfrastructure('addInfrastructure'
							, '<?php echo $langs->trans('InfrastructureYourInfrastructureLabel') ?>'
							, '<?php echo $langs->trans('InfrastructureSubtotal'); ?>'
							, '?<?php echo $idvar ?>=<?php echo $object->id; ?>'
							, '<?php echo $_SERVER['PHP_SELF']; ?>'
							, {<?php echo $idvar; ?>: <?php echo (int)$object->id; ?>,
						action:'add_total_line'
							}
						/*,false,false, <?php echo getDolGlobalString('INFRASTRUCTURE_ALLOW_ADD_LINE_UNDER_TITLE') ? 'true' : 'false'; ?>*/
						);
					});
					$('a[rel=add_free_text]').click(function (e) {
						e.preventDefault();
						promptInfrastructure('addFreeTxt',
							"<?php echo $langs->transnoentitiesnoconv('InfrastructureYourTextLabel') ?>",
							"<?php echo $langs->trans('InfrastructureAddLineDescription'); ?>",
							'?<?php echo $idvar ?>=<?php echo $object->id; ?>',
							'<?php echo $_SERVER['PHP_SELF']; ?>', {
								<?php echo $idvar; ?>: <?php echo (int)$object->id; ?>,action:'add_free_text'
							},
							true,
							true,
							<?php echo getDolGlobalString('INFRASTRUCTURE_ALLOW_ADD_LINE_UNDER_TITLE') ? 'true' : 'false'; ?>
						);
					});
				});
			</script>
		<?php
	}

	/**
	* Update a infrastructure or title line with its display options.
	*
	* @param	CommonObject		$object		Parent object (invoice, order, proposal, ...)
	* @param	CommonObjectLine	$line		Infrastructure or title line to update
	* @return	int								<0 if KO, >0 if OK
	*/
	function infrastructure_updateInfrastructureLine(&$object, &$line)
	{

		$label					= GETPOST('line-title', 'restricthtml');
		$description			= ($line->qty>90) ? '' : GETPOST('line-description', 'restricthtml');
		$pagebreak				= GETPOST('line-pagebreak', 'int');
		$showTableHeaderBefore	= GETPOST('line-showTableHeaderBefore', 'int');
		$printAsList			= GETPOST('line-printAsList', 'int');
		$printCondensed			= GETPOST('line-printCondensed', 'int');
		$showTotalHT			= GETPOST('line-showTotalHT', 'int');
		$showReduc				= GETPOST('line-showReduc', 'int');
		$level					= GETPOST('infrastructure_level', 'int');
		// Pré-charger les ExtraFields existants pour conserver options_infrastructure_show_qty lorsque la case
		// "Afficher la quantité cumulée" n'apparaît pas dans le formulaire (constante globale vide à l'édition).
		if (empty($line->array_options) && method_exists($line, 'fetch_optionals')) {
			$line->fetch_optionals();
		}
		if (!empty($level)) {
			if ($line->qty > 90) {
				$line->qty = 100 - $level; // Si on edit une ligne sous-total
			} else {
				$line->qty = $level;
			}
		}
		$line->array_options['options_show_table_header_before']	= $showTableHeaderBefore;
		$line->array_options['options_print_as_list']				= $printAsList;
		$line->array_options['options_print_condensed']				= $printCondensed;
		$line->array_options['options_show_total_ht']				= $showTotalHT;
		$line->array_options['options_show_reduc']					= $showReduc;
		// N'écraser options_infrastructure_show_qty que si le formulaire incluait effectivement la case
		// (marqueur hidden 'line-showQty-present' rendu uniquement quand showQtyForObject() est vrai).
		// Sans ce garde-fou, une édition de sous-total alors que la constante globale est vide écrasait
		// systématiquement la valeur en -1, masquant la qté même après réactivation de la constante.
		if (GETPOSTISSET('line-showQty-present')) {
			$showQty												= GETPOSTISSET('line-showQty') ? GETPOST('line-showQty', 'int') : -1;
			$line->array_options['options_infrastructure_show_qty']	= $showQty;
		}
		$res														= TInfrastructure::doUpdateLine($object, $line->id, $description, 0, $line->qty, 0, '', '', 0, 9, 0, 0, 'HT', $pagebreak, 0, 1, null, 0, $label, TInfrastructure::getModuleNumber(), $line->array_options);
		$TKey														= null;
		if ($line->element == 'propaldet' && getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_PROPALDET')) {
			$TKey = explode(',', getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_PROPALDET'));
		} elseif ($line->element == 'commandedet' && getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_COMMANDEDET')) {
			$TKey = explode(',', getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_COMMANDEDET'));
		} elseif ($line->element == 'facturedet' && getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_FACTUREDET')) {
			$TKey = explode(',', getDolGlobalString('INFRASTRUCTURE_LIST_OF_EXTRAFIELDS_FACTUREDET'));
		}
		// TODO ajouter la partie fournisseur
		if (!empty($TKey)) {
			$TLine	= TInfrastructure::getLinesFromTitleId($object, $line->id);
			foreach ($TLine as $object_line) {
				foreach ($TKey as $key) {
					if (array_key_exists('options_'.$key, $line->array_options)) {
						$object_line->array_options['options_'.$key] = $line->array_options['options_'.$key];
					}
				}
				$object_line->insertExtraFields();
			}
		}
		return $res;
	}

	/**
	* Update all lines of a infrastructure block from a title line.
	*
	* @param	CommonObject		$object		Parent object (invoice, order, proposal, ...)
	* @param	CommonObjectLine	$line		Title line defining the infrastructure block
	* @return	int								Number of updated lines, or negative value if errors
	*/
	function infrastructure_updateInfrastructureBloc($object, $line)
	{
		global $langs;

		$infrastructure_tva_tx		= $infrastructure_tva_tx_init	= GETPOST('infrastructure_tva_tx', 'int');
		$infrastructure_progress	= $infrastructure_progress_init	= GETPOST('infrastructure_progress', 'int');
		$array_options				= $line->array_options;
		$showBlockExtrafields		= GETPOST('showBlockExtrafields', 'aZ09');
		if ($infrastructure_tva_tx != '' || $infrastructure_progress != '' || (!empty($showBlockExtrafields) && !empty($array_options))) {
			$error_progress	= $nb_progress_update = $nb_progress_not_updated = 0;
			$parentTitleId	= $line->id;
			$TLine			= TInfrastructure::getLinesFromTitleId($object, $line->id, true);
			// Clés propres au module infrastructure — ne pas les propager aux titres enfants
			$infrastructureOwnKeys	= ['options_show_total_ht', 'options_show_reduc', 'options_infrastructure_show_qty', 'options_hideblock', 'options_show_table_header_before', 'options_print_as_list', 'options_print_condensed', 'options_infrastructure_ol'];
			foreach ($TLine as &$line) {
				if ($line->id == $parentTitleId || TInfrastructure::isTotal($line)) {
					continue;	// Ignorer le titre parent lui-même et les sous-totaux
				}
				if (!TInfrastructure::isModInfrastructureLine($line)) {
					$infrastructure_tva_tx = $infrastructure_tva_tx_init; // ré-init car la variable peut évoluer
					if (!empty($showBlockExtrafields)) {
						$line->array_options = $array_options;
					}
					if ($infrastructure_tva_tx == '') {
						$infrastructure_tva_tx = $line->tva_tx;
					}
					if ($object->element == 'facture' && getDolGlobalString('INVOICE_USE_SITUATION') && $object->type == Facture::TYPE_SITUATION) {
						$infrastructure_progress = $infrastructure_progress_init;
						if ($infrastructure_progress == '') {
							$infrastructure_progress = $line->situation_percent;
						} else {
							$prev_percent = $line->get_prev_progress($object->id);
							if ($infrastructure_progress < $prev_percent) {
								$nb_progress_not_updated++;
								$infrastructure_progress = $line->situation_percent;
							}
						}
					}
					$res = TInfrastructure::doUpdateLine($object, $line->id, $line->desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $infrastructure_tva_tx, $line->product_type, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->fk_parent_line, $line->skip_update_total, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, $line->array_options, $infrastructure_progress, $line->fk_unit);
					if ($res > 0) {
						$success_updated_line++;
					} else {
						$error_updated_line++;
					}
				} elseif (TInfrastructure::isTitle($line) && !empty($showBlockExtrafields) && !empty($array_options)) {
					// Titre enfant : propager uniquement les extrafields non gérés par infrastructure
					foreach ($array_options as $optKey => $optVal) {
						if (!in_array($optKey, $infrastructureOwnKeys)) {
							$line->array_options[$optKey] = $optVal;
						}
					}
					$line->insertExtraFields();
				}
			}
			if ($nb_progress_not_updated > 0) {
				setEventMessage($langs->trans('InfrastructureNbProgressNotUpdated', $nb_progress_not_updated), 'warnings');
			}
			if ($success_updated_line > 0) {
				setEventMessage($langs->trans('InfrastructureSuccessUpdatedLine', $success_updated_line));
			}
			if ($error_updated_line > 0) {
				setEventMessage($langs->trans('InfrastructureErrorUpdatedLine', $error_updated_line), 'errors');
				return -$error_updated_line;
			}
			return $success_updated_line;
		}
		return 0;
	}

	/**
	* Maj du bloc pour forcer le total_tva et total_ht à 0 et recalculer le total du document
	*
	* @param	int		$lineid			Title lineid
	* @param	int		$infrastructure_ol	0 = "Non optionnelle" prise en compte des totaux des lignes; 1 = "Optionnelle" non prise en compte des totaux du bloc; null = update de toutes les lignes
	*/
	function infrastructure_updateLineOL($element, $elementid, $lineid, $infrastructure_ol=null, $notrigger = 0)
	{
		global $db, $langs, $tmp_object_ol, $hookmanager;

		$error = 0;
		if (empty($element)) {
			$error++;
		}
		if (!$error) {
			if (!empty($tmp_object_ol) && $tmp_object_ol->element == $element && $tmp_object_ol->id == $elementid) {
				$object = $tmp_object_ol;
			} else {
				$TAllowedElements	= [
					'propal'				=> 'Propal',
					'commande'				=> 'Commande',
					'facture'				=> 'Facture',
					'supplier_proposal'		=> 'SupplierProposal',
					'order_supplier'		=> 'CommandeFournisseur',
					'invoice_supplier'		=> 'FactureFournisseur',
				];
				if (empty($TAllowedElements[$element])) {
					$error++;
					return ['success' => false, 'error' => 'Invalid element'];
				}
				$classname = $TAllowedElements[$element];
				$object	= new $classname($db); // Propal | Commande | Facture
				$res	= $object->fetch($elementid);
				if ($res < 0) {
					$error++;
				} else {
					$tmp_object_ol = $object;
				}
			}
		}
		if (!$error) {
			foreach ($object->lines as &$l) {
				if($l->id == $lineid) {
					$line = $l;
					break;
				}
			}
			if (!empty($line)) {
				$db->begin();
				if(TInfrastructure::isModInfrastructureLine($line)) {
					if (TInfrastructure::isTitle($line)) {
						// Update le contenu du titre (ainsi que le titre lui même)
						$TTitleBlock = TInfrastructure::getLinesFromTitleId($object, $lineid, true);
						foreach($TTitleBlock as &$line_block) {
							$res = infrastructure_doUpdate($object, $line_block, $infrastructure_ol, $notrigger);
						}
					}
				} else {
					$res = infrastructure_doUpdate($object, $line, $infrastructure_ol, $notrigger);
				}
				// update_price() délègue l'exclusion des lignes optionnelles (special_code = 3) au hook
				// updateTotalPrice (ActionsInfrastructure::updateTotalPrice). Ce hook n'est déclenché que si
				// le contexte carte correspondant a été initialisé sur $hookmanager : sans ce init (cas des
				// appelants hors page carte, ex. l'endpoint AJAX script/interface.php), le total du document
				// resterait calculé par le code standard Dolibarr sans exclusion. initHooks() est idempotent
				// (voir HookManager::initHooks()) : rien ne se passe si le contexte est déjà initialisé.
				if (is_object($hookmanager)) {
					$TElementToHookContext	= [
						'propal'				=> 'propalcard',
						'commande'				=> 'ordercard',
						'facture'				=> 'invoicecard',
						'supplier_proposal'	=> 'supplier_proposalcard',
						'order_supplier'		=> 'ordersuppliercard',
						'invoice_supplier'		=> 'invoicesuppliercard',
					];
					if (!empty($TElementToHookContext[$element])) {
						$hookmanager->initHooks([$TElementToHookContext[$element]]);
					}
				}
				$res	= $object->update_price(1);
				if ($res <= 0) {
					$error++;
				}
				if (!$error) {
					setEventMessage($langs->trans('InfrastructureUpdateNcSuccess'));
					$db->commit();
					return ['success' => true, 'message' => $langs->trans('InfrastructureUpdateNcSuccess')];
				} else {
					setEventMessage($langs->trans('InfrastructureUpdateNcError'), 'errors');
					$db->rollback();
					return ['success' => false, 'error' => $langs->trans('InfrastructureUpdateNcError')];
				}
			}
		}
		return ['success' => false, 'error' => 'Unknown error'];
	}

	function infrastructure_updateLine($element, $elementid, $lineid)
	{
		infrastructure_updateLineOL($element, $elementid, $lineid);
	}

	/**
	* Get session variable names for hide options based on context
	*
	* @param	array	$contextArray	Array of context strings
	* @return	array					Array with keys 'hideInnerLines', 'hideqtys', 'hideprices'
	*/
	function infrastructure_getSessionNames($contextArray)
	{
		if (in_array('invoicecard', $contextArray)) {
			$suffix = 'facture';
		} elseif (in_array('invoicesuppliercard', $contextArray)) {
			$suffix = 'facture_fournisseur';
		} elseif (in_array('propalcard', $contextArray)) {
			$suffix = 'propal';
		} elseif (in_array('supplier_proposalcard', $contextArray)) {
			$suffix = 'supplier_proposal';
		} elseif (in_array('ordercard', $contextArray)) {
			$suffix = 'commande';
		} elseif (in_array('ordersuppliercard', $contextArray)) {
			$suffix = 'commande_fournisseur';
		} else {
			$suffix = 'unknown';
		}
		return ['hideInnerLines'	=> 'infrastructure_hideInnerLines_'.$suffix,
					'hideqtys'	=> 'infrastructure_hideqtys_'.$suffix,
					'hideprices'		=> 'infrastructure_hideprices_'.$suffix,
				];
	}

	/**
	*	Override PDF text color from a color configuration constant. Does nothing if the constant is empty or invalid (preserves any auto color set previously, e.g. white-on-dark by infrastructure_getPdfBackgroundStyle).
	*
	*	@param	TCPDF	$pdf			PDF object
	*	@param	string	$colorConst		Global constant name for text color (e.g. 'INFRASTRUCTURE_PDF_TITLE_COLOR')
	*	@return	bool					true if a color was applied, false if left untouched
	*/
	function infrastructure_setPdfTextColor(&$pdf, $colorConst)
	{
		$rawValue			= getDolGlobalString($colorConst);
		if ($rawValue === '') {
			return false;
		}
		$normalizedColor	= ($rawValue[0] !== '#') ? '#'.$rawValue : $rawValue;
		if (function_exists('colorValidateHex') && colorValidateHex($normalizedColor) && function_exists('colorStringToArray')) {
			$rgb	= colorStringToArray($normalizedColor, [0, 0, 0]);
			$pdf->setColor('text', $rgb[0], $rgb[1], $rgb[2]);
			return true;
		}
		return false;
	}

	/**
	* Compute PDF background style from a color configuration constant.
	* Si une ligne spéciale (titre `qty 1-9` ou sous-total `qty 91-99`) est fournie, la couleur de base est éclaircie selon
	* le niveau via `colorLighten()` en utilisant le pourcentage de luminosité PDF dédié
	* (`INFRASTRUCTURE_PDF_TITLE_AND_TOTAL_BRIGHTNESS_PERCENTAGE`, fallback sur la version écran).
	*
	* @param	TCPDF				$pdf					PDF object
	* @param	string				$colorConst				Global constant name for background color (e.g. 'INFRASTRUCTURE_PDF_TITLE_BACKGROUND_COLOR')
	* @param	string				$heightOffsetConst		Global constant name for cell height offset
	* @param	string				$posYOffsetConst		Global constant name for cell Y position offset
	* @param	CommonObjectLine	$line					Ligne spéciale du module (optionnelle) — si fournie, applique la nuance par niveau
	* @return	array										Array with keys 'fill', 'color', 'heightOffset', 'posYOffset'
	*/
	function infrastructure_getPdfBackgroundStyle(&$pdf, $colorConst, $heightOffsetConst = '', $posYOffsetConst = '', $line = null)
	{
		$result	= ['fill'			=> false,
						'color'			=> [233, 233, 233],
						'heightOffset'	=> 0,
						'posYOffset'	=> 0,
					];
		$rawColor			= getDolGlobalString($colorConst);
		$normalizedColor	= ($rawColor !== '' && $rawColor[0] !== '#') ? '#'.$rawColor : $rawColor;
		if ($normalizedColor && function_exists('colorValidateHex') && colorValidateHex($normalizedColor) && function_exists('colorStringToArray')) {
			if (is_object($line) && function_exists('colorLighten')) {
				$brightness	= (float) getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_AND_TOTAL_BRIGHTNESS_PERCENTAGE');
				if ($line->qty >= 91 && $line->qty <= 99) {
					$offset				= $line->qty < 99 ? (99 - $line->qty) * $brightness : 1;
					$lightened			= colorLighten($normalizedColor, $offset);
					if (colorValidateHex($lightened)) {
						$normalizedColor	= $lightened;
					}
				} elseif ($line->qty >= 1 && $line->qty <= 9) {
					$offset				= $line->qty > 1 ? ($line->qty - 1) * $brightness : 1;
					$lightened			= colorLighten($normalizedColor, $offset);
					if (colorValidateHex($lightened)) {
						$normalizedColor	= $lightened;
					}
				}
			}
			$result['fill']		= true;
			$result['color']	= colorStringToArray($normalizedColor, [233, 233, 233]);
			if (function_exists('colorIsLight') && !colorIsLight($normalizedColor)) {
				$pdf->setColor('text', 255, 255, 255);
			}
			if ($heightOffsetConst && getDolGlobalString($heightOffsetConst)) {
				$result['heightOffset']	= doubleval(getDolGlobalString($heightOffsetConst));
			}
			if ($posYOffsetConst && getDolGlobalString($posYOffsetConst)) {
				$result['posYOffset']	= doubleval(getDolGlobalString($posYOffsetConst));
			}
		}
		return $result;
	}

	/**
	*	Format a price for PDF rendering. Clone autonome de pdf_InfraSPlus_price() (module InfraSPackPlus)
	*	pour permettre au module Infrastructure de fonctionner indépendamment.
	*	Utilise les constantes INFRASPLUS_PDF_ROUNDING_UP / INFRASPLUS_PDF_ROUNDING_TOT / INFRASPLUS_PDF_SHOW_CUR_SYMB
	*	si elles sont définies (lecture directe de llx_const, ne nécessite pas que le module InfraSPackPlus soit actif),
	*	sinon retombe sur les constantes Dolibarr standard MAIN_MAX_DECIMALS_UNIT / MAIN_MAX_DECIMALS_TOT.
	*
	*	@param	CommonObject	$object			Document parent (utilisé pour multicurrency_code)
	*	@param	float			$price			Montant à formater
	*	@param	Translate		$outputlangs	Translations
	*	@param	int				$forceSymb		Force l'affichage du symbole de devise (1 = oui)
	*	@param	int				$local			Force l'usage de la devise locale (vs multicurrency_code)
	*	@param	string			$priceType		'U' pour prix unitaire, 'T' pour total, '' pour autre
	*	@return	string							Montant formaté
	**/
	function infrastructure_pdf_price($object, $price, $outputlangs, $forceSymb = 0, $local = 0, $priceType = '')
	{
		global $conf;

		$roundingUP		= getDolGlobalInt('INFRASPLUS_PDF_ROUNDING_UP', 0);
		$roundingTot	= getDolGlobalInt('INFRASPLUS_PDF_ROUNDING_TOT', 0);
		$roundingDol	= min(getDolGlobalString('MAIN_MAX_DECIMALS_UNIT', ''), getDolGlobalString('MAIN_MAX_DECIMALS_TOT', ''));
		$rounding		= empty($priceType) || ($priceType == 'U' && empty($roundingUP)) || ($priceType == 'T' && empty($roundingTot)) ? $roundingDol : ($priceType == 'U' ? $roundingUP : ($priceType == 'T' ? $roundingTot : ''));
		$currency		= !empty($object->multicurrency_code) && empty($local) ? $object->multicurrency_code : $conf->currency;
		$showCurSymb	= !empty($forceSymb) ? 1 : getDolGlobalInt('INFRASPLUS_PDF_SHOW_CUR_SYMB', 0);
		return price($price, 0, $outputlangs, 1, $rounding, $rounding, !empty($showCurSymb) ? $currency : '');
	}

	/**
	* duplicate from action_submodule
	*
	* @param	object			$object		Document object (invoice, order, propal, ...)
	* @param	object			$line		Line object
	* @param	bool			$use_level	level is used to get total of a bloc with infrastructure line with level superior to the line (and not only the lines with same level)
	* @param	int				$return_all	If set to 1, returns an array with total, total_tva, total_ttc, and TTotal_tva (total TVA by rate)
	* @return	array|float|int
	*/
	function infrastructure_getTotalLineFromObject(&$object, &$line, $use_level=false, $return_all=0) {

		global $conf;

		$rang		= $line->rang;
		$qty_line	= $line->qty;
		$lvl		= 0;
		if (TInfrastructure::isTotal($line)) {
			$lvl = TInfrastructure::getNiveau($line);
		}
		$total			= 0;
		$total_tva		= 0;
		$total_ttc		= 0;
		$TTotal_tva		= [];
		$title_break	= TInfrastructure::getParentTitleOfLine($object, $rang, $lvl);
		$sign			= isset($object->type) && $object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE') ? -1 : 1;
		$builddoc		= GETPOST('action', 'aZ09') == 'builddoc' ? true : false;
		$TLineReverse	= array_reverse($object->lines);
		foreach($TLineReverse as $l) {
			$l->total_ttc	= doubleval($l->total_ttc);
			$l->total_ht	= doubleval($l->total_ht);
			if ($l->rang >= $rang) {
				continue;
			}
			if (!empty($title_break) && $title_break->id == $l->id) {
				break;
			} elseif (!TInfrastructure::isModInfrastructureLine($l)) {
				// TODO retirer le test avec $builddoc quand Dolibarr affichera le total progression sur la card et pas seulement dans le PDF
				if ($builddoc && $object->element == 'facture' && $object->type==Facture::TYPE_SITUATION) {
					if ($l->situation_percent > 0 && !empty($l->total_ht)) {
						$prev_progress	= 0;
						$progress		= 1;
						if (method_exists($l, 'get_prev_progress')) {
							$prev_progress	= $l->get_prev_progress($object->id);
							$progress		= ($l->situation_percent - $prev_progress) / 100;
						}
						$result					= $sign * ($l->total_ht / ($l->situation_percent / 100)) * $progress;
						$total					+= $result;
						$total_tva				+= $sign * ($l->total_tva / ($l->situation_percent / 100)) * $progress;
						$TTotal_tva[$l->tva_tx] += $sign * ($l->total_tva / ($l->situation_percent / 100)) * $progress;
						$total_ttc				+= $sign * ($l->total_tva / ($l->total_ttc / 100)) * $progress;

					}
				} else {
					if ($l->product_type != 9) {
						$total					+= $l->total_ht;
						$total_tva				+= $l->total_tva;
						$TTotal_tva[$l->tva_tx] += $l->total_tva;
						$total_ttc				+= $l->total_ttc;
					}
				}
			}
		}
		if (!$return_all) {
			return $total;
		} else {
			return [$total, $total_tva, $total_ttc, $TTotal_tva];
		}
	}

	/**
	* Retourne le progrès actuel d'une ligne de facture de situation, additionne le progrès précédent et le pourcentage de la ligne (sauf pour une facture acompte)
	*
	* @param	FactureLigne	$line		L'objet ligne de facture
	* @param	int				$factureid	ID de la facture
	* @return	float						Progrès actuel en pourcentage (0 à 100)
	*/
	function infrastructure_getLineCurrentProgress($factureid, $line)
	{
		global $db;

		$previous_progress	= (floatval(DOL_VERSION) >= 21) ? $line->getAllPrevProgress($factureid) : $line->get_prev_progress($factureid);
		$parent				= new Facture($db);
		$res				= $parent->fetch($factureid);
		if ($res) {
			if ($parent->type == Facture::TYPE_CREDIT_NOTE) {
				return $previous_progress;
			}
			return $previous_progress + floatval($line->situation_percent);
		} else {
			dol_syslog($parent->error, LOG_ERR);
			return 0;
		}
	}

	/**
	* Get titles flat array
	*
	* @param	array	$TTitleNumeroted	Titles numeroted
	* @param	array	$resArray			Result array
	* @return	array
	*/
	function infrastructure_getTitlesFlatArray($TTitleNumeroted = [], &$resArray = [])
	{
		if (is_array($TTitleNumeroted) && !empty($TTitleNumeroted)) {
			foreach ($TTitleNumeroted as $tn) {
				$resArray[$tn['line']->id] = $tn;
				if (array_key_exists('children', $tn)) {
					infrastructure_getTitlesFlatArray($tn['children'], $resArray);
				}
			}
		}
		return $resArray;
	}

	//@TODO change all call to this method with the method in lib !!!!
	/**
	* Get total line from object
	*
	* @param	CommonObject		$object		Object
	* @param	CommonObjectLine	$line		Line
	* @param	bool				$use_level	Use level
	* @param	int					$return_all	Return all
	* @return	array|float|int
	*/
	function infrastructure_get_totalLineFromObject(&$object, &$line, $use_level = false, $return_all = 0)
	{
		$rang	= $line->rang;
		$lvl	= 0;
		if (TInfrastructure::isTotal($line)) {
			$lvl = TInfrastructure::getNiveau($line);
		}
		$memoEnabled	= isset($object->context['infrastructureCache']['warmed']) && !empty($object->context['infrastructureCache']['warmed']);
		$memoKey		= $rang.'|'.$lvl.'|'.intval((bool) $use_level).'|'.intval($return_all);
		if ($memoEnabled) {
			if (!isset($object->context['infrastructureCache']['totalLineByKey']) || !is_array($object->context['infrastructureCache']['totalLineByKey'])) {
				$object->context['infrastructureCache']['totalLineByKey']	= [];
			}
			if (array_key_exists($memoKey, $object->context['infrastructureCache']['totalLineByKey'])) {
				return $object->context['infrastructureCache']['totalLineByKey'][$memoKey];
			}
		}
		$title_break					= TInfrastructure::getParentTitleOfLine($object, $rang, $lvl);
		$total							= 0;
		$total_tva						= 0;
		$total_ttc						= 0;
		$total_qty						= 0;
		$total_options					= 0;
		$multicurrency_total_options	= 0;
		$TTotal_tva						= [];
		$TTotal_tva_array				= [];
		$multicurrency_total_ht			= 0;
		$multicurrency_total_ttc		= 0;
		$sign							= 1;
		if ($memoEnabled) {
			if (!isset($object->context['infrastructureCache']['linesReversed']) || !is_array($object->context['infrastructureCache']['linesReversed'])) {
				$object->context['infrastructureCache']['linesReversed']	= array_reverse($object->lines);
			}
			$TLineReverse	= $object->context['infrastructureCache']['linesReversed'];
		} else {
			$TLineReverse	= array_reverse($object->lines);
		}
		$listOuvrages				= [];
		if (isset($object->type) && $object->type == 2 && getDolGlobalString('INVOICE_POSITIVE_CREDIT_NOTE')) {
			$sign = -1;
		}
		if (!empty(isModEnabled('ouvrage')) && class_exists('Ouvrage') ) {
			// loop over the lines above the current total line
			foreach ($TLineReverse as $l) {
				$isOuvrage	= Ouvrage::isOuvrage($l) ? 1 : 0;	// ouvrage ??
				if (!empty($title_break) && $title_break->id == $l->id) {
					break;								// We go back from the end to the beginning, so when we find the associated title we stop
				} elseif (!empty($isOuvrage)) {			// it's a ouvrage
					$listOuvrages[$l->id]	= $l->qty;	// record the quantity linked to the ID
				}
			}
		}
		foreach($TLineReverse as $l) {
			$l->total_ttc				= doubleval($l->total_ttc);
			$l->total_ht				= doubleval($l->total_ht);
			$l->multicurrency_total_ht	= doubleval($l->multicurrency_total_ht);
			$l->multicurrency_total_ttc = doubleval($l->multicurrency_total_ttc);
			$isOuvrage					= !empty(isModEnabled('ouvrage')) && class_exists('Ouvrage') && Ouvrage::isOuvrage($l) ? 1 : 0;
			if ($l->rang >= $rang) {
				continue;
			}
			if (!empty($title_break) && $title_break->id == $l->id) {
				break;
			} elseif (!empty($l->array_options['options_infrastructure_ol'])) {
				// Ligne marquée optionnelle : montant exclu du total du bloc, cumulé séparément pour l'annotation du sous-total.
				// La quantité reste, elle, cumulée normalement (non masquée).
				$total_qty						+= $l->qty;
				$total_options					+= $l->total_ht;
				$multicurrency_total_options	+= $l->multicurrency_total_ht;
			} elseif (!TInfrastructure::isModInfrastructureLine($l) && empty($isOuvrage)) {
				$totalQty	= !empty($listOuvrages) && !empty($l->fk_parent_line) && array_key_exists($l->fk_parent_line, $listOuvrages) ? $listOuvrages[$l->fk_parent_line] : 1;
				$total_qty += $l->qty;
				if ($object->element == 'facture' && $object->type == Facture::TYPE_SITUATION) {
					$sitFacTotLineAvt	= getDolGlobalInt('INFRASPLUS_PDF_SITFAC_TOTLINE_AVT', 0);
					// 1 = (legacy mode): situation_percent is cumulative (state at situation)
					// 2 = (new mode): situation_percent is non-cumulative (delta of current situation)
					$isCumulative = getDolGlobalInt('INVOICE_USE_SITUATION') === 1;
					if ($l->situation_percent > 0 && !empty($l->total_ht) && empty($sitFacTotLineAvt)) {
						$prev_progress = method_exists($l, 'get_prev_progress') ? $l->get_prev_progress($object->id) : 0;
						if ($isCumulative) {
							// legacy mode: $l->situation_percent = cumulative progress within the cycle
							$progressState				= $l->situation_percent;
							$progressDelta				= $progressState - $prev_progress;
							$progressRatio				= $progressDelta / $progressState;
							$lineTotalHT				= $sign * $l->total_ht * $progressRatio;
							$lineTotalTVA				= $sign * $l->total_tva * $progressRatio;
							$lineTotalTTC				= $sign * $l->total_ttc * $progressRatio;
							$lineMulticurrencyTotalHT	= $sign * $l->multicurrency_total_ht * $progressRatio;
							$lineMulticurrencyTotalTTC	= $sign * $l->multicurrency_total_ttc * $progressRatio;
						} else {
							// new mode: $l->situation_percent = progress delta of this situation invoice
							// the delta (=non-cumulative) values are stored directly on the line
							$lineTotalHT				= $l->total_ht;
							$lineTotalTVA				= $l->total_tva;
							$lineTotalTTC				= $l->total_ttc;
							$lineMulticurrencyTotalHT	= $l->multicurrency_total_ht;
							$lineMulticurrencyTotalTTC	= $l->multicurrency_total_ttc;
						}
						$total						+= $lineTotalHT;
						$total_tva					+= $lineTotalTVA;
						$total_ttc					+= $lineTotalTTC;
						if (!isset($TTotal_tva[$l->tva_tx])) {
							$TTotal_tva[$l->tva_tx]	= 0;
						}
						$TTotal_tva[$l->tva_tx]		+= $lineTotalTVA;
						$multicurrency_total_ht		+= $lineMulticurrencyTotalHT;
						$multicurrency_total_ttc	+= $lineMulticurrencyTotalTTC;
					} elseif ($l->product_type != 9) {
						$total						+= $l->total_ht * $totalQty;
						$total_tva					+= $l->total_tva * $totalQty;
						$TTotal_tva[$l->tva_tx]		+= $l->total_tva * $totalQty;
						$total_ttc					+= $l->total_ttc * $totalQty;
						$multicurrency_total_ht		+= $l->multicurrency_total_ht * $totalQty;
						$multicurrency_total_ttc	+= $l->multicurrency_total_ttc * $totalQty;
					}
				} elseif ($l->product_type != 9) {
					$total							+= $l->total_ht * $totalQty;
					$total_tva						+= $l->total_tva * $totalQty;
					$multicurrency_total_ht			+= $l->multicurrency_total_ht * $totalQty;
					if (! isset($TTotal_tva[$l->tva_tx])) {
						$TTotal_tva[$l->tva_tx]	= 0;
					}
					$TTotal_tva[$l->tva_tx]			+= $l->total_tva * $totalQty;
					$total_ttc						+= $l->total_ttc * $totalQty;
					$multicurrency_total_ttc		+= $l->multicurrency_total_ttc * $totalQty;
					$vatrate = (string) $l->tva_tx;
					if (($l->info_bits & 0x01) == 0x01) {
						$vatrate .= '*';
					}
					$vatcode	= $l->vat_src_code;
					if (empty($TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'])) {
						$TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'] = 0;
					}
					$TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')] = ['vatrate' => $vatrate, 'vatcode' => $vatcode, 'amount' => $TTotal_tva_array[$vatrate.($vatcode ? ' ('.$vatcode.')' : '')]['amount'] + $l->total_tva, 'base' => $total];
				}
			}
		}
		if (!$return_all) {
			$result	= $total;
		} else {
			$result	= [$total, $total_tva, $total_ttc, $TTotal_tva, $total_qty, $TTotal_tva_array, $multicurrency_total_ht, $multicurrency_total_ttc, $total_options, $multicurrency_total_options];
		}
		if ($memoEnabled) {
			$object->context['infrastructureCache']['totalLineByKey'][$memoKey]	= $result;
		}
		return $result;
	}

	/**
	* TODO ne gère pas encore la numération des lignes "Totaux"
	*
	* @param	CommonObjectLine[]	$TLineTitle		Array of title lines
	* @param	CommonObjectLine|null	$line_reference	Parent title line reference
	* @param	int					$level			Level
	* @param	int					$prefix_num 	Prefix number
	* @return	array
	*/
	function infrastructure_formatNumerotation(&$TLineTitle, $line_reference = null, $level = 1, $prefix_num = 0)
	{
		$i							= 1;
		$j							= 0;
		$TTitle 					= [];
		$TLineElementsWithoutLabel	= ['facture_fourn_det', 'commande_fournisseurdet'];
		foreach ($TLineTitle as $k => &$line) {
			if (!empty($line_reference) && $line->rang <= $line_reference->rang) continue;
			if (!empty($line_reference) && $line->qty <= $line_reference->qty) break;
			if ($line->qty == $level) {
				$TTitle[$j]['numerotation'] = ($prefix_num == 0) ? $i : $prefix_num.'.'.$i;
				if (empty($line->label) && (in_array($line->element, $TLineElementsWithoutLabel))) {
					$line->label	= !empty($line->desc) ? $line->desc : $line->description;
					$line->desc		= $line->description = '';
				}
				$line->label		= $TTitle[$j]['numerotation'].' '.$line->label;
				$TTitle[$j]['line'] = &$line;
				$deep_level			= $line->qty;
				do {
					$deep_level++;
					$TTitle[$j]['children'] = infrastructure_formatNumerotation($TLineTitle, $line, $deep_level, $TTitle[$j]['numerotation']);
				} while (empty($TTitle[$j]['children']) && $deep_level <= 10); // Exemple si un bloc Titre lvl 1 contient pas de sous lvl 2 mais directement un sous lvl 5
				// Rappel on peux avoir jusqu'a 10 niveau de titre
				$i++;
				$j++;
			}
		}
		return $TTitle;
	}

	/**
	* Get HTML data
	*
	* @param	array		$parameters		Parameters
	* @param	CommonObject$object			Object
	* @param	string		$action			Action
	* @param	HookManager	$hookmanager	Hook manager
	* @return	string
	*/
	function infrastructure_getHtmlData($parameters, &$object, &$action, HookManager $hookmanager)
	{

		$line							= &$parameters['line'];
		$ThtmlData['data-id']           = $line->id;
		$ThtmlData['data-product_type'] = $line->product_type;
		$ThtmlData['data-qty']          = 0; //$line->qty;
		$ThtmlData['data-level']        = TInfrastructure::getNiveau($line);
		if (TInfrastructure::isTitle($line)) {
			$ThtmlData['data-isinfrastructure']			= 'title';
			$ThtmlData['data-folder-status']		= 'open';
			if (!empty($line->array_options['options_hideblock'])) {
				$ThtmlData['data-folder-status']	= 'closed';
			}
		} elseif (TInfrastructure::isTotal($line)) {
			$ThtmlData['data-isinfrastructure']	= 'infrastructure';
		} else {
			$ThtmlData['data-isinfrastructure']	= 'freetext';
		}
		// Change or add data  from hooks
		$parameters	= array_replace($parameters, [  'ThtmlData' => $ThtmlData ]);
		// hook
		$reshook	= $hookmanager->executeHooks('infrastructureLineHtmlData', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}
		if ($reshook > 0) {
			$ThtmlData	= $hookmanager->resArray;
		}
		return infrastructure_implodeHtmlData($ThtmlData);
	}

	/**
	* Implode HTML data
	*
	* @param array $ThtmlData HTML data
	* @return string
	*/
	function infrastructure_implodeHtmlData($ThtmlData = [])
	{
		$data = '';
		foreach ($ThtmlData as $k => $h) {
			if (is_array($h)) {
				$h = json_encode($h);
			}
			$data .= $k.'="'.dol_htmlentities($h, ENT_QUOTES).'" ';
		}
		return $data;
	}

	/**
	* Set doc TVA
	*
	* @param	TCPDF			$pdf	PDF
	* @param	CommonObject	$object	Object
	* @return	bool
	*/
	function infrastructure_setDocTVA(&$pdf, &$object)
	{
		$hideqtys	= GETPOST('hideqtys', 'int');
		if(empty($hideqtys)) return false;
		// TODO can't add VAT to document without lines... :-/
		return true;
	}

	/**
	* Show select title to add
	*
	* @param	CommonObject $object Object
	* @return	void
	*/
	function infrastructure_showSelectTitleToAdd(&$object)
	{
		global $langs;

		TInfrastructure::getAllTitleFromDocument($object);
		?>
		<script type="text/javascript">
			$(function () {
				var add_button = $("#addline");
				if (add_button.length > 0) {
					add_button.closest('tr').prev('tr.liste_titre').children('td:last').addClass('center').text("<?php echo $langs->trans('InfrastructureTitleToAddUnderTitle'); ?>");
					var select_title = $(<?php echo json_encode(infrastructure_getHtmlSelectTitle($object)); ?>);
					add_button.before(select_title);
				}
			});
		</script>
		<?php
	}

	// PDF generation cache helpers *****************

	/**
	*	Cache du parent title d'une ligne (par rang). Évite de refaire un array_reverse + foreach complet à chaque appel de hook PDF.
	*
	*	@param	CommonObject	$object	Document
	*	@param	int				$rang	Rang de la ligne
	*	@return	bool|object				Ligne titre parente ou false
	**/
	function infrastructure_getCachedParentTitle(&$object, $rang)
	{
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context	= [];
		}
		if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
			$object->context['infrastructureCache']	= [];
		}
		if (!isset($object->context['infrastructureCache']['parentTitleByRang']) || !is_array($object->context['infrastructureCache']['parentTitleByRang'])) {
			$object->context['infrastructureCache']['parentTitleByRang']	= [];
		}
		if (array_key_exists($rang, $object->context['infrastructureCache']['parentTitleByRang'])) {
			return $object->context['infrastructureCache']['parentTitleByRang'][$rang];
		}
		// Si le cache a été pré-chauffé (infrastructure_warmPDFInfrastructureCache), l'absence de clé => pas de parent
		if (!empty($object->context['infrastructureCache']['warmed'])) {
			$object->context['infrastructureCache']['parentTitleByRang'][$rang]	= false;
			return false;
		}
		$res	= TInfrastructure::getParentTitleOfLine($object, $rang, 0);
		$object->context['infrastructureCache']['parentTitleByRang'][$rang]	= $res;
		return $res;
	}

	/**
	*	Cache de la chaîne complète des titres englobants d'une ligne. Reproduit TInfrastructure::getAllTitleFromLine mais en O(1) après pré-chauffage.
	*	Fallback sur l'implémentation non mise en cache si le cache n'a pas été chauffé.
	*
	*	@param	CommonObject			$object		Document
	*	@param	CommonObjectLine		$line		Ligne
	*	@return	array								Titres englobants indexés par id
	**/
	function infrastructure_getCachedAllTitleFromLine(&$object, &$line)
	{
		if (empty($line) || !is_object($line) || !isset($line->rang)) {
			return [];
		}
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context	= [];
		}
		if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
			$object->context['infrastructureCache']	= [];
		}
		if (isset($object->context['infrastructureCache']['allTitleChainByRang'][$line->rang])) {
			return $object->context['infrastructureCache']['allTitleChainByRang'][$line->rang];
		}
		if (!empty($object->context['infrastructureCache']['warmed'])) {
			// Cache chauffé mais rang absent => chaîne vide
			return [];
		}
		return TInfrastructure::getAllTitleFromLine($line);
	}

	/**
	*	Pré-chauffage du cache des titres parents pour toutes les lignes en un seul passage.
	*	Évite les O(n²) cumulés dans les hooks PDF (appels répétés de getParentTitleOfLine + getAllTitleFromLine).
	*	Utilise une pile de titres ouverts : title => push, infrastructure => pop (sémantique alignée sur getParentTitleOfLine avec $lvl=0).
	*
	*	@param	CommonObject	$object	Document
	*	@return	void
	**/
	function infrastructure_warmPDFInfrastructureCache(&$object)
	{
		if (empty($object->lines) || !is_array($object->lines)) {
			return;
		}
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context	= [];
		}
		if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
			$object->context['infrastructureCache']	= [];
		}
		$parentByRang	= [];
		$chainByRang	= [];
		$openTitles		= [];	// pile de lignes titre ouvertes
		foreach ($object->lines as $line) {
			if (!is_object($line) || !isset($line->rang)) {
				continue;
			}
			// Parent du rang courant = sommet de pile avant traitement
			$parent		= !empty($openTitles) ? end($openTitles) : false;
			$parentByRang[$line->rang]	= $parent;
			// Chaîne complète = parent + chaîne du parent (indexée par id comme getAllTitleFromLine)
			if ($parent) {
				$parentChain	= isset($chainByRang[$parent->rang]) ? $chainByRang[$parent->rang] : [];
				$chain			= [$parent->id => $parent] + $parentChain;
			} else {
				$chain	= [];
			}
			$chainByRang[$line->rang]	= $chain;
			// Mise à jour de la pile pour la ligne suivante
			if (TInfrastructure::isTitle($line)) {
				$openTitles[]	= $line;
			} elseif (TInfrastructure::isTotal($line)) {
				array_pop($openTitles);
			}
		}
		$object->context['infrastructureCache']['parentTitleByRang']	= $parentByRang;
		$object->context['infrastructureCache']['allTitleChainByRang']	= $chainByRang;
		$object->context['infrastructureCache']['warmed']				= true;
	}

	/**
	*	Cache du résultat de titleHasTotalLine pour une ligne titre.
	*
	*	@param	CommonObject	$object			Document
	*	@param	object			$title_line		Ligne titre
	*	@param	bool			$strict_mode	Mode strict
	*	@return	bool
	**/
	function infrastructure_getCachedTitleHasTotal(&$object, &$title_line, $strict_mode = false)
	{
		if (empty($title_line) || !is_object($title_line) || !isset($title_line->rang)) {
			return false;
		}
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context	= [];
		}
		if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
			$object->context['infrastructureCache']	= [];
		}
		if (!isset($object->context['infrastructureCache']['titleHasTotalByKey']) || !is_array($object->context['infrastructureCache']['titleHasTotalByKey'])) {
			$object->context['infrastructureCache']['titleHasTotalByKey']	= [];
		}
		$key	= $title_line->rang.'_'.($strict_mode ? '1' : '0');
		if (array_key_exists($key, $object->context['infrastructureCache']['titleHasTotalByKey'])) {
			return $object->context['infrastructureCache']['titleHasTotalByKey'][$key];
		}
		$res	= TInfrastructure::titleHasTotalLine($object, $title_line, $strict_mode, false);
		$object->context['infrastructureCache']['titleHasTotalByKey'][$key]	= $res;
		return $res;
	}

	// PDF rendering helpers ************************

	/**
	*	Capture l'instance du modèle PDF natif appelant via debug_backtrace (parcours de la pile à la recherche d'un objet possédant la méthode _tableau()).
	*	Résultat mis en cache sur $object->context['infrastructureCache']['nativePdfModel'] pour éviter le coût des debug_backtrace successifs durant une même génération PDF.
	*	Le cache est réinitialisé par beforePDFCreation qui vide $object->context['infrastructureCache'].
	*
	*	@param	CommonObject	$object		Document (porte le cache via $object->context)
	*	@return	object|null					Instance du modèle PDF appelant, ou null si non trouvé
	**/
	function infrastructure_getCallerNativePdfModel(&$object)
	{
		if (!isset($object->context) || !is_array($object->context)) {
			$object->context	= [];
		}
		if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
			$object->context['infrastructureCache']	= [];
		}
		$cache	= $object->context['infrastructureCache']['nativePdfModel'] ?? null;
		if ($cache === false) {
			return null;
		}
		if (is_object($cache)) {
			return $cache;
		}
		$bt	= debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
		foreach ($bt as $frame) {
			if (empty($frame['object']) || !is_object($frame['object'])) {
				continue;
			}
			$obj	= $frame['object'];
			if ($obj instanceof ActionsInfrastructure) {
				continue;
			}
			if (method_exists($obj, '_tableau')) {
				$object->context['infrastructureCache']['nativePdfModel']	= $obj;
				return $obj;
			}
		}
		$object->context['infrastructureCache']['nativePdfModel']	= false;
		return null;
	}

	/**
	*	Dessine l'en-tête de tableau (libellés des colonnes) au-dessus d'un titre infrastructure portant l'option show_table_header_before.
	*	Compatible modèles natifs Dolibarr legacy (pdf_azur, pdf_cyan, pdf_crabe, pdf_sponge, pdf_einstein) via les propriétés posxXXX, et modèles modernes à cols (pdf_octopus, pdf_eratosthene) via $pdfModel->cols.
	*
	*	@param	TCPDF		$pdf		Instance PDF
	*	@param	object		$pdfModel	Instance du modèle PDF appelant
	*	@param	float		$posy		Position Y où dessiner l'en-tête
	*	@return	float					Hauteur consommée en mm (0 si rien dessiné)
	**/
	function infrastructure_drawNativeTableHeaderBefore($pdf, $pdfModel, $posy)
	{
		global $langs;

		if (!function_exists('pdf_getPDFFontSize')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		}
		$default_font_size	= pdf_getPDFFontSize($langs);
		$headerHeight		= 5;	// Hauteur standard d'un en-tête de tableau (5 mm dans pdf_azur)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 1);
		// Modèles modernes avec cols (pdf_octopus, pdf_eratosthene...) : utilise la définition de colonnes du modèle
		if (property_exists($pdfModel, 'cols') && is_array($pdfModel->cols) && !empty($pdfModel->cols)) {
			foreach ($pdfModel->cols as $colKey => $colDef) {
				if (method_exists($pdfModel, 'getColumnStatus') && !$pdfModel->getColumnStatus($colKey)) {
					continue;
				}
				if (empty($colDef['title'])) {
					continue;
				}
				$xstartpos	= (int) ($colDef['xStartPos'] ?? 0);
				$padLeft	= (int) ($colDef['title']['padding'][3] ?? 0);
				$padTop		= (int) ($colDef['title']['padding'][0] ?? 0);
				$padRight	= (int) ($colDef['title']['padding'][1] ?? 0);
				$width		= (int) ($colDef['width'] ?? 0) - $padLeft - $padRight;
				$labelText	= !empty($colDef['title']['label']) ? $colDef['title']['label'] : $langs->transnoentities($colDef['title']['textkey'] ?? '');
				$align		= $colDef['title']['align'] ?? 'L';
				if ($width > 0) {
					$pdf->SetXY($xstartpos + $padLeft, $posy + $padTop);
					$pdf->MultiCell($width, 2, $labelText, '', $align);
				}
			}
			if (property_exists($pdfModel, 'marge_gauche') && property_exists($pdfModel, 'marge_droite') && property_exists($pdfModel, 'page_largeur')) {
				$pdf->line($pdfModel->marge_gauche, $posy + $headerHeight, $pdfModel->page_largeur - $pdfModel->marge_droite, $posy + $headerHeight);
			}
			return $headerHeight;
		}
		// Modèles natifs legacy (pdf_azur, pdf_cyan, pdf_crabe, pdf_sponge, pdf_einstein) : aligne sur la logique de _tableau() de ces modèles
		if (property_exists($pdfModel, 'posxdesc') && property_exists($pdfModel, 'posxup') && property_exists($pdfModel, 'posxqty')) {
			$posxdesc			= $pdfModel->posxdesc;
			$posxup				= $pdfModel->posxup;
			$posxqty			= $pdfModel->posxqty;
			$posxtva			= property_exists($pdfModel, 'posxtva') ? $pdfModel->posxtva : null;
			$posxunit			= property_exists($pdfModel, 'posxunit') ? $pdfModel->posxunit : null;
			$posxdiscount		= property_exists($pdfModel, 'posxdiscount') ? $pdfModel->posxdiscount : null;
			$postotalht			= property_exists($pdfModel, 'postotalht') ? $pdfModel->postotalht : null;
			$atleastonediscount	= property_exists($pdfModel, 'atleastonediscount') ? $pdfModel->atleastonediscount : 0;
			$pdf->SetXY($posxdesc - 1, $posy + 1);
			$pdf->MultiCell(108, 2, $langs->transnoentities('Designation'), '', 'L');
			if (!getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') && !getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN') && $posxtva !== null) {
				$pdf->SetXY($posxtva - 3, $posy + 1);
				$pdf->MultiCell($posxup - $posxtva + 3, 2, $langs->transnoentities('VAT'), '', 'C');
			}
			$pdf->SetXY($posxup - 1, $posy + 1);
			$pdf->MultiCell($posxqty - $posxup - 1, 2, $langs->transnoentities('PriceUHT'), '', 'C');
			if ($posxunit !== null) {
				$pdf->SetXY($posxqty - 1, $posy + 1);
				$pdf->MultiCell($posxunit - $posxqty - 1, 2, $langs->transnoentities('Qty'), '', 'C');
				if (getDolGlobalInt('PRODUCT_USE_UNITS') && $posxdiscount !== null) {
					$pdf->SetXY($posxunit - 1, $posy + 1);
					$pdf->MultiCell($posxdiscount - $posxunit - 1, 2, $langs->transnoentities('Unit'), '', 'C');
				}
			} else {
				$pdf->SetXY($posxqty - 1, $posy + 1);
				$endXqty	= $posxdiscount !== null ? $posxdiscount - 1 : ($postotalht !== null ? $postotalht - 1 : $posxqty + 16);
				$pdf->MultiCell($endXqty - $posxqty, 2, $langs->transnoentities('Qty'), '', 'C');
			}
			if (!empty($atleastonediscount) && $posxdiscount !== null && $postotalht !== null) {
				$pdf->SetXY($posxdiscount - 1, $posy + 1);
				$pdf->MultiCell($postotalht - $posxdiscount + 1, 2, $langs->transnoentities('ReductionShort'), '', 'C');
			}
			if ($postotalht !== null) {
				$pdf->SetXY($postotalht - 1, $posy + 1);
				$pdf->MultiCell(30, 2, $langs->transnoentities('TotalHTShort'), '', 'C');
			}
			if (property_exists($pdfModel, 'marge_gauche') && property_exists($pdfModel, 'marge_droite') && property_exists($pdfModel, 'page_largeur')) {
				$pdf->line($pdfModel->marge_gauche, $posy + $headerHeight, $pdfModel->page_largeur - $pdfModel->marge_droite, $posy + $headerHeight);
			}
			return $headerHeight;
		}
		return 0;
	}

	/**
	*	Redessine les valeurs des colonnes TVA, Total HT (et Total TTC si disponible) sur la ligne d'un titre porteur de totaux stockés.
	*	Utilisé après un saut de page où les hooks vat/total ont été neutralisés (sinon Dolibarr les dessinerait avec le $curY d'origine — avant le saut de page — soit dans la zone d'en-tête de la nouvelle page).
	*	Compatible modèles natifs Dolibarr legacy (pdf_azur, pdf_cyan, pdf_crabe, pdf_sponge, pdf_einstein) via les propriétés posxXXX / postotalht, et modèles modernes à cols (pdf_octopus, pdf_eratosthene) via $pdfModel->cols.
	*
	*	@param	TCPDF			$pdf		Instance PDF
	*	@param	object			$pdfModel	Instance du modèle PDF appelant
	*	@param	CommonObject	$object		Document parent
	*	@param	object			$line		Ligne titre
	*	@param	float			$posy		Position Y où dessiner les colonnes
	*	@return	void
	**/
	function infrastructure_drawTitleColumnsAtPosY($pdf, $pdfModel, &$object, &$line, $posy)
	{
		global $langs;

		$use_multicurrency	= isModEnabled('multicurrency') && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1 ? 1 : 0;
		$totalHtToPrint		= '';
		if (isset($line->infrastructure_title_total_ht)) {
			if ($use_multicurrency && isset($line->infrastructure_title_multicurrency_total_ht)) {
				$totalHtToPrint	= price($line->infrastructure_title_multicurrency_total_ht, 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			} else {
				$totalHtToPrint	= price($line->infrastructure_title_total_ht, 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			}
		}
		$totalTtcToPrint	= '';
		if (isset($line->infrastructure_title_total_ttc)) {
			if ($use_multicurrency && isset($line->infrastructure_title_multicurrency_total_ttc)) {
				$totalTtcToPrint	= price($line->infrastructure_title_multicurrency_total_ttc, 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			} else {
				$totalTtcToPrint	= price($line->infrastructure_title_total_ttc, 0, $langs, 1, 0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
			}
		}
		$vatToPrint			= '';
		if (isset($line->infrastructure_common_vat) && $line->infrastructure_common_vat !== false && $line->infrastructure_common_vat !== null) {
			$vatToPrint		= vatrate($line->infrastructure_common_vat, true);
		}
		$qtyToPrint			= '';
		if (isset($line->infrastructure_title_total_qty)) {
			$qtyToPrint		= (string) $line->infrastructure_title_total_qty;
		}
		if ($totalHtToPrint === '' && $vatToPrint === '' && $totalTtcToPrint === '' && $qtyToPrint === '') {
			return;
		}
		$titleStyle	= getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_STYLE');
		$pdf->SetFont('', $titleStyle, 9);
		infrastructure_setPdfTextColor($pdf, 'INFRASTRUCTURE_PDF_TITLE_COLOR');
		// Modèles modernes à cols (pdf_octopus, pdf_eratosthene...)
		if (property_exists($pdfModel, 'cols') && is_array($pdfModel->cols) && !empty($pdfModel->cols)) {
			if ($vatToPrint !== '') {
				infrastructure_drawTitleColumnCell($pdf, $pdfModel, 'vat', $posy, $vatToPrint);
			}
			if ($qtyToPrint !== '') {
				infrastructure_drawTitleColumnCell($pdf, $pdfModel, 'qty', $posy, $qtyToPrint);
			}
			if ($totalHtToPrint !== '') {
				infrastructure_drawTitleColumnCell($pdf, $pdfModel, 'totalexcltax', $posy, $totalHtToPrint);
			}
			if ($totalTtcToPrint !== '') {
				infrastructure_drawTitleColumnCell($pdf, $pdfModel, 'totalincltax', $posy, $totalTtcToPrint);
			}
			return;
		}
		// Modèles InfraSPlus (infraspackplus) : système $pdfModel->tableau[col]['posx'|'larg'] indexé par clés 'tva' / 'qty' / 'totalht' / 'totalttc'. Les positions sont calculées dans write_file() avant le rendu des lignes.
		// Le libellé du titre est dessiné par pdfAddTitle avec setCellPaddings(L, 1, R, 1) — padding top de 1mm qui décale verticalement le contenu de 1mm vers le bas par rapport au $posy d'entrée. Pour aligner les valeurs TVA / Qté / Total HT / Total TTC sur la même baseline que le libellé, on applique la même offset Y de 1mm aux MultiCell (équivalent à la branche legacy ci-dessous qui utilise $posy + 1).
		if (property_exists($pdfModel, 'tableau') && is_array($pdfModel->tableau)) {
			$heightline	= property_exists($pdfModel, 'heightline') && !empty($pdfModel->heightline) ? $pdfModel->heightline : 3;
			$padTop		= 1.0;
			if ($vatToPrint !== '' && !empty($pdfModel->tableau['tva']['posx']) && !empty($pdfModel->tableau['tva']['larg'])) {
				$pdf->MultiCell($pdfModel->tableau['tva']['larg'], $heightline, $vatToPrint, '', 'R', 0, 1, $pdfModel->tableau['tva']['posx'], $posy + $padTop, true, 0, 0, false, 0, 'M', false);
			}
			if ($qtyToPrint !== '' && !empty($pdfModel->tableau['qty']['posx']) && !empty($pdfModel->tableau['qty']['larg'])) {
				$pdf->MultiCell($pdfModel->tableau['qty']['larg'], $heightline, $qtyToPrint, '', 'R', 0, 1, $pdfModel->tableau['qty']['posx'], $posy + $padTop, true, 0, 0, false, 0, 'M', false);
			}
			if ($totalHtToPrint !== '' && !empty($pdfModel->tableau['totalht']['posx']) && !empty($pdfModel->tableau['totalht']['larg'])) {
				$pdf->MultiCell($pdfModel->tableau['totalht']['larg'], $heightline, $totalHtToPrint, '', 'R', 0, 1, $pdfModel->tableau['totalht']['posx'], $posy + $padTop, true, 0, 0, false, 0, 'M', false);
			}
			if ($totalTtcToPrint !== '' && !empty($pdfModel->tableau['totalttc']['posx']) && !empty($pdfModel->tableau['totalttc']['larg'])) {
				$pdf->MultiCell($pdfModel->tableau['totalttc']['larg'], $heightline, $totalTtcToPrint, '', 'R', 0, 1, $pdfModel->tableau['totalttc']['posx'], $posy + $padTop, true, 0, 0, false, 0, 'M', false);
			}
			return;
		}
		// Modèles natifs legacy (pdf_azur, pdf_cyan, pdf_crabe, pdf_sponge, pdf_einstein) : utilise les propriétés posxXXX
		if (property_exists($pdfModel, 'posxtva') && $pdfModel->posxtva && $vatToPrint !== '') {
			$posxup	= property_exists($pdfModel, 'posxup') ? $pdfModel->posxup : ($pdfModel->posxtva + 12);
			$pdf->SetXY($pdfModel->posxtva - 3, $posy + 1);
			$pdf->MultiCell($posxup - $pdfModel->posxtva + 3, 3, $vatToPrint, 0, 'R');
		}
		if (property_exists($pdfModel, 'posxqty') && $pdfModel->posxqty && $qtyToPrint !== '') {
			$endXqty	= property_exists($pdfModel, 'posxunit') && $pdfModel->posxunit
				? $pdfModel->posxunit : (property_exists($pdfModel, 'posxdiscount') && $pdfModel->posxdiscount
				? $pdfModel->posxdiscount : (property_exists($pdfModel, 'postotalht') && $pdfModel->postotalht ? $pdfModel->postotalht : $pdfModel->posxqty + 16));
			$pdf->SetXY($pdfModel->posxqty - 1, $posy + 1);
			$pdf->MultiCell($endXqty - $pdfModel->posxqty, 3, $qtyToPrint, 0, 'R');
		}
		if (property_exists($pdfModel, 'postotalht') && $pdfModel->postotalht && $totalHtToPrint !== '') {
			$rightEdge	= property_exists($pdfModel, 'page_largeur') && property_exists($pdfModel, 'marge_droite')
				? $pdfModel->page_largeur - $pdfModel->marge_droite
				: $pdfModel->postotalht + 30;
			$pdf->SetXY($pdfModel->postotalht, $posy + 1);
			$pdf->MultiCell($rightEdge - $pdfModel->postotalht, 3, $totalHtToPrint, 0, 'R');
		}
	}

	/**
	*	Dessine le contenu d'une cellule de colonne sur un modèle PDF moderne (à cols).
	*	Reproduit le placement effectué par printStdColumnContent() pour assurer un alignement identique au rendu standard.
	*
	*	@param	TCPDF		$pdf		Instance PDF
	*	@param	object		$pdfModel	Instance du modèle PDF appelant
	*	@param	string		$colKey		Clé de colonne (ex. 'vat', 'totalexcltax', 'totalincltax')
	*	@param	float		$posy		Position Y de la cellule
	*	@param	string		$text		Texte à afficher
	*	@return	void
	**/
	function infrastructure_drawTitleColumnCell($pdf, $pdfModel, $colKey, $posy, $text)
	{
		if (empty($pdfModel->cols[$colKey])) {
			return;
		}
		if (method_exists($pdfModel, 'getColumnStatus') && !$pdfModel->getColumnStatus($colKey)) {
			return;
		}
		$colDef		= $pdfModel->cols[$colKey];
		$xstartpos	= (int) ($colDef['xStartPos'] ?? 0);
		$padLeft	= (int) ($colDef['content']['padding'][3] ?? 0);
		$padTop		= (int) ($colDef['content']['padding'][0] ?? 0);
		$padRight	= (int) ($colDef['content']['padding'][1] ?? 0);
		$width		= (int) ($colDef['width'] ?? 0) - $padLeft - $padRight;
		$align		= $colDef['content']['align'] ?? 'R';
		if ($width > 0) {
			$pdf->SetXY($xstartpos + $padLeft, $posy + $padTop);
			$pdf->MultiCell($width, 2, $text, '', $align);
		}
	}

	// PDF lines preprocessing helpers (called from beforePDFCreation) ***

	/**
	*	Force remise_percent = 100 (sentinelle non nulle) sur les sous-totaux dont le titre parent porte l'option show_reduc.
	*	Sans cela, pdf_azur et autres modèles natifs n'invoquent pas le hook pdf_getlineremisepercent (test if ($line->remise_percent)) et le pourcentage de remise globale calculé par ce hook ne s'affiche jamais.
	*	Le pourcentage réel est calculé ensuite par le hook qui réécrit $this->resprints.
	*
	*	@param	CommonObject	$object	Document parent
	*	@return	void
	**/
	function infrastructure_forceRemisePercentForShowReduc(&$object)
	{
		if (empty($object->lines) || !is_array($object->lines)) {
			return;
		}
		foreach ($object->lines as &$line) {
			if (TInfrastructure::isTotal($line)) {
				$parentTitle	= infrastructure_getCachedParentTitle($object, $line->rang);
				if (is_object($parentTitle) && empty($parentTitle->array_options)) {
					$parentTitle->fetch_optionals();
				}
				if (!empty($parentTitle->id) && !empty($parentTitle->array_options['options_show_reduc'])) {
					$line->remise_percent	= 100;	// Sentinel non-zero — final % comes from pdf_getlineremisepercent hook
				}
			}
		}
		unset($line);
	}

	/**
	*	Implements the INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL option : when active, copies the totals (HT, TVA, TTC, multicurrency, common VAT rate) from each subtotal line to its parent title line (same level), then physically removes the subtotal lines from $object->lines so they are not rendered in the PDF. The title-side hooks (pdf_getlinetotalexcltax / pdf_getlinetotalwithtax / pdf_getlinevatrate) read the stored values to render totals directly on the title row.
	*
	*	Must run BEFORE infrastructure_applyTitlePrintAsListOrCondensed so the children of titles flagged with print_as_list / print_condensed are still present when getCommonVATRate / infrastructure_get_totalLineFromObject iterate $object->lines on each subtotal.
	*
	*	@param	CommonObject	$object	Document parent (propal, commande, facture...)
	*	@return	void
	**/
	function infrastructure_applyTitleWithTotal(&$object)
	{
		if (empty($object->lines) || !is_array($object->lines)) {
			return;
		}
		if (empty(getDolGlobalString('INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL'))) {
			return;
		}
		// Pré-chauffe infrastructure_getCachedTitleHasTotal pour chaque titre AVANT la suppression des sous-totaux. Sinon les hooks hideqtys / hideprices / hideInnerLines (qui exigent la présence d'un sous-total sous le titre parent) seraient désactivés à tort puisque la fonction itère $object->lines pour trouver le sous-total.
		foreach ($object->lines as $line) {
			if (TInfrastructure::isTitle($line)) {
				infrastructure_getCachedTitleHasTotal($object, $line, false);
				infrastructure_getCachedTitleHasTotal($object, $line, true);
			}
		}
		$showQtyByDefault	= TInfrastructure::showQtyForObject($object, 'pdf');
		$linesToHide		= [];
		foreach ($object->lines as $idx => $subTotal) {
			if (!TInfrastructure::isTotal($subTotal)) {
				continue;
			}
			// Localise le titre parent : titre de même niveau précédent le sous-total dans $object->lines.
			$niveau			= TInfrastructure::getNiveau($subTotal);
			$parentTitleIdx	= -1;
			for ($j = $idx - 1; $j >= 0; $j--) {
				$prev	= $object->lines[$j];
				if (TInfrastructure::isTitle($prev) && TInfrastructure::getNiveau($prev) == $niveau) {
					$parentTitleIdx	= $j;
					break;
				}
			}
			if ($parentTitleIdx >= 0) {
				$TInfo		= infrastructure_get_totalLineFromObject($object, $subTotal, false, 1);
				$vatRate	= TInfrastructure::getCommonVATRate($object, $subTotal);
				$parent		= &$object->lines[$parentTitleIdx];
				$parent->infrastructure_title_total_ht	= $TInfo[0];
				$parent->infrastructure_title_total_tva	= $TInfo[1];
				$parent->infrastructure_title_total_ttc	= $TInfo[2];
				if (!empty($TInfo[6])) {
					$parent->infrastructure_title_multicurrency_total_ht	= $TInfo[6];
				}
				if (!empty($TInfo[7])) {
					$parent->infrastructure_title_multicurrency_total_ttc	= $TInfo[7];
				}
				$parent->infrastructure_common_vat	= $vatRate;
				// Quantité cumulée : reportée sur le titre si INFRASTRUCTURE_DEFAULT_DISPLAY_QTY_FOR_TOTAL_ON_ELEMENTS_PDF
				if ($showQtyByDefault) {
					if (empty($subTotal->array_options) && method_exists($subTotal, 'fetch_optionals')) {
						$subTotal->fetch_optionals();
					}
					if (TInfrastructure::showQtyForObjectLine($subTotal, $showQtyByDefault)) {
						$parent->infrastructure_title_total_qty	= $TInfo[4];
					}
				}
				unset($parent);
			}
			$linesToHide[$idx]	= true;
		}
		if (!empty($linesToHide)) {
			// Sauvegarde des sous-totaux retirés dans le contexte — utilisé par InfraSPackPlus pour construire la page récap même quand INFRASTRUCTURE_PDF_TITLE_WITH_TOTAL est actif.
			if (!isset($object->context) || !is_array($object->context)) {
				$object->context	= [];
			}
			if (!isset($object->context['infrastructureCache']) || !is_array($object->context['infrastructureCache'])) {
				$object->context['infrastructureCache']	= [];
			}
			$removedTotals	= [];
			foreach ($linesToHide as $idx => $bool) {
				$total				= $object->lines[$idx];
				$parentTitleRang	= 0;
				$niveau				= TInfrastructure::getNiveau($total);
				for ($j = $idx - 1; $j >= 0; $j--) {
					if (TInfrastructure::isTitle($object->lines[$j]) && TInfrastructure::getNiveau($object->lines[$j]) == $niveau) {
						$parentTitleRang	= $object->lines[$j]->rang;
						break;
					}
				}
				$removedTotals[]	= ['total' => $total, 'parentTitleRang' => $parentTitleRang, 'level' => $niveau];
			}
			$object->context['infrastructureCache']['removedTotals']	= $removedTotals;
			$newLines	= [];
			foreach ($object->lines as $idx => $line) {
				if (empty($linesToHide[$idx])) {
					$newLines[]	= $line;
				}
			}
			$object->lines	= array_values($newLines);
		}
	}

	/**
	*	Folds child product/service lines below a title flagged with print_as_list (qty 20) or print_condensed (qty 30) into the title's description, then removes those children from $object->lines. Allows native Dolibarr PDF templates (azur, crabe, sponge, cyan...) to honor the InfraStructure block options the same way the InfraSPackPlus templates do via descWorksHidden.
	*
	*	Children are accumulated up to the next infrastructure-line (title, subtotal, free text) — sub-titles terminate the parent's accumulation. The transformation is in-memory only; the database is untouched.
	*
	*	@param	CommonObject	$object	Document parent (propal, commande, facture...)
	*	@return	void
	**/
	function infrastructure_applyTitlePrintAsListOrCondensed(&$object)
	{
		if (empty($object->lines) || !is_array($object->lines)) {
			return;
		}
		// Quick check : do we have at least one title with the flag ? Avoid useless work.
		$hasFlaggedTitle	= false;
		foreach ($object->lines as $line) {
			if (TInfrastructure::isTitle($line)) {
				$pal	= !empty($line->array_options['options_print_as_list']) && $line->array_options['options_print_as_list'] > 0;
				$pcond	= !empty($line->array_options['options_print_condensed']) && $line->array_options['options_print_condensed'] > 0;
				if ($pal || $pcond) {
					// InfraS add begin
					if (getDolGlobalString('INFRASTRUCTURE_MANAGE_OL') && !empty($line->array_options['options_infrastructure_ol'])) {
						continue; // Bloc OL : pas de transformation print_as_list/condensed, les hooks OL s'en chargent
					}
					// InfraS add end
					$hasFlaggedTitle	= true;
					break;
				}
			}
		}
		if (!$hasFlaggedTitle) {
			return;
		}
		// Subtotals will lose their children once we filter — freeze their totals now (same pattern as hideInnerLines flow).
		foreach ($object->lines as &$line) {
			if (TInfrastructure::isTotal($line)) {
				$TInfo				= infrastructure_get_totalLineFromObject($object, $line, false, 1);
				$line->total_ht		= $TInfo[0];
				$line->total_tva	= $TInfo[1];
				$line->total		= $line->total_ht;
				$line->total_ttc	= $TInfo[2];
				if (TInfrastructure::getNiveau($line) == 1) {
					$line->TTotal_tva		= $TInfo[3];
					$line->TTotal_tva_array	= $TInfo[5];
				}
				if (!empty($TInfo[6])) {
					$line->multicurrency_total_ht	= $TInfo[6];
				}
				if (!empty($TInfo[7])) {
					$line->multicurrency_total_ttc	= $TInfo[7];
				}
				// Pré-calcul du taux TVA commun pendant que les lignes filles sont encore présentes (pdf_getlinevatrate les cherchera après leur suppression).
				$line->infrastructure_common_vat	= TInfrastructure::getCommonVATRate($object, $line);
			}
		}
		unset($line);
		$groupTitleIdx	= -1;	// Index in $object->lines of the title currently absorbing children, -1 if none
		$groupMode		= '';	// 'list' or 'condensed'
		$groupBuffer	= '';	// HTML accumulated for the current group
		$linesToHide	= [];
		foreach ($object->lines as $idx => $line) {
			if (TInfrastructure::isTitle($line)) {
				if ($groupTitleIdx >= 0 && $groupBuffer !== '') {
					infrastructure_flushPrintAsListGroup($object, $groupTitleIdx, $groupMode, $groupBuffer);
				}
				$groupTitleIdx	= -1;
				$groupMode		= '';
				$groupBuffer	= '';
				$printAsList	= !empty($line->array_options['options_print_as_list']) && $line->array_options['options_print_as_list'] > 0;
				$printCondensed	= !empty($line->array_options['options_print_condensed']) && $line->array_options['options_print_condensed'] > 0;
				// InfraS add begin
				if (($printAsList || $printCondensed) && getDolGlobalString('INFRASTRUCTURE_MANAGE_OL') && !empty($line->array_options['options_infrastructure_ol'])) {
					continue; // Bloc OL : pas de transformation print_as_list/condensed
				}
				// InfraS add end
				if ($printAsList) {
					$groupTitleIdx	= $idx;
					$groupMode		= 'list';
					$groupBuffer	= '<ul>';
				} elseif ($printCondensed) {
					$groupTitleIdx	= $idx;
					$groupMode		= 'condensed';
					$groupBuffer	= '';
				}
			} elseif (TInfrastructure::isTotal($line) || TInfrastructure::isFreeText($line)) {
				if ($groupTitleIdx >= 0 && $groupBuffer !== '') {
					infrastructure_flushPrintAsListGroup($object, $groupTitleIdx, $groupMode, $groupBuffer);
				}
				$groupTitleIdx	= -1;
				$groupMode		= '';
				$groupBuffer	= '';
			} else {
				if ($groupTitleIdx >= 0) {
					$linesToHide[$idx]	= true;
					$rawLabel			= '';
					if (!empty($line->label)) {
						$rawLabel	= $line->label;
					} elseif (!empty($line->product_label)) {
						$rawLabel	= $line->product_label;
					} elseif (!empty($line->product_ref)) {
						$rawLabel	= $line->product_ref;
					} elseif (!empty($line->desc)) {
						$rawLabel	= $line->desc;
					} elseif (!empty($line->description)) {
						$rawLabel	= $line->description;
					}
					$cleanLabel	= dol_escape_htmltag(trim(strip_tags($rawLabel)));
					$qtySuffix	= ((float) $line->qty > 1) ? ' ('.$line->qty.')' : '';
					if ($groupMode === 'list') {
						$groupBuffer	.= '<li>'.$cleanLabel.$qtySuffix.'</li>';
					} else {
						$groupBuffer	.= ($groupBuffer === '' ? '' : ', ').$cleanLabel.$qtySuffix;
					}
				}
			}
		}
		if ($groupTitleIdx >= 0 && $groupBuffer !== '') {
			infrastructure_flushPrintAsListGroup($object, $groupTitleIdx, $groupMode, $groupBuffer);
		}
		if (!empty($linesToHide)) {
			$newLines	= [];
			foreach ($object->lines as $idx => $line) {
				if (empty($linesToHide[$idx])) {
					$newLines[]	= $line;
				}
			}
			$object->lines	= array_values($newLines);
		}
	}

	/**
	*	Append the buffered list/condensed HTML to a title line's desc/description so native Dolibarr PDF templates render it below the title label.
	*
	*	@param	CommonObject	$object			Document parent
	*	@param	int				$groupTitleIdx	Index of the title line in $object->lines
	*	@param	string			$groupMode		'list' or 'condensed'
	*	@param	string			$groupBuffer	Accumulated HTML body
	*	@return	void
	**/
	function infrastructure_flushPrintAsListGroup(&$object, $groupTitleIdx, $groupMode, $groupBuffer)
	{
		if (!isset($object->lines[$groupTitleIdx])) {
			return;
		}
		$closing	= ($groupMode === 'list') ? '</ul>' : '';
		$existing	= $object->lines[$groupTitleIdx]->desc ?? '';
		$separator	= ($existing !== '' && substr($existing, -6) !== '</ul>' && substr($existing, -5) !== '<br/>') ? '<br/>' : '';
		$merged		= $existing.$separator.$groupBuffer.$closing;
		$object->lines[$groupTitleIdx]->desc		= $merged;
		$object->lines[$groupTitleIdx]->description	= $merged;
	}
