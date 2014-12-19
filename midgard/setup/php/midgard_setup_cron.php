<?php

require_once 'midgard_setup_getargs.php';
require_once 'midgard_setup_ui_cli.php';
require_once 'midgard_setup_log.php';
require_once 'midgard_quick_setup.php';

define('MIDGARD_SETUP_CRON_URI', '/midcom-exec-midcom/cron.php');
define('MIDGARD_SETUP_CRON_FILE', '/etc/cron.d/midgard');
define('MIDCOM_CRON_ENTRY_AUTO', "# MIDCOM CRON generated by midgard_cron");

class midgard_setup_cron extends midgard_setup_getargs
{
    private $cron_types = array('hour', 'day', 'week', 'month');
    
    private $cron_type = null;

    function __construct()
	{
        /* Setup type. Quick or interactive one */
        $this->all_args['cron_type'] = array
        (
            'short' => 'ct',
            'max'   => 1,
            'min'   => 0,
            'desc'  => 'Cron type.',
            'default' => '',
        );

        parent::__construct();

        $setup = new midgard_quick_setup($this->get_config_name());
        $setup->connect();
    }

    private function midcom_cron_is_set()
    {
        if (!file_exists(MIDGARD_SETUP_CRON_FILE))
        {
            return false;
        }

        $content = file(MIDGARD_SETUP_CRON_FILE);
        $pattern = MIDCOM_CRON_ENTRY_AUTO;
        $midcom_entry = preg_grep("/{$pattern}/i", $content);

        if (empty($midcom_entry))
        {
            return false;
        }

        return true;
    }

    static function midcom_cron_set()
    {
        if (!is_writable(MIDGARD_SETUP_CRON_FILE))
        {
            midgard_setup_log::write(_("Can not set midcom cron. Cron file isn't writable"));
        }

        if (self::midcom_cron_is_set())
        {
            return;
        }

        /* Midgard used to use /etc/crontab, so copy midgard_cron lines to the midgard cron file */
        shell_exec("grep midgard_cron /etc/crontab >> ".MIDGARD_SETUP_CRON_FILE);
        /* ...and remove the copied lines to prevent double runs */
        shell_exec("sed -i '/midgard_cron/d' /etc/crontab");

        $content = file_get_contents(MIDGARD_SETUP_CRON_FILE);

        $mcs = MIDGARD_SETUP_PHP_CMD;
        $mcs .= " ";
        $mcs .= MIDGARD_SETUP_DIRECTORY_USR . "/share/midgard/setup/php/midgard_cron.php";

        $content .= "\n";
        $content .= MIDCOM_CRON_ENTRY_AUTO;
        $content .= "\n";
        $content .= "*/2  * * * *   root    " . $mcs . " \n";
        $content .= "30 * * * *   root  " . $mcs . " --cron_type hour \n";        
        $content .= "15 3 * * *   root  " . $mcs . " --cron_type day \n";

        file_put_contents(MIDGARD_SETUP_CRON_FILE, $content);
    }
    
    function get_cron_type()
    {
        return $this->get_arg_value('cron_type');
    }

    function list_sitegroup_hosts()
    {
        $hosts = array();

        $sgs = midgard_sitegroup::list_names();
        
        if (!empty($sgs)) 
        {
            foreach ($sgs as $sg)
            {
                $qb = new midgard_query_builder("midgard_host");
                $qb->add_constraint("prefix", "<>", "/admin");
                $qb->add_constraint("online", "=", true);

                $ret = $qb->execute();

                if (empty($ret))
                {
                    continue;
                }

                $hosts = array_merge($hosts, $ret); 
            }
        }

        return $hosts;
    }

    function list_hosts()
    {
        $hosts = array();
        
        $sgs = midgard_sitegroup::list_names();
        
        if (empty($sgs))
        {
            return $hosts;
        }

        foreach ($sgs as $sg)
        {
            midgard_connection::set_sitegroup($sg);    
            $hosts = array_merge($hosts, $this->list_sitegroup_hosts());
        }
    
        return $hosts;
    }

    function do_request(midgard_host $host, $uri = NULL, $cron_type = null)
    {
        if ($host->metadata->navnoentry == 1)
        {
            return FALSE;
        }

        if ($uri == NULL)
        {
            return FALSE;
        }
        
        $protocol = "http://";
        $port = "";
        
        if ($host->port == '443') 
        {
            $protocol = "https://";
            $port = ":".$host->port;
            
        } 
        elseif (   $host->port != '80' 
                && $host->port != '443'
                && $host->port != '0')
        {
            $port = ":".$host->port;
        }

        if (   $cron_type == null 
            || $cron_type === ''
            || $cron_type === ' ')
        {
            $param = "";
        }
        else 
        {    
            $param = "?type=" . $cron_type;
        }

        $url = $protocol . $host->name . $port . $host->prefix . $uri . $param;

        $code = 401;
        $allowed_codes = array(200, 301, 302);
        require_once('HTTP/Request.php');
         
        $req = new HTTP_Request($url);
        if ($req->sendRequest(true))
        { 
            $code = $req->getResponseCode();
        }

        if (!in_array($code, $allowed_codes))
        {
            midgard_setup_ui_cli::warning("Cron request for {$url} failed with code {$code}");
            return false;
        }

        return true;
    }

    function execute($type = null, $uri = null)
    {
        if ($type == null)
        {
            $cron_type = $this->get_cron_type();
        }
        else 
        {            
            $cron_type = $type;
        }

        $pid_file = "/var/run/midcom_services_cron_" . $this->get_config_name() . "_" . $cron_type . ".pid";
    
        if (file_exists($pid_file))
        {
            return;
        }

        file_put_contents($pid_file, "1");

        if ($uri == null)
        {
            $uri = MIDGARD_SETUP_CRON_URI;
        }

        $hosts = $this->list_hosts();

        if (empty($hosts))
        {
            unlink($pid_file);
            return;
        }
        
        foreach ($hosts as $host)
        {
            $this->do_request($host, $uri, $cron_type);
        }

        unlink($pid_file);
    }
}

?>
