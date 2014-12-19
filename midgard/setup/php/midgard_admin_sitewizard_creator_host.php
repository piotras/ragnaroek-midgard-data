<?php

/*
 * Created on Aug 6, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
require_once('midgard_admin_sitewizard_creator.php');
require_once('midgard_admin_sitewizard_creator_structure.php');
require_once('midgard_admin_sitewizard_creator_sitegroup.php');
require_once('midgard_admin_sitewizard_exception.php');

final class midgard_admin_sitewizard_creator_host extends midgard_admin_sitewizard_creator
{   
    private $host = null;
    
    private $root_page = null;
    
    private $host_url = '';
    
    private $host_prefix = '';
    
    private $host_port = 0;
    
    private $host_style_id = null;

    private $host_style_path = null;
    
    private $page_title = '';
    
    private $copy_host = null;
    
    private $make_host_copy = false;
    
    private $copy_host_url = '';
    
    private $copy_host_prefix = '';
    
    private $copy_host_port = '';
    
    private $create_child_style = true;

    private $trash_elements = array();

    private $root_topic = null;

    private $component = 'net.nehmer.static';

    protected $midcom3_host = false;

    protected $sitegroup_creator = null;

    public function __construct(midgard_setup_config &$config, $parent_link = null)
    {
        parent::__construct($config, $parent_link);
    }
    
    public function initialize($sitegroup_id)
    {
        $this->verbose("Initializing host creation");
    
        if (!is_object($this->sitegroup))
        {        
            if (!$this->sitegroup = mgd_get_sitegroup($sitegroup_id))
            {
                throw new midgard_admin_sitewizard_exception("Sitewizard couldn't initialize sitegroup object.
                    Reason: " . mgd_errstr());
            }
            else
            {
                $this->verbose("Getting sitegroup for host creation GUID: " . $this->sitegroup->guid);
                
                return true;
            }
        }
        
        return true;
    }
    
    public function set_midcom3_host($enable = false)
    {
        $this->midcom3_host = $enable;
    }

    public function set_create_child_style($create = true)
    {
        $this->create_child_style = $create;
    }
    
    private function create_host()
    {
        $this->verbose("Starting to create host for sitegroup GUID: " . $this->sitegroup->guid);
    
        $this->host = new midgard_host();
        $this->host->sitegroup = $this->sitegroup->id;
        $this->host->name = $this->host_url;
        $this->host->port = $this->host_port;
        $this->host->online = 1;
        $this->host->owner = $this->sitegroup->adminid;
        
        if (!is_null($this->host_style_id))
        {
            $this->host->style = $this->host_style_id;
            
            $qb = new midgard_query_builder('midgard_style');
            $qb->add_constraint('up', '=', 0);
            $qb->add_constraint('id', '=', $this->host_style_id);
            $styles = $qb->execute();
            
            if (count($styles) > 0 && $this->create_child_style)
            {
                $extend_style = new midgard_style();
                $extend_style->up = $this->host_style_id;
                $extend_style->sitegroup = $this->sitegroup->id;
                $extend_style->name = sprintf('%s_for_%s_%d', $styles[0]->name, 
                $this->host_url . str_replace('/', '_', $this->host_prefix),  $this->host_port);
                             
                if (!$extend_style->create())
                {
                    throw new midgard_admin_sitewizard_exception(_("Failed to create extend style for host GUID: ") 
                        . $this->host->guid . midgard_connection::get_error_string());                
                }
                else
                {
                    $this->host->style = $extend_style->id;

                    /* Create style's path */
                    try
                    {
                        $parent_style = new midgard_style($this->host_style_id);
                    }
                    catch(midgard_error_exception $e)
                    {
                        /* FIXME , report error */
                    }
                    
                    $this->host_style_path = "/" . $parent_style->name . "/" . $extend_style->name;
                }
            }
            elseif (!$this->create_child_style)
            {
                $this->host->style = $styles[0]->id;
            }
        }

        if (!$this->host->create())
        {
            throw new midgard_admin_sitewizard_exception("Failed to create host for sitegroup GUID: " 
                . $this->sitegroup->guid);
        }
        else
		{
			if ($this->host->sitegroup == 0) 
			{
				return;
			}

            $this->host->prefix = $this->host_prefix;
            if (!$this->host->update())
            {
                throw new midgard_admin_sitewizard_exception("Failed to set host prefix for host GUID: " 
                    . $this->host->guid);
            }
        
            $this->verbose("Created host PREFIX: " . $this->host->name . $this->host->prefix . " GUID: " 
				. $this->host->guid);

			$mconfig = $this->setup_config->midgard_config;
			/* Try to create asgard host. Fail silently. */
			$user = midgard_user::auth($mconfig->midgardusername, $mconfig->midgardpassword, null);
            if($user && $user->is_root())
            {
                $this->set_default_component('net.nemein.redirector');
                $this->create_asgard_host();
                $this->set_default_component();
                $this->sitegroup_creator->login_administrator();
            } 

            return true;
        }
    }
    
    private function copy_host()
    {
        $this->verbose("Starting to create copy host for sitegroup GUID: " . $this->sitegroup->guid);
    
        $this->copy_host = new midgard_host();
        $this->copy_host->sitegroup = $this->host->sitegroup;
        
        if (!empty($this->copy_host_url))
        {
            $this->copy_host->name = $this->copy_host_url;
        }
        else
        {
            $this->copy_host->name = $this->host->name;
        }
        
        if (!empty($this->copy_host_port))
        {
            $this->copy_host->port = $this->copy_host_port;
        }
        else
        {
            $this->copy_host->port = $this->host->port;
        }
        
        if (!empty($this->copy_host_prefix))
        {
            $this->copy_host->prefix = $this->copy_host_prefix;
        }
        else
        {
            $this->copy_host->prefix = $this->host->prefix;
        }
        $this->copy_host->online = 1;
        $this->copy_host->owner = $this->host->owner;
        $this->copy_host->style = $this->host->style;
        $this->copy_host->root = $this->host->root;
        
        if (!$this->copy_host->create())
        {
            throw new midgard_admin_sitewizard_exception("Failed to create host copy of host GUID " 
                . $this->shost->guid);
        }
    }
  
    /* Add object to trash elements. We purge them in cleanup step */
    private function add_to_trash($object)
    {
        $this->trash_elements[] = &$object;
    }

    /* FIXME, this function must invoke destructor before returning.
     * We can not leave created topic in database if parameter creation failed */
    private function create_root_topic($host, $page, $set_topic = false)
	{	
        if($host == null && $page == null)
        {
            return false;
        }

        if($this->midcom3_host)
        {
            return TRUE;
        }

		/* Check if there is already at least one root topic so we allow user to select it. */
		$ret = array();
		if ($set_topic) 
		{
        	$qb = new midgard_query_builder("midgard_topic");
        	//$qb->add_constraint("sitegroup", "=", $page->sitegroup);
			$qb->add_constraint("up", "=", 0);
			/* Do not advertise topics with redirector component */
        	$qb->add_constraint("component", "<>", "net.nemein.redirector");
        	$qb->add_constraint("component", "<>", "");
			$ret = $qb->execute();
		}

        if (!empty($ret))
        {
            $list = array();
            foreach ($ret as $topic)
			{
                $list[] = $topic->name . " ({$topic->component}) ";
            }

            $msg = _("Root topics were found. You can select one for newly created host or ignore to create a new one. (For new sites, press enter.)");
            $selected = $this->setup_config->ui->get_key_from_list($msg, $list, true);

            if (array_key_exists($selected, $list))
            {
				$this->root_topic = $ret[$selected];

				/* Force host to use the same style as topic does */
				if ($this->root_topic->style) 
				{
					$topic_style = new midgard_style();
					if ($topic_style->get_by_path ($this->root_topic->style))
					{
						$this->host->style = $topic_style->id;
					}
				}

                return TRUE;
            }
        }

        $_topic = new midgard_topic();
        $_topic->sitegroup = $page->sitegroup;
        $_topic->name = str_replace('.', '-', $host->name) . str_replace('/', '_', $host->prefix) . "_" . $host->port . "_root-topic"; 
        $_topic->extra = $host->name . $host->prefix;
        $_topic->style = $this->host_style_path;
        $_topic->styleInherit = true;
        $_topic->component = $this->component; 

        $created = $_topic->create(); 

        if(!$created)
        {
            $this->setup_config->ui->warning(_("Failed to create root topic") . " " . midgard_connection::get_error_string());
            return FALSE;
        }

        $param_created =
            $this->host->parameter("midgard", "midcom_root_topic_guid", $_topic->guid);

        if(!$param_created)
        {
            $this->setup_config->ui->warning(_("Failed to configure host topic"));
            return FALSE;
        }

        /* FIXME. Hack, we need to set url for redirector component */ 
        if($this->component == 'net.nemein.redirector')
        {
            /* We create /admin prefixed SG0 host */
            $_topic->parameter("net.nemein.redirector", "redirection_type", "url");
            $_topic->parameter("net.nemein.redirector", "redirection_url", "__PREFIX____mfa/asgard/");
        }

        $this->root_topic = &$_topic; 

        return TRUE;
    }

    private function create_code_init($page)
    {
        if($this->midcom3_host)
        {
            return TRUE;
        }

        $element = new midgard_pageelement(); 
        $element->sitegroup = $page->sitegroup; 
        $element->name = "code-init"; 
        $element->page = $page->id; 
        $guid = $this->root_topic->guid; 
        $cache_path =  MIDGARD_SETUP_DIRECTORY_VAR . '/cache/midgard/midcom/';
        $log_path =  MIDGARD_SETUP_DIRECTORY_VAR . '/log/midgard/midcom/' . str_replace(array('/', ' '), array('_', '_'), "{$this->host->name}{$this->host->prefix}_{$this->host->port}") . '.log';
        $element->value = "<(code-init-before-midcom)><?php 
\$GLOBALS['midcom_config_local']['midcom_root_topic_guid'] = \"$guid\"; 
\$GLOBALS['midcom_config_local']['log_level'] = 2; 
\$GLOBALS['midcom_config_local']['log_filename'] = '{$log_path}'; 
\$GLOBALS['midcom_config_local']['cache_base_directory'] = '{$cache_path}'; 
require 'midcom/lib/midcom.php'; 
?><(code-init-after-midcom)><?php 
\$_MIDCOM->codeinit(); 
?>";
        unset($cache_path, $log_path);
        $created = $element->create(); 
        
        if(!$created) { 
            $this->setup_config->ui->warning(_("Failed to create object") . " midgard_pageelement(code-init)"); 
            return FALSE; 
        }

        $this->add_to_trash($element);

        return TRUE; 
    }

    private function create_root_page($host = null, $set_topic = false)
	{
        if($host == null)
        {
            return false;
        }

        $this->verbose("Starting to create root page for sitegroup GUID: " . $this->sitegroup->guid);    
    
        $root_page = new midgard_page();
        $root_page->sitegroup = $host->sitegroup;
        $root_page->up = 0;
		$root_page->name = str_replace('.', '-', $host->name) . str_replace('/', '_', $host->prefix) . '_' . $host->port . '_root';
        $root_page->title = $host->name . $host->prefix . ' root page';
        $root_page->content = '<?php $_MIDCOM->content(); ?>'; 
        
        if($this->midcom3_host)
        {
            $root_page->content = "";
        }

        $root_page->author = 1;
        $root_page->info = 'active';
        
        if ($this->page_title == '')
        {
            $root_page->title = $this->sitegroup->name;
        }
        else
        {
            $root_page->title = $this->page_title;
		}

		if (!$root_page->create())
        {
            throw new midgard_admin_sitewizard_exception("Failed to create root page for sitegroup GUID " 
                . $this->sitegroup->guid . " (" . midgard_connection::get_error_string() . ")");
        }
        else
        {
            $this->verbose("Created root page for sitegroup GUID: " . $this->sitegroup->guid);
        
            $this->host->root = $root_page->id;
            $this->root_page = $root_page;
            $host->root = $root_page->id;
			
			if(!$this->create_root_topic($host, $root_page, $set_topic))
            {
				throw new midgard_admin_sitewizard_exception(_("Failed to create root topic") . " (" . $root_page->name . ") " ._("Reason").midgard_connection::get_error_string());
            }

            if(!$this->create_code_init($root_page))
            {
                throw new midgard_admin_sitewizard_exception(_("Failed to create code-init")._("Reason").midgard_connection::get_error_string());
            }

            if(!$this->midcom3_host)
            {
                $code_finish = new midgard_pageelement();
                $code_finish->name = "code-finish";
                $code_finish->page = $root_page->id;
                $code_finish->info = 'inherit';
                $code_finish->sitegroup = $root_page->sitegroup;
                $code_finish->value = '<?php $_MIDCOM->finish(); ?>';
            
                if (!$code_finish->create())
                {
                    throw new midgard_admin_sitewizard_exception("Failed to create code-finish. Reason: " 
                        . mgd_errstr());            
                }
            }

            if (!$host->update())
            {
                throw new midgard_admin_sitewizard_exception("Failed to update hosts root property. Reason: " 
                    . mgd_errstr());
            }
            else
            {
                $this->verbose("Updated new hosts root property.");
            
                return true;
            }
        }
    }
    
    public function set_host_style($style_id)
    {
        $this->verbose("Setting host style id: " . $style_id);
    
        $this->host_style_id = $style_id;
    }
    
    public function set_host_url($host_url)
    {
        $this->verbose("Setting host url \"" . $host_url . "\"");
    
        $this->host_url = $host_url;
    }
    
    public function set_host_port($host_port)
    {
        $this->verbose("Setting host port \"" . $host_port . "\"");
    
        $this->host_port = $host_port;
    }
    
    public function set_host_prefix($host_prefix)
    {
        if(($host_prefix === '')
            || ($host_prefix === null))
        {
            return;
        }

        $this->verbose("Setting host prefix \"" . $host_prefix . "\"");

        $this->host_prefix = $host_prefix;
    }
    
    public function set_page_title($page_title)
    {
        $this->page_title = $page_title;
    }
    
    public function set_make_host_copy($make_host_copy)
    {
        $this->make_host_copy = $make_host_copy;
    }
    
    public function set_copy_host_url($url)
    {
        $this->copy_host_url = $url;
    }
    
    public function set_copy_host_port($port)
    {
        $this->copy_host_port = $port;
    }
    
    public function set_copy_host_prefix($prefix)
    {
        $this->copy_host_prefix = $prefix;
    }
    
    public function cleanup()
    {
        $this->verbose("Host creator cleaning up!!");
    
        if (is_object($this->root_page))
            $this->root_page->purge();
        if (is_object($this->host))
            $this->host->purge();

        if(empty($this->elements))
            return;

        foreach($this->trash_elements as $object)
        {
            if(is_object($object))
            {
                $object->purge();
            }
        }
    }
    
    public function next_link()
    {
        $this->verbose('Initializing next link in the creation chain.');
        $this->next_link = new midgard_admin_sitewizard_creator_structure($this);
        midgard_admin_sitewizard::$current_state = $this->next_link;
        
        return $this->next_link;
    }
    
    public function previous_link()
    {
        $this->verbose('Returning previous link in the creation chain.');
    
        midgard_admin_sitewizard::$current_state = $this->parent_link;
        return $this->parent_link;
    }
    
    public function get_host()
    {
        return $this->host;
    }
     
    public function get_root_page()
    {
        return $this->root_page;
    }
    
    public function get_host_name()
    {
        return $this->host_url;
    }
    
    public function get_host_prefix()
    {
        return $this->host_prefix;
    }
    
    public function get_host_port()
    {
        return $this->host_port;
    }
    
    public function get_root_page_title()
    {
        return $this->page_title;
    }
   
    public function set_default_component($component = null)
    {
        if ($component === null
            || $component === '' )
        {
            $this->component = 'net.nehmer.static';
            return;
        }

        $this->component = $component;
    }

    public function get_default_component()
    {
        return $this->component;
    }
   
    private function create_asgard_host()
    {
        $user = midgard_connection::get_user();

        if(!$user)
        {
            $this->setup_config->ui->message(_("No user is authenticated. Can not create Asgard host."));
            return false;
        }

        if(!$user->is_root())
        {
            $this->setup_config->ui->message(_("Looged in user is not a root."));
            return false;
        } 

        /* check if there's asgard already */
        $qb = new midgard_query_builder("midgard_host");
        $qb->add_constraint("name", "=", $this->host_url);
        $qb->add_constraint("port", "=", $this->host_port); 
        $qb->add_constraint("prefix", "=", "/admin");

        $ret = $qb->execute();

        if(!empty($ret))
        {
            return true;
        }

        /* Create SG0 host */
        $host = new midgard_host();
        $host->sitegroup = 0; /* Set SG0 explicitly */
        $host->name = $this->host_url;
        $host->port = $this->host_port;
        $host->prefix = "/admin";
        $host->online = 1;
        $host->owner = 1; /* root user has always id 1 */
	
        if(!$host->create())
        {
            throw new midgard_admin_sitewizard_exception(_("Failed to create SG0 asgard host."));
        }

        if(!$this->create_root_page($host))
        {
            return false;
        }

        return true;
    }

    public function execute()
    {
        $this->verbose('Starting execution chain (midgard_admin_sitewizard_creator_host)');

        if ($this->parent_link != null)
        {
	        $this->verbose('Executing parent');

	        try
	        {
                $this->parent_link->execute();	
                
                if (is_object($this->parent_link))
                {
                    $this->sitegroup = $this->parent_link->get_sitegroup();
                }	
	        }
	        catch (midgard_admin_sitewizard_exception $e)
	        {
                $e->error();

                throw new midgard_admin_sitewizard_exception("Failed to execute parent creator");
	        }
        }

        try 
		{
			$this->create_host();
	        $this->create_root_page($this->host, true);
	        
	        if ($this->make_host_copy)
            {
                $this->copy_host();
            }

	        $this->verbose("Sitewizard created host successfully.");
	        
	        return $this->host->guid;
        }
	    catch(midgard_admin_sitewizard_exception $e)
	    {
	        $e->error();      
	        
            $this->cleanup();
  
	        throw new midgard_admin_sitewizard_exception("Failed to create host");
	
	        return false;
	    }    
    }

    public function set_sitegroup_creator( midgard_admin_sitewizard_creator_sitegroup $creator)
    {
        $this->sitegroup_creator = $creator;
    }
}

?>
