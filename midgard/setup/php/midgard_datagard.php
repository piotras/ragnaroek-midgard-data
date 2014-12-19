<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/* Try to load extension if config class is not found */
if(!class_exists("midgard_config"))
    dl('midgard.so');

/* We need to update pear itself and install Console_Getargs */
require_once 'midgard_setup_pear.php';
$msp = new midgard_setup_pear();
$msp->prepare_pear();

require_once 'midgard_setup_getargs.php';
require_once 'midgard_quick_setup.php';
require_once 'midgard_setup_wizard.php';

$msga = new midgard_setup_getargs();

$arg_type = $msga->get_setup_type();
$arg_action = $msga->get_setup_action();
$arg_config = $msga->get_config_name();
$arg_verbose = $msga->get_debug_level();
$arg_package = $msga->get_package();
$argstr = $msga->get_argstr();

if (!extension_loaded('midgard'))
{
     midgard_setup_ui_cli::error(_("No Midgard PHP extension loaded. Ensure your PHP settings have extension=midgard.so"));
}

if($arg_verbose > 0)
    midgard_setup_ui_cli::message(_("Starting Midgard setup"));

if($arg_verbose > 0)
    midgard_setup_ui_cli::message("'{$arg_type}'" . _(" type selected"));

/* Initialize setup */
switch ($arg_type) {

    case 'wizard':
    case 'w':

        $setup = new midgard_setup_wizard($arg_config);
        break;

    default:

        try {
            $setup = new midgard_quick_setup($arg_config);
        } catch (Exception $e) {
            midgard_setup_ui_cli::error($e->getMessage());
        }
        break;
}

switch ($arg_action) {

    case 'dbinstall':
        
        $setup->install_database();
        break;

    case 'install':
       
		$setup->install_database();
		$setup->configure_pear();
		$setup->install_pear();

		if ($arg_package != "")
		{
            require_once 'midgard_setup_app.php';

            $setup->connect();
            $setup->configure_sitegroup(true);
            $setup->install_sitegroup(); 
            $setup->configure_application($arg_package);
            $setup->install_application();
        }
        
        break;

    case 'pear':
        
        $setup->connect();
        $setup->configure_user();
        $setup->auth_user();
        $setup->configure_pear($arg_package);
        $setup->install_pear();
        
        break;

    case 'dbupdate':
        
        $setup->connect();
        $setup->update_database();
        
        break;
   
    case 'sitegroup':
        
        $setup->connect();
        $setup->configure_sitegroup();
        $setup->install_sitegroup();

        break;

    case 'vhost':      
        
        $setup->connect();
        $setup->configure_sitegroup();
        $setup->install_sitegroup();
        $setup->configure_vhost();
        $setup->install_vhost();
        
        break;

    case 'midcom3vhost':      
        
        $setup->connect();
        $setup->configure_sitegroup();
        $setup->install_sitegroup();
        $setup->configure_midcom3vhost();
        $setup->install_vhost();
        
        break;

    case 'config-set':

        if($arg_config == "")
            midgard_setup_ui_cli::error(_("Action 'config-set' requires also argument '-c / --configuration' (default missing - report bug!)"));

        $mc = new midgard_config();
        
        /* We need to read file so that we don't loose other settings */ 
        $mc->read_file($arg_config);

        if (!$kv = $msga->get_config_set())
            midgard_setup_ui_cli::error(_("Action 'config-set' requires also argument '-s / --config-set'"));

        $mc->{$kv[0]} = $kv[1];
        if(!$mc->save_file($arg_config))
            midgard_setup_ui_cli::error(_("Failed to save configuration"));
        
        break;

    case 'upgrade':

        midgard_setup_ui_cli::error(_("Action 'upgrade' needs to be run using the datagard command, try: datagard" . $argstr));

        break;

    /* should it happen? probably there must be monty in actions array */
    default:
        
        midgard_setup_ui_cli::monty();
        
        break;
}

?>
