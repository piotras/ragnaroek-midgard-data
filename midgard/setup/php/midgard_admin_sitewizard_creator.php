<?php
/*
 * Created on Aug 13, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
abstract class midgard_admin_sitewizard_creator
{
    protected $verbose = false;

    protected $parent_link = null;	

    protected $next_link = null;

    protected $start_chain = true;

    protected $setup_config = null;

    protected $links = array();

    protected $sitegroup = null;

    protected $sitegroup_name = null;

    public function __construct(midgard_setup_config &$config, $parent_link = null)
    {
        $this->setup_config = &$config;
        $this->parent_link = $parent_link;
    }

    public function __destruct()
    {
        if(isset($this->parent_link))
        {
            $this->parent_link->__destruct();
            unset($this->parent_link);
        }

        if(isset($this->next_link))
        {
            $this->next_link->__destruct();
            unset($this->next_link);
        }
    }

    final public function add_link($object)
    {
        $this->links[] = $object;
    }

    final public function cleanup_links()
    {
        if(empty($this->links))
            return;

        foreach($this->links as $link)
        {
            $link->cleanup();
        }
    }

    final public function set_sitegroup($identifier)
    {
        if (is_object($identifier) 
            && is_a($identifier, "midgard_sitegroup"))
        {
            $this->sitegroup = $identifier;
            return;
        }

        if ($identifier == MIDGARD_SITEGROUP_0)
        {
            $this->sitegroup = new midgard_sitegroup();
            $this->sitegroup->name = null;

            $this->sitegroup_name = null;

            return;
        }

        if ($this->sitegroup == null)
        {
            try
            {
                $this->sitegroup = new midgard_sitegroup($identifier);
            }
            catch (midgard_error_exception $e) {

                $this->sitegroup = new midgard_sitegroup();
                if (is_string($identifier))
                {
                    $this->sitegroup_name = $identifier;
                }
            }
        }
    }

    final public function set_sitegroup_name($name = null)
    {
        if ($name === null)
            return;

        $this->sitegroup_name = $name;
    }

    final public function get_sitegroup()
    {
        return $this->sitegroup;
    }

    abstract protected function cleanup();
	
    abstract protected function initialize($guid);
	
    abstract protected function execute();
	
    abstract protected function next_link();
	
    abstract protected function previous_link();
 	
 	/*
 	protected function set_parent_link($parent_link)
 	{
 		$this->parent_link = $parent_link;
 	}
 	
    protected function get_parent_link()
 	{
 		return $this->parent_link;
 	}
 	*/
 	
    protected function start_chain($start)
    {
        $this->start_chain = $start;
    }

    protected function verbose($message = "")
    {
        if ($this->verbose)
        {
            $this->setup_config->ui->message($message);
        }
    }
	
    public function sitewizard_auth_user()
    {
    
    }

    public function set_verbose($verbose = false)
    {
        $this->verbose = $verbose;
    }
 	
    /**
     * Gets template structures from filesystem
     */	 
    protected function get_structure_config_filesystem($path)
    {
        eval('$evaluated = array(' . file_get_contents($path) . ');'); 	            
        $keys = array_keys($evaluated);
        if (count($keys) != 0)
        {
            if (is_array($evaluated))
            {
                return $evaluated;
            }
        }
  
        return false;
    }

    protected function get_structure_config_filesystem_xml($path)
    {
        //$raw = file_get_contents($path);
    }
}
?>
