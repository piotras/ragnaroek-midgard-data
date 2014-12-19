<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'midgard_setup_config.php';
require_once 'midgard_setup_database.php';
require_once 'midgard_setup_pear.php';
require_once 'midgard_setup_vhost.php';
require_once 'midgard_setup_log.php';
require_once 'midgard_admin_sitewizard_creator_sitegroup.php';
require_once 'midgard_setup_cron.php';
require_once 'midgard_setup_app.php';

/**
 * Base class for all midgard setup types.
 * Setup is divided into single steps and each of them performs
 * two tasks: configure and install.
 * 
 * Configure part is designed to ask user and get particular data, 
 * so midgard_quick_setup minimize this part and tries to collect 
 * sensible defaults. However any class which extends this one, may 
 * ask user its own way; through dialog windows, shell inputs, or 
 * html or gtk widgets.
 * 
 * Install part is designed to perform installation without asking 
 * the user. One may compare it to debian's 'preinst' and 'postinst'
 * scripts. In this part derived class should be aware what is able 
 * to do. For example www user interface can not correctly install
 * Virtual hosts, however may configure it.
 */

class midgard_quick_setup
{
    /**
     * midgard_setup_config object which is responsible to initialize 
     * correct midgard_setup_ui object and holds reference to named 
     * configuration through midgard_config object. The latter may 
     * be initialized without named configuration if needed.
     */
    protected $setup_config;

    /**
     * midgard_setup_database object
     */
    protected $midgard_setup_database;

    /**
     * midgard_setup_pear object
     */
    protected $midgard_setup_pear;

    /**
     * midgard_setup_vhost object
     */
    protected $midgard_setup_vhost;

    /**
     * Object which is responsible for host creation.
     * By default it's midgard_admin_sitewizard_creator_host.
     */
    protected $host_creator = null;

    /**
     * Object which is responsible for sitegroup creation.
     * By default it's midgard_admin_sitewizard_creator_sitegroup.
     */
    protected $sitegroup_creator = null;

    /**
     * An array which holds all sitegroup related data:
     * 'object' MidgardSitegroup object ( will be replaced with midgard_sitegroup )
     * 'admin' name of sitegroup administrator
     * 'password' password of sitegroup administrator
     */
    protected $sitegroup = array(); 

    /**
     * Named midgard configuration used by instance
     */
    protected $config_name;

    /**
     * Name of the host
     */
    protected $host_name = null;

    /**
     * Port of the host
     */
    protected $host_port = 80;
    
    /**
     * Prefix of the host
     */
    protected $host_prefix = "";

    /**
     * Virtual database ( sitegroup ) name.
     */
    protected $sitegroup_name = null;

    /**
     * Midgard user username. 
     * It might be user, admin or root.
     */
    protected $midgard_username = "root";

    /**
     * Midgard user password.
     */
    protected $midgard_password = "password";

    /**
     * Sitegroup creation toggle
     */
    protected $sitegroup_create = false;

    /**
     * midgard_config object, rarely initialized
     * The one associated with setup_config should be used instead.
     */
    public $midgard_config;

    /**
     * Name of the style which is used as default for every generated host.
     */
    protected $default_style = "Template_SimpleGray";

    /**
     * Name of application (Midgard package) to be installed.
     */
    protected $app_name = null;

    /**
     * Midgard pear packages to be installed/upgraded.
     */
    protected $pear_packages = array();

    /**
     * Initialize new midgard_setup_config and midgard_setup_ui_cli objects
     */
    public function __construct($name = NULL)
    {
        if (empty($name)) $name = 'midgard';
        $this->config_name = $name;
        $this->init_config();
        $this->setup_config = new midgard_setup_config('cli', $name);

        $this->sitegroup['object'] = null;
        $this->sitegroup['admin'] = 'admin';
        $this->sitegroup['password'] = 'password';
    }

    /**
     * Initialize configuration
     */
    protected function init_config()
    {
        $create_config = true;
        $config = new midgard_config();
        /* We need to check if file exists because we shouldn't overwrite
         * It's list workaround if core API is not going to support exists method */
        $files = $config->list_files();
        if(!empty($files)) {
            foreach($files as $_config_name) {
                if($_config_name == $this->config_name) {
                    $create_config = false;
                    break;
                }
            }
        }
        if ($create_config) {
            /* Set default values */
            $config->database = $this->config_name;
            $config->dbuser = $config->database;
            $dbpass = $this->password(); $config->dbpass = $dbpass;
            $config->tablecreate = true;
            $config->tableupdate = true;
            $config->blobdir = MIDGARD_SETUP_DIRECTORY_VAR."/lib/midgard/blobs/".$config->database;
            $config->midgardusername = "root";
            $midgardpassword = $this->password(11); $config->midgardpassword = $midgardpassword;
            $this->create_config($config);
        }
    }

    /**
     * Create configuration file
     * Other setup types probably override/extend this method
     */
    protected function create_config(&$config)
    {
        /* We just use the default values as we are non-interactive 
         * Other setup types can override settings
         * e.g. ask which password to use etc. */
        $config->save_file($this->config_name);
    }

    /**
     * Return randomized password
     */
    static protected function password($max=69, $min=7)
    {
        $length = mt_rand($min, $max); /* length of the password is something between $min and $max chars */
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"; /* allowed chars */

        $password = "";
        for ($i = 0; $i < $length; $i++)
        $password .= $chars[mt_rand(0, strlen($chars)-1)];

        return $password;
    }

    /**
     * Connect to Midgard database
     */
    function connect()
    {
        if (isset($_MIDGARD_CONNECTION))
        {
            return;
        }

        $midgard = new midgard_connection();
        if (!$midgard->open($this->config_name))
        {
            throw new $this->setup_config->ui->exception(_("Can not connect to database with given configuration ") . "'{$this->config_name}'");
        }
    }

    /**
     * Configure Midgard username and password.
     */
     public function configure_user()
     {
        /* We get username and password from midgard configuration. */
        $this->midgard_username = $this->setup_config->midgard_config->midgardusername;
        $this->midgard_password = $this->setup_config->midgard_config->midgardpassword;
     }

    /**
     * Authenticate Midgard user.
     */
     public function auth_user()
     {
        if (!isset($_MIDGARD_CONNECTION))
        {
            throw new $this->setup_config->ui->exception(_("Can not authenticate user. Not connected."));
        }

        /* Authentication has been made. Silently ignore and return. */
        if (midgard_connection::get_user())
        {
            return;
        }

        if ($this->sitegroup_name == MIDGARD_SITEGROUP_0)
        {
            $user = midgard_user::auth($this->midgard_username, $this->midgard_password, null);
        } 
        else 
        {    
            $user = midgard_user::auth($this->midgard_username, $this->midgard_password, $this->sitegroup_name);
        }

        if (!$user)
        {
            throw new $this->setup_config->ui->exception(_("Can not authenticate user. " . "'{$this->midgard_username}'"));
        }
     }

    /**
     * Configure pear packages.
     * Quick setup by default install all packages
     */
    public function configure_pear($packages = array())
    {
        $this->setup_config->ui->message(_("Configure all packages in quick setup type"));
        if (!is_array($packages))
        {
            if (empty($packages)) $packages = array();
            else $packages = array($packages);
        }
        $this->pear_packages = $packages;
    }

    /**
     * Install configured pear packages
     */
    final public function install_pear()
    {
        midgard_setup_log::write(_("Installing PEAR packages"));

        try 
        {
            $this->midgard_setup_pear = new midgard_setup_pear();

        } 
        catch (Exception $e)
        {
            $this->setup_config->ui->error($e->getMessage());
        }

        $this->midgard_setup_pear->prepare_pear();
        $this->midgard_setup_pear->set_channels();
        if (empty($this->pear_packages))
        {
            $this->midgard_setup_pear->install_midcom_roles();
            $this->midgard_setup_pear->install_base_packages();
        }
        else
        {
            $this->midgard_setup_pear->install_packages($this->pear_packages);
        }
    }

    /**
     * Install configured midgard database
     * This method invokes shell commands ( like mysql ), 
     * so it's not recommended to invoke it in web browser interface.
     */
    final public function install_database()
    {
        try
        {
            $this->midgard_setup_database = new midgard_setup_database($this->setup_config);
        } 
        catch (Exception $e)
        {    
            $this->setup_config->ui->error($e->getMessage());
        }
        
        $this->midgard_setup_database->set_admin_name('root');
        $this->midgard_setup_database->create_database_blobdir();
        $this->midgard_setup_database->create_database(); 
    }

    /**
     * Updates database
     * It's safe to call this method on any level as database update
     * is performed with Midgard API. There's no need to initialize
     * any connection, or invoke any commands ( like mysql ).
     */
    public function update_database()
    {
        if (isset($this->midgard_setup_database))
        {
            return $this->midgard_setup_database->update_database();
        }

        try 
        {    
            $this->midgard_setup_database = new midgard_setup_database($this->setup_config);
        } 
        catch (Exception $e) 
        {    
            $this->setup_config->ui->error($e->getMessage());
        }

        $this->midgard_setup_database->update_database();
    }

    public function set_vhost($name, $port = 80, $prefix = '')
    { 
        $this->host_name = $name;
        $this->host_port = $port;
        $this->host_prefix = $prefix;
    }

    public function configure_sitegroup($for_auth = false)
    {
        $this->setup_config->ui->message(_("Configure default sitegroup in quick setup type"));
        $this->sitegroup_create = true;
    }

    final public function install_sitegroup()
    {
        if ($this->sitegroup_create == false)
        {
            if ($this->sitegroup_creator == null)
            {
                $this->sitegroup_creator = new midgard_admin_sitewizard_creator_sitegroup($this->setup_config);
                $this->sitegroup_creator->set_sitegroup($this->sitegroup_name);
                $this->sitegroup_creator->set_sitegroup_name($this->sitegroup_name);
            }

            $this->sitegroup_creator->login_administrator();
            return;
        }

        /* Try to create sitegroup */
 
        /* Log in with configuration's credentials */
        $root_name = $this->setup_config->midgard_config->midgardusername;
        $root_pass = $this->setup_config->midgard_config->midgardpassword;
        $user = midgard_user::auth($root_name, $root_pass, null);
        if (!$user) 
        {
            $this->setup_config->ui->error(_("Failed to log in as root."));
        } 
        else
        {    
            $this->setup_config->ui->message(_("Logged in as root"));
        }

        /* FIXME, it's workaround, we need to get sitegroup name 
        * before we ask for hostname */
        if ($this->sitegroup_name == null)
        {
            $this->sitegroup_name = exec('hostname');
        }

        $sgname = $this->sitegroup_name;

        /* Check if sitegroup with given name already exists */

        if($this->sitegroup_creator == null)
        {
            $sg_creator = new midgard_admin_sitewizard_creator_sitegroup($this->setup_config);
            $this->sitegroup_creator = &$sg_creator;
        }

        $this->sitegroup_creator->set_sitegroup($this->sitegroup_name);
        $this->sitegroup_creator->set_sitegroup_admin_group_name($this->sitegroup_name." administrators");
                
        $created = $this->sitegroup_creator->execute();

        if ($created)
        {
            $this->setup_config->ui->message(_("Succesfully created sitegroup") . " ({$sgname})");
        } 
        else
        {
            
            $this->setup_config->ui->error(_("Couldn't create sitegroup") . "({$sgname})");
        }

        /* sitegroup is created, let's log in as sitegroup administrator 
         * to create initial host */
        $this->sitegroup_creator->login_administrator();   
    }

    public function configure_vhost()
    {
        $this->setup_config->ui->message(_("Configure Apache virtual host"));   

        /* TODO, *any* error handling here is needed */
        if ($this->host_name == null)
        {
            $default_hostname = exec('hostname');
        }

        if (!isset($this->host_name))
        {
            $value = $this->setup_config->ui->get_text(_("Hostname to use ?"), $default_hostname);
            
            if (   !$value 
                && $value == '') 
            {
                $value = $default_hostname;
            } 

            if (   $value 
                && $value == '')
            {      
                $this->setup_config->ui->warning(_("Can not create host with empty name"));
                $this->configure_vhost();
            }

            $this->set_vhost($value);
        }

        $this->host_creator = new midgard_admin_sitewizard_creator_host($this->setup_config);
        /* FIXME, change to Hemingway or Gray template */
        $qb = new midgard_query_builder("midgard_style");
        $qb->add_constraint('name', '=', $this->default_style);
        $ret = $qb->execute();

        if (empty($ret))
        {
            $this->setup_config->ui->error(_("Can not set default style for a host.")); 
        }
            
        $this->host_creator->set_host_style($ret[0]->id); 
    }

    public function install_vhost()
    {
        $this->setup_config->ui->message(_("Installing Apache virtual host"));

        if (is_null($this->host_creator))
        {
            $this->host_creator = new midgard_admin_sitewizard_creator_host($this->setup_config);
        }
        
        $this->host_creator->set_host_url($this->host_name);
        $this->host_creator->set_host_port($this->host_port);
        $this->host_creator->set_host_prefix($this->host_prefix);
        $this->host_creator->set_sitegroup($this->sitegroup_creator->get_sitegroup());
        $this->host_creator->set_sitegroup_creator($this->sitegroup_creator);
        $this->host_creator->execute();

        try 
        {
            $this->midgard_setup_vhost = new midgard_setup_vhost($this->setup_config, $this->host_name, $this->host_port, $this->host_prefix);
        } 
        catch (Exception $e)
        {
            $this->setup_config->ui->error($e->getMessage());
        }

        $this->midgard_setup_vhost->set_host_directories();
        $this->midgard_setup_vhost->enable_apache_module();
        $this->midgard_setup_vhost->create_configuration();

        /* Enable midcom cron if it's not enabled */
        midgard_setup_cron::midcom_cron_set();

        /* FIXME, derived class might want to ask before reloading */
        $this->midgard_setup_vhost->apache_reload();
    }

    public function configure_application($name = null)
    {
        if ($name == null 
            || $name ==="")
        {
            $this->setup_config->ui->error(_("Can not install application. Empty name given."));
        }
        if (!is_string($name))
        {
            $this->setup_config->ui->error(_("Can not install application. Only one application at a time."));
        }

        $this->app_name = $name;
    }

    final public function install_application()
    {
       $msa = new midgard_setup_app($this->setup_config);
       $msa->install($this->app_name);
    }
}
?>
