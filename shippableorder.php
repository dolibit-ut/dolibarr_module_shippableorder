<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville   <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur    <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo  <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin          <regis.houssin@capnetworks.com>
 * Copyright (C) 2012      Juanjo Menent          <jmenent@2byte.es>
 * Copyright (C) 2013      Christophe Battarel    <christophe.battarel@altairis.fr>
 * Copyright (C) 2013      Cédric Salvador        <csalvador@gpcsolutions.fr>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/commande/liste.php
 *	\ingroup    commande
 *	\brief      Page to list orders
 */


require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('/shippableorder/class/shippableorder.class.php');

$langs->load('orders');
$langs->load('deliveries');
$langs->load('companies');
$langs->load('shippableorder@shippableorder');

$orderyear=GETPOST("orderyear","int");
$ordermonth=GETPOST("ordermonth","int");
$deliveryyear=GETPOST("deliveryyear","int");
$deliverymonth=GETPOST("deliverymonth","int");
$sref=GETPOST('sref','alpha');
$sref_client=GETPOST('sref_client','alpha');
$snom=GETPOST('snom','alpha');
$sall=GETPOST('sall');
$socid=GETPOST('socid','int');
$search_user=GETPOST('search_user','int');
$search_sale=GETPOST('search_sale','int');

// Security check
$id = (GETPOST('orderid')?GETPOST('orderid'):GETPOST('id','int'));
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'commande', $id,'');

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');

$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='c.rowid';
if (! $sortorder) $sortorder='DESC';
$limit = $conf->liste_limit;

$viewstatut=GETPOST('viewstatut');


/*function getStockReel($idcommande){
	global $db;
	$commande = new Commande($db);
	$commande->fetch($idcommande);
	
	$nbExpediable = 0;
	$nbProduit = 0;
	
	$TSomme = array();
	
	foreach($commande->lines as $line){
		
		if($line->product_type==0 && $line->fk_product>0) {
			$nbProduit++;
			
			$TSomme[$line->fk_product] += $line->qty;
			
			$produit = new Product($db);
			$produit->fetch($line->fk_product);
			
			$produit->load_stock();
			
			if($TSomme[$line->fk_product] < $produit->stock_reel) {
				$nbExpediable++;
			}
		}
	}
	
	if($nbExpediable == 0) {
		return 0;
	} else if ($nbExpediable == $nbProduit) {
		return 1;
	} else {
		return 2;
	}
}

function getStockTheorique($idcommande,$socid){
	global $db,$conf;
	
	$commande = new Commande($db);
	$commande->fetch($idcommande);
	$commande->fetch_lines(true); //only product
	
	$stocktheorique = 0;
	
	foreach($commande->lines as $line){
		$produit = new Product($db);
		$produit->fetch($line->fk_product);
		$produit->load_stock();
		
		//echo $produit->stock_reel.' *** <br>';
		// Calculating a theorical value of stock if stock increment is done on real sending
		if (! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT))
		{
			$stock_commande_client=$stock_commande_fournisseur=0;

			if (! empty($conf->commande->enabled))
			{
				$result=$produit->load_stats_commande(0,'1,2');
				if ($result < 0) dol_print_error($db,$produit->error);
				$stock_commande_client=$produit->stats_commande['qty'];
			}
			if (! empty($conf->fournisseur->enabled))
			{
				$result=$produit->load_stats_commande_fournisseur(0,'3');
				if ($result < 0) dol_print_error($db,$produit->error);
				$stock_commande_fournisseur=$produit->stats_commande_fournisseur['qty'];
			}

			$stocktheorique = $product->stock_reel-($stock_commande_client+$stock_sending_client)+$stock_commande_fournisseur;
			
			if($line->qty > $stocktheorique)
				return false;
		}
	}
	
	return true;
}

function etatStock($idcommande,$socid){
	
	if(getStockReel($idcommande) && getStockTheorique($idcommande,$socid))
		return img_picto('En Stock', 'statut4.png');
	else 
		return img_picto('Hors Stock', 'statut8.png');
}

function etatStockReel($idcommande,$socid){
	
	if(getStockReel($idcommande) == 1)
		return img_picto('En Stock', 'statut4.png');
	elseif(getStockReel($idcommande) == 0) 
		return img_picto('Hors Stock', 'statut8.png');
	else
		return img_picto('Partiellement en Stock', 'statut1.png');
}

function etatStockTheorique($idcommande,$socid){
	
	if(getStockTheorique($idcommande,$socid))
		return img_picto('En Stock', 'statut4.png');
	else 
		return img_picto('Hors Stock', 'statut1.png');
}*/

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('orderlist'));

/*
 * Actions
 */

$parameters=array('socid'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hook

// Do we click on purge search criteria ?
if (GETPOST("button_removefilter_x"))
{
    $search_categ='';
    $search_user='';
    $search_sale='';
    $search_ref='';
    $search_refcustomer='';
    $search_societe='';
    $search_montant_ht='';
    $orderyear='';
    $ordermonth='';
    $deliverymonth='';
    $deliveryyear='';
}

/*
 * View
 */

$now=dol_now();

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic = new Societe($db);

$help_url="EN:Module_Customers_Orders|FR:Module_Commandes_Clients|ES:Módulo_Pedidos_de_clientes";
llxHeader('',$langs->trans("ShippableOrders"),$help_url);

$sql = 'SELECT s.nom, s.rowid as socid, s.client, c.rowid, c.ref, c.total_ht, c.ref_client,';
$sql.= ' c.date_valid, c.date_commande, c.note_private, c.date_livraison, c.fk_statut, c.facture as facturee, (SELECT SUM(qty) FROM '.MAIN_DB_PREFIX.'commandedet WHERE fk_commande = c.rowid) as qty_prod';
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe as s';
$sql.= ', '.MAIN_DB_PREFIX.'commande as c';
$sql.= ', '.MAIN_DB_PREFIX.'commandedet as cd';
// We'll need this table joined to the select in order to filter by sale
if ($search_sale > 0 || (! $user->rights->societe->client->voir && ! $socid)) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
if ($search_user > 0)
{
    $sql.=", ".MAIN_DB_PREFIX."element_contact as ec";
    $sql.=", ".MAIN_DB_PREFIX."c_type_contact as tc";
}
$sql.= ' WHERE c.fk_soc = s.rowid';
$sql.= ' AND c.rowid = cd.fk_commande';
$sql.= ' AND c.entity = '.$conf->entity;
if ($socid)	$sql.= ' AND s.rowid = '.$socid;
if (!$user->rights->societe->client->voir && !$socid) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
if ($sref) {
	$sql .= natural_search('c.ref', $sref);
}
if ($sall)
{
	$sql .= natural_search(array('c.ref', 'c.note_private'), $sall);
}
if ($viewstatut <> '')
{
	if ($viewstatut < 4 && $viewstatut > -3)
	{
		if ($viewstatut == 1 && empty($conf->expedition->enabled)) $sql.= ' AND c.fk_statut IN (1,2)';	// If module expedition disabled, we include order with status 'sending in process' into 'validated'
		else $sql.= ' AND c.fk_statut = '.$viewstatut; // brouillon, validee, en cours, annulee
		if ($viewstatut == 3)
		{
			$sql.= ' AND c.facture = 0'; // need to create invoice
		}
	}
	if ($viewstatut == 4)
	{
		$sql.= ' AND c.facture = 1'; // invoice created
	}
	if ($viewstatut == -2)	// To process
	{
		//$sql.= ' AND c.fk_statut IN (1,2,3) AND c.facture = 0';
		$sql.= " AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))";    // If status is 2 and facture=1, it must be selected
	}
	if ($viewstatut == -3)	// To bill
	{
		$sql.= ' AND c.fk_statut in (1,2,3)';
		$sql.= ' AND c.facture = 0'; // invoice not created
	}
}
if ($ordermonth > 0)
{
    if ($orderyear > 0 && empty($day))
    $sql.= " AND c.date_valid BETWEEN '".$db->idate(dol_get_first_day($orderyear,$ordermonth,false))."' AND '".$db->idate(dol_get_last_day($orderyear,$ordermonth,false))."'";
    else if ($orderyear > 0 && ! empty($day))
    $sql.= " AND c.date_valid BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $ordermonth, $day, $orderyear))."' AND '".$db->idate(dol_mktime(23, 59, 59, $ordermonth, $day, $orderyear))."'";
    else
    $sql.= " AND date_format(c.date_valid, '%m') = '".$ordermonth."'";
}
else if ($orderyear > 0)
{
    $sql.= " AND c.date_valid BETWEEN '".$db->idate(dol_get_first_day($orderyear,1,false))."' AND '".$db->idate(dol_get_last_day($orderyear,12,false))."'";
}
if ($deliverymonth > 0)
{
    if ($deliveryyear > 0 && empty($day))
    $sql.= " AND c.date_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear,$deliverymonth,false))."' AND '".$db->idate(dol_get_last_day($deliveryyear,$deliverymonth,false))."'";
    else if ($deliveryyear > 0 && ! empty($day))
    $sql.= " AND c.date_livraison BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $deliverymonth, $day, $deliveryyear))."' AND '".$db->idate(dol_mktime(23, 59, 59, $deliverymonth, $day, $deliveryyear))."'";
    else
    $sql.= " AND date_format(c.date_livraison, '%m') = '".$deliverymonth."'";
}
else if ($deliveryyear > 0)
{
    $sql.= " AND c.date_livraison BETWEEN '".$db->idate(dol_get_first_day($deliveryyear,1,false))."' AND '".$db->idate(dol_get_last_day($deliveryyear,12,false))."'";
}
if (!empty($snom))
{
	$sql .= natural_search('s.nom', $snom);
}
if (!empty($sref_client))
{
	$sql.= ' AND c.ref_client LIKE \'%'.$db->escape($sref_client).'%\'';
}
if ($search_sale > 0) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$search_sale;
if ($search_user > 0)
{
    $sql.= " AND ec.fk_c_type_contact = tc.rowid AND tc.element='commande' AND tc.source='internal' AND ec.element_id = c.rowid AND ec.fk_socpeople = ".$search_user;
}

$sql .= ' AND c.fk_statut = 1';

$sql.= ' GROUP BY c.rowid ORDER BY '.$sortfield.' '.$sortorder;
$sql.= $db->plimit($limit + 1,$offset);

//echo $sql; exit;

//print $sql;
$resql = $db->query($sql);
if ($resql)
{
	if ($socid)
	{
		$soc = new Societe($db);
		$soc->fetch($socid);
		$title = $langs->trans('ListOfOrders') . ' - '.$soc->nom;
	}
	else
	{
		$title = $langs->trans('ListOfOrders');
	}
	if (strval($viewstatut) == '0')
	$title.=' - '.$langs->trans('StatusOrderDraftShort');
	if ($viewstatut == 1)
	$title.=' - '.$langs->trans('StatusOrderValidatedShort');
	if ($viewstatut == 2)
	$title.=' - '.$langs->trans('StatusOrderOnProcessShort');
	if ($viewstatut == 3)
	$title.=' - '.$langs->trans('StatusOrderToBillShort');
	if ($viewstatut == 4)
	$title.=' - '.$langs->trans('StatusOrderProcessedShort');
	if ($viewstatut == -1)
	$title.=' - '.$langs->trans('StatusOrderCanceledShort');
	if ($viewstatut == -2)
	$title.=' - '.$langs->trans('StatusOrderToProcessShort');
	if ($viewstatut == -3)
	$title.=' - '.$langs->trans('StatusOrderValidated').', '.(empty($conf->expedition->enabled)?'':$langs->trans("StatusOrderSent").', ').$langs->trans('StatusOrderToBill');

	$param='&socid='.$socid.'&viewstatut='.$viewstatut;
	if ($ordermonth)      $param.='&ordermonth='.$ordermonth;
	if ($orderyear)       $param.='&orderyear='.$orderyear;
	if ($deliverymonth)   $param.='&deliverymonth='.$deliverymonth;
	if ($deliveryyear)    $param.='&deliveryyear='.$deliveryyear;
	if ($sref)            $param.='&sref='.$sref;
	if ($snom)            $param.='&snom='.$snom;
	if ($sref_client)     $param.='&sref_client='.$sref_client;
	if ($search_user > 0) $param.='&search_user='.$search_user;
	if ($search_sale > 0) $param.='&search_sale='.$search_sale;

	$num = $db->num_rows($resql);
	print_barre_liste($title, $page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num);
	$i = 0;

	// Lignes des champs de filtre
	print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="viewstatut" value="'.$viewstatut.'">';

	print '<table class="noborder" width="100%">';

	$moreforfilter='';

 	// If the user can view prospects other than his'
 	if ($user->rights->societe->client->voir || $socid)
 	{
 		$langs->load("commercial");
 		$moreforfilter.=$langs->trans('ThirdPartiesOfSaleRepresentative'). ': ';
		$moreforfilter.=$formother->select_salesrepresentatives($search_sale,'search_sale',$user);
	 	$moreforfilter.=' &nbsp; &nbsp; &nbsp; ';
 	}
	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid)
	{
	    $moreforfilter.=$langs->trans('LinkedToSpecificUsers'). ': ';
	    $moreforfilter.=$form->select_dolusers($search_user,'search_user',1);
	}
	if (! empty($moreforfilter))
	{
	    print '<tr class="liste_titre">';
	    print '<td class="liste_titre" colspan="9">';
	    print $moreforfilter;
	    print '</td></tr>';
	}

	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans('Ref'),$_SERVER["PHP_SELF"],'c.ref','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('RefCustomerOrder'),$_SERVER["PHP_SELF"],'c.ref_client','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Company'),$_SERVER["PHP_SELF"],'s.nom','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('OrderDate'),$_SERVER["PHP_SELF"],'c.date_commande','',$param, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('DeliveryDate'),$_SERVER["PHP_SELF"],'c.date_livraison','',$param, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('AmountHT'),$_SERVER["PHP_SELF"],'c.total_ht','',$param, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Status'),$_SERVER["PHP_SELF"],'c.fk_statut','',$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('QtyProd'),$_SERVER["PHP_SELF"],'qty_prod','',$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('InStock'),$_SERVER["PHP_SELF"],'qty_prod','',$param,'align="right"',$sortfield,$sortorder);
	//print_liste_field_titre($langs->trans('RealStock'),$_SERVER["PHP_SELF"],'qty_prod','',$param,'align="right"',$sortfield,$sortorder);
	//print_liste_field_titre($langs->trans('TheoricStock'),$_SERVER["PHP_SELF"],'qty_prod','',$param,'align="right"',$sortfield,$sortorder);
	print '</tr>';
	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" size="6" type="text" name="sref" value="'.$sref.'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="6" name="sref_client" value="'.$sref_client.'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" name="snom" value="'.$snom.'">';
	print '</td>';
	print '<td class="liste_titre">&nbsp;';
	print '</td><td class="liste_titre">&nbsp;';
	print '</td><td class="liste_titre">&nbsp;';
	print '</td><td class="liste_titre">&nbsp;';
	print '</td><td class="liste_titre">&nbsp;';
	//print '</td><td class="liste_titre">&nbsp;';
	//print '</td><td class="liste_titre">&nbsp;';
	print '</td><td align="right" class="liste_titre">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png"  value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td></tr>';

	$var=true;
	$total=0;
	$subtotal=0;

	$generic_commande = new Commande($db);
	$shippableOrder = new ShippableOrder();
	while ($i < min($num,$limit))
	{
		$objp = $db->fetch_object($resql);
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td class="nowrap">';

		$generic_commande->id=$objp->rowid;
		$generic_commande->ref=$objp->ref;

		print '<table class="nobordernopadding"><tr class="nocellnopadd">';
		print '<td class="nobordernopadding nowrap">';
		print $generic_commande->getNomUrl(1,($viewstatut != 2?0:$objp->fk_statut));
		print '</td>';

		print '<td style="min-width: 20px" class="nobordernopadding nowrap">';
		if (($objp->fk_statut > 0) && ($objp->fk_statut < 3) && $db->jdate($objp->date_valid) < ($now - $conf->commande->client->warning_delay)) print img_picto($langs->trans("Late"),"warning");
		if(!empty($objp->note_private))
		{
			print ' <span class="note">';
			print '<a href="'.DOL_URL_ROOT.'/commande/note.php?id='.$objp->rowid.'">'.img_picto($langs->trans("ViewPrivateNote"),'object_generic').'</a>';
			print '</span>';
		}
		print '</td>';

		print '<td width="16" align="right" class="nobordernopadding hideonsmartphone">';
		$filename=dol_sanitizeFileName($objp->ref);
		$filedir=$conf->commande->dir_output . '/' . dol_sanitizeFileName($objp->ref);
		$urlsource=$_SERVER['PHP_SELF'].'?id='.$objp->rowid;
		print $formfile->getDocumentsLink($generic_commande->element, $filename, $filedir);
		print '</td>';
		print '</tr></table>';

		print '</td>';

		// Ref customer
		print '<td>'.$objp->ref_client.'</td>';

		// Company
		$companystatic->id=$objp->socid;
		$companystatic->nom=$objp->nom;
		$companystatic->client=$objp->client;
		print '<td>';
		print $companystatic->getNomUrl(1,'customer');

		// If module invoices enabled and user with invoice creation permissions
		if (! empty($conf->facture->enabled) && ! empty($conf->global->ORDER_BILLING_ALL_CUSTOMER))
		{
			if ($user->rights->facture->creer)
			{
				if (($objp->fk_statut > 0 && $objp->fk_statut < 3) || ($objp->fk_statut == 3 && $objp->facturee == 0))
				{
					print '&nbsp;<a href="'.DOL_URL_ROOT.'/commande/orderstoinvoice.php?socid='.$companystatic->id.'">';
					print img_picto($langs->trans("CreateInvoiceForThisCustomer").' : '.$companystatic->nom, 'object_bill', 'hideonsmartphone').'</a>';
				}
			}
		}
		print '</td>';

		// Order date
		$y = dol_print_date($db->jdate($objp->date_commande),'%Y');
		$m = dol_print_date($db->jdate($objp->date_commande),'%m');
		$ml = dol_print_date($db->jdate($objp->date_commande),'%B');
		$d = dol_print_date($db->jdate($objp->date_commande),'%d');
		print '<td align="right">';
		print $d;
		print ' <a href="'.$_SERVER['PHP_SELF'].'?orderyear='.$y.'&amp;ordermonth='.$m.'">'.$ml.'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?orderyear='.$y.'">'.$y.'</a>';
		print '</td>';

		// Delivery date
		$y = dol_print_date($db->jdate($objp->date_livraison),'%Y');
		$m = dol_print_date($db->jdate($objp->date_livraison),'%m');
		$ml = dol_print_date($db->jdate($objp->date_livraison),'%B');
		$d = dol_print_date($db->jdate($objp->date_livraison),'%d');
		print '<td align="right">';
		print $d;
		print ' <a href="'.$_SERVER['PHP_SELF'].'?deliveryyear='.$y.'&amp;deliverymonth='.$m.'">'.$ml.'</a>';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?deliveryyear='.$y.'">'.$y.'</a>';
		print '</td>';

		// Amount HT
		print '<td align="right" class="nowrap">'.price($objp->total_ht).'</td>';

		// Statut
		print '<td align="right" class="nowrap">'.$generic_commande->LibStatut($objp->fk_statut,$objp->facturee,5).'</td>';
	
		//Quantité de produit
		print '<td align="right" class="nowrap">'.$objp->qty_prod.'</td>';
		//Etat du stock : en stock / hors stock
		print '<td align="right" class="nowrap">'.$shippableOrder->orderStockStatus($objp->rowid).'</td>';
		//Stock réel
		//print '<td align="right" class="nowrap">'.etatStockReel($objp->rowid,$objp->socid).'</td>';
		//Stock Théorique
		//print '<td align="right" class="nowrap">'.etatStockTheorique($objp->rowid,$objp->socid).'</td>';
		
		print '</tr>';

		$total+=$objp->total_ht;
		$subtotal+=$objp->total_ht;
		$i++;
	}

	if (! empty($conf->global->MAIN_SHOW_TOTAL_FOR_LIMITED_LIST))
	{
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td class="nowrap" colspan="5">'.$langs->trans('TotalHT').'</td>';
		// Total HT
		print '<td align="right" class="nowrap">'.price($total).'</td>';
		print '<td class="nowrap">&nbsp;</td>';
		print '</tr>';
	}

	print '</table>';

	print '</form>';
	
	?>
	<br>
	<table>
		<tr>
			<td colspan="2">Légende :</td>
		</tr>
		<tr>
			<td><?=  img_picto('', 'statut4.png');?></td>
			<td>En stock - commande totalement expédiable</td>
		</tr>
		<tr>
			<td><?=  img_picto('En Stock', 'statut1.png');?></td>
			<td>Stock partiellement expédiable</td>
		</tr>
		<tr>
			<td><?=  img_picto('En Stock', 'statut8.png');?></td>
			<td>Stock insufisant - commande non expédiable</td>
		</tr>
	</table>
	
	<?php

	$db->free($resql);
}
else
{
	print dol_print_error($db);
}

llxFooter();

$db->close();
?>
