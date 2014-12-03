<?php
class ShippableOrder
{
	function __construct () {
		$this->TlinesShippable = array();
		$this->order = null;
		$this->nbProduct = 0;
		$this->nbShippable = 0;
		$this->nbPartiallyShippable = 0;
		
		$this->TProduct = array(); // Tableau des produits chargés pour éviter de recharger les même plusieurs fois
	}
	
	function isOrderShippable($idOrder){
		global $db;
		
		$this->order = new Commande($db);
		$this->order->fetch($idOrder);
		$this->order->loadExpeditions();
		
		$this->nbShippable = 0;
		$this->nbPartiallyShippable = 0;
		$this->nbProduct = 0;
		
		$TSomme = array();
		foreach($this->order->lines as $line){
			
			if($line->product_type==0 && $line->fk_product>0) {
				// Prise en compte des quantité déjà expédiées
				$qtyAlreadyShipped = $this->order->expeditions[$line->id];
				$line->qty_toship = $line->qty - $qtyAlreadyShipped;
				
				$isshippable = $this->isLineShippable($line, $TSomme);
				
				// Expédiable si toute la quantité est expédiable
				if($isshippable == 1) {
					$this->nbShippable++;
				}
				
				if($isshippable == 2) {
					$this->nbPartiallyShippable++;
				}
				
				if($this->TlinesShippable[$line->id]['to_ship'] > 0) {
					$this->nbProduct++;
				}

			}
		}
	}
	
	function isLineShippable(&$line, &$TSomme) {
		global $db,$conf;
		
		$TSomme[$line->fk_product] += $line->qty_toship;
		
		if(!isset($line->stock)) {
			if(empty($this->TProduct[$line->fk_product])) {
				$produit = new Product($db);
				$produit->fetch($line->fk_product);
				$produit->load_stock(false);
				$this->TProduct[$line->fk_product] = $produit;
			} else {
				$produit = &$this->TProduct[$line->fk_product];
			}
			$line->stock = $produit->stock_reel;
			
			//Filtrer stock uniquement des entrepôts en conf
			if($conf->global->SHIPPABLEORDER_SPECIFIC_WAREHOUSE){
				define('INC_FROM_DOLIBARR',true);
				dol_include_once('/shippableorder/config.php');
				$PDOdb = new TPDOdb;
				$line->stock = 0;
				//Récupération des entrepôts valide
				$TWarehouseName = explode(';', $conf->global->SHIPPABLEORDER_SPECIFIC_WAREHOUSE);
				$TIdWarehouse = TRequeteCore::_get_id_by_sql($PDOdb, "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE label IN ('".implode("','", $TWarehouseName)."')");

				foreach($produit->stock_warehouse as $identrepot => $objecttemp ){
					if(in_array($identrepot, $TIdWarehouse)){
						$line->stock +=  $objecttemp->real;
					}
				}
			}
		}
		
		if($line->stock <= 0 || $line->qty_toship <= 0) {
			$isShippable = 0;
			$qtyShippable = 0;
		} else if ($TSomme[$line->fk_product] <= $line->stock) {
			$isShippable = 1;
			$qtyShippable = $line->qty_toship;
		} else {
			$isShippable = 2;
			$qtyShippable = $line->qty_toship - $TSomme[$line->fk_product] + $line->stock;
		}
		
		$this->TlinesShippable[$line->id] = array('stock'=>$line->stock,'shippable'=>$isShippable,'to_ship'=>$line->qty_toship,'qty_shippable'=>$qtyShippable);
		
		return $isShippable;
	}
	
	function orderStockStatus($short=true){
		global $langs;
		
		$txt = '';
		
		if($this->nbProduct == 0)
			$txt .= img_picto($langs->trans('TotallyShipped'), 'statut5.png');
		else if($this->nbProduct == $this->nbShippable)
			$txt .= img_picto($langs->trans('EnStock'), 'statut4.png');
		else if($this->nbPartiallyShippable > 0)
			$txt .= img_picto($langs->trans('StockPartiel'), 'statut1.png');
		else if($this->nbShippable == 0)
			$txt .= img_picto($langs->trans('HorsStock'), 'statut8.png');
		else
			$txt .= img_picto($langs->trans('StockPartiel'), 'statut1.png');
		
		$label = 'NbProductShippable';
		if($short) $label = 'NbProductShippableShort';
		
		$txt .= ' '.$langs->trans($label, $this->nbShippable, $this->nbProduct);
		
		return $txt;
	}
	
	function orderLineStockStatus($line){
		global $langs;
		
		if(isset($this->TlinesShippable[$line->id])) {
			$isShippable = $this->TlinesShippable[$line->id];
		} else {
			return '';
		}
		
		$infos = '';
		
		// Produit déjà totalement expédié
		if($isShippable['to_ship'] <= 0) {
			$pictopath = img_picto('', 'statut5.png', '', false, 1);
		}
		
		// Produit avec un reste à expédier
		else if($isShippable['shippable'] == 1) {
			$pictopath = img_picto('', 'statut4.png', '', false, 1);
		} elseif($isShippable['shippable'] == 0) {
			$pictopath = img_picto('', 'statut8.png', '', false, 1);
		} else {
			$pictopath = img_picto('', 'statut1.png', '', false, 1);
		}
		
		$infos = $langs->trans('QtyInStock', $isShippable['stock']);
		$infos.= "\n".$langs->trans('RemainToShip', $isShippable['to_ship']);
		$infos.= "\n".$langs->trans('QtyShippable', $isShippable['qty_shippable']);
		
		$picto = '<img src="'.$pictopath.'" border="0" title="'.$infos.'">';
		
		return $picto;
	}
	
	function is_ok_for_shipping(){
		if($this->nbProduct == $this->nbShippable && $this->nbShippable != 0) return true;
		
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
				$shipping->date_delivery = $this->order->date_livraison;
				$shipping->note_public = $this->order->note_public;
				$shipping->note_private = $this->order->note_private;
				
				$shipping->weight_units = 0;
				$shipping->weight = "NULL";
				$shipping->sizeW = "NULL";
				$shipping->sizeH = "NULL";
				$shipping->sizeS = "NULL";
				$shipping->size_units = 0;
				$shipping->socid = $this->order->socid;
				$shipping->modelpdf = !empty($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF) ? $conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF : 'rouget';
				
				foreach($this->order->lines as $line) {
					
					if($this->TlinesShippable[$line->id]['stock'] > 0) {
						$shipping->addline($TEnt_comm[$this->order->id], $line->id, $this->TlinesShippable[$line->id]['qty_shippable']);
					}
				}
				
				$nbShippingCreated++;
				$shipping->create($user);
				
				// Génération du PDF
				if(!empty($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF)) $TFiles[] = $this->shipment_generate_pdf($shipping);
			}

			if($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF) $this->generate_global_pdf($TFiles);
			
			if($nbShippingCreated > 0) {
				setEventMessage($langs->trans('NbShippingCreated', $nbShippingCreated));
				header("Location: ".dol_buildpath('/expedition/liste.php',2));
				exit;
			}
		} else {
			setEventMessage($langs->trans('NoOrderSelected'), 'warnings');
		}
	}

	function shipment_generate_pdf(&$shipment) {
		global $conf, $langs, $db;
		
		// Il faut recharger les lignes qui viennent juste d'être créées
		$shipment->fetch($shipment->id);
		
		$outputlangs = $langs;
		if ($conf->global->MAIN_MULTILANGS) {$newlang=$shipment->client->default_lang;}
		if (! empty($newlang)) {
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result=expedtion_pdf_create($db, $shipment, $shipment->modelpdf, $outputlangs);
		
		if($result > 0) {
			$objectref = dol_sanitizeFileName($shipment->ref);
			$dir = $conf->expedition_bon->dir_output . "/" . $objectref;
			$file = $dir . "/" . $objectref . ".pdf";
			return $file;
		}
		
		return '';
	}

	function generate_global_pdf($TFiles) {
		global $langs, $conf;
		
        // Create empty PDF
        $pdf=pdf_getInstance();
        if (class_exists('TCPDF'))
        {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($langs));

        if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

		// Add all others
		foreach($TFiles as $file)
		{
			// Charge un document PDF depuis un fichier.
			$pagecount = $pdf->setSourceFile($file);
			for ($i = 1; $i <= $pagecount; $i++)
			{
				$tplidx = $pdf->importPage($i);
				$s = $pdf->getTemplatesize($tplidx);
				$pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
				$pdf->useTemplate($tplidx);
			}
		}

		// Create output dir if not exists
		$diroutputpdf = $conf->shippableorder->multidir_output[$conf->entity];
		dol_mkdir($diroutputpdf);

		// Save merged file
		$filename=strtolower(dol_sanitizeFileName($langs->transnoentities("OrderShipped")));
		if ($pagecount)
		{
			$now=dol_now();
			$file=$diroutputpdf.'/'.$filename.'_'.dol_print_date($now,'dayhourlog').'.pdf';
			$pdf->Output($file,'F');
			if (! empty($conf->global->MAIN_UMASK))
			@chmod($file, octdec($conf->global->MAIN_UMASK));
		}
		else
		{
			setEventMessage($langs->trans('NoPDFAvailableForChecked'),'errors');
		}
	}
}