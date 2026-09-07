<?php
	/* Copyright (C) 2025-2026	Sylvain Legrand			- <contact@infras.fr>	InfraS - <https://www.infras.fr>
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
	* along with this program.  If not, see <http://www.gnu.org/licenses/>.
	*
	* 	\file		/infrastructure/core/triggers/interface_90_modInfrastructure_infrastructuretrigger.class.php
	* 	\ingroup	Infrastructure
	* 	\brief		Triggers for Infrastructure
	*/
	include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	include_once DOL_DOCUMENT_ROOT.'/delivery/class/delivery.class.php';
	include_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
	dol_include_once('/infrastructure/class/infrastructure.class.php');
	dol_include_once('/infrastructure/core/lib/infrastructure.lib.php');
	dol_include_once('/infrastructure/core/lib/infrastructureAdmin.lib.php');

	/**
	 * Triggers class
	 */
	class InterfaceInfrastructuretrigger extends DolibarrTriggers
	{
		public $db;
		public $name				= '';							// Name of the trigger @var mixed|string
		public $description			= '';							// Description of the trigger @var string
		public $version				= self::VERSION_DEVELOPMENT;	// Version of the trigger @var string
		public $picto				= 'technic';					// Image of the trigger @var string
		public $family				= '';							// Category of the trigger @var string
		public $errors				= [];						// Errors reported by the trigger @var array
		public const VERSION_DEVELOPMENT	= 'development';				// @var string module is in development
		public const VERSION_EXPERIMENTAL	= 'experimental';				// @var string module is experimental
		public const VERSION_DOLIBARR		= 'dolibarr';					// @var string module is dolibarr ready
		/**
		* Constructor
		* @param DoliDB $db Database handler
		*/
		public function __construct($db)
		{
			global $langs;

			$langs->load('infrastructure@infrastructure');
			$this->db			= $db;
			$this->name			= preg_replace('/^Interface/i', '', get_class($this));
			$this->family		= 'Modules '.$langs->trans('basenameInfrastructure');
			$this->description	= $langs->trans('Module550090DescTrigger');
			$currentversion		= infrastructure_getLocalVersionMinDoli('infrastructure');
			$this->version		= $currentversion[0];	// 'development', 'experimental', 'dolibarr' or version
			$this->picto		= 'infrastructure@infrastructure';
		}

		/**
		* Trigger name
		*
		* @return string Name of trigger file
		*/
		public function getName()
		{
			return $this->name;
		}

		/**
		* Trigger description
		*
		* @return string Description of trigger file
		*/
		public function getDesc()
		{
			return $this->description;
		}

		/**
		* Trigger version
		*
		* @return string Version of trigger file
		*/
		public function getVersion()
		{
			global $langs;

			$langs->load('admin');
			if ($this->version == 'development') {
				return $langs->trans('Development');
			} elseif ($this->version == 'experimental') {
				return $langs->trans('Experimental');
			} elseif ($this->version == 'dolibarr') {
				return DOL_VERSION;
			} elseif (!empty($this->version)) {
				return $this->version;
			} else {
				return $langs->trans('Unknown');
			}
		}

		/**
		* @param CommonObject	 $parent  Document containing the line
		* @param CommonObjectLine $object  Line
		* @param int			  $rang	Rank of the line
		* @return void
		*/
		public static function addToBegin(&$parent, &$object, $rang)
		{
			foreach ($parent->lines as &$line) {
				// Si (ma ligne courrante n'est pas celle que je viens d'ajouter) et que (le rang courrant est supérieure au rang du titre)
				if ($object->id != $line->id && $line->rang > $rang) {
					// Update du rang de toutes les lignes suivant mon titre
					$parent->updateRangOfLine($line->id, $line->rang+1);
				}
			}
			// Update du rang de la ligne fraichement ajouté pour la déplacer sous mon titre
			$parent->updateRangOfLine($object->id, $rang+1);
			$object->rang	= $rang+1;
		}

		/**
		* @param CommonObject		$parent  Document containing the line
		* @param CommonObjectLine	$object  Line
		* @param int				$rang	Rank of the line
		* @return void
		*/
		public static function addToEnd(&$parent, &$object, $rang)
		{
			$title_level				= -1;
			$infrastructure_line_found	= false;
			foreach ($parent->lines as $k => &$line) {
				if ($line->rang < $rang) {
					continue;
				} elseif ($line->rang == $rang) { // Je suis sur la ligne de titre où je souhaite ajouter ma nouvelle ligne en fin de bloc
					$title_level	= $line->qty;
				} elseif (!$infrastructure_line_found && $title_level > -1 && $line->qty == 100 - $title_level) { // Le level de mon titre a été trouvé avant, donc maintenant je vais m'arrêter jusqu'à trouver un sous-total
					$infrastructure_line_found	= true;
					$rang	= $line->rang;
				}
				if ($infrastructure_line_found) {
					$parent->updateRangOfLine($line->id, $line->rang+1);
				}
			}
			if ($infrastructure_line_found) {
				$parent->updateRangOfLine($object->id, $rang);
				$object->rang	= $rang;
			}
		}

		/**
		* Function called when a Dolibarrr business event is done.
		* All functions 'runTrigger' are triggered if file
		* is inside directory core/triggers
		*
		* @param	string		$action		Event action code
		* @param	object		$object		Object
		* @param	User		$user		Object user
		* @param	Translate	$langs		Object langs
		* @param	Conf		$conf		Object conf
		* @return   int						<0 if KO, 0 if no triggered ran, >0 if OK
		*/
		public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
		{
			if (!isModEnabled('infrastructure')) {
				return 0;
			}
			$langs->load('infrastructure@infrastructure');

			// Action normalization
			if ($action == 'LINEBILL_UPDATE') {
				$action	= 'LINEBILL_MODIFY';
			}
			if ($action == 'LINEORDER_UPDATE') {
				$action	= 'LINEORDER_MODIFY';
			}
			if ($action == 'LINEPROPAL_UPDATE') {
				$action	= 'LINEPROPAL_MODIFY';
			}
			if ($action == 'LINEBILL_SUPPLIER_UPDATE') {
				$action	= 'LINEBILL_SUPPLIER_MODIFY';
			}
			dol_syslog('Trigger "'.$this->name.'" for action '.$action.' launched by '.__FILE__.' id = '.(isset($object->id) ? $object->id : ''));
			// Line invoice insert/create: deposit handling + shipping origin special_code
			if (in_array($action, ['LINEBILL_INSERT', 'LINEBILL_CREATE'])) {
				$this->LineInvoiceInsert($object, $user);
			}
			// Special code propagation from shipping/delivery origin
			if ($action === 'LINEBILL_INSERT') {
				$this->ShippingOriginLine($object, $user);
			}
			// Add line under title
			if (getDolGlobalString('INFRASTRUCTURE_ALLOW_ADD_LINE_UNDER_TITLE') && in_array($action, ['LINEPROPAL_INSERT', 'LINEORDER_INSERT', 'LINEBILL_INSERT'])) {
				$this->AddLineUnderTitle($object, $action);
			}
			// Orders to invoice title blocks
			if (in_array($action, ['LINEBILL_INSERT', 'LINEBILL_CREATE', 'LINEBILL_SUPPLIER_CREATE'])) {
				$this->OrdersToInvoiceBloc($object, $user, $action, $langs);
			}
			// Situation percent reset on line modify
			if ($action == 'LINEBILL_MODIFY') {
				$this->SituationPercentReset($object, $user);
			}
			// Optional handling
			if (getDolGlobalString('INFRASTRUCTURE_MANAGE_OL') && in_array($action, ['LINEPROPAL_INSERT', 'LINEPROPAL_MODIFY', 'LINEORDER_INSERT', 'LINEORDER_MODIFY', 'LINEBILL_INSERT', 'LINEBILL_MODIFY', 'LINEBILL_SUPPLIER_CREATE', 'LINEBILL_SUPPLIER_MODIFY'])) {
				$this->OptionalLineManagement($object, $user, $action, $langs);
			}
			// Recurring invoice TVA fix
			if ($action == 'BILL_CREATE' && !empty($object->fac_rec)) {
				$this->RecurringInvoiceCreate($object);
			}
			// Shipping title/infrastructure cleanup / Clone handling / Situation final
			if ($action == 'SHIPPING_CREATE') {
				$this->ShippingCreate($object, $user, $langs);
			} elseif (in_array($action, ['PROPAL_CREATE', 'ORDER_CREATE', 'BILL_CREATE']) && !empty($object->context) && in_array('createfromclone', $object->context)) {
				$this->CreateFromClone($object, $user, $action, $langs, $conf);
			} elseif ($action == 'BILL_MODIFY') {
				$this->SituationFinal($object);
			}
			return 0;
		}

		/**
		* Handle line invoice insert/create: deposit handling + shipping origin special_code
		* Refer to issue #379
		*
		* @param	object	$object		Line object
		* @param	User	$user		User object
		* @return	void
		*/
		private function LineInvoiceInsert($object, $user)
		{
			static $TInvoices	= [];
			/** @var FactureLigne $object */
			if ($object->origin_id > 0 && $object->origin === 'shipping') {
				$originLine		= new ExpeditionLigne($object->db);
				$resFetch		= $originLine->fetch($object->origin_id);
				if ($resFetch > 0 && $originLine->element_type === 'commande') {
					$originOriginLine	= new OrderLine($object->db);
					if ($originOriginLine->fetch($originLine->fk_elementdet) > 0) {
						if (TInfrastructure::isModInfrastructureLine($originOriginLine)) {
							$object->special_code	= TInfrastructure::getModuleNumber();
							$object->update($user, 1);
						}
					}
				}
			}
			if (!array_key_exists($object->fk_facture, $TInvoices) || $TInvoices[$object->fk_facture] === null) {
				$staticInvoice	= new Facture($this->db);
				if ($staticInvoice->fetch($object->fk_facture) < 0) {
					$object->error		= $staticInvoice->error;
					$object->errors[]	= $staticInvoice->errors;
				}
				$isEligible						= $staticInvoice->type == Facture::TYPE_DEPOSIT && GETPOST('typedeposit', 'aZ09') == 'variablealllines';
				$TInvoices[$object->fk_facture]	= $isEligible;
			}
			if ($TInvoices[$object->fk_facture]) {
				if (!empty($object->origin) && !empty($object->origin_id) && $object->special_code == TInfrastructure::getModuleNumber()) {
					$valuedeposit	= price2num(str_replace('%', '', GETPOST('valuedeposit', 'alpha')), 'MU');
					$object->qty	= 100 * $object->qty / $valuedeposit;
					if ($object->update(null, 1) < 0) {
						$object->errors[]	= $object->errors;
					}
				}
			}
		}

		/**
		* Handle special_code propagation from shipping/delivery origin line
		*
		* @param	object	$object		Line object
		* @param	User	$user		User object
		* @return	int					<0 if KO, 0 if OK
		*/
		private function ShippingOriginLine($object, $user)
		{
			if (!isset($object->origin) || !in_array($object->origin, ['shipping', 'delivery']) || empty($object->origin_id)) {
				return 0;
			}
			if ($object->element === 'delivery') {
				$originSendingLine	= new DeliveryLine($this->db);
			} else {
				$originSendingLine	= new ExpeditionLigne($this->db);
			}
			$originSendingLineFetchReturn	= $originSendingLine->fetch($object->origin_id);
			if ($originSendingLineFetchReturn < 0) {
				$this->error	= $originSendingLine->error;
				$this->errors	= $originSendingLine->errors;
				return $originSendingLineFetchReturn;
			}
			$originOrderLine			= new OrderLine($this->db);
			$originOrderLineFetchReturn	= $originOrderLine->fetch($originSendingLine->fk_elementdet ?? $originSendingLine->fk_origin_line);
			if ($originOrderLineFetchReturn < 0) {
				$this->error	= $originOrderLine->error;
				$this->errors	= $originOrderLine->errors;
				return $originOrderLineFetchReturn;
			}
			if ($originOrderLine->special_code == TInfrastructure::getModuleNumber()) {
				$object->special_code	= TInfrastructure::getModuleNumber();
				$updateReturn			= $object->update($user, 1); // No trigger to prevent loops
				if ($updateReturn < 0) {
					$this->error	= $object->error;
					$this->errors	= $object->errors;
					return $updateReturn;
				}
			}
			return 0;
		}

		/**
		* Handle adding a line under a specific title
		*
		* @param	object	$object		Line object
		* @param	string	$action		Event action code
		* @return	void
		*/
		private function AddLineUnderTitle(&$object, $action)
		{
			$id	= GETPOST('under_title', 'int');
			if ($id <= 0) {
				return;
			}
			switch ($action) {
				case 'LINEPROPAL_INSERT':
					$parent 	= new Propal($this->db);
					$parent->fetch($object->fk_propal);
					$lineobj	= new PropaleLigne($this->db);
					$lineobj->fetch($id);
					$rang   	= $lineobj->rang;
				break;
				case 'LINEORDER_INSERT':
					$parent		= new Commande($this->db);
					$parent->fetch($object->fk_commande);
					$lineobj	= new OrderLine($this->db);
					$lineobj->fetch($id);
					$rang		= $lineobj->rang;
				break;
				case 'LINEBILL_INSERT':
					$parent		= new Facture($this->db);
					$parent->fetch($object->fk_facture);
					$lineobj	= new FactureLigne($this->db);
					$lineobj->fetch($id);
					$rang		= $lineobj->rang;
				break;
				case 'LINEBILL_SUPPLIER_CREATE':
					$parent		= new FactureFournisseur($this->db);
					$parent->fetch($object->fk_facture_fourn);
					$lineobj	= new SupplierInvoiceLine($this->db);
					$lineobj->fetch($id);
					$rang		= $lineobj->rang;
				break;
				default:
					$parent	= $object;
					$rang	= null;
				break;
			}
			if (getDolGlobalString('INFRASTRUCTURE_ADD_LINE_UNDER_TITLE_AT_END_BLOCK')) {
				self::addToEnd($parent, $object, $rang);
			} else {
				self::addToBegin($parent, $object, $rang);
			}
		}

		/**
		* Handle orders to invoice title blocks
		*
		* @param	object		$object		Line object
		* @param	User		$user		User object
		* @param	string		$action		Event action code
		* @param	Translate	$langs		Translation object
		* @return	void
		*/
		private function OrdersToInvoiceBloc($object, $user, $action, $langs)
		{
			global $infrastructure_current_rang, $infrastructure_bloc_previous_fk_commande, $infrastructure_skip, $infrastructure_bloc_already_add_st;

			$is_supplier	= $action == 'LINEBILL_SUPPLIER_CREATE' ? true : false;
			if ($infrastructure_skip) {
				$infrastructure_skip	= false;
				return;
			}
			$infrastructure_add_title_bloc_from_orderstoinvoice	= GETPOST('infrastructure_add_title_bloc_from_orderstoinvoice', 'alpha') && GETPOST('createbills_onebythird', 'int');
			if (empty($infrastructure_add_title_bloc_from_orderstoinvoice)) {
				return;
			}
			$isOrderTitle	= TInfrastructure::isTitle($object);
			$isOrderTotal	= TInfrastructure::isTotal($object);
			if ($isOrderTitle || $isOrderTotal) {
				$object->qty	+= $isOrderTitle ? 1 : -1;
				if ($is_supplier) {
					$object->update(1);
				} else {
					$object->update($user, 1);
				}
			}
			if ($object->origin == 'order_supplier') {
				$current_fk_commande	= $object->origin_id;
			} else {
				$current_fk_commande	= TInfrastructure::getOrderIdFromLineId($object->origin_id, $is_supplier);
			}
			$last_fk_commandedet	= TInfrastructure::getLastLineOrderId($current_fk_commande, $is_supplier);
			if (!$is_supplier) {
				$facture	= new Facture($this->db);
				$ret		= $facture->fetch($object->fk_facture);
			} else {
				$facture	= new FactureFournisseur($this->db);
				$ret		= $facture->fetch($object->fk_facture_fourn);
			}
			$rang	= 0;
			if ($ret > 0 && !$infrastructure_bloc_already_add_st) {
				$rang	= !empty($infrastructure_current_rang) ? $infrastructure_current_rang : $object->rang;
				if ($current_fk_commande != $infrastructure_bloc_previous_fk_commande) {
					if (!$is_supplier) {
						$commande	= new Commande($this->db);
					} else {
						$commande	= new CommandeFournisseur($this->db);
					}
					$commande->fetch($current_fk_commande);
					$label	= getDolGlobalString('INFRASTRUCTURE_TEXT_FOR_TITLE_ORDERS_TO_INVOICE');
					if (empty($label)) {
						$label	= 'Commande [__REFORDER__]';
						if (!$is_supplier) {
							$label	.= ' - R\xe9f\xe9rence client : [__REFCUSTOMER__]';
						}
					}
					$label	= str_replace(['__REFORDER__', '__REFCUSTOMER__'], [$commande->ref, $commande->ref_client], $label);
					$desc	= '';
					if (GETPOST('infrastructure_add_shipping_list_to_title_desc', 'int')) {
						$desc	= $this->getShippingList($commande->id);
					}
					if (!empty($current_fk_commande)) {
						$infrastructure_skip	= true;
						TInfrastructure::addTitle($facture, $label, 1, $rang, $desc);
						$rang++;
					}
				}
				$object->rang	= $rang;
				$facture->updateRangOfLine($object->id, $rang);
				$rang++;
				if ($last_fk_commandedet === (int) $object->origin_id && !empty($current_fk_commande)) {
					$infrastructure_skip				= true;
					$infrastructure_bloc_already_add_st	= 1;
					$rang								+= 2;
					TInfrastructure::addTotal($facture, $langs->trans('InfrastructureSubtotal'), 1, $rang);
					$infrastructure_bloc_already_add_st	= 0;
					$rang++;
				}
			}
			$infrastructure_bloc_previous_fk_commande	= $current_fk_commande;
			$infrastructure_current_rang				= $rang;
		}

		/**
		* Handle situation percent reset on line modify
		*
		* @param	object	$object		Line object
		* @param	User	$user		User object
		* @return	void
		*/
		private function SituationPercentReset($object, $user)
		{
			if (GETPOST('all_progress', 'alpha') && TInfrastructure::isModInfrastructureLine($object)) {
				$object->situation_percent	= 0;
				$object->update($user, true);
			}
		}

		/**
		* Handle Optional line management
		*
		* @param	object		$object		Line object
		* @param	User		$user		User object
		* @param	string		$action		Event action code
		* @param	Translate	$langs		Translation object
		* @return	void
		*/
		private function OptionalLineManagement($object, $user, $action, $langs)
		{
			if (!function_exists('_updateLineOL')) {
				dol_include_once('/infrastructure/core/lib/infrastructure.lib.php');
			}
			dol_syslog('[INFRASTRUCTURE_MANAGE_OL] Trigger "'.$this->name.'" for action "'.$action.'" launched by '.__FILE__.'. object='.$object->element.' id='.$object->id);
			$TTitle	= TInfrastructure::getAllTitleFromLine($object);
			foreach ($TTitle as &$line) {
				if (!empty($line->array_options['options_infrastructure_ol'])) {
					if (!TInfrastructure::isModInfrastructureLine($object)) {
						$object->special_code = 3;
					}
					if ($object->element == 'propal') {
						$res	= $object->update(null);
					} else {
						$res	= $object->update($user, 1);
					}
					if ($res > 0) {
						setEventMessage($langs->trans('InfrastructureUpdateNcSuccess'));
					}
					break;
				}
			}
			if (empty($object->array_options)) {
				$object->fetch_optionals();
			}
			if (!empty($object->array_options['options_infrastructure_ol'])) {
				if (!TInfrastructure::isModInfrastructureLine($object)) {
					$object->special_code = 3;
				}
				if ($object->element == 'propaldet') {
					$res	= $object->update(1);
				} else {
					$res	= $object->update($user, 1);
				}
				if ($res > 0) {
					setEventMessage($langs->trans('InfrastructureUpdateNcSuccess'));
				}
			}
			$parent_element	= '';
			if ($object->element == 'propaldet') {
				$parent_element	= 'propal';
			}
			if ($object->element == 'commandedet') {
				$parent_element	= 'commande';
			}
			if ($object->element == 'facturedet') {
				$parent_element	= 'facture';
			}
			if (!empty($parent_element) && !empty($object->array_options['options_infrastructure_ol'])) {
				infrastructure_updateLineOL($parent_element, $object->{'fk_'.$parent_element}, $object->id, $object->array_options['options_infrastructure_ol'], 1);
			}
		}

		/**
		* Handle recurring invoice TVA fix on BILL_CREATE
		*
		* @param	object	$object		Invoice object
		* @return	void
		*/
		private function RecurringInvoiceCreate($object)
		{
			$object->fetch_lines();
			foreach ($object->lines as &$line) {
				if (TInfrastructure::isTotal($line) && !empty($line->tva_tx)) {
					$line->tva_tx	= 0;
					$line->update();
				}
			}
		}

		/**
		* Handle shipping title/infrastructure cleanup on SHIPPING_CREATE
		*
		* @param	object		$object		Expedition object
		* @param	User		$user		User object
		* @param	Translate	$langs		Translation object
		* @return	void
		*/
		private function ShippingCreate($object, $user, $langs)
		{
			$object->fetch_lines();
			$object->fetchObjectLinked();
			$cmd	= null;
			if (count($object->linkedObjectsIds['commande'] ?? []) === 1) {
				$cmd	= new Commande($this->db);
				$res	= $cmd->fetch(current($object->linkedObjectsIds['commande']));
				if ($res <= 0) {
					setEventMessage($langs->trans('InfrastructureErrorLoadingLinkedOrder'), 'errors');
				} else {
					$resLines	= $cmd->fetch_lines();
					if ($resLines <= 0) {
						setEventMessage($langs->trans('InfrastructureErrorLoadingLinesFromLinkedOrder'), 'errors');
					}
				}
			}
			$linesToDelete	= [];
			foreach ($object->lines as &$line) {
				$orderline	= new OrderLine($this->db);
				$orderline->fetch($line->origin_line_id);
				if (getDolGlobalString('INFRASTRUCTURE_NO_TITLE_SHOW_ON_EXPED_GENERATION')) {
					if (!isset($line->special_code) && $cmd) {
						foreach ($cmd->lines as $cmdLine) {
							if ($cmdLine->id == $line->origin_line_id) {
								$line->special_code	= $cmdLine->special_code;
								break;
							}
						}
					}
					if (TInfrastructure::isModInfrastructureLine($line)) {
						$resdelete	= $line->delete($user);
						if ($resdelete < 0) {
							setEventMessage($langs->trans('InfrastructureErrorDeleteLine'), 'errors');
						}
					}
				}
				if (TInfrastructure::isModInfrastructureLine($orderline)) {
					$line->special_code	= TInfrastructure::getModuleNumber();
				}
				if (TInfrastructure::isTitle($line)) {
					$lines	= TInfrastructure::getLinesFromTitleId($object, $line->id, true);
					$blocks	= [];
					$isThereProduct	= false;
					foreach ($lines as $lineInBlock) {
						if (TInfrastructure::isModInfrastructureLine($lineInBlock)) {
							$blocks[$lineInBlock->id]	= $lineInBlock;
						} else {
							$isThereProduct	= true;
						}
					}
					if (!$isThereProduct) {
						$linesToDelete	= [...$linesToDelete, ...$blocks];
					}
				}
			}
			if (!empty($linesToDelete)) {
				foreach ($linesToDelete as $lineToDelete) {
					$lineToDelete->delete($user);
				}
			}
		}

		/**
		* Handle clone: reset OL line amounts
		*
		* @param	object		$object		Document object
		* @param	User		$user		User object
		* @param	string		$action		Event action code
		* @param	Translate	$langs		Translation object
		* @param	Conf		$conf		Conf object
		* @return	void
		*/
		private function CreateFromClone($object, $user, $action, $langs, $conf)
		{
			$doli_action	= GETPOST('action', 'aZ09');
			if (!getDolGlobalString('INFRASTRUCTURE_MANAGE_OL') || !in_array($doli_action, ['confirm_clone'])) {
				return;
			}
			dol_syslog("[INFRASTRUCTURE_MANAGE_OL] Trigger '".$this->name."' for action '".$action."' launched by ".__FILE__.". object=".$object->element." id=".$object->id);
			if (method_exists($object, 'fetch_lines')) {
				$object->fetch_lines();
			} else {
				$object->fetch($object->id);
			}
			foreach ($object->lines as &$line) {
				if (empty($line->array_options)) {
					$line->fetch_optionals();
				}
				if (!TInfrastructure::isModInfrastructureLine($line) && !empty($line->array_options['options_infrastructure_ol'])) {
					$line->special_code = 3;
					if ($line->element == 'propaldet') {
						$res	= $line->update(1);
					} else {
						$res	= $line->update($user, 1);
					}
					if ($res > 0) {
						setEventMessage($langs->trans('Infrastructure_update_ol_success'));
					}
				}
			}
			if (!empty($line)) {
				$object->update_price(1);
			}
		}

		/**
		* Handle situation final flag on BILL_MODIFY
		*
		* @param	object	$object		Invoice object
		* @return	void
		*/
		private function SituationFinal($object)
		{
			if (!getDolGlobalString('INVOICE_USE_SITUATION') || $object->element != 'facture' || $object->type != Facture::TYPE_SITUATION) {
				return;
			}
			$object->situation_final	= 1;
			foreach ($object->lines as $line) {
				$progress	= infrastructure_getLineCurrentProgress($object->id, $line);
				if (!TInfrastructure::isModInfrastructureLine($line) && $progress < 100) {
					$object->situation_final	= 0;
					break;
				}
			}
			$sql	= 'UPDATE '.$this->db->prefix().'facture SET situation_final = '.((int) $object->situation_final).' WHERE rowid = '.((int) $object->id);
			$object->db->query($sql);
		}

		/**
		*  List BL ref
		*  @param  int	$orderId  ID of order
		*  @return	string
		*/
		private function getShippingList($orderId)
		{
			global $langs;

			$langs->load('infrastructure@infrastructure');

			$refBlList	= [];
			$refExpList	= [];
			if (!function_exists('isModEnabled')) {
				return '';
			}
			if (!isModEnabled('expedition')) {
				return '';
			}
			// LIST SHIPPING LINKED TO ORDER
			$sqlShip	= 'SELECT fk_target FROM '.$this->db->prefix().'element_element';
			$sqlShip	.= ' WHERE targettype = "shipping" AND sourcetype = "commande" AND fk_source = '.((int) $orderId);
			$sqlShip	.= ' ORDER BY fk_source ASC';
			$resultShip	= $this->db->query($sqlShip);
			if ($resultShip) {
				while ($shipping	= $this->db->fetch_object($resultShip) ) {
					if (isModEnabled('delivery')) {
						// SELECT LIVRAISON LINKED TO SHIPPING
						$sqlBl	= 'SELECT liv.ref FROM '.$this->db->prefix().'element_element AS el';
						$sqlBl	.= ' JOIN '.$this->db->prefix().'livraison AS liv ON el.fk_target = liv.rowid';
						$sqlBl	.= ' WHERE el.targettype = "delivery" AND el.sourcetype = "shipping" AND el.fk_source='.((int) $shipping->fk_target).' AND liv.fk_statut = 1';
						$sqlBl	.= ' ORDER BY el.fk_target ASC';
						$resultDelivery	= $this->db->query($sqlBl);
						if ($resultDelivery) {
							while ($delivery	= $this->db->fetch_object($resultDelivery) ) {
								$refBlList[]	= $delivery->ref;
							}
						}
					}
					// SELECT SHIPPING REF
					$sqlExp		= 'SELECT rowid, ref FROM '.$this->db->prefix().'expedition WHERE rowid = '.((int) $shipping->fk_target);
					$resultExp	= $this->db->query($sqlExp);
					if ($resultExp) {
						$exp			= $this->db->fetch_object($resultExp);
						$refExpList[]	= $exp->ref;
					}
				}
			}
			$refList	= [...$refBlList, ...$refExpList];
			$output		= '';
			if (!empty($refExpList)) {
				$objectLabel	= count($refExpList) > 1 ? $langs->trans('InfrastructureLinkedShippings') : $langs->trans('InfrastructureLinkedShipping');
				$output			.= (!empty($output)?'<br>':'').'<strong>'.$objectLabel.' :</strong> '.implode(', ', $refList);
			}
			if (!empty($refBlList)) {
				$objectLabel	= count($refBlList) > 1 ? $langs->trans('InfrastructureLinkedDeliveries') : $langs->trans('InfrastructureLinkedDelivery');
				$output			.= (!empty($output)?'<br>':'').'<strong>'.$objectLabel.' :</strong> '.implode(', ', $refList);
			}
			return $output;
		}
	}
