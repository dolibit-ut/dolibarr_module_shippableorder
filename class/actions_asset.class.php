<?php
class ActionsAsset
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {  
      	global $db;
		/*echo '<pre>';
		print_r($object);
		echo '</pre>';exit;*/
		
		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	
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

		if (in_array('ordercard',explode(':',$parameters['context']))) 
        {
        	
        }

		return 0;
	}
}