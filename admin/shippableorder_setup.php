<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 */

/**
 * 	\file		admin/shippableorder.php
 * 	\ingroup	shippableorder
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT . "/core/class/extrafields.class.php";
require_once '../lib/shippableorder.lib.php';
dol_include_once('/abricot/includes/class/class.form.core.php');

// Translations
$langs->load("admin");
$langs->load("shippableorder@shippableorder");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');
	
/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{

	$code=$reg[1];
	
	$value = GETPOST($code);
	if(is_array($value))$value = implode(',',$value);
	
	if($code === 'SHIPPABLEORDER_ENTREPOT_BY_USER' && !empty($value)) create_extrafield('entrepot_preferentiel', 'Entrepôt préférentiel', 'sellist', 'user', array('options'=>array('entrepot:label:rowid'=>'')));
	
	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	
if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

$ent_by_user_activated = !empty($conf->global->SHIPPABLEORDER_ENTREPOT_BY_USER);

/*
 * View
 */
$page_name = "ShippableOrderSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = shippableorderAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104050Name"),
    0,
    "shippableorder@shippableorder"
);

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";


$form=new TFormCore();
$formdoli=new Form($db);
// Add shipment as titles in invoice
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.($ent_by_user_activated ? '<s>' : '').$langs->trans("StockEntrepot").($ent_by_user_activated ? '</s>' : '');
if($ent_by_user_activated) print ' <span style="color:red;">'.$langs->trans('EntrepotByUserActivated').'</span>';
print '</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_SPECIFIC_WAREHOUSE">';

dol_include_once('/product/class/html.formproduct.class.php');

$formDoli=new Form($db);
$formprod = new FormProduct($db);
$formprod->loadWarehouses();

$TWareHouse = array();
foreach($formprod->cache_warehouses as $id=>$ent) {
	$TWareHouse[$id]=$ent['label'];	
}


echo $formDoli->multiselectarray('SHIPPABLEORDER_SPECIFIC_WAREHOUSE',$TWareHouse,explode(',', $conf->global->SHIPPABLEORDER_SPECIFIC_WAREHOUSE));

print '<input type="submit" '.($ent_by_user_activated ? 'disabled="disabled"' : '').' class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Entrepot par utilisateur
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("EntrepotByUser").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_ENTREPOT_BY_USER">';
print $formdoli->selectyesno("SHIPPABLEORDER_ENTREPOT_BY_USER",$conf->global->SHIPPABLEORDER_ENTREPOT_BY_USER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Generate automatically shipment pdf
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("GenerateShipmentPDF").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_GENERATE_SHIPMENT_PDF">';
dol_include_once('/core/modules/expedition/modules_expedition.php');
$liste = ModelePdfExpedition::liste_modeles($db);
print $formdoli->selectarray('SHIPPABLEORDER_GENERATE_SHIPMENT_PDF', $liste, $conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF, 1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

if(!empty($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF) && $conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF != -1 && strpos($conf->global->SHIPPABLEORDER_GENERATE_SHIPMENT_PDF, 'generic_expedition_odt') === false) {
	// Generate global PDF containing all PDF
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("GenerateGlobalPDFForCreatedShipments").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_GENERATE_GLOBAL_PDF">';
	print $formdoli->selectyesno("SHIPPABLEORDER_GENERATE_GLOBAL_PDF",$conf->global->SHIPPABLEORDER_GENERATE_GLOBAL_PDF,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
}

// Automatically close order if all product has been sent
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CloseOrderWhenComplete").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_CLOSE_ORDER">';
print $formdoli->selectyesno("SHIPPABLEORDER_CLOSE_ORDER",$conf->global->SHIPPABLEORDER_CLOSE_ORDER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AllowAllLines").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLE_ORDER_ALLOW_ALL_LINE">';
print $formdoli->selectyesno("SHIPPABLE_ORDER_ALLOW_ALL_LINE",$conf->global->SHIPPABLE_ORDER_ALLOW_ALL_LINE,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AllowShippingIfNotEnoughStock").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLE_ORDER_ALLOW_SHIPPING_IF_NOT_ENOUGH_STOCK">';
print $formdoli->selectyesno("SHIPPABLE_ORDER_ALLOW_SHIPPING_IF_NOT_ENOUGH_STOCK",$conf->global->SHIPPABLE_ORDER_ALLOW_SHIPPING_IF_NOT_ENOUGH_STOCK,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AutoValidateShipping").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING">';
print $formdoli->selectyesno("SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING",$conf->global->SHIPPABLE_ORDER_AUTO_VALIDATE_SHIPPING,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Don't redirect after creating expeditions
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("DisableAutoRedirect").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT">';
print $formdoli->selectyesno("SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT",$conf->global->SHIPPABLE_ORDER_DISABLE_AUTO_REDIRECT,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Don't check shippable orders by default
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("DisableAutoCheckOrder").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_NO_DEFAULT_CHECK">';
print $formdoli->selectyesno("SHIPPABLEORDER_NO_DEFAULT_CHECK",$conf->global->SHIPPABLEORDER_NO_DEFAULT_CHECK,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Don't check draft shipping quantities
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("DontCheckDreftShippingQuantities").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SHIPPABLEORDER_DONT_CHECK_DRAFT_SHIPPING_QTY">';
print $formdoli->selectyesno("SHIPPABLEORDER_DONT_CHECK_DRAFT_SHIPPING_QTY",$conf->global->SHIPPABLEORDER_DONT_CHECK_DRAFT_SHIPPING_QTY,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '</table>';

llxFooter();

$db->close();

function create_extrafield($code, $label, $type, $elementtype, $options=array()) {
	
	global $db;
	
	$e = new ExtraFields($db);
	$e->addExtraField($code, $label, $type, '', '', $elementtype, 0, 0, '', $options);
	
}
