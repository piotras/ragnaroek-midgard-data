<?php
/*
 * Created on Aug 6, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
require_once 'midgard_admin_sitewizard_creator_structure.php';
require_once 'midgard_admin_sitewizard_creator_sitegroup.php';
require_once 'midgard_admin_sitewizard_exception.php';
require_once 'midgard_setup_ui_midcom.php';
require_once 'midgard_setup_config.php';

class midgard_admin_sitewizard
{
    public static $current_state = null;
	
    private $verbose;
	
    public function __construct()
    {		
        $this->verbose = false;	
    }

    public function set_verbose($verbose)
    {
        $this->verbose = $verbose;     
    }

    public function request_chain_shutdown()
    {

    }
	
    public function initialize_sitegroup_creation()
    {
        try
	    {
            $setup_config = new midgard_setup_config("midcom", null, null); 
            $sitegroup_creator = new midgard_admin_sitewizard_creator_sitegroup(&$setup_config);
	        $sitegroup_creator->set_verbose($this->verbose);
	    }
	    catch (midgard_admin_sitewizard_exception $e)
	    {
            throw new midgard_admin_sitewizard_exception('Failed to initialize sitegroup creation');

	        return false;
	    }
	    
	    $this->current_state = $sitegroup_creator;

	    return $sitegroup_creator;		
    }
	
    public function initialize_host_creation($sitegroup_guid)
    {
        try
		{
			$setup_config = new midgard_setup_config("midcom", null, null); 
            $host_creator = new midgard_admin_sitewizard_creator_host(&$setup_config);
	        $host_creator->set_verbose($this->verbose);
	        $host_creator->initialize($sitegroup_guid);
	    }
	    catch (midgard_admin_sitewizard_exception $e)
	    {
            throw new midgard_admin_sitewizard_exception("Failed to initialize host creation 
                for sitegroup GUID: " . $sitegroup_guid);

	        return false;
	    }
	    
	    $this->current_state = $host_creator;

	    return $host_creator;				
    }
	
    public function initialize_structure_creation($host_guid)
    {	
        try
		{
			$setup_config = new midgard_setup_config("midcom", null, null); 
            $structure_creator = new midgard_admin_sitewizard_creator_structure(&$setup_config);
	        $structure_creator->set_verbose($this->verbose);
	        $structure_creator->initialize($host_guid);
	    }
	    catch (midgard_admin_sitewizard_exception $e)
	    {
            throw new midgard_admin_sitewizard_exception("Failed to initialize structure creation 
                for host GUID: " . $host_guid);

	        return false;
	    }
	    
	    $this->current_state = $structure_creator;

	    return $structure_creator;
    }
	
    public function get_current_state()
    {
        return self::current_state;
    }
    
    
	
}

?>
