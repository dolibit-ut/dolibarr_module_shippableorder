<?php
class ShippableOrder
{
	function __construct () {
		$this->TlinesShippable = array();
		$this->order = null;
	}
	
	function isOrderShippable($idOrder){
		global $db;
		
		$this->order = new Commande($db);
		$this->order->fetch($idOrder);
		$this->order->loadExpeditions();
		
		$nbShippable = 0;
		$nbProduct = 0;
		
		$TSomme = array();
		foreach($this->order->lines as $line){
			
			if($line->product_type==0 && $line->fk_product>0) {
				$nbProduct++;
				
				// Prise en compte des quantité déjà expédiées
				$qtyAlreadyShipped = $this->order->expeditions[$line->id];
				$line->qty_toship = $line->qty - $qtyAlreadyShipped;
				
				$isshippable = $this->isLineShippable($line, $TSomme);
				if($isshippable == 1) {
					$nbShippable++;
				}
			}
		}
		
		return array('nbProduct'=>$nbProduct, 'nbShippable'=>$nbShippable);
	}
	
	function isLineShippable(&$line, &$TSomme) {
		global $db;
		
		$TSomme[$line->fk_product] += $line->qty_toship;
		
		if(!isset($line->stock)) {
			$produit = new Product($db);
			$produit->fetch($line->fk_product);
			
			$produit->load_stock();
			$line->stock = $produit->stock_reel;
		}
		
		if($line->stock <= 0) {
			$isShippable = 0;
			$qtyShippable = 0;
		} else if ($TSomme[$line->fk_product] < $line->stock) {
			$isShippable = 1;
			$qtyShippable = $line->qty_toship;
		} else {
			$isShippable = 2;
			$qtyShippable = $line->qty_toship - $TSomme[$line->fk_product] + $line->stock;
		}
		
		$this->TlinesShippable[$line->id] = array('stock'=>$line->stock,'shippable'=>$isShippable,'to_ship'=>$line->qty_toship,'qty_shippable'=>$qtyShippable);
		
		return $isShippable;
	}
	
	function orderStockStatus($idOrder,$short=true){
		global $langs;
		
		$isShippable = $this->isOrderShippable($idOrder);
		$txt = '';
		
		if($isShippable['nbProduct'] == $isShippable['nbShippable'])
			$txt .= img_picto($langs->trans('EnStock'), 'statut4.png');
		elseif($isShippable['nbShippable'] == 0)
			$txt .= img_picto($langs->trans('HorsStock'), 'statut8.png');
		else
			$txt .= img_picto($langs->trans('StockPartiel'), 'statut1.png');
		
		$label = 'NbProductShippable';
		if($short) $label = 'NbProductShippableShort';
		
		$txt .= ' '.$langs->trans($label, $isShippable['nbShippable'], $isShippable['nbProduct']);
		
		return $txt;
	}
	
	function orderLineStockStatus($line){
		global $langs;
		
		if(isset($this->TlinesShippable[$line->id])) {
			$isShippable = $this->TlinesShippable[$line->id];
		} else {
			return '';
		}
		
		if($isShippable['shippable'] == 1) {
			$pictopath = img_picto('', 'statut4.png', '', false, 1);
			$infos = $langs->trans('EnStock', $isShippable['stock']);
		} elseif($isShippable['shippable'] == 0) {
			$pictopath = img_picto('', 'statut8.png', '', false, 1);
			$infos = $langs->trans('HorsStock', $isShippable['stock']);
		} else {
			$pictopath = img_picto('', 'statut1.png', '', false, 1);
			$infos = $langs->trans('StockPartiel', $isShippable['stock']);
		}
		
		$infos.= "\n".$langs->trans('RemainToShip', $isShippable['to_ship']);
		$infos.= "\n".$langs->trans('QtyShippable', $isShippable['qty_shippable']);
		
		$picto = '<img src="'.$pictopath.'" border="0" title="'.$infos.'">';
		
		return $picto;
	}
	
	function is_ok_for_shipping($idOrder){
		$isShippable = $this->isOrderShippable($idOrder);
		
		if($isShippable['nbProduct'] == $isShippable['nbShippable']) return true;
		
		return false;
	}
	
	/**
	 * Création automatique des expéditions à partir de la liste des expédiables, uniquement avec les quantité expédiables
	 */
	function createShipping($db, $TIDCommandes, $TEnt_comm) {
		global $user, $langs;
		
		dol_include_once('/expedition/class/expedition.class.php');
		
		$nbShippingCreated = 0;
		
		if(count($TIDCommandes) > 0) {
			
			foreach($TIDCommandes as $id_commande) {
				
				$this->isOrderShippable($id_commande);

				$shipping = new Expedition($db);
				$shipping->origin = 'commande';
				$shipping->origin_id = $id_commande;
				
				$shipping->weight_units = 0;
				$shipping->weight = 0;
				$shipping->size = 0;
				$shipping->sizeW = 0;
				$shipping->sizeH = 0;
				$shipping->sizeS = 0;
				$shipping->size_units = 0;
				$shipping->socid = $this->order->socid;
				
				foreach($this->order->lines as $line) {
					
					if($this->TlinesShippable[$line->id]['stock'] > 0) {
						$shipping->addline($TEnt_comm[$this->order->id], $line->id, $this->TlinesShippable[$line->id]['stock']);
					}
				}
				
				$nbShippingCreated++;
				$shipping->create($user);
			}
			
			if($nbShippingCreated > 0) {
				setEventMessage($langs->trans('NbShippingCreated', $nbShippingCreated));
			}
		}
	}
}