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
		global $db,$conf;
		
		$this->order = new Commande($db);
		$this->order->fetch($idOrder);
		$this->order->loadExpeditions();
		$this->order->fetchObjectLinked('','','','shipping');
		
		// Calcul du montant restant à expédier
		$this->order->total_ht_shipped = 0;
		if(!empty($this->order->linkedObjects['shipping'])) {
			foreach($this->order->linkedObjects['shipping'] as &$exp) {
				$this->order->total_ht_shipped += $exp->total_ht;
			}
		}
		$this->order->total_ht_to_ship = $this->order->total_ht - $this->order->total_ht_shipped;
		
		$this->nbShippable = 0;
		$this->nbPartiallyShippable = 0;
		$this->nbProduct = 0;
		
		$TSomme = array();
		foreach($this->order->lines as &$line){
			
			if (!empty($conf->global->SHIPPABLE_ORDER_ALLOW_ALL_LINE) || ($line->product_type==0 && $line->fk_product>0))
			{
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

			} elseif($line->product_type==1) { // On ne doit pas tenir compte du montant des services (et notament les frais de port) dans la colonne montant HT restant à expédier
				if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) $this->order->total_ht_to_ship -= $line->total_ht;
			}
		}
	}
	
	function isLineShippable(&$line, &$TSomme) {
		global $db,$conf;
		
		$TSomme[$line->fk_product] += $line->qty_toship;

		if(!isset($line->stock) && $line->fk_product > 0) {
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
				$line->stock = 0;
				//Récupération des entrepôts valide
				$TWarehouseName = explode(';', $conf->global->SHIPPABLEORDER_SPECIFIC_WAREHOUSE);
				$TIdWarehouse=array();
				$res_wh = $db->query( "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE label IN ('".implode("','", $TWarehouseName)."')");
				
				while($obj_wh = $db->fetch_object($res_wh)) {
					$TIdWarehouse[] = $obj_wh->rowid;
				}

				foreach($produit->stock_warehouse as $identrepot => $objecttemp ){
					if(in_array($identrepot, $TIdWarehouse)){
						$line->stock +=  $objecttemp->real;
					}
				}
			}
		}

		if ($conf->global->SHIPPABLE_ORDER_ALLOW_SHIPPING_IF_NOT_ENOUGH_STOCK )
		{
			$isShippable = 1;
			$qtyShippable = $line->qty;
			$line->stock = $line->qty;
		}
		else if($line->stock <= 0 || $line->qty_toship <= 0) {
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
		if($isShippable['to_ship'] > 0 && $isShippable['to_ship'] != $line->qty) {
			$picto.= ' ('.$isShippable['to_ship'].')';
		}
		
		return $picto;
	}
	
	function is_ok_for_shipping(){
		if($this->nbProduct == $this->nbShippable && $this->nbShippable != 0) return true;
		
		return false;
	}
	
	function orderCommandeByClient($TIDCommandes) {
		
		global $db;
		
		$TCommande = array();
		//var_dump($TIDCommandes);
		foreach($TIDCommandes as $id_commande) {
			$o=new Commande($db);
			$o->fetch($id_commande);
			
			$TCommande[] = $o;
				
		}
		
		usort($TCommande, array('ShippableOrder','_sort_by_client'));
		
		$TIDCommandes=array();
		foreach($TCommande as &$o ) {
			
			$TIDCommandes[] = $o->id;
		}
		
		//var_dump($TIDCommandes);
		return $TIDCommandes;
	}
	function _sort_by_client(&$a, &$b) {
			
		if($a->socid < $b->socid) return -1;
		else if($a->socid > $b->socid) return 1;
		else return 0; 
		
	}
	/**
	 * Création automatique des expéditions à partir de la liste des expédiables, uniquement avec les quantité expédiables
	 */
	function createShipping($db, $TIDCommandes, $TEnt_comm) {
		global $user, $langs, $conf;
		
		dol_include_once('/expedition/class/expedition.class.php');
		dol_include_once('/core/modules/expedition/modules_expedition.php');
		
		// Option pour la génération PDF
		$hidedetails = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
		$hidedesc = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
		$hideref = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
		
		$nbShippingCreated = 0;
		
		if(count($TIDCommandes) > 0) {
			
			$TIDCommandes = $this->orderCommandeByClient($TIDCommandes);
			
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
				
				// Valider l'expédition
				if (!empty($conf->global->SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING)) 
				{
					$shipping->statut = 0;
					$shipping->valid($user);
				} 
				
				// Génération du PDF
				if(!empty($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF)) $TFiles[] = $this->shipment_generate_pdf($shipping, $hidedetails, $hidedesc, $hideref);
			}

			if($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF) $this->generate_global_pdf($TFiles);
			
			if($nbShippingCreated > 0) {
				setEventMessage($langs->trans('NbShippingCreated', $nbShippingCreated));
				$dol_version = (float) DOL_VERSION;
				if ($dol_version <= 3.6) header("Location: ".dol_buildpath('/expedition/liste.php',2));
				else header("Location: ".dol_buildpath('/expedition/list.php',2));
				exit;
			}
		} else {
			setEventMessage($langs->trans('NoOrderSelected'), 'warnings');
		}
	}

	function shipment_generate_pdf(&$shipment, $hidedetails, $hidedesc, $hideref) {
		global $conf, $langs, $db;
		
		// Il faut recharger les lignes qui viennent juste d'être créées
		$shipment->fetch($shipment->id);
		/*echo '<pre>';
		print_r($shipment);
		exit;*/
		
		$outputlangs = $langs;
		if ($conf->global->MAIN_MULTILANGS) {$newlang=$shipment->client->default_lang;}
		if (! empty($newlang)) {
			$outputlangs = new Translate("",$conf);
			$outputlangs->setDefaultLang($newlang);
		}
		$result=expedition_pdf_create($db, $shipment, $shipment->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
		
		if($result > 0) {
			$objectref = dol_sanitizeFileName($shipment->ref);
			$dir = $conf->expedition->dir_output . "/sending/" . $objectref;
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
