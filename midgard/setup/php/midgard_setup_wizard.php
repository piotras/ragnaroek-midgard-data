<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'midgard_quick_setup.php';

class midgard_setup_wizard extends midgard_quick_setup
{
    public function __construct($name = NULL)
    {
        parent::__construct($name);
    }

    protected function create_config(&$config)
    {
        $setup_config = new midgard_setup_config('cli', NULL, $config);

        $config->host = $setup_config->ui->get_text(_("Database host?"), $config->host);
        $config->midgardusername = $setup_config->ui->get_text(_("Midgard root username?"), $config->midgardusername);
        $config->midgardpassword = $setup_config->ui->get_text(_("Midgard root password?"), $config->midgardpassword);
        
        parent::create_config($config);
    }

    public function configure_pear($packages = array())
    {
        $this->setup_config->ui->message(_("Configure all packages in wizard setup type"));
        parent::configure_pear($packages);
    }

    public function configure_user()
    {
        if (midgard_connection::get_user())
        {
            return;
        }

        self::configure_sitegroup(true);
    }

    public function configure_sitegroup($for_auth = false)
    {
        $for_auth_msg = "";
        if ($for_auth) $for_auth_msg = "for authentication (choose defaults here unless you know what you're doing)";
        $this->setup_config->ui->message(_("Configure Midgard domain (sitegroup) ")._($for_auth_msg));

        $sg_names = '';
        $i = 0;

        if ($for_auth) 
        {
            $default_sitegroup = MIDGARD_SITEGROUP_0;
        }
        else {
            $default_sitegroup = exec('hostname');
        }

        $this->sitegroup_creator = new midgard_admin_sitewizard_creator_sitegroup($this->setup_config);

        $sg_array = array();

        if (!$for_auth)
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

        $this->sitegroup_name = $this->setup_config->ui->get_text(_("Midgard domain (sitegroup) name ?"), $default_sitegroup, $sg_names);
		$this->sitegroup_create = true;
        if (   (in_array($this->sitegroup_name, $sg_array)) 
            || ($this->sitegroup_name == MIDGARD_SITEGROUP_0))
        {
            $this->sitegroup_create = false;
        } 

        if (   ($this->sitegroup_create == false) 
            && ($this->sitegroup_name != MIDGARD_SITEGROUP_0))
        { 

            $sg_admin_username = $this->setup_config->ui->get_text(_("Domain username?"), 'admin');
            $sg_admin_password = $this->setup_config->ui->get_text(_("Domain password?"), 'password');

            $this->midgard_username = $sg_admin_username;
            $this->midgard_password = $sg_admin_password;

            $this->sitegroup_creator->set_sitegroup($this->sitegroup_name);
            $this->sitegroup_creator->set_sitegroup_name($this->sitegroup_name);

            $this->sitegroup_creator->set_sitegroup_admin_username($sg_admin_username);
            $this->sitegroup_creator->set_sitegroup_admin_password($sg_admin_password);

            return;
        }

        /* Do not ask for Midgard root username, if there's no sitegroup (unless we're called for auth).
         * It means, we make initial setup. */
        if ($sg_names != 'none' || $for_auth)
        {
            $suggest_username = $this->setup_config->midgard_config->midgardusername;
            $suggest_password = $this->setup_config->midgard_config->midgardpassword;

            $this->setup_config->midgard_config->midgardusername = $this->setup_config->ui->get_text(_("Midgard root username?"), $suggest_username);
            $this->setup_config->midgard_config->midgardpassword = $this->setup_config->ui->get_text(_("Midgard root password?"), $suggest_password);

            $this->midgard_username = $this->setup_config->midgard_config->midgardusername;
            $this->midgard_password = $this->setup_config->midgard_config->midgardpassword;
            
            $this->sitegroup_creator->set_sitegroup(MIDGARD_SITEGROUP_0);
            $this->sitegroup_creator->set_sitegroup_admin_username($this->midgard_username);
            $this->sitegroup_creator->set_sitegroup_admin_password($this->midgard_password);
        }

        if ($this->sitegroup_create)
        {
            $sg_admin_username = $this->setup_config->ui->get_text(_("Domain username?")." "._("(Used for site administration.)"), 'admin');
            $sg_admin_password = $this->setup_config->ui->get_text(_("Domain password?")." "._("(Used for site administration.)"), $this->password(11));

            $this->sitegroup_creator->set_sitegroup($this->sitegroup_name);
            $this->sitegroup_creator->set_sitegroup_admin_username($sg_admin_username);
			$this->sitegroup_creator->set_sitegroup_admin_password($sg_admin_password);
			$this->sitegroup_creator->set_sitegroup_name($this->sitegroup_name);
        }
    }

    public function configure_vhost()
    {
        $this->setup_config->ui->message(_("Configure Apache virtual host"));   

        /* TODO, *any* error handling here is needed */
        if ($this->host_name == null)
        {
            $default_hostname = exec('hostname');
        }

        $hostname = $this->setup_config->ui->get_text(_("Hostname to use ?"), $default_hostname);
        $port = $this->setup_config->ui->get_text(_("Port ?"), '80');
        $prefix = $this->setup_config->ui->get_text(_("Prefix ?"), '');

        /* check if we have such host already */
        $ports_array = array(0, $port);
        $qb = new midgard_query_builder("midgard_host");
        $qb->add_constraint("name", "=", $hostname);
        $qb->add_constraint("port", "IN", $ports_array);
        $qb->add_constraint("prefix", "=", $prefix);

        $host_n = $qb->count();

        /* We found host. Trigger warning and loop. */
        if ($host_n > 0)
        {
            $this->setup_config->ui->warning(_("Host with this name already exists."));
            $this->configure_vhost();
        }

        $this->set_vhost($hostname, $port, $prefix);

        $this->host_creator = new midgard_admin_sitewizard_creator_host($this->setup_config); 
        $qb = new midgard_query_builder("midgard_style");
        $qb->add_constraint("name", "=", $this->default_style);
        $ret = $qb->execute();

        if(empty($ret))
        {
            $this->setup_config->ui->error(_("Can not set default style for a host.")); 
        }
            
        $this->host_creator->set_host_style($ret[0]->id);
    }

    public function configure_midcom3vhost()
    {
        $this->configure_vhost();
        $this->host_creator->set_midcom3_host(true);
    }

}
?>
