<?php
/*
 * Created on Aug 6, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
require_once('midgard_admin_sitewizard_creator.php');
require_once('midgard_admin_sitewizard_exception.php');

/* Include Midcom constants in case we are running cli */
if (!defined('MIDCOM_PRIVILEGE_ALLOW'))
{
define ('MIDCOM_PRIVILEGE_ALLOW', 1);
define ('MIDCOM_PRIVILEGE_DENY', 2);
define ('MIDCOM_PRIVILEGE_INHERIT', 3);
}

class midgard_admin_sitewizard_creator_structure extends midgard_admin_sitewizard_creator
{
    private $host = null;

    private $sitegroup_creator = null;

    private $host_creator = null;

    private $root_topic_name = null;

    private $root_topic = null;

    private $root_page = null;

    private $creation_root_topic = null;

    private $creation_root_group = null;
     
    private $config = null;
     
    private $structure = null;

    private $created_groups = array();
    
    private $schemadb = null;
    
    private $schemavalues = array();
    
    private $midcom_path = '';
    
    private $symlink_sources = array();
    
    private $symlink_targets = array();

    public function __construct(midgard_setup_config &$config, $parent_link = null)
    {
        // Always a good idea to run parent constructor
        parent::__construct($config, $parent_link);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    protected function cleanup()
    {
        $this->verbose('Structure creator cleaning up!');

    }
 
    public function initialize($host_guid)
    {
        $this->verbose('Initializing structure creation');

        if ($this->host == null)
         {
            if (!$this->host = new midgard_host($host_guid))
            {
                throw new midgard_admin_sitewizard_exception("Sitewizard couldn't initialize host 
                    object. Reason: " . mgd_errstr());

                return false;
            }
            else
            {
                $this->verbose("Getting sitegroup of host GUID: " . $this->host->guid);
                $sitegroup_id = $this->host->sitegroup;

                $this->sitegroup = new midgard_sitegroup($sitegroup_id);

                $this->verbose("Getting root page of host GUID: " . $this->host->guid);
                $this->root_page = new midgard_page();
                $this->root_page->get_by_id($this->host->root);

                $root_topic_guid = $this->root_page->parameter('midgard', 'midcom_root_topic_guid');

                if (empty($root_topic_guid))
                {
                    throw new midgard_admin_sitewizard_exception("Failed to get valid root topic 
                        guid from root page parameter GUID: " . $root_topic_guid . " Reason: " . mgd_errstr());

                    return false;
                }
                else
                {
                    $this->verbose("Getting root topic of page GUID: " . $this->root_page->guid);
                    $this->root_topic = new midgard_topic($root_topic_guid);
                }

                return true;
            }
        }
    }

    public function set_root_topic_name($name)
    {
        /* Should be null special,  not allowed case */
        $this->root_topic_name = $name;
    }

    public function get_root_topic_name()
    {
        if ($this->root_topic_name != null)
        {
            return $this->root_topic_name;
        }

        return str_replace('.', '-', $this->replace($this->structure['root']['name'])) . str_replace('/', '_', $this->get_host_prefix());
    }

    private function get_host_name()
    {
        if (is_object($this->host))
        {
            return $this->host->name;
        }

        return exec('hostname');
    }  
 
    private function get_host_prefix()
    {
        if (is_object($this->host))
        {
            return $this->host->prefix;
        }

        return "";
    }  

    private function get_host_style()
    {
        if (is_object($this->host))
        {
            return $this->host->style;
        }

        return 0;
    }  

    public function set_sitegroup_creator($object)
    {
        if(!($object instanceof midgard_sitegroup))
        {
            /* FIXME , handle error */            
        }

        $this->sitegroup_creator = $object;
    }

    public function get_sitegroup_creator()
    {
        return $this->sitegroup_creator;
    }

    public function set_host_creator($object)
    {
        if(!($object instanceof midgard_host))
        {
            /* FIXME , handle error */            
        }

        $this->host_creator = $object;
    }

    public function get_host_creator()
    {
        return $this->host_creator;
    }
   
    private function is_structure_created()
    {
        if ($this->root_topic != null)
        {
            $qb = new midgard_query_builder('midgard_topic');
            $qb->add_constraint('up', '=', $this->root_topic->id);
            
            if ($qb->count() > 0)
            {
                return true;
            }
        }
        
        return false;
    }
    
    public function get_schemadb()
    {
        return $this->schemadb;
    }
     
    public function read_config($path)
    {
        $this->config = $this->get_structure_config_filesystem($path);   
        
        $values = each($this->config);
 			
        if (isset($values['value']['schemadb'])) 
        {
            $this->schemadb = $values['value']['schemadb'];
        } 
    }
    
    public function alter_config($key_chain, $value)
    {
        if ($this->config != null)
        {
            $arr_ref =& $this->config;
           
            for($i = 0; $i < count($key_chain); $i++)
            {
                $arr_ref =& $arr_ref[$key_chain[$i]];
            }
            
            $arr_ref = $value;  
        }
        else
        {
            throw new midgard_admin_sitewizard_exception('There is no configuration array.');
        }
    }
     
   /**
    * There is no next link in the chain 
    * so this just runs execute()
    */
    public function next_link()
    {
        $this->next_link = $this;
        midgard_admin_sitewizard::$current_state = $this->next_link;
    }
     
    public function previous_link()
    {
        $this->verbose('Returning previous link in the creation chain.');
    
        midgard_admin_sitewizard::$current_state = $this->parent_link;
        return $this->parent_link;
    }
    
    private function create_index_article($topic_id, $title = '')
    {
        $this->verbose("Creating index article for topic ID: " . $topic_id . " Title: " . $title);
    
        $article = new midgard_article();
        $article->topic = $topic_id;
        $article->title = $title;
        $article->name = 'index';
        $article->sitegroup = $this->sitegroup->id;
        
        if (!$article->create())
        {
            throw new midgard_admin_sitewizard_exception("Failed to create index article GUID: " 
                . $article->guid . " Reason: " . mgd_errstr());             
        }
        else
        {
            return true;
        }
        
        return false;      
    }
    
    private function create_symlinks()
    {
        if (count($this->symlink_sources) > 0)
        {
            foreach($this->symlink_sources as $source_id => $guid)
            {
                if (array_key_exists($source_id, $this->symlink_targets))
                {
                    $this->verbose("Creating symlink GUID: " . $guid);
                
                    $targets = $this->symlink_targets[$source_id];
                    
                    foreach($targets as $target)
                    {         
                        $target->parameter($target->component, 'symlink_topic', $guid);
                     
                        if (!$target->update())
                        {
                            throw new midgard_admin_sitewizard_exception("Failed create symlink GUID: " 
                                . $guid . " Reason: " . mgd_errstr()); 
                        }
                    }
                }
            }
        }
    }
    
    private function update_symlinks()
    {
    
    }

    public function set_creation_root_group($group_guid)
    {
        if (!$this->creation_root_group = new midgard_group($group_guid))
        {
            throw new midgard_admin_sitewizard_exception("Failed to set root group for structure creation GUID: " 
            . $group_guid . " Reason: " . mgd_errstr()); 
    
            return true;
        }
        else
        {
            $this->verbose("Setting group for structure creation GUID: " . $group_guid);

            return true;
        }
    }

    public function set_creation_root_topic($topic_guid)
    {
        if (!$this->creation_root_topic = new midgard_topic($topic_guid))
		{
            throw new midgard_admin_sitewizard_exception("Failed to set root topic for structure creation GUID: " 
				. $topic_guid . " Reason: " . mgd_errstr()); 

			return true;
        }
        else
        {
            $this->verbose("Setting topic for structure creation GUID: " . $topic_guid);

            return true;
        }
    }

    public function create_creation_root_group($owner_guid, $group_name)
    {
        $this->verbose("Creating a root group for group structure creation under group GUID: " .  $owner_guid);

        if ($owner_guid != 0)
        {
            $owner_group = new midgard_group($owner_guid);
            $owner_id = $owner_group->id;
        }
        else
        {
            $owner_id = 0;
        }
        
        $qb = new midgard_query_builder('midgard_group');
        $qb->add_constraint('sitegroup', '=', $this->sitegroup->id);
        $qb->add_constraint('owner', '=', $owner_id);
        $qb->add_constraint('name', '=', $group_name);

        $groups = $qb->execute();

        if (count($groups) > 0)
        {
            $this->verbose("Group \"" . $group_name . "\" GUID: " . $groups[0]->guid 
            . " already exists. Setting as root group for structure creation.");

            $this->creation_root_group = $groups[0];

            return true;
        }
        else
        {
            $this->creation_root_group = new midgard_group();
            $this->creation_root_group->owner = $owner_id;
            $this->creation_root_group->sitegroup = $this->sitegroup->id;
            $this->creation_root_group->name = $group_name;

            if (!$this->creation_root_group->create())
            {
                throw new midgard_admin_sitewizard_exception("Failed to create root group for group 
                    structure creation under group GUID: " . $this->creation_root_group->guid);
            }
            else
            {
                return true;
            }
        }
    }

    public function create_creation_root_topic($topic_guid, $topic_name = '', $topic_title = '', 
        $component = '', $parameters = array(), $style = '', $create_index = false)
    {
        $this->verbose('Creating a root topic for structure creation');

        $topic = new midgard_topic($topic_guid);
        
        $mc = new midgard_collector('midgard_topic', 'up', $topic->id);
        $mc->add_constraint('name', '=', $topic_name);
        $mc->set_key_property('id');
        $mc->execute();
        $keys = $mc->list_keys();
        
        if (count($keys) > 0)
        {
            throw new midgard_admin_sitewizard_exception("Can't create root topic! Reason: name already exists.");
        }
        else
        {
            $this->creation_root_topic = new midgard_topic();
            $this->creation_root_topic->extra = $topic_title;
            $this->creation_root_topic->name = $this->get_root_topic_name();
            $this->creation_root_topic->sitegroup = $this->sitegroup->id;
            $this->creation_root_topic->component = $component;
            $this->creation_root_topic->up = $topic->id;
            
            if (!empty($style))
            {
                $this->verbose("Setting style for topic" . $topic_name . ": (" . $style . ")");
                $this->creation_root_topic->style = $style;
            }

            if (!$this->creation_root_topic->create())
            {
                throw new midgard_admin_sitewizard_exception("Failed to create root topic for structure creation GUID: " 
                    . $this->creation_root_topic->guid . " Reason: " . mdg_errstr());
            }
            else
            {
                // Setting style parameter
                if (!empty($style))
                {
                    $this->creation_root_topic->parameter('midcom', 'style', $style);
                }
                
                $this->verbose("Created root topic for structure creation GUID: " 
                    . $this->creation_root_topic->guid);
        
                // Setting additional parameters
                foreach ($parameters as $domain => $parameter)
                {
                    foreach ($parameter as $name => $value)
                    {
                        $this->creation_root_topic->parameter($domain, $name, $value);
                    }
                }
                
                if ($create_index == true)
                {
                    $this->create_index_article($this->creation_root_topic->id, $topic_title);
                }
            }
        }
    }
    
    public function set_schema_values($schemavalues)
    {       
        $this->schemavalues = $schemavalues;      
    }

    private function replace($string)
    {
        $result = str_replace('__HOSTNAME__', $this->get_host_name(), $string);
        /* $result = str_replace('__HOSTTITLE__', $this->root_page->title, $result); */
        
 	    foreach ($this->created_groups as $name => $group)
 	    {
 	       // Change __GROUPGUID_groupname strings to GUIDs of the actual created groups
 	       $result = str_replace("__GROUPGUID_{$name}", $group->guid, $result);
 	    }
 	         	    
 	    foreach ($this->schemavalues as $field => $inputted_value)
 	    {
 	        // Change all __SCHEMA_fieldname strings to values inputted in the DM2 editor
 	        $result = str_replace("__SCHEMA_{$field}", $inputted_value, $result);     
 	    } 
 	    
        return $result;
    }

    private function create_topic_structure_recursive($nodes, $parent_node)
    {
        foreach ($nodes as $node)
        {
            $this->verbose("Creating topic \"" . $this->replace($node['title']) . "\" under parent topic GUID: " 
            . $parent_node->guid);

            $topic = new midgard_topic();
            $topic->up = $parent_node->id;
            $topic->name = $this->replace($node['name']);
            $topic->extra = $this->replace($node['title']);
            $topic->sitegroup = $this->sitegroup->id;
            $topic->component = $node['component'];
            
            if (isset($node['style']) && !empty($node['style']))
            {
                $this->verbose("Setting style for topic " . $node['name'] . ": (" . $node['style'] . ")");
                $topic->style = $node['style'];
            }
            
            if (isset($node['nav_noentry']) && $node['nav_noentry'] == true)
            {
                $topic->metadata->navnoentry = true;
            }

            if (!$topic->create())
            {
                $this->setup_config->ui->error(_("Failed to create object") . " midgard_topic ({$topic->extra}): " . $_MIDGARD_CONNECTION->get_error_string());
            }
            else
            {
                // Setting style parameter
                if (isset($node['style']) && !empty($node['style']))
                {
                    $topic->parameter('midcom', 'style', $node['style']);
                }
                
                // Setting additional parameters
                if (isset($node['parameters']) && count($node['parameters']) > 0)
                {
                    $this->verbose("Starting to set additional parameters for topic GUID: " . $topic->guid);
                    
                    $this->set_parameters($node, $topic);
                }

                // Setting midcom acl privileges
                if (isset($node['acl']) && count($node['acl']) > 0)
                {
                    $this->verbose("Starting to set midcom acl privileges for topic GUID: " . $topic->guid);

                    $this->set_privileges($node, $topic);
                }
                
                // Setting metadata
                if (isset($node['metadata']) && count($node['metadata']) > 0)
                {
                    $this->verbose("Starting to set metadata for topic GUID: " . $topic->guid);
                
                    $this->set_metadata($node, $topic);
                }
                
                // Creating index article
                if (isset($node['create_index']) && $node['create_index'] == true)
                {
                    $this->create_index_article($topic->id, $this->replace($node['title']));
                }
                
                // Adding symlink source
                if (isset($node['symlink_source_id']))
                {
                    $this->symlink_sources[$node['symlink_source_id']] = $topic->guid;
                }
                
                // Adding symlink target
                if (isset($node['symlink_target_id']))
                {
                    $this->symlink_targets[$node['symlink_target_id']][] = $topic;
                }
                
                // Setting styleInherit
                if (isset($node['style_inherit']) && $node['style_inherit'] == true)
                {
                    $this->creation_root_topic->styleInherit = 1;
                }
                
                // Setting style
                if (isset($node['style']))
                {
                    $this->creation_root_topic->style = $node['style'];
                }
            }

            if (isset($node['nodes']) && count($node['nodes']) > 0)
            {
                $this->create_topic_structure_recursive($node['nodes'], $topic);
            }
        }
    }

    private function set_metadata($config_node, $object)
    {
        foreach ($config_node['metadata'] as $field => $value)
        {
            $value = $this->replace($value);
            $this->verbose("Setting metadata (" . $field . "=" . $value .") for object GUID: " . $object->guid);
            
            if (!property_exists($object->metadata, $field)) 
            { 
                $object->parameter('midcom.helper.metadata', $field, $value); 
            }
            else
            {
                $object->metadata->{$field} = $value;
                $object->update();
            }
        }
        return true;
    }

    private function set_parameters($config_node, $object)
    {
        foreach ($config_node['parameters'] as $domain => $parameter)
        {
            foreach ($parameter as $name => $value)
            {
                $value = $this->replace($value);
                $this->verbose("Setting parameter (" . $domain . "," . $name . "," . $value .") for object GUID: "
                    . $object->guid);
            
                $object->parameter($domain, $name, $value);
            }
        }
        return true;
    }

    private function set_privileges($config_node, $object)
    {
        foreach($config_node['acl'] as $identifier => $privilege)
        {
            $assignee = null;
            if (array_key_exists($assignee, $this->created_groups))
            {
                $assignee = "group:{$this->created_groups[$identifier]->guid}";
            }
            else
            {
                // This can be any valid ACL identifier, including EVERYONE, USERS, user:76ba9524959311db899a913818d3e97de97d and group:c9a5de389c0011dbbd29b1f9232d1a451a45
                $assignee = $identifier;
            }
            
            if (is_null($assignee))
            {
                // Faulty assignment
                continue;
            }
            
            foreach($privilege as $name => $value)
            {
                $acl = new midcom_core_privilege_db(); 
                $acl->objectguid = $object->guid;
                $acl->name = $name;
                $acl->sitegroup = $this->sitegroup->id;
                $acl->assignee = $assignee;
                $acl->value = $value;
    
                if (!$acl->create())
                {
                    throw new midgard_admin_sitewizard_exception();
                }
                else
                {
                    $this->verbose("ACL (" . $acl->name . "," . $acl->assignee . "," 
                        . $acl->value . " set for object GUID: " . $object->guid);
        
                    return true;
                }
            } 
        }
    }

    private function create_topic_structure()
    {
        $this->verbose("Starting to create topic structure");
        
        if (!is_null($this->creation_root_topic))
        {
            $this->verbose("under topic GUID: " . $this->creation_root_topic->guid);
        }

        foreach ($this->config as $structure)
        {
            $this->structure = $structure;
        }

        // Creating root topic from configuration if it hasn't been set already
        if ($this->creation_root_topic == null)
        {
            $this->verbose('Creating root topic from configuration');

            $this->creation_root_topic = new midgard_topic();
            $this->creation_root_topic->up = 0;
            $this->creation_root_topic->sitegroup = $this->sitegroup->id;
            $this->creation_root_topic->extra = $this->replace($this->structure['root']['title']); 
            $this->creation_root_topic->name = $this->get_root_topic_name();
            $this->creation_root_topic->component = $this->structure['root']['component'];
            
            if (isset($this->structure['root']['style']) && !empty($this->structure['root']['style']))
            {
                $this->verbose("Setting style for topic " . $this->structure['root']['name'] 
                    . "(" . $this->structure['root']['style'] . ")");
                $this->creation_root_topic->style = $this->structure['root']['style'];
            }
            else
            {
                $this->verbose("Setting style for topic " . $this->structure['root']['name'] . ": (" . $this->get_host_style() . ")");
                
                if (isset($this->structure['root']['use_inherited_style']) && $this->structure['root']['use_inherited_style'] == false)
                {
                    $this->creation_root_topic->style = "/" . $this->get_host_style_name($this->host->style, false); 
                }           
                else
                {
                    $this->creation_root_topic->style = "/" . $this->get_host_style_name($this->get_host_style());
                }
            }
            
            if (isset($this->structure['root']['nav_noentry']) && $this->structure['root']['nav_noentry'] == true)
            {
                $this->creation_root_topic->metadata->navnoentry = true;
            }
            
			$created = $this->creation_root_topic->create();
			$error_code = midgard_connection::get_error ();

			/* Topic exists, so we assume structure also does */
			if (!$created && $error_code == MGD_ERR_DUPLICATE)
			{
				return true;
			}

			if (!$created)
			{
                throw new midgard_admin_sitewizard_exception("Failed to create root topic \"" 
                    . $this->get_root_topic_name() . "\" for structure creation" . ". " . midgard_connection::get_error_string());

                return false;    
            }
            else
            {
                // Setting style parameter
                if (isset($this->structure['root']['style']) && !empty($this->structure['root']['style']))
                {
                     $this->creation_root_topic->parameter('midcom', 'style', $this->structure['root']['style']);
                }
                else
                {
                    if (isset($this->structure['root']['use_inherited_style']) && $this->structure['root']['use_inherited_style'] == false)
                    {
                        $this->creation_root_topic->parameter('midcom', 'style', "/" . $this->get_host_style_name($this->host->style, false));
                    }
                    else
                    {
                        $this->creation_root_topic->parameter('midcom', 'style', "/" . $this->get_host_style_name($this->get_host_style()));
                    }
                }
            
                // Setting additional parameters
                if (isset($this->structure['root']['parameters']) && count($this->structure['root']['parameters']) > 0)
                {
                    $this->set_parameters($this->structure['root'], $this->creation_root_topic);
                }

                // Setting midcom acl privileges
                if (isset($this->structure['root']['acl']) && count($this->structure['root']['acl']) > 0)
                {
                    $this->set_privileges($this->structure['root'], $this->creation_root_topic);
                }
                
                // Setting metadata
                if (isset($this->structure['root']['metadata']) && count($this->structure['root']['metadata']) > 0)
                {
                    $this->set_metadata($this->structure['root'], $this->creation_root_topic);
                }
                
                // Setting styleInherit
                if (isset($this->structure['root']['style_inherit']) && $this->structure['root']['style_inherit'] == true)
                {
                    $this->creation_root_topic->styleInherit = 1;
                    $this->creation_root_topic->update();
                }
                
                if (isset($this->structure['root']['create_index']) && $this->structure['root']['create_index'] == true)
                {
                    $this->create_index_article($this->creation_root_topic->id, $this->replace($this->structure['root']['title']));
                }
            }
        }

        // Making sure the topic is empty before creating a structure
       $qb = new midgard_query_builder('midgard_topic');
       $qb->add_constraint('up', '=', $this->creation_root_topic->id);
       $qb->add_constraint('sitegroup', '=', $this->sitegroup->id);
       $topics = $qb->execute();
    
       if (count($topics) > 0)
       {
           throw new midgard_admin_sitewizard_exception('Creation root topic is not empty!');
       }
       else
       {
           try
           {
               $this->create_topic_structure_recursive($this->structure['root']['nodes'], $this->creation_root_topic);
           }
           catch (midgard_admin_sitewizard_exception $e)
           {
                $e->error();

                throw new midgard_admin_sitewizard_exception("Failed to create topic structure under topic GUID: " 
                    . $this->creation_root_topic->guid . "Reason: " . mgd_errstr());
        
            }
        }
    }

    private function update_topic_structure()
    {
    
    }

    private function create_group_structure()
    {
        if ($this->creation_root_group != null)
        {   
            $group_owner_id = $this->creation_root_group->id;
        }
        else
        {
             $group_owner_id = $this->sitegroup->adminid;
        }

        $this->verbose("Starting to create groups under group ID: " . $group_owner_id);

        $qb = new midgard_query_builder('midgard_group');
        $qb->add_constraint('sitegroup', '=', $this->sitegroup->id);
        $qb->add_constraint('owner', '=', $group_owner_id);
        $groups = $qb->execute();

        if (count($groups) != 0)
        {
            // Ok, let's first check if a group already exists          
            foreach ($this->config as $structure)
            {
                foreach ($structure['groups'] as $key => $candidate_group)
                {
            
                    $this->verbose("Checking if group \"" . $candidate_group['name'] . "\" already exists");

                    $group_match = 0;

                    foreach ($groups as $group)
                    {
                        if ($group->name == $candidate_group['name'])
                        {
                            $this->created_groups[$group->name] = $group;
                            $group_match++; 
                        }
                    }

                    if ($group_match > 0)
                    {
                        $this->verbose("Group \"" . $candidate_group['name'] . "\" already exists! No need to create");   
                    }
                    elseif (count($groups) > 0)
                    {
                        $this->verbose("Group \"" . $candidate_group['name'] . "\" does not exist! Creating now");
           
                        $new_group = new midgard_group();
                        $new_group->owner = $group_owner_id;
                        $new_group->sitegroup = $this->sitegroup->id;
                        $new_group->name = $this->replace($candidate_group['name']);
                        
                        if (!$new_group->create())
                        {
                            throw new midgard_admin_sitewizard("Failed to create group \"" . $candidate_group['name'] 
                                . "\" GUID: " . $new_group->guid . " Reason: " . mgd_errstr());
                        }
                        else
                        {
                            $this->created_groups[$new_group->name] = $new_group; 
                            
                            if (array_key_exists('subgroups', $candidate_group))
                            {
                                foreach($candidate_group['subgroups'] as $subgroup)
                                {
                                    $new_subgroup = new midgard_group();
                                    $new_subgroup->owner = $new_group->id;
                                    $new_subgroup->sitegroup = $this->sitegroup->id;
                                    $new_subgroup->name = $this->replace($subgroup['name']);
                                    
                                    if (!$new_subgroup->create())
                                    {
                                        throw new midgard_admin_sitewizard("Failed to create subgroup \"" . $subgroup['name'] 
                                            . "\" GUID: " . $new_subgroup->guid . " Reason: " . mgd_errstr());
                                    }
                                    else
                                    {
                                        $this->verbose("Subgroup \"" . $subgroup['name'] 
                                            . "\" does not exist! Creating now");
                                        $this->created_groups[$new_subgroup->name] = $new_subgroup;
                                    }
                                }
                            }
                            
                            return true;
                        }
                    }
                }
            }
        }
        else
        {
            // There are no groups so we can just create the groups from config file         
            foreach ($this->config as $structure)
            {       
                
                if (empty($structure['groups']))
                {
                    continue;
                }
 
                foreach ($structure['groups'] as $candidate_group)
                {
                    $this->verbose("Group \"" . $this->replace($candidate_group['name']) . "\" does not exist! Creating now");
            
                    $new_group = new midgard_group();
                    $new_group->name = $this->replace($candidate_group['name']);
                    $new_group->sitegroup = $this->sitegroup->id;
                    $new_group->owner = $group_owner_id;

                    if (!$new_group->create())
                    {
                        throw new midgard_admin_sitewizard("Failed to create group \"" . $candidate_group['name'] 
                            . "\" GUID: " . $new_group->guid . " Reason: " . meg_errstr());
                    }
                    else
                    {
                        $this->created_groups[$new_group->name] = $new_group; 
                            
                        if (array_key_exists('subgroups', $candidate_group))
                        {
                            foreach($candidate_group['subgroups'] as $subgroup)
                            {
                                $new_subgroup = new midgard_group();
                                $new_subgroup->owner = $new_group->id;
                                $new_subgroup->sitegroup = $this->sitegroup->id;
                                $new_subgroup->name = $this->replace($subgroup['name']);
                                    
                                if (!$new_subgroup->create())
                                {
                                    throw new midgard_admin_sitewizard("Failed to create subgroup \"" . $subgroup['name'] 
                                        . "\" GUID: " . $new_group->guid . " Reason: " . mgd_errstr());
                                }
                                else
                                {
                                    $this->verbose("Subgroup \"" . $subgroup['name'] 
                                        . "\" does not exist! Creating now");
                                    $this->created_groups[$new_subgroup->name] = $new_subgroup;
                                }
                                
                                
                            }
                        }
                            
                         return true;
                    }
                }
            }            
        }
    }
    
    private function update_group_structure()
    {
    
    }
    
    public function get_host_style_name($style_id, $use_inherited_style = true)
    {
        $qb = new midgard_query_builder('midgard_style');
        $qb->add_constraint('id', '=', $style_id);
        $styles = $qb->execute();    
        
        if (count($styles) > 0)
        {
            if (!$use_inherited_style)
            {
                $qb = new midgard_query_builder('midgard_style');
                $qb->add_constraint('id', '=', $styles[0]->up);
                $parent_styles = $qb->execute();
                
                if (count($parent_styles) > 0)
                {
                    return $parent_styles[0]->name;
                }
                else
                {
                    return $styles[0]->name;
                }
            }
            
            return $styles[0]->name;
        }
        else
        {
            return '';
        }
    }
    
    public function get_host()
    {
        return $this->host;
    }
    
    public function get_root_page()
    {
        return $this->root_page;
    }
    
    public function get_creation_root_topic()
    {
        return $this->creation_root_topic;
    }
    
    public function get_creation_root_group()
    {
        return $this->creation_root_group;
    }   
    
    public function set_midcom_path($path)
    {
        $this->midcom_path = $path;
    }
    
    public function execute()
    {
        $this->verbose('Starting execution chain (midgard_admin_sitewizard_creator_structure)');

        /* Create sitegroup or check if it's set */
        if($this->sitegroup_creator !== null)
        {
            if(!$this->sitegroup_creator->execute())
            {
                $this->setup_config->ui->error(_("Failed to create object") . "midgard_sitegroup: " . $_MIDGARD_CONNECTION->get_error_string());
            } 
            else {
                
                $this->sitegroup = $this->sitegroup_creator->get_sitegroup();
            }
        }

        /* Create host */
        if($this->host_creator !== null){

            $this->host_creator->set_sitegroup($this->sitegroup);
            
            if(!$this->host_creator->execute())
            {
                $this->setup_config->ui->error(_("Failed to create object") . "midgard_host: " . $_MIDGARD_CONNECTION->get_error_string());
            } 
            else {
                
                $this->host = $this->host_creator->get_host();
            }
        }

        /* TODO, REFACTOR following code */
        try 
        {
            if (!$this->is_structure_created())
            {
                $this->create_group_structure();
                $this->create_topic_structure();
                $this->create_symlinks();

                $this->verbose("Sitewizard created website structure successfully.");
            
                return $this->creation_root_topic->guid;
            }
            else
            {
                /*
                $this->update_group_structure();
                $this->update_topic_structure();
                $this->update_symlinks();
                */
            
                $this->verbose("Sitewizard updated website structure successfully.");
            
                return $this->root_topic->guid;            
            }
        }
        catch(midgard_admin_sitewizard_exception $e)
        {
            //$this->parent_link->cleanup() 
            $this->cleanup();
 
            $this->setup_config->ui->error(_("Failed to create structure") . $e->error()); 
    
            return false;
        }
    }
}
 
?>
