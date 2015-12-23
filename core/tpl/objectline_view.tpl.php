<?php
/* Copyright (C) 2010-2013	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2010-2011	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2013	Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2013		Florian Henry		<florian.henry@open-concept.pro>
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
 *
 * To use this template, the following var must be defined
 * $type, $text, $description, $line
 */

$stock = '';
if(isset($this->shippableorder)) {
	if($line->fk_product_type == 0 && !empty($line->fk_product)) {
		$shippableOrder = $this->shippableorder;
		$stock = ' '.$shippableOrder->orderLineStockStatus($line);
	}
}

$dol_version = (float) DOL_VERSION;

if ($dol_version < 3.8)
{
	require dol_buildpath('/shippableorder/core/tpl/37objectline_view.tpl.php');
}
else
{
	require dol_buildpath('/shippableorder/core/tpl/38objectline_view.tpl.php');
}
