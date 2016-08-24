<?php
/* Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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
require 'config.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
dol_include_once('/expedition/class/expedition.class.php');
dol_include_once('/shippableorder/class/shippableorder.class.php');
dol_include_once('/product/class/html.formproduct.class.php');

$langs->load('orders');
$langs->load('deliveries');
$langs->load('companies');
$langs->load('shippableorder@shippableorder');

$orderyear = GETPOST("orderyear", "int");
$ordermonth = GETPOST("ordermonth", "int");
$deliveryyear = GETPOST("deliveryyear", "int");
$deliverymonth = GETPOST("deliverymonth", "int");
$sref = GETPOST('sref', 'alpha');
$sref_client = GETPOST('sref_client', 'alpha');
$snom = GETPOST('snom', 'alpha');
$sall = GETPOST('sall');
$socid = GETPOST('socid', 'int');
$search_user = GETPOST('search_user', 'int');
$search_sale = GETPOST('search_sale', 'int');
$search_status = GETPOST('search_status', 'int');
if ($search_status == - 1) {
	$search_status = '';
}

// Security check
$id = (GETPOST('orderid') ? GETPOST('orderid') : GETPOST('id', 'int'));
if ($user->societe_id)
	$socid = $user->societe_id;
$result = restrictedArea($user, 'commande', $id, '');

$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');

$page = GETPOST("page", 'int');
if ($page == - 1) {
	$page = 0;
}
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield)
	$sortfield = 'c.date_livraison';
if (! $sortorder)
	$sortorder = 'ASC';

$limit = (GETPOST('show_all') == 1) ? false : $conf->liste_limit;

$diroutputpdf = $conf->shippableorder->multidir_output[$conf->entity];

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array(
		'orderlist' 
));

/**
 * Actions
 */

$action = $_REQUEST['action'];

switch ($action) {
	case 'createShipping' :
		if (! empty($_REQUEST['subCreateShip'])) {
			$TIDCommandes = $_REQUEST['TIDCommandes'];
			$TEnt_comm = $_REQUEST['TEnt_comm'];
			
			$order = new ShippableOrder($db);
			$order->createShipping($TIDCommandes, $TEnt_comm);
		}
		
		if (! empty($_REQUEST['subSetSent'])) {
			$TIDCommandes = $_REQUEST['TIDCommandes'];
			$order = new Commande($db);
			foreach ( $TIDCommandes as $idCommande ) {
				$order->setStatut(2, $idCommande, 'commande');
			}
		}
		
		break;
	
	case 'remove_file' :
		$file = GETPOST('file');
		if (! empty($file)) {
			$file = DOL_DATA_ROOT . '/shippableorder/' . $file;
			$ret = dol_delete_file($file, 0, 0, 0);
			if ($ret)
				setEventMessage($langs->trans("FileWasRemoved", GETPOST('file')));
			else
				setEventMessage($langs->trans("ErrorFailToDeleteFile", GETPOST('file')), 'errors');
		}
		
		break;
	
	default :
		
		break;
}

/**
 * View
 */

$parameters = array(
		'socid' => $socid 
);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hook
                                                                              
// Do we click on purge search criteria ?
if (GETPOST("button_removefilter_x")) {
	$search_user = '';
	$search_sale = '';
	$sref = '';
	$sref_client = '';
	$snom = '';
	$orderyear = '';
	$ordermonth = '';
	$deliverymonth = '';
	$deliveryyear = '';
}

/**
 * *********************************************************************************************************************
 * **************************************************View****************************************************************
 * ********************************************************************************************************************
 */

$now = dol_now();

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic = new Societe($db);

$help_url = "EN:Module_Customers_Orders|FR:Module_Commandes_Clients|ES:Módulo_Pedidos_de_clientes";
llxHeader('', $langs->trans("ShippableOrders"), $help_url);
?>
<script type="text/javascript">
$(document).ready(function() {
	$("#checkall").click(function() {
		$(".checkforgen").attr('checked', true);
	});
	$("#checknone").click(function() {
		$(".checkforgen").attr('checked', false);
	});
});
</script>
<?php

$sql = 'SELECT s.nom, s.rowid as socid, s.client, c.rowid, c.ref, c.total_ht, c.ref_client,';
$sql .= ' c.date_valid, c.date_commande, c.note_private, c.date_livraison, c.fk_statut, c.facture as facturee,';
if ($conf->clinomadic->enabled) {
	$sql .= " ce.reglement_recu,";
}

$sql .= ' (SELECT SUM(qty) FROM ' . MAIN_DB_PREFIX . 'commandedet WHERE fk_commande = c.rowid AND fk_product > 0 AND product_type = 0) as qty_prod';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
$sql .= ', ' . MAIN_DB_PREFIX . 'commande as c';
$sql .= ', ' . MAIN_DB_PREFIX . 'commandedet as cd';
if ($conf->clinomadic->enabled) {
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_extrafields as ce ON (ce.fk_object = cd.fk_commande)";
}
// We'll need this table joined to the select in order to filter by sale
if ($search_sale > 0 || (! $user->rights->societe->client->voir && ! $socid))
	$sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc";
if ($search_user > 0) {
	$sql .= ", " . MAIN_DB_PREFIX . "element_contact as ec";
	$sql .= ", " . MAIN_DB_PREFIX . "c_type_contact as tc";
}
$sql .= ' WHERE c.fk_soc = s.rowid';
$sql .= ' AND c.rowid = cd.fk_commande';
$sql .= ' AND c.entity = ' . $conf->entity;
if ($socid)
	$sql .= ' AND s.rowid = ' . $socid;
if (! $user->rights->societe->client->voir && ! $socid)
	$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
if ($sref) {
	$sql .= natural_search('c.ref', $sref);
}
if ($sall) {
	$sql .= natural_search(array(
			'c.ref',
			'c.note_private' 
	), $sall);
}
if ($ordermonth > 0) {
	if ($orderyear > 0 && empty($day))
		$sql .= " AND c.date_valid BETWEEN '" . $db->idate(dol_get_first_day($orderyear, $ordermonth, false)) . "' AND '" . $db->idate(dol_get_last_day($orderyear, $ordermonth, false)) . "'";
	else if ($orderyear > 0 && ! empty($day))
		$sql .= " AND c.date_valid BETWEEN '" . $db->idate(dol_mktime(0, 0, 0, $ordermonth, $day, $orderyear)) . "' AND '" . $db->idate(dol_mktime(23, 59, 59, $ordermonth, $day, $orderyear)) . "'";
	else
		$sql .= " AND date_format(c.date_valid, '%m') = '" . $ordermonth . "'";
} else if ($orderyear > 0) {
	$sql .= " AND c.date_valid BETWEEN '" . $db->idate(dol_get_first_day($orderyear, 1, false)) . "' AND '" . $db->idate(dol_get_last_day($orderyear, 12, false)) . "'";
}
if ($deliverymonth > 0) {
	if ($deliveryyear > 0 && empty($day))
		$sql .= " AND c.date_livraison BETWEEN '" . $db->idate(dol_get_first_day($deliveryyear, $deliverymonth, false)) . "' AND '" . $db->idate(dol_get_last_day($deliveryyear, $deliverymonth, false)) . "'";
	else if ($deliveryyear > 0 && ! empty($day))
		$sql .= " AND c.date_livraison BETWEEN '" . $db->idate(dol_mktime(0, 0, 0, $deliverymonth, $day, $deliveryyear)) . "' AND '" . $db->idate(dol_mktime(23, 59, 59, $deliverymonth, $day, $deliveryyear)) . "'";
	else
		$sql .= " AND date_format(c.date_livraison, '%m') = '" . $deliverymonth . "'";
} else if ($deliveryyear > 0) {
	$sql .= " AND c.date_livraison BETWEEN '" . $db->idate(dol_get_first_day($deliveryyear, 1, false)) . "' AND '" . $db->idate(dol_get_last_day($deliveryyear, 12, false)) . "'";
}
if (! empty($snom)) {
	$sql .= natural_search('s.nom', $snom);
}
if (! empty($sref_client)) {
	$sql .= ' AND c.ref_client LIKE \'%' . $db->escape($sref_client) . '%\'';
}
if ($search_sale > 0)
	$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " . $search_sale;
if ($search_user > 0) {
	$sql .= " AND ec.fk_c_type_contact = tc.rowid AND tc.element='commande' AND tc.source='internal' AND ec.element_id = c.rowid AND ec.fk_socpeople = " . $search_user;
}

$sql .= ' AND c.fk_statut IN (1,2)';
// var_dump($conf->global->STOCK_SUPPORTS_SERVICES);
if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) {
	$sql .= ' AND cd.product_type = 0';
}

$sql .= ' GROUP BY c.rowid ORDER BY ' . $sortfield . ' ' . $sortorder;
if ($limit > 0) {
	$sql2 = $sql;
	$sql .= $db->plimit($limit + 1, $offset);
}

// echo $sql; exit;

// print $sql;
$resql = $db->query($sql);
if ($resql) {
	if ($socid) {
		$soc = new Societe($db);
		$soc->fetch($socid);
		$title = $langs->trans('ShippableOrders') . ' - ' . $soc->nom;
	} else {
		$title = $langs->trans('ShippableOrders');
	}
	
	$param = '';
	if ($socid)
		$param .= '&socid=' . $socid;
	if ($ordermonth)
		$param .= '&ordermonth=' . $ordermonth;
	if ($orderyear)
		$param .= '&orderyear=' . $orderyear;
	if ($deliverymonth)
		$param .= '&deliverymonth=' . $deliverymonth;
	if ($deliveryyear)
		$param .= '&deliveryyear=' . $deliveryyear;
	if ($sref)
		$param .= '&sref=' . $sref;
	if ($snom)
		$param .= '&snom=' . $snom;
	if ($sref_client)
		$param .= '&sref_client=' . $sref_client;
	if ($search_user > 0)
		$param .= '&search_user=' . $search_user;
	if ($search_sale > 0)
		$param .= '&search_sale=' . $search_sale;
	if ($search_status > 0)
		$param .= '&$search_status=' . $search_status;
	if ($limit === false)
		$param .= '&show_all=1';
	
	$num = $db->num_rows($resql);
	$i = 0;
	
	if ($limit !== false) {
		$totalLine = null;
		if (isset($sql2)) {
			$resql2 = $db->query($sql2);
			if ($resql2)
				$totalLine = $db->num_rows($resql2);
		}
		print_barre_liste($title . '&nbsp;<a href="' . $_SERVER["PHP_SELF"] . '?show_all=1' . $param . '">' . $langs->trans('ShowAllLine') . '</a>', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $totalLine);
	} else {
		print_fiche_titre($title, '<a href="' . $_SERVER["PHP_SELF"] . '?show_all=0' . (str_replace('&show_all=1', '', $param)) . '">' . $langs->trans('NotShowAllLine') . '</a>', $picto = 'title_generic.png');
	}
	
	// Lignes des champs de filtre
	print '<form method="GET" action="' . $_SERVER["PHP_SELF"] . '">';
	
	print '<table class="noborder" width="100%">';
	
	$moreforfilter = '';
	
	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid) {
		$langs->load("commercial");
		$moreforfilter .= $langs->trans('ThirdPartiesOfSaleRepresentative') . ': ';
		$moreforfilter .= $formother->select_salesrepresentatives($search_sale, 'search_sale', $user);
		$moreforfilter .= ' &nbsp; &nbsp; &nbsp; ';
	}
	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid) {
		$moreforfilter .= $langs->trans('LinkedToSpecificUsers') . ': ';
		$moreforfilter .= $form->select_dolusers($search_user, 'search_user', 1);
	}
	if (! empty($moreforfilter)) {
		print '<tr class="liste_titre">';
		print '<td class="liste_titre" colspan="10">';
		print $moreforfilter;
		print '</td><td>';
		print '</td><td>';
		print '</td></tr>';
	}
	
	print '<tr class="liste_titre">';
	if ($limit === false)
		print '<input type="hidden" name="show_all" value="1" />';
	print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], 'c.ref', '', $param, '', $sortfield, $sortorder);
	if ($conf->clinomadic->enabled)
		print_liste_field_titre($langs->trans('Règlement'), $_SERVER["PHP_SELF"], 'c.ref_client', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('RefCustomerOrder'), $_SERVER["PHP_SELF"], 'c.ref_client', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Company'), $_SERVER["PHP_SELF"], 's.nom', '', $param, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('OrderDate'), $_SERVER["PHP_SELF"], 'c.date_commande', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('DeliveryDate'), $_SERVER["PHP_SELF"], 'c.date_livraison', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('AmountHT'), $_SERVER["PHP_SELF"], 'c.total_ht', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('AmountHTToShip'), $_SERVER["PHP_SELF"], '', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Status'), $_SERVER["PHP_SELF"], 'c.fk_statut', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('QtyProd'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('InStock'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('Warehouse'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans('CreateShipment'), $_SERVER["PHP_SELF"], 'qty_prod', '', $param, 'align="right"', $sortfield, $sortorder);
	
	$generic_commande = new Commande($db);
	
	$formproduct = new FormProduct($db);
	$shippableOrder = new ShippableOrder($db);
	
	print '</tr>';
	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" size="6" type="text" name="sref" value="' . $sref . '">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="6" name="sref_client" value="' . $sref_client . '">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" name="snom" value="' . $snom . '">';
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre">>&nbsp;</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">' . $shippableOrder->selectShippableOrderStatus('search_status', $search_status) . '</td>';
	// print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="' . img_picto($langs->trans("Search"), 'search.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("Search")) . '" title="' . dol_escape_htmltag($langs->trans("Search")) . '">';
	print '&nbsp; ';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="' . img_picto($langs->trans("Search"), 'searchclear.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '" title="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '">';
	print '</td>';
	print '<td class="liste_titre" align="center">';
	print '<a href="#" id="checkall">' . $langs->trans("All") . '</a> / <a href="#" id="checknone">' . $langs->trans("None") . '</a>';
	print '</td>';
	print '</tr>';
	print '</form>';
	print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
	
	$var = true;
	$total = 0;
	$totaltoship = 0;
	$subtotal = 0;
	
	while ( $objp = $db->fetch_object($resql) ) {
		
		$BdisplayLine = true;
		
		$generic_commande->id = $objp->rowid;
		$generic_commande->ref = $objp->ref;
		$shippableOrder->isOrderShippable($objp->rowid);
		
		if (! empty($search_status)) {
			$result = $shippableOrder->orderStockStatus(true, 'code');

			if ($result != $search_status) {
				$BdisplayLine = false;
			} 
		}
		if ($BdisplayLine == true) {
			
			$var = ! $var;
			print '<tr ' . $bc[$var] . '>';
			print '<td class="nowrap">';
			
			print '<table class="nobordernopadding"><tr class="nocellnopadd">';
			print '<td class="nobordernopadding nowrap">';
			print $generic_commande->getNomUrl(1);
			print '</td>';
			
			print '<td style="min-width: 20px" class="nobordernopadding nowrap">';
			if (($objp->fk_statut > 0) && ($objp->fk_statut < 3) && max($db->jdate($objp->date_commande), $db->jdate($objp->date_livraison)) < ($now - $conf->commande->client->warning_delay))
				print img_picto($langs->trans("Late"), "warning");
			if (! empty($objp->note_private)) {
				print ' <span class="note">';
				print '<a href="' . DOL_URL_ROOT . '/commande/note.php?id=' . $objp->rowid . '">' . img_picto($langs->trans("ViewPrivateNote"), 'object_generic') . '</a>';
				print '</span>';
			}
			print '</td>';
			
			print '<td width="16" align="right" class="nobordernopadding hideonsmartphone">';
			$filename = dol_sanitizeFileName($objp->ref);
			$filedir = $conf->commande->dir_output . '/' . dol_sanitizeFileName($objp->ref);
			$urlsource = $_SERVER['PHP_SELF'] . '?id=' . $objp->rowid;
			print $formfile->getDocumentsLink($generic_commande->element, $filename, $filedir);
			print '</td>';
			print '</tr></table>';
			
			print '</td>';
			
			// Payer : oui/non spécific Nomadic
			if ($conf->clinomadic->enabled) {
				print '<td align="center" class="nowrap" style="font-weight:bold;">' . ucfirst(($objp->reglement_recu != 'oui') ? "Non" : $objp->reglement_recu) . '</td>';
			}
			
			// Ref customer
			print '<td>' . $objp->ref_client . '</td>';
			
			// Company
			$companystatic->id = $objp->socid;
			$companystatic->nom = $objp->nom;
			$companystatic->client = $objp->client;
			print '<td>';
			print $companystatic->getNomUrl(1, 'customer');
			print '</td>';
			
			// Order date
			$y = dol_print_date($db->jdate($objp->date_commande), '%Y');
			$m = dol_print_date($db->jdate($objp->date_commande), '%m');
			$ml = dol_print_date($db->jdate($objp->date_commande), '%B');
			$d = dol_print_date($db->jdate($objp->date_commande), '%d');
			print '<td align="right">';
			print $d . '/';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?orderyear=' . $y . '&amp;ordermonth=' . $m . '">' . $m . '/</a>';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?orderyear=' . $y . '">' . $y . '</a>';
			print '</td>';
			
			// Delivery date
			$y = dol_print_date($db->jdate($objp->date_livraison), '%Y');
			$m = dol_print_date($db->jdate($objp->date_livraison), '%m');
			$ml = dol_print_date($db->jdate($objp->date_livraison), '%B');
			$d = dol_print_date($db->jdate($objp->date_livraison), '%d');
			print '<td align="right">';
			print $d . '/';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?deliveryyear=' . $y . '&amp;deliverymonth=' . $m . '">' . $m . '/</a>';
			print '<a href="' . $_SERVER['PHP_SELF'] . '?deliveryyear=' . $y . '">' . $y . '</a>';
			print '</td>';
			
			// Amount HT
			print '<td align="right" class="nowrap">' . price($objp->total_ht) . '</td>';
			
			// Amount HT remain to ship
			print '<td align="right" class="nowrap">' . price(round($shippableOrder->order->total_ht_to_ship, 2)) . '</td>';
			
			// Statut
			print '<td align="right" class="nowrap">' . $generic_commande->LibStatut($objp->fk_statut, $objp->facturee, 5) . '</td>';
			
			// Quantité de produit
			print '<td align="right" class="nowrap">' . $objp->qty_prod . '</td>';
			
			// Expédiable
			print '<td align="right" class="nowrap">' . $shippableOrder->orderStockStatus() . '</td>';
			
			if (! empty($conf->global->SHIPPABLEORDER_DEFAULT_WAREHOUSE)) {
				$default_wharehouse = $conf->global->SHIPPABLEORDER_DEFAULT_WAREHOUSE;
			} else {
				// Sélection de l'entrepot à déstocker pour l'expédition
				// On met par défaut le premier entrepot créé
				$sql2 = "SELECT rowid";
				$sql2 .= " FROM " . MAIN_DB_PREFIX . "entrepot";
				$sql2 .= " ORDER BY rowid ASC";
				$sql2 .= " LIMIT 1";
				$resql2 = $db->query($sql2);
				$res2 = $db->fetch_object($resql2);
				$default_wharehouse = $res2->rowid;
			}
			
			if ($shippableOrder->nbProduct > 0) {
				// TEnt_comm[] : clef = id_commande val = id_entrepot
				print '<td align="right" class="nowrap">' . $formproduct->selectWarehouses($default_wharehouse, 'TEnt_comm[' . $objp->rowid . ']', '', 1) . '</td>';
				/*echo strtotime($objp->date_livraison);exit;
				 echo dol_now();exit;*/
				
				// Checkbox pour créer expédition
				$checked = $shippableOrder->is_ok_for_shipping() && strtotime($objp->date_livraison) <= dol_now() ? 'checked="checked"' : '';
				if ($conf->global->SHIPPABLEORDER_NO_DEFAULT_CHECK) {
					$checked = false;
				}
				
				print '<td align="right" class="nowrap">' . '<input class="checkforgen" type="checkbox" ' . $checked . ' name="TIDCommandes[]" value="' . $objp->rowid . '" />' . '</td>';
			} else {
				print '<td colspan="2">&nbsp;</td>';
			}
			
			print '</tr>';
			
			$total += $objp->total_ht;
			$totaltoship += $shippableOrder->order->total_ht_to_ship;
			$subtotal += $objp->total_ht;
			$i ++;
		}
	}
	
	if ($total > 0) {
		print '<tr class="liste_total">';
		if ($limit === false) {
			print '<td align="left">' . $langs->trans("TotalHT") . '</td>';
		} else {
			print '<td align="left">' . $langs->trans("TotalHTforthispage") . '</td>';
		}
		
		print '<td colspan="5" align="right"">' . price($total);
		print '<td align="right"">' . price($totaltoship) . '<td colspan="5"></td>';
		print '</tr>';
	}
	
	print '</table>';
	
	if ($num > 0 && $user->rights->expedition->creer) {
		print '<input type="hidden" name="action" value="createShipping"/>';
		print '<br /><input style="float:right" class="butAction" type="submit" name="subCreateShip" value="' . $langs->trans('CreateShipmentButton') . '" />';
		if ($conf->global->SHIPPABLEORDER_ALLOW_CHANGE_STATUS_WITHOUT_SHIPMENT) {
			print '<input style="float:right" class="butAction" type="submit" name="subSetSent" value="' . $langs->trans('SetOrderSentButton') . '" />';
		}
	}
	print '</form>';
	
	?>
<br>
<table>
	<tr>
		<td colspan="2">Légende :</td>
	</tr>
		<?php foreach($shippableOrder->statusShippable as $statusdesckey=>$statusdescval) {?>
		<tr>
		<td><?php echo $statusdescval['picto'];?></td>
		<td><?php echo $statusdescval['trans']; ?></td>
	</tr>
		<?php }?>
	</table>

<?php
	
	if ($conf->global->SHIPPABLEORDER_GENERATE_GLOBAL_PDF) {
		print '<br><br>';
		// We disable multilang because we concat already existing pdf.
		$formfile = new FormFile($db);
		$formfile->show_documents('shippableorder', '', $diroutputpdf, $urlsource, false, true, '', 1, 1, 0, 48, 1, $param, $langs->trans("GlobalGeneratedFiles"));
	}
	
	$db->free($resql);
} else {
	print dol_print_error($db);
}

llxFooter();

$db->close();
?>
