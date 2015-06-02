<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 */

define('MIDCOM_STATIC_DIR_REL', 'midcom/static');

require_once 'PEAR.php';
require_once 'PEAR/Config.php';
require_once 'PEAR/Command/Channels.php';
require_once 'PEAR/Command/Config.php';
require_once 'PEAR/Frontend/CLI.php';
require_once 'PEAR/Installer.php';
require_once 'PEAR/Installer/Role.php';
require_once 'PEAR/Registry.php';
require_once 'PEAR/Downloader/Package.php';
require_once 'midgard_setup_globals.php';
require_once 'midgard_setup_log.php';

if (!class_exists('midgard_setup_ui_cli'))
{
    require_once 'midgard_setup_ui_cli.php';
}

define('PEAR_VERSION_REQUIRED', '1.7.1');

class midgard_setup_pear 
{

    private $midcom_channel;
    private $midcom_channel_alias;
    private $midcom_channel_url;
    private $midcom_static_dir = null;
    private $midcom_channel_base;
    private $htmlpurifier_channel;
    private $firephp_channel;
    private $initial_base_packages;
    private $initial_midgard_packages = array();
    private $initial_midgard_templates = array();
    private $initial_pear_dir;
    private $initial_www_dir;
    private $pear_dir;
    private $www_dir;
    private $www_dir_hack = null;
    private $initial_state;
    private $installed_old_packages = array();

    private $pear_ui;
    private $pear_config;
    private $pear_registery;
    private $pear_channel;
    private $pear_installer;
    private $pear_downloader;

    public function __construct()
    {
        midgard_setup_log::write(_("Initialize new ").__CLASS__);

        $this->midcom_channel = "ragnaroek.pear.midgard-project.org";
        $this->midcom_channel_alias = "ragnaroek"; 
        $this->midcom_channel_base = 'http://ragnaroek.pear.midgard-project.org/rest';

        $this->htmlpurifier_channel = "htmlpurifier.org";	
        $this->firephp_channel = "pear.firephp.org";

        $this->initial_base_packages = array
        (
            'HTML_QuickForm',
            'HTML_Common'
        );
        
        /* Read simple txt files and create arrays for initial packages and templates */
        self::get_packages_list($this->initial_midgard_packages, 'midgard_setup_pear_packages');
        self::get_packages_list($this->initial_midgard_templates, 'midgard_setup_pear_templates');

        $this->pear_config = PEAR_Config::singleton();
        if(PEAR::IsError($this->pear_config))
        {
            $msg = "Failed to initialize PEAR_Config::singleton";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);
        }
      
        /* Be silent */
        $set = $this->pear_config->set('verbose', 0, 'user', false);

        $this->initial_state = $this->pear_config->get('preferred_state', 'user', false);
        if (!$this->initial_state)
        {
            $this->initial_state = 'stable';
        }
        $this->set_state('beta');

        $this->initial_php_dir = $this->pear_config->get('php_dir');   
        
        $this->set_midcom_static();
        
        $this->pear_ui = new PEAR_Frontend_CLI(); 
        if (PEAR::IsError($this->pear_ui))
        {
            $msg = "Failed to initialize PEAR_Frontend_CLI";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);
        }

        $this->pear_channel = new PEAR_Command_Channels($this->pear_ui, $this->pear_config);
         
        if (PEAR::IsError($this->pear_channel))
        {
            $msg = "Failed to initialize PEAR_Command_Channels";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);          
        }

        $this->pear_installer = new PEAR_Installer($this->pear_ui);
        if (PEAR::IsError($this->pear_installer))
        {
            $msg = "Failed to initialize PEAR_Installer";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);                     
        }
                
        $this->pear_downloader = new PEAR_Downloader($this->pear_ui, array(), $this->pear_config);
            /* Set force with setOptions method */
            /* array('force' => 'yes'), &$this->pear_config); */
        if (PEAR::IsError($this->pear_downloader))
        {
            $msg = "Failed to initialize PEAR_Downloader";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);                     
        }

        $this->pear_registry = new PEAR_Registry();
        if (PEAR::IsError($this->pear_registry))
        {
            $msg = "Failed to initialize PEAR_registry";
            midgard_setup_log::write_exception($msg);
            throw new Exception($msg, 0);                     
        }
    }

    private function set_midcom_static()
    {
        if ($this->www_dir_hack === null)
        {
            /* we can not use isDefined method as it always returns true */
            /* let's get key's value and return if it's empty, false or null */
            if (($this->pear_config->get('www_dir') === '')
                 || ($this->pear_config->get('www_dir') === false)
                 || ($this->pear_config->get('www_dir') === null)) 
            { 
                return;
            }

            $this->www_dir_hack = $this->pear_config->get('www_dir');
        }

        /* Workaround for missed web_dir (which always used php_dir by default) */
        $this->www_dir_hack = $this->pear_config->get('php_dir');

        $this->initial_www_dir = $this->www_dir_hack;

        if (!strstr($this->initial_www_dir, 'midcom'))
        {
            $this->midcom_static_dir = $this->initial_www_dir."/".MIDCOM_STATIC_DIR_REL;    
        } 
        else 
        {
            $this->midcom_static_dir = $this->initial_www_dir;
        }

        midgard_setup_log::write("Setting midcom static dir {$this->midcom_static_dir}");
        
        $set = $this->pear_config->set('www_dir', $this->midcom_static_dir);

        if (!$set) 
        {
            midgard_setup_ui_cli::warning(_("Failed to set '{$this->midcom_static_dir}' www_dir"));
        }
    }

    private function get_packages_list(array &$packages_array, $filename)
    {
    	$filepath = MIDGARD_SETUP_DIRECTORY_USR."/share/midgard/setup/php/".$filename;

        if(!$content = file($filepath))
        {
            midgard_setup_ui_cli::error(_("Failed to get packages list from file") . "( {$filepath} )");
        }

        foreach($content as $line)
        {
            $line = trim($line);

            /* TODO, add more validation if needed */
            if(   strstr($line, '#') 
               || $line == '')
            {
                continue;
            }
            
            $packages_array[] = $line;
        }
    }

    final public function midcom_static_dir()
    {
        if ($this->midcom_static_dir === null)
        {
            return $this->pear_config->get('www_dir');
        }

        return $this->midcom_static_dir;
    }

    public function set_state($state = 'beta')
    {
        if (empty($state))
        {
            $state = 'stable';
        }

        /* $_channel = $this->midcom_channel; */
        $_channel = false;

        /* Setting 'system' doesn't seem to work so I set 'user' one */
        $set = $this->pear_config->set('preferred_state', $state, 'user', $_channel);

        if(!$set)
        {
            midgard_setup_ui_cli::warning(_("Failed to set '{$state}' preferred_state"));
        }
        else 
        {
            midgard_setup_log::write("Preferred pear package state changed ({$state})");
        }
    }

    /* This method actually reloads config object associated with our class
    instance. But the point is to get role's config keys available just after
    packages has been installed. Keep in mind that running pear from command 
    line you execute separate processes with completely new ( refreshed ) 
    instances. During any midgard_setup_pear execution we need to invoke this
    method to "workaround" not existing config keys issue */
    private static function reload_roles(&$config)
    {
        PEAR_Installer_Role::registerRoles();
        PEAR_Installer_Role::initializeConfig($config);
    }

    final public function install_midcom_roles()
    { 
        midgard_setup_log::write(_("Installing MidCOM roles"));
        
        /* Reload roles */ 
        midgard_setup_pear::reload_roles($this->pear_config);
      
        $this->www_dir_hack = $this->pear_config->configuration_info['www_dir']['default'];

        $this->set_midcom_static();	

        $this->install_midcom_package('Role_Mgdschema', true); 
        midgard_setup_pear::reload_roles($this->pear_config);

        $mgdschema_dir = MIDGARD_SETUP_MIDGARD_PREFIX . "/share/midgard/schema";
        $set = $this->pear_config->set('mgdschema_dir', $mgdschema_dir, 'system');
    
        if(!$set)
        {
            midgard_setup_ui_cli::warning(_("Failed to set '{$mgdschema_dir}' mgdschema_dir"));
        }

        /* Do not install sql role
        $this->install_midcom_package('Role_Midgardsql', true);
        midgard_setup_pear::reload_roles($this->pear_config);

        $midgardsql_dir = MIDGARD_SETUP_MIDGARD_PREFIX . "/share/midgard/sql/update";
        $set = $this->pear_config->set('midgardsql_dir', $midgardsql_dir, 'system');
    
        if (!$set)
        {
            midgard_setup_ui_cli::warning(_("Failed to set '{$midgardsql_dir}' midgardsql_dir"));
        }
         */

        $this->install_midcom_package('Role_Midgardelement', true);
        midgard_setup_pear::reload_roles($this->pear_config);

        /* Developer notice/warning: midgard_config_file is not set by us
         * We rely on Role_Midgardelement's default "midgard" value */

        /*
        $set = $this->pear_config->set('mgdschema_dir', $state, 'system', $mgdschema_dir);
    
        if(!$set)
        {
            midgard_setup_ui_cli::warning(_("Failed to set '{$mgdschema_dir}' mgdschema_dir")); 
        }
        */
    }

    final public function install_base_packages()
    {
        midgard_setup_ui_cli::message(_("Installing MidCOM base packages"));

        /* Uninstall old midcom */
        $this->clean_old_midcom();

        foreach($this->initial_midgard_packages as $name) 
        {
            if (!$this->install_midcom_package($name, false))
            {
                midgard_setup_ui_cli::error(_("Can not install midcom package ") . $name);
            }   
        }

        /* We force templates installation.
         * Package might be already installed but is not imported to database.
         * Package itself will take action itself if it's already installed or not */
       	midgard_setup_ui_cli::message(_("Installing MidCOM base templates"));

        foreach($this->initial_midgard_templates as $name) 
        {
            if (!$this->install_midcom_package($name, true))
            {
                midgard_setup_ui_cli::error(_("Can not install midcom package ") . $name);
            }
        }

        /* Try to upgrade exisiting not upgraded yet packages */
        if(!class_exists('PEAR_Command_Install'))
        {
            require_once 'PEAR/Command/Install.php';
        }

        $reg = &$this->pear_config->getRegistry();
        
        if (PEAR::isError($reg))
        {
            midgard_setup_ui_cli::error($reg->getMessage());
        }
            
        /* We get full channel name from alias. 
        * Despite the fact, docs says this method returns void type */
        $channel = $reg->channelName('ragnaroek');
        
        /* And we get arary of all ragnaroek packages installed. */
        $installed = $reg->packageInfo(null, null, $channel);

        midgard_setup_ui_cli::message(_("Trying to update all available and not yet updated ragnaroek packages.") . " " . _("Please wait..."));
        /* Do not force packages update. Update only those which can be updated */
        foreach ($installed as $package) 
        {
            $this->install_midcom_package($package['name'], false);
        }

        /* Remove pearified channel if already registered. */
        $this->clean_pearified();

        /* Try to install packages which we found from old channel.
         * Ignore silently if one of them can not be installed */
        if (empty($this->installed_old_packages))
        {
            return;
        }
        
        midgard_setup_ui_cli::message(_("Installing MidCOM packages found from previous installation.") . " " . _("Please wait..."));

        foreach ($this->installed_old_packages as $name)
        {
            /* Do not force installation. Package might be previously installed */
            $this->install_midcom_package($name, false);
        }
    }

    final public function install_packages($packages = array())
    {
        midgard_setup_ui_cli::message(_("Installing packages"));

        foreach($packages as $name)
	{
            /* Force installation because these packages were given explicitly */
            if (!$this->install_midcom_package($name, true))
            {
                midgard_setup_ui_cli::error(_("Can not install midcom package ") . $name);
            }
        }
    }

    private function install_package_from_pear($name, $options)
    {
        if (!class_exists('PEAR_Command_Install'))
        {
            require_once 'PEAR/Command/Install.php';
        }
        
        midgard_setup_ui_cli::message(_("Installing PEAR package ") . $name . ". " .  _("Please wait..."));

        $pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
        $installed = $pci->doInstall('install', $options, array($name));
        
        if(PEAR::isError($installed))
        {
            midgard_setup_ui_cli::error("PEAR Installer: package '".$name."' ".$installed->getMessage());
        }
    }

    final public function prepare_pear()
    {
        midgard_setup_log::write("Prepare PEAR"); 

        $options = array
        (
            'onlyreqdeps' => 'true', 
            'upgrade' => 'true', 
            'force' => 'true'
        );
        
        /* PEAR itself */
        $name = 'PEAR';
        $installed_version = $this->pear_registry->packageInfo($name, 'version');

        midgard_setup_log::write(_("Updating PEAR channel"));
        $ret = $this->pear_channel->doUpdate('channel-update', array(), array('pear.php.net'));
        
        if (PEAR::isError($ret))
        {
            midgard_setup_ui_cli::error($ret->getMessage());
        }

        if (version_compare($installed_version, PEAR_VERSION_REQUIRED, '>='))
        {
            midgard_setup_log::write("PEAR is up to date");            
        } 
        else
        { 
            $this->install_package_from_pear($name, $options);

            /* TODO, check if we really need to stop datagard and start it again */
            /* midgard_setup_ui_cli::error(_("Updated PEAR channel. Please start datagard again.")); */
        }

        if(!class_exists('PEAR_Command_Install'))
        {
            require_once 'PEAR/Command/Install.php';
        }

        /* Console_GetArgs */
        midgard_setup_log::write("Install Console_GetArgs");
	    $name = 'Console_GetArgs';
	    if ($this->pear_registry->packageExists($name))
        {
            midgard_setup_log::write("Console_GetArgs already installed");
            return;
        }
        
        $this->install_package_from_pear($name, $options);

        /* HTML_QuickForm */
        midgard_setup_log::write("Install HTML_QuickForm");
	    $name = 'HTML_QuickForm';
	    if ($this->pear_registry->packageExists($name))
        {
            midgard_setup_log::write("HTML_QuickForm already installed");
            return;
        }
        
        $this->install_package_from_pear($name, $options);
	    
        /* Text_CAPTCHA */
        $name = 'Text_CAPTCHA';
	    if ($this->pear_registry->packageExists($name))
        {
            midgard_setup_log::write("Text_CAPTCHA already installed");
            return;
        }
        
        $this->install_package_from_pear($name, $options);
    }

    final public function install_midcom_package($name, $force = false)
    {
        midgard_setup_ui_cli::message(_("Installing MidCOM package ") . $name . ". " .  _("Please wait... "), false, false);

        if (strpos($name, $this->midcom_channel_alias . '/') === 0)
        {
            $pkg_name = $name;
        }
        else
        {
            $pkg_name = "{$this->midcom_channel_alias}/{$name}";
        }

        $options = array
        (
            'onlyreqdeps' => 'true',
            'upgrade' => 'true'
        );

        if (!class_exists('PEAR_Command_Install'))
        {
            require_once 'PEAR/Command/Install.php';
        }

        if (!$force)
        {
            /* Packages which have files with the midgardelement role are always forced */
            $filelist = $this->pear_registry->packageInfo($name, 'filelist', $this->midcom_channel);
            if (!empty($filelist)) 
            {
                foreach ($filelist as $file)
                {
                    if ($file['role'] == "midgardelement")
                    {
                        $force = true;
                        break;
                    }
                }
            }
        }
           
        if($force) 
        {
            $options['force'] = 'true';
            
            $pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
            $installed = $pci->doInstall('install', $options, array($pkg_name));

            if (PEAR::isError($installed))
            {
                midgard_setup_ui_cli::error("PEAR Installer: package '".$name."' ".$installed->getMessage());
            }
            
            midgard_setup_ui_cli::message(_("successfully installed"), true);
            return true;
        }

        /* We should check if package exists in channel or newly updated 
        channel xml file. Registry check , checks only local data.
        if($this->pear_registry->packageExists($name, $this->midcom_channel))
        {
            midgard_setup_ui_cli::warning(_("Can not install $pkg_name package. It doesn't exists in $this->midcom_channel channel"));
        }
        */

        if ($this->package_is_newer($name, $this->midcom_channel, $this->midcom_channel_alias, $this->midcom_channel_base))
        {         
            $pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
            $installed = $pci->doInstall("install", $options, array($pkg_name));

            if (PEAR::isError($installed))
            {
                midgard_setup_ui_cli::error("PEAR Installer: newer package '{$name}' " . $installed->getMessage());
            }

            midgard_setup_ui_cli::message(_("successfully installed"), true);
            return true;
        } 
        else 
        {
            midgard_setup_ui_cli::message(_("already installed"), true);
            return true;
        }
        
        return false;
    }

    private function package_is_newer($name, $channel, $channel_alias, $channel_base)
    {
        $installed_version = $this->pear_registry->packageInfo($name, 'version', $channel);
        
        if (is_null($installed_version)) 
        {
            return true;
        }

        $rest = $this->pear_config->getREST('1.0');

        $params = array
        (
            'package' => $name,
            'channel' => $channel_alias
        );

        $state = $this->pear_config->get('preferred_state');

        $url = $rest->getDownloadURL("{$channel_base}/", $params, $state, $installed_version);

        if (PEAR::isError($url)) 
        {
            midgard_setup_ui_cli::error($url->getMessage());
        }

        /* 
        $txt = $name . " - installed: ". $installed_version ." available: ". $url['version'];
        midgard_setup_ui_cli::message($txt);
        */

        if (version_compare($installed_version, $url['version'], '>=')) 
        {
            return false;
        }

        return true;
    }

    final public function set_channels()
    {
        midgard_setup_log::write(_("Setting MidCOM channels"));

        /* update pear.php.net */
        $ret = $this->pear_channel->doUpdate('channel-update', array(), array('pear.php.net')); 

        if (PEAR::isError($ret)) 
        {        
            midgard_setup_ui_cli::error($ret->getMessage());
        }
        
        /* discover pear.midcom-project.org */
        if (!$this->pear_registry->channelExists($this->midcom_channel)) 
        {
            $ret = $this->pear_channel->doDiscover('channel-add', array(), array($this->midcom_channel));
            
            if(PEAR::isError($ret))
            {
                midgard_setup_ui_cli::error($ret->getMessage());
            }
        }

        /* discover htmlpurifier */
        if (!$this->pear_registry->channelExists($this->htmlpurifier_channel))
        {
            $ret = $this->pear_channel->doDiscover('channel-add', array(), array($this->htmlpurifier_channel));
        
            if(PEAR::isError($ret))
            {
                midgard_setup_ui_cli::error($ret->getMessage());
            }
        }

        /* discover firephp */
        /*
        if (!$this->pear_registry->channelExists($this->firephp_channel))
        {
            $ret = $this->pear_channel->doDiscover('channel-add', array(), array($this->firephp_channel));

            if(PEAR::isError($ret))
            {
                midgard_setup_ui_cli::error($ret->getMessage());
            }
        }
         */

        $this->update_channels();
    }

    final public function update_channels()
    {
        midgard_setup_log::write(_("Updating MidCOM channels"));

        /* update midcom channel */
        $ret = $this->pear_channel->doUpdate('channel-update', array(), array($this->midcom_channel));
        if (PEAR::isError($ret)) 
        {
            midgard_setup_ui_cli::error($ret->getMessage());
        }

        /* update htmlpurified */
        $ret = $this->pear_channel->doUpdate('channel-update', array(), array($this->htmlpurifier_channel));
        if (PEAR::isError($ret))
        {
            midgard_setup_ui_cli::error($ret->getMessage());
        }

        /* update firephp */
        /*
        $ret = $this->pear_channel->doUpdate('channel-update', array(), array($this->firephp_channel));
        if (PEAR::isError($ret))
        {
            midgard_setup_ui_cli::error($ret->getMessage());
        }
         */
    }

    private function clean_old_midcom()
    {
        /* Do not attempt to initialize PEAR_Command_Registry here.
         * This class ( and its methods ) behave differently with 
         * different uis. Just get registry and packages' names 
         * Do not use doList("list", array("channel" => "midcom"), array()); */

        if(!class_exists('PEAR_Command_Install'))
        {
            require_once 'PEAR/Command/Install.php';
        }

        $reg = &$this->pear_config->getRegistry();

        if (PEAR::isError($reg))
        {
            midgard_setup_ui_cli::error($reg->getMessage());
        }

		/* Remove deprecated midcom.admin.styleeditor (#1708) */
		$ragna_channel = $reg->channelName('ragnaroek');
		$mase = $reg->packageInfo("midcom_admin_styleeditor", null, $ragna_channel);
		if (!is_null ($mase)) 
		{
			$packages[] = "ragnaroek/midcom_admin_styleeditor";
			$options['force'] = 'true';
 			$pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
			$uninstalled = $pci->doUninstall ('uninstall', $options, $packages);

	        if (PEAR::isError ($uninstalled))
    	    {
        	    midgard_setup_ui_cli::warning("PEAR Installer: " . $uninstalled->getMessage());
        	}
		}

        /* There's no midcom channel so ignore it and return */
	    if (!$reg->channelExists('midcom'))
	    {
		    return;
        }

        /* We get full channel name from alias. 
         * Despite the fact, docs says this method returns void type */
        $channel = $reg->channelName('midcom');	

        /* And we get arary of packages installed from midcom channel */
        $installed = $reg->packageInfo(null, null, $channel);

        if(empty($installed))
        {
            return;
        }

        midgard_setup_ui_cli::message(_("Found old midcom packages. Uninstalling. Please wait..."));
 
        /* Create array with all packages, so we can uninstall them with one command */
        $packages = array();

        foreach ($installed as $package)
        {     
            $pkg_name = "midcom/{$package['name']}";
            $this->installed_old_packages[] = $package['name'];
            $packages[] = $pkg_name;
        } 

        /* unlimited is A MUST */
        ini_set('memory_limit', '-1');

        $options['force'] = 'true';
        
        $pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
        $uninstalled = $pci->doUninstall('uninstall', $options, $packages);
        if (PEAR::isError($uninstalled))
        {
            midgard_setup_ui_cli::error("PEAR Installer: " . $uninstalled->getMessage());
        }

        if (!$reg->deleteChannel('midcom'))
        {
            midgard_setup_ui_cli::message(_("Failed to delete old midcom channel"));
        }

        return;
    }

    private function clean_pearified()
    {
        /* Do not attempt to initialize PEAR_Command_Registry here.
         * This class ( and its methods ) behave differently with 
         * different uis. Just get registry and packages' names 
         * Do not use doList("list", array("channel" => "midcom"), array()); */

        if(!class_exists('PEAR_Command_Install'))
            require_once 'PEAR/Command/Install.php';

        $reg = &$this->pear_config->getRegistry();

        if(PEAR::isError($reg))
            midgard_setup_ui_cli::error($reg->getMessage());

        /* There's no midcom channel so ignore it and return */
	    if(!$reg->channelExists("pearified"))
		    return;

        /* We get full channel name from alias. 
         * Despite the fact, docs says this method returns void type */
        $channel = $reg->channelName("pearified");	
       
        /* And we get arary of packages installed from midcom channel */
        $installed = $reg->packageInfo(null, null, $channel);

        if(empty($installed))
            return;

        /* I bet the order of the pearified channel packages is wrong.
         * We would be trying to uninstall in wrong dependency order.
         * So just tell the user the channel and its packages aren't needed anymore and return. */
        midgard_setup_ui_cli::message(_("Found pearified channel packages. They are deprecated."));
        midgard_setup_ui_cli::message(_("You should normally uninstall the pearified packages and delete the pearified channel."));
        return;

        midgard_setup_ui_cli::message(_("Found pearified channel packages. Uninstalling. Please wait..."));
 
        /* Create array with all packages, so we can uninstall them with one command */
        $packages = array();

        foreach ($installed as $package) {
            
            $pkg_name = "pearified/".$package['name'];
            $this->installed_old_packages[] = $package['name'];
            $packages[] = $pkg_name;

        } 

        /* unlimited is A MUST */
        ini_set("memory_limit", "-1");

        $options['force'] = 'true';
        
        $pci = new PEAR_Command_Install($this->pear_ui, $this->pear_config);
        $uninstalled = $pci->doUninstall("uninstall", $options, $packages);
        if(PEAR::isError($uninstalled))
            midgard_setup_ui_cli::error("PEAR Installer: ".$uninstalled->getMessage());

        if(!$reg->deleteChannel("pearified"))
        {
            midgard_setup_ui_cli::message(_("Failed to delete old midcom channel"));
        }

        return;
    }

    /* TODO */
    /* Add destructor which reverts www_dir and php_dir back.
    We should define own dirs per instance life cycle */
}

?>
