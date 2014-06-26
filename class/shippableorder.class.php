<?php
class ShippableOrder
{
	function __construct () {
		$this->TlinesShippable = array();
	}
	
	function isOrderShippable($idOrder){
		global $db;
		
		$order = new Commande($db);
		$order->fetch($idOrder);
		
		$nbShippable = 0;
		$nbProduct = 0;
		
		$TSomme = array();
		foreach($order->lines as $line){
			
			if($line->product_type==0 && $line->fk_product>0) {
				$nbProduct++;
				
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
		
		$TSomme[$line->fk_product] += $line->qty;
		
		if(!isset($line->stock)) {
			$produit = new Product($db);
			$produit->fetch($line->fk_product);
			
			$produit->load_stock();
			$line->stock = $produit->stock_reel;
		}
		
		if($line->stock <= 0) {
			$isShippable = 0;
		} else if ($TSomme[$line->fk_product] < $line->stock) {
			$isShippable = 1;
		} else {
			$isShippable = 2;
		}
		
		$this->TlinesShippable[$line->id] = array('stock'=>$line->stock,'shippable'=>$isShippable);
		
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
	
	function is_ok_for_shipping($idOrder){
		global $langs;
		
		$isShippable = $this->isOrderShippable($idOrder);
		
		if($isShippable['nbProduct'] == $isShippable['nbShippable'])
			return true;
		elseif($isShippable['nbShippable'] == 0)
			return false;
		else
			return false;

	}
	
	function orderLineStockStatus($line){
		global $langs;
		
		if(isset($this->TlinesShippable[$line->id])) {
			$isShippable = $this->TlinesShippable[$line->id];
		} else {
			return '';
		}
		
		$txt = '';
		if($isShippable['shippable'] == 1)
			$txt .= img_picto($langs->trans('EnStock', '('.$isShippable['stock'].')'), 'statut4.png');
		elseif($isShippable['shippable'] == 0)
			$txt .= img_picto($langs->trans('HorsStock', '('.$isShippable['stock'].')'), 'statut8.png');
		else
			$txt .= img_picto($langs->trans('StockPartiel', '('.$isShippable['stock'].')'), 'statut1.png');
		
		return $txt;
	}
}