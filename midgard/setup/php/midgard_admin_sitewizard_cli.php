<?php
/*
 * Created on Aug 13, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
require_once('midgard_admin_sitewizard.php');
require_once('midgard_admin_sitewizard_exception.php'); 

$midgard = new midgard_connection();
$midgard->open("midgard");
//mgd_config_init("midgard");

try
{
    $sitewizard = new midgard_admin_sitewizard();
    $sitewizard->set_verbose(true);
    
    $sitegroup_creator = $sitewizard->initialize_sitegroup_creation();
    $sitegroup_creator->set_sitegroup("Koe52");
    $sitegroup_creator->set_sitegroup_admin_username("juuseri");
    $sitegroup_creator->set_sitegroup_admin_password("passu");
     
    $host_creator = $sitegroup_creator->next_link();
    $host_creator->set_verbose(true);
    
    // 5d6c1efa5aad11dca2b901052dafd248d248
    
    //$host_creator = $sitewizard->initialize_host_creation('5d6c1efa5aad11dca2b901052dafd248d248');
    $host_creator->set_host_url('http://www.jee.com');
    $host_creator->set_host_prefix('/jee');
    
    //print_r($host_creator);
    
    //$host_creator->execute();
    
   
    //print_r($host_creator);
    
    //$parent = $host_creator->previous_link();
    //print_r($parent);
    
    //$sitegroup_creator->execute();
    

    //$structure_creator = $sitewizard->initialize_structure_creation('983e66725acd11db845a197adaa843af43af');
    $structure_creator = $host_creator->next_link();
    $structure_creator->set_verbose(true);
    $structure_creator->read_config('structure_config.inc');
    $structure_creator->alter_config(array('blog', 'root', 'name'), 'jee');
    
    //$structure_creator->set_creation_root_topic('6d3af3384be911dcb7b0b3bf4b275d0d5d0d');
    $structure_creator->create_creation_root_topic('6d3af3384be911dcb7b0b3bf4b275d0d5d0d', "test15", "Test15", "net.nehmer.static", array("koe" => array("koe")));
    //$structure_creator->set_creation_root_group('94f058364f0f11dc93f803ebc4b67c0c7c0c'); 
    $structure_creator->create_creation_root_group('94f058364f0f11dc93f803ebc4b67c0c7c0c', "testgroup21");
    $structure_creator->execute(); 
    
}
catch (midgard_admin_sitewizard_exception $e)
{
    $e->error();
    echo "WE SHOULD HANDLE THIS \n";
}

?>
