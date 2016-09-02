<?php
class ActionsShippableorder
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {  
      	global $db, $langs;
		
		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut < 3) 
        {
        	dol_include_once('/shippableorder/class/shippableorder.class.php');
			
			$shippableOrder = new ShippableOrder($db);
			$shippableOrder->isOrderShippable($object->id);
        	echo '<tr><td>'.$langs->trans('ShippableStatus').'</td>';
			echo '<td>'.$shippableOrder->orderStockStatus(false).'</td></tr>';
			$object->shippableorder = $shippableOrder;
        }

		return 0;
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) 
    {  
      	global $db, $user, $langs;
		dol_include_once('/hevea/class/hevea_tools.class.php');
		
		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut < 3) 
        {
     		
			dol_include_once('/shippableorder/class/shippableorder.class.php');
        	
			$shippableOrder =  &$object->shippableorder;
			
			?>
			<script type="text/javascript">
				$('table#tablelines tr.liste_titre td.linecoldescription').first().after('<td class="linecolstock" align="right" style="color:#fff;"><?php echo $langs->trans('Available') ?></td>');				
				<?php
				foreach($object->lines as &$line) {
					
					$stock = $shippableOrder->orderLineStockStatus($line,true);
					
					$hevea_tools = new HeveaTools($db);
					$TInfosStock = $hevea_tools->getInfosStockProduct($user, $line->fk_product);
					
					if(!empty($TInfosStock['dt_receive'])) $stock.= '<br/> Liv. : '.$TInfosStock['dt_receive'].'';
					
					?>
					$('table#tablelines tr[id=row-<?php echo $line->id; ?>] td.linecoldescription').after("<td class=\"linecolstock nowrap\" align=\"right\"><?php echo addslashes($stock) ?></td>");				
					<?php
				}
				
				?>
			</script>
			<?php
		}
		
	}

}