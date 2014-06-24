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
		
		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	dol_include_once('/shippableorder/class/shippableorder.class.php');
        	$shippableOrder = new ShippableOrder();
        	echo '<tr><td>'.$langs->trans('ShippableStatus').'</td>';
			echo '<td>'.$shippableOrder->orderStockStatus($object->id,false).'</td></tr>';
			$object->shippableorder = $shippableOrder;
        }

		return 0;

	}
     
    function formEditProductOptions($parameters, &$object, &$action, $hookmanager) 
    {
    	global $db;

    	if (in_array('ordercard',explode(':',$parameters['context'])))
        {
        	
        }
		
        return 0;
    }

	function formAddObjectLine ($parameters, &$object, &$action, $hookmanager) {
		
		global $db;

		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}

	function printObjectLine ($parameters, &$object, &$action, $hookmanager){
		
		global $db;
echo 'test';
		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	dol_include_once('/shippableorder/class/shippableorder.class.php');
        	$shippableOrder = new ShippableOrder();
        	echo '<tr><td>'.$langs->trans('ShippableStatus').'</td>';
			echo '<td>'.$shippableOrder->orderStockStatus($object).'</td></tr>';
        }

		return 0;
	}
}