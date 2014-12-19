<?php
    
require_once 'midgard_setup_globals.php';
require_once 'midgard_setup_ui_cli.php';
require_once 'midgard_setup_log.php';
require_once 'midgard_setup_pear.php';

class midgard_setup_app 
{
    private $_apps = array();
    private $_setup_config = null;
    private $_app = null;
    private $_app_name;
    private $_sitegroup_name;

    public function __construct(midgard_setup_config $config)
    {
        $this->_apps = array (

            "blog" => array (
                
                "packages" => array ("net_nehmer_blog"),
                "templates" => array ("template_Simplegray"),
                "structure" => "structure_config.inc" 
            ),

            "openpsa" => array (
                
                "packages" => array ("org_openpsa_calendar", 
                                    "org_openpsa_documents",
                                    "org_openpsa_mypage",
                                    "org_openpsa_projects",
                                    "org_openpsa_contacts",
                                    "org_openpsa_sales",
                                    "org_openpsa_invoices",
                                    "org_openpsa_expenses",
                                    "org_openpsa_reports",
                                    "net_nemein_wiki"),
                "templates" => array ("template_OpenPsa2"),
                "structure" => "structure_openpsa.inc" 
            )
        );         

        $this->_setup_config = $config;
    }

    final public function get_app_names()
    {
        return array_keys($this->_apps);    
    }

    private function configure_sitegroup()
    {
        $this->_setup_config->ui->message(_("Configure target Midgard domain (sitegroup) for application"));

        $sg_names = '';
        $i = 0;

        $sg_array = midgard_sitegroup::list_names();

        if (!empty($sg_array))
        {
            foreach ($sg_array as $name)
            {
                if ($i >= 1)
                {
                    $sg_names .= ", ";
                }

                $sg_names .= $name;
                $i++;
            }
        }

        if ($i == 0)
        {
            $sg_names .= 'none';
        }

        $this->_sitegroup_name = $this->_setup_config->ui->get_text(_("Target Midgard domain (sitegroup) name ?"), "", $sg_names);

        if ($this->_sitegroup_name === "")
        {
            $this->_setup_config->ui->message(_("Target Midgard domain (sitegroup) not selected"));
            $this->configure_sitegroup();
        } 
    }

    final public function install($name = null)
    {
        if ($name == null
            || $name === "") 
        {
            $this->_setup_config->ui->warning(_("Expected Midgard application name to be installed."));
            return;
        }

        if (!array_key_exists($name, $this->_apps))
        {
            $this->_setup_config->ui->warning(_("Can not install {$name}. It is not registered as Midgard application."));
            return;          
        }

        $this->_app = $this->_apps[$name];
        $this->_app_name = $name;

        $this->install_pear_packages();
        $this->configure_sitegroup();
        $this->install_structure();
    }

    private function install_pear_packages()
    {
        $msp = new midgard_setup_pear();
        $msp->prepare_pear();
        $msp->set_channels();
  
        /* Install required packages from PEAR*/ 
        if (!empty($this->_app['packages']))
        {
            foreach ($this->_app['packages'] as $key => $name) 
            {
                if (!$msp->install_midcom_package($name))
                {
                    /* pear class throwed the error, let's return silently */
                    return;          
                }
            }
        }

        /* Install required templates from PEAR*/ 
        if (!empty($app['templates']))
        {
            foreach ($this->_app['templates'] as $key => $name) 
            {
                if (!$msp->install_midcom_package($name, true))
                {
                    /* pear class throwed the error, let's return silently */
                    return;          
                }
            }
        }
    }
    
    private function install_structure()
    {
        if ($this->_app == null)
        {
            $this->_setup_config->ui->error(_("Can not install application structure. No application selected."));
            return;          
        }

        $sc = new midgard_admin_sitewizard_creator_structure($this->_setup_config);
        $sc->set_sitegroup($this->_sitegroup_name);
        $sc->set_root_topic_name($this->_app_name . " root topic");
        $sc->read_config(MIDGARD_SETUP_ROOT_DIR . "/" . $this->_app['structure']);
        $sc->execute(); 

        $this->_setup_config->ui->message($this->_app_name . _(" successfully installed"));
    }
}

?>
