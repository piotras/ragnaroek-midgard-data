<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'midgard_setup_ui_cli.php';
require_once 'midgard_setup_log.php';
require_once 'midgard_setup_ui_midcom.php';

class midgard_setup_config
{
    public $midgard_config;
    public $ui;

    function __construct($ui = 'cli', $config_name = NULL, midgard_config $config = NULL)
    {

        if(!defined('MIDGARD_SETUP_MIDGARD_PREFIX'))
            require_once 'midgard_setup_globals.php';

        switch ($ui) 
        {   
            case 'midcom':
                $this->ui =& new midgard_setup_ui_midcom();
                break;

            default:
                /* Print this when we are in debug mode, TODO */
                /* midgard_setup_log::write(_("Initialize new ").__CLASS__."(cli)"); */
                $this->ui =& new midgard_setup_ui_cli();
        }

        self::configure_check();

        if($config_name === null && $config === null)
            return;

        if($config_name === "")
        {
            throw $this->ui->exception(_("Can not create setup for unnamed configuration"));
        }

        if($config_name != NULL && $config != NULL)
            throw $this->ui->exception(_("Can not create setup for named configuration and midgard_config"));

        if($config_name != NULL)
        {
            $this->midgard_config = new midgard_config();
            if(!$this->midgard_config->read_file($config_name, false))
                throw new $this->ui->exception(
                    _("Can not read configuration ") . "'{$config_name}'");
        
        } else {

            $this->midgard_config = &$config;
        }
    }

    /* Validate whatever is possible to validate */
    private function configure_check()
    {
        /* ini settings */
        $memlimit = strtolower(ini_get("memory_limit"));
        $memlimit = str_replace("m", "", $memlimit);
        if((int)$memlimit < 100 ) 
        { 
            ini_set("memory_limit", "100M");
        }

        if(MIDGARD_SETUP_MIDGARD_PREFIX == "")
            throw new $this->ui->exception(_("failed to check correct setup prefix"));

        if(MIDGARD_SETUP_DIRECTORY_ETC == "")
            throw new $this->ui->exception(_("Failed to check correct /etc directory"));

        if(MIDGARD_SETUP_DIRECTORY_USR == "")
            throw new $this->ui->exception(_("Failed to check correct /usr directory"));

        if(MIDGARD_SETUP_DIRECTORY_VAR == "")
            throw new $this->ui->exception(_("Failed to check correct /var directory"));
        
        /* TODO , check if every is dir */

        /* MYSQL */
        /* Temporary, probably we must handle this other way in Midgard2 */
        if(!is_executable(MIDGARD_SETUP_MYSQL_CMD))
            throw new $this->ui->exception(_("'mysql' command is not executable"));

        if(!is_executable(MIDGARD_SETUP_MYSQLADMIN_CMD))
            throw new $this->ui->exception(_("'mysqladmin' command is not executable"));

        if(!is_executable(MIDGARD_SETUP_MYSQLDUMP_CMD))
            throw new $this->ui->exception(_("'mysqldump' command is not executable"));

        /* APACHE */
        if(!is_executable(MIDGARD_SETUP_APACHE_CMD))
            throw new $this->ui->exception(_("'apache' command is not executable"));
        
        if(!file_exists(MIDGARD_SETUP_APACHE_CONF))
            throw new $this->ui->exception(_("Apache's configuration file not found"));

        if(!is_dir(MIDGARD_SETUP_APACHE_CONF_PATH))
            throw new $this->ui->exception(_("Apache's configuration directory doesn't exists"));
        
        /* This can fail on Suse */
        if(!is_dir(MIDGARD_SETUP_APACHE_LIBEXEC_PATH))
            throw new $this->ui->exception(_("Apache's modules directory doesn't exists"));

        /* User andgroup need more checks */
        if(MIDGARD_SETUP_APACHE_USER == "")
            throw new $this->ui->exception(_("Apache user is empty"));

        if(MIDGARD_SETUP_APACHE_GROUP == "")
            throw new $this->ui->exception(_("Apache group is empty"));
        
        /* PHP */
        if(!is_executable(MIDGARD_SETUP_PHP_CMD))
            throw new $this->ui->exception(_("php command is not executable"));

        if(!is_dir(MIDGARD_SETUP_ROOT_DIR))
            throw new $this->ui->exception(_("Setup root directory doesn't exists"));

        /* PHP classes validation */
        if(!class_exists('PEAR'))
            require_once 'PEAR.php';

        if(!class_exists('PEAR_Frontend_CLI'))
            require_once 'PEAR/Frontend/CLI.php';

        if(!class_exists('PEAR_Command_Channels'))
            require_once 'PEAR/Command/Channels.php';

        if(!class_exists('PEAR_Installer'))
            require_once 'PEAR/Installer.php';
        
        if(!class_exists('PEAR_Downloader'))
            require_once 'PEAR/Registry.php';         
    }
}
?>
