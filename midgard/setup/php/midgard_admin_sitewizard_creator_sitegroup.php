<?php

/*
 * Created on Aug 6, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
require_once('midgard_admin_sitewizard_creator.php');
require_once('midgard_admin_sitewizard_creator_host.php');
require_once('midgard_admin_sitewizard_exception.php');

class midgard_admin_sitewizard_creator_sitegroup extends midgard_admin_sitewizard_creator
{   
    private $sitegroup_admin_username = 'admin';
    
    private $sitegroup_admin_password = 'password';
    
    private $sitegroup_admin_group = null;
    
    private $sitegroup_admin_user = null;
    
    private $sitegroup_admin_group_name = 'midgard-project.org administrators';
    
    private $sitegroup_admin_group_member = null;

    public function __construct(midgard_setup_config &$config)
    {
        parent::__construct($config, null);
    }
    
    public function initialize($guid)
    {
        // Do nothing
    }
        
    public function set_sitegroup_admin_username($admin_name)
    {
        $this->verbose("Setting new sitegroup admin username \"" . $admin_name . "\"");
        $this->sitegroup_admin_username = $admin_name;
    }
    
    public function set_sitegroup_admin_password($admin_password)
    {
        $this->verbose("Setting new sitegroup admin password \"" . $admin_password . "\"");
        $this->sitegroup_admin_password = $admin_password;    
    }
    
    private function create_sitegroup()
    {
        /* midgard_setup's configure routines check if sitegroup already exists.
           I do not add it here, as it must be done during configure phase */

        $this->verbose("Starting to create new sitegroup \"" . $this->sitegroup_name . "\""); 
       
        $this->sitegroup = new midgard_sitegroup();
        $this->sitegroup->name = $this->sitegroup_name;
        
        if ($this->sitegroup->create()) 
        { 
            $this->verbose("Sitegroup created GUID: " . $this->sitegroup->guid);
            return true;
        }
        else
        {
            $this->sitegroup = null;
            throw new midgard_admin_sitewizard_exception("Failed to create new sitegroup! Reason: " 
                . mgd_errstr());
        }        
    }
    
    private function create_sitegroup_admin_group()
    {
        $this->sitegroup_admin_group = new midgard_group();
        $this->sitegroup_admin_group->sitegroup = $this->sitegroup->id;
        
        if ($this->sitegroup_admin_group_name != '')
        {
            $this->sitegroup_admin_group->name = $this->sitegroup_admin_group_name;
        }
        else
        {
            $this->sitegroup_admin_group->name = str_replace('.', '-', $this->sitegroup->name) . "_administrators";
        }
        
        if (!$this->sitegroup_admin_group->create())
        {
            throw new midgard_admin_sitewizard_exception("" . mgd_errstr());
        }
        else
        {
            $this->verbose("Sitegroup admin group \"" . $this->sitegroup_admin_group->name 
                . "\" created GUID: " . $this->sitegroup_admin_group->guid);
            
            return true;
        }
    }
    
    private function create_sitegroup_admin_user()
    {
        $this->sitegroup_admin_user = new midgard_person();
        $this->sitegroup_admin_user->username = $this->sitegroup_admin_username;
        $this->sitegroup_admin_user->lastname = $this->sitegroup_admin_username;
        $this->sitegroup_admin_user->sitegroup = $this->sitegroup->id;
        $this->sitegroup_admin_user->password = "**{$this->sitegroup_admin_password}";
        
        if (!$this->sitegroup_admin_user->create())
        {
            throw new midgard_admin_sitewizard_exception("Failed to create sitegroup admin user. Reason: "
                . mgd_errstr());
        }
        else
        {
            $this->verbose("Sitegroup admin user \"" . $this->sitegroup_admin_user->username 
                . "\" created GUID: " . $this->sitegroup_admin_user->guid);

            $user = new midgard_user($this->sitegroup_admin_user);
            $user->password($this->sitegroup_admin_user->username, $this->sitegroup_admin_password);
                
            $this->sitegroup_admin_group_member = new midgard_member();
            $this->sitegroup_admin_group_member->gid = $this->sitegroup_admin_group->id;
            $this->sitegroup_admin_group_member->uid = $this->sitegroup_admin_user->id;
            $this->sitegroup_admin_group_member->sitegroup = $this->sitegroup->id;
            
            if (!$this->sitegroup_admin_group_member->create())
            {
                throw new midgard_admin_sitegroup_exception("Failed to create admin group membership. Reason: " 
                    . mgd_errstr());
            }
            else
            {
                $this->verbose("Sitegroup admin group membership created GUID: " 
                    . $this->sitegroup_admin_group_member->guid);
                
                $this->sitegroup->adminid = $this->sitegroup_admin_group->id;

                if (!$this->sitegroup->update())
                {
                    throw new midgard_admin_sitewizard_exception("Failed to set admin group to sitegroup GUID: " 
                        . $this->sitegroup->guid . ". Reason: " . mgd_errstr());
                }
                else
                {
                    $this->verbose("Set admin group to sitegroup GUID: " . $this->sitegroup->guid);
                    
                    return true;
                }               
            }
        }
    }
        
    /**
     * Check if the user is in sitegroup zero.
     * Ahould be converted to an acl check when this is supported
     * in the midgard API.
     */
    private function can_create_sitegroup()
    {
        if ($_MIDGARD['root'] != 1)
        {
            throw new midgard_admin_sitewizard_exception("Not authenticated as SG0 admin. This will exit...");
                    
            return false;
        }
        
        return true;
    }
    
    public function set_sitegroup_admin_group_name($admin_group_name)
    {
        $this->sitegroup_admin_group_name = $admin_group_name;
    }
   
    public function login_administrator()
    {
        $user = midgard_user::auth($this->sitegroup_admin_username, $this->sitegroup_admin_password, $this->sitegroup_name);

        if(!$user) 
        {
            $this->setup_config->ui->error(_("Couldn't log in as sitegroup administrator") . " ( {$this->sitegroup_name} )");
            
        } else {
            
            $this->setup_config->ui->message(_("Succesfully logged in as sitegroup administrator"));
        }
    }

    public function cleanup()
    {
        $this->verbose("Sitegroup creator cleaning up!");
    
        if (is_object($this->sitegroup_admin_user))
        $this->sitegroup_admin_user->delete();
        if (is_object($this->sitegroup_admin_group))
        $this->sitegroup_admin_group->delete();
        if (is_object($this->sitegroup))
        $this->sitegroup->delete();
    }
    
    public function next_link()
    {
        $this->verbose("Initializing next link in the creation chain.");
        $this->next_link = new midgard_admin_sitewizard_creator_host($this);
        midgard_admin_sitewizard::$current_state = $this->next_link;
        
        return $this->next_link;
    }
    
    public function previous_link()
    {
        $this->verbose("There is no parent link. Returning null");
        
        midgard_admin_sitewizard::$current_state = $this;
        
        return null;
    }
    
    public function execute()
    {
        try
        {
            if ($this->can_create_sitegroup())
            {            
                $this->create_sitegroup();
                $this->create_sitegroup_admin_group();
                $this->create_sitegroup_admin_user();
                
                $this->verbose('Sitegroup created successfully.');
        
                return true;
            }
        }
        catch (midgard_admin_sitewizard_exception $e)
        {
            $e->error();
            $this->cleanup();
            
            throw new midgard_admin_sitewizard_exception();
            
            return false;
        
        } 
    }
}

?>
