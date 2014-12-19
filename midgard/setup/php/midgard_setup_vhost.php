<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'midgard_setup_globals.php';
require_once 'midgard_setup_vhost_config.php';

class midgard_setup_vhost 
{
    public $name;
    public $port;
    public $prefix;

    private $midgard_host;
    private $setup_config;

    private $database;
    private $host_dir;
    private $blob_dir;
    private $root_file;
    private $root_file_mmp;
    private $cache_dir;
    private $midcom_cache_dir;
    private $config_file_path;
    private $midgard_httpd_conf;
    private $midgard_httpd_dir;

    private $midcom_rewrite_condition;
    
    public function __construct(midgard_setup_config &$config, $name, $port = '80', $prefix = '')
    {
        $this->name = $name;
        $this->port = $port;
        $this->prefix = $prefix;
        $this->setup_config = &$config;

        if (!$this->name || $this->name == '')
        {
            throw new Exception("Failed to initialize new virtual host with empty name", 0);
        }

        if (MIDGARD_SETUP_APACHE_LIBEXEC_PATH == '')
        {
            throw new Exception("MIDGARD_SETUP_APACHE_LIBEXEC_PATH is empty");
        }

        /* Database */
        $this->database = $this->setup_config->midgard_config->database;
        if (!empty($this->setup_config->midgard_config->host))
            $this->database = $this->setup_config->midgard_config->host.":".$this->database;

        /* DocumentRoot */
        $this->host_dir = MIDGARD_SETUP_DIRECTORY_VAR."/lib/midgard/vhosts/";
        $this->host_dir .= $this->name."/".$this->port;
        
        /* Blobdir */
        $this->blob_dir = $this->setup_config->midgard_config->blobdir;
        if (empty($this->blob_dir)) {
            $this->blob_dir = MIDGARD_SETUP_DIRECTORY_VAR."/lib/midgard/blobs/";
            $this->blob_dir .= $this->setup_config->midgard_config->database;
        }

        /* RootFile(s) */
        $this->root_file = MIDGARD_SETUP_APACHE_LIBEXEC_PATH."/midgard-root-nommp.php";
        $this->root_file_mmp = MIDGARD_SETUP_APACHE_LIBEXEC_PATH."/midgard-root.php";

        /* Cache dir */
        $this->cache_dir = MIDGARD_SETUP_DIRECTORY_VAR."/cache/midgard/";
        $this->cache_dir .= $this->setup_config->midgard_config->database;

        /* Config file location */
        $this->config_file_path = MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache/vhosts/";
        $this->config_file_path .= $this->name."_".$this->port;
       
        /* midgard's httpd.conf directory and file location */
        $this->midgard_httpd_conf = MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache/httpd.conf";
        $this->midgard_httpd_dir = MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache";        

        /* Ugly but acceptable atm IMO */
        $this->midcom_rewrite_condition = "
# Uncomment if you want to redirect all midcom-admin requests to
# secured host. Keep in mind that mod_rewrite module is mandatory
# and should be loaded in Apache configuration.
# More docs about configuration may be found at:
# http://www.midgard-project.org/midcom-permalink-ebfd755b5fc58087bc4f5771585c63eb
# --- SSL rewrite Start ---
#RewriteEngine On
#RewriteCond %{REQUEST_URI} !^/midcom-admin.*
#RewriteCond %{REQUEST_URI} !^/midcom-static.*
#RewriteCond %{REQUEST_URI} !\.(gif|jpg|css|ico)$
#RewriteRule /(.*)$ http://{$this->name}/$1

#RewriteEngine On
#RewriteCond %{REQUEST_URI} ^/midcom-admin.*
#RewriteRule /(.*)$ https://{$this->name}/$1
# --- SSL rewrite End --- ";
    }

    static private function directive_exists($directive, $filepath)
    {
        if(!is_file($filepath))
            return FALSE;
        
        if(!$content = file($filepath))
            return FALSE;
        
        $directives = array();

        $length = strlen($directive);

        foreach($content as $line)
        {
            $exists = strstr($line, $directive);
            if($exists != FALSE && strlen($line) > $length)
            {
                $listen = substr_compare($line, $directive, 1, $length);
                if($listen > -1)
                    $directives[] = $line;
            }
        }

        if(count($directives) > 0)
            return $directives;

        return FALSE;
    }

    static private function param_exists_in_directives($param, $directives)
    {
        if($directives)
        {
            foreach($directives as $line)
            {
                $chunks = explode(" ", $line);
                
                foreach($chunks as $piece)
                {
                    $piece = trim($piece);
                    if($param == $piece)
                        return TRUE;
                }
            }
        }

        return FALSE;
    }

    private function apache_is_listening($port)
    {
        $directives = self::directive_exists("Listen", MIDGARD_SETUP_APACHE_CONF);
       
        if(self::param_exists_in_directives($port, $directives))
            return TRUE;

        $included = self::directive_exists("Include", MIDGARD_SETUP_APACHE_CONF);
        
        $included_directives = array();

        if($included != FALSE)
        {
            foreach($included as $line)
            {
                if(substr($line, 0, 7) == "Include")
                {
                    $filepath = trim(substr($line, 7));
                    if(is_file($filepath))
                    { 
                        $new_array = self::directive_exists("Listen", $filepath);
                        if(is_array($new_array))
                        {
                            $included_directives = 
                                array_merge($included_directives, $new_array);
                        }
                    }
                }
            }
        }
        
        if(self::param_exists_in_directives($port, $included_directives))
            return TRUE;

        $directives = self::directive_exists("Listen", $this->midgard_httpd_conf);

        if(self::param_exists_in_directives($port, $directives))
            return TRUE;

        return FALSE;
    }
    
    private function create_midgard_httpd_conf()
    {
        $load_module = MIDGARD_SETUP_APACHE_LIBEXEC_PATH."/midgard-apache2.so";
        $conf_dir = $this->midgard_httpd_dir;
        $port = $this->port;

        if(!is_file($this->midgard_httpd_conf)) 
        {
            
            $content = "LoadModule midgard_module {$load_module} \n";
            $content .= "\n";

            /* Check if apache is listening already on this port */
            if(!$this->apache_is_listening($port))
            {
                $content .= "Listen {$port} \n";
                $content .= "NameVirtualHost *:{$port}\n";
                $content .= "\n";
            }
            
            $content .= "Include {$conf_dir}/vhosts/[^\.#]*\n";
            
            if(!file_put_contents($this->midgard_httpd_conf, $content))
                return FALSE;

            return TRUE;
        }   

        return TRUE;
    }

    public function enable_apache_module()
    {
        $module_enable = FALSE;        

        if(!$content = file(MIDGARD_SETUP_APACHE_CONF))
        { 
            midgard_setup_ui_cli_config::warning(_("Can not read").MIDGARD_SETUP_APACHE_CONF);
            return FALSE;
        }
       
        $this->create_midgard_httpd_conf();

        /* check if midgard's httpd.conf is included */
        foreach($content as $line)
        {
            $included = strstr($line, "midgard/apache/httpd.conf");
            if($included != FALSE)
            {
                $module_enable = TRUE;
                break;
            }
        }

        if($module_enable)
            return TRUE;
        
        reset($content);

        $lines = count($content);

        $module_line = "Include ".MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache/httpd.conf";

        $content[$lines++] = " \n";
        $content[$lines++] = "#Include midgard's configuration \n";
        $content[$lines++] = $module_line;
        
        $new_content = implode($content);

        if(!file_put_contents(MIDGARD_SETUP_APACHE_CONF, $new_content))
            return FALSE;

        return TRUE;
    }

    public function create_configuration($location = NULL)
    {
        /* In case of a prefixed host: Create vhost file only if it's missing (#824) */
        if (!empty($this->prefix) && file_exists($this->config_file_path)) return;

        $cnf = new midgard_setup_vhost_config($this->name, $this->port);
        
        /* Add directives */
        $cnf->add_directive("DocumentRoot", array($this->host_dir));
        $cnf->add_directive("AddDefaultCharset", array("utf-8"));
        
        /* Midgard directives */
        $cnf->add_directive("\n# MIDGARD settings \n", array());
        $cnf->add_directive("MidgardEngine", array("on"));
        $cnf->add_directive("MidgardBlobDir", array($this->blob_dir));
        $cnf->add_directive("#MidgardRootfile", array($this->root_file));
        $cnf->add_directive("MidgardRootFile", array($this->root_file_mmp));
        $cnf->add_directive("MidgardPageCacheDir", array($this->cache_dir));
        $cnf->add_directive("MidgardDefaultRealm", array("Midgard"));
        $cnf->add_directive("MidgardDatabase", 
            array (
                $this->database,
                $this->setup_config->midgard_config->dbuser,
                $this->setup_config->midgard_config->dbpass )); 

        /* TODO , add <Directory ${MIDCOM_STATIC_DIR}> Allow from all */

        /* PHP settings */
        $cnf->add_directive("\n# PHP settings \n", array());
        $cnf->add_directive("php_admin_flag", array("file_uploads", "On"));
        $cnf->add_directive("php_flag", array("magic_quotes_gpc", "Off"));
        $cnf->add_directive("php_value", array("memory_limit", "50M"));
        $cnf->add_directive("php_value", array("post_max_size","50M"));
        $cnf->add_directive("php_value", array("upload_max_filesize","50M"));

        $cnf->add_directive($this->midcom_rewrite_condition, array());

        /* Add directories */
        $cnf->add_directory($this->host_dir, 
            array("Options +SymLinksIfOwnerMatch", "Allow from all"));
        $cnf->add_directory($this->cache_dir, array("Allow from all"));

        $midcom_static_dir = $this->host_dir."/midcom-static";
        $cnf->add_directory($midcom_static_dir, 
            array ( 
                "Options -Indexes",
                "<FilesMatch \"\.(php)$\">",
                "Deny from all",
                "</FilesMatch>" ));

        $fckeditor_dir = $midcom_static_dir."/midcom.helper.datamanager2/fckeditor";
        $cnf->add_directory($fckeditor_dir,
            array (
                "Options -Indexes",
                "<FilesMatch \"\.(php)$\">",
                "Allow from all",
                "</FilesMatch>" ));
		
        /* Add locations */
        $cnf->add_location("/midcom-static/",
            array (
                "MidgardEngine Off"));

        $content = $cnf->get_configuration();       
        
        if($location == NULL)
            $config_file = $this->config_file_path;
        else 
            $config_file = $location;

        if(!file_put_contents($this->config_file_path, $content))
            return FALSE;
            
        return TRUE;        
    }

    static public function list_hosts()
    {

    }

    static public function get_host_by_name($name, $port = 80)
    {

    }

    public function set_host_directories()
    {
        $_etc_config_dir = MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache";
        
        /* etc directories */
        if(!is_dir($_etc_config_dir))
            mkdir($_etc_config_dir, 0700, TRUE);

        $_etc_vhosts_dir = $_etc_config_dir."/vhosts";
        if(!is_dir($_etc_vhosts_dir))
            mkdir($_etc_vhosts_dir, 0700, TRUE);

        /* cache directory */
        $cache_dir = MIDGARD_SETUP_DIRECTORY_VAR."/cache/midgard";
        if(!is_dir($cache_dir))
            mkdir($cache_dir, 0711, TRUE);

        chown($cache_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($cache_dir, MIDGARD_SETUP_APACHE_GROUP);

        /* midcom cache directory */
        $midcom_cache_dir = MIDGARD_SETUP_DIRECTORY_VAR."/cache/midgard/midcom";
        if(!is_dir($midcom_cache_dir))
            mkdir($midcom_cache_dir, 0711, TRUE);

        chown($midcom_cache_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($midcom_cache_dir, MIDGARD_SETUP_APACHE_GROUP);

        /* database cache directory */
        if(!is_dir($this->cache_dir))
            mkdir($this->cache_dir, 0711, TRUE);

        chown($this->cache_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($this->cache_dir, MIDGARD_SETUP_APACHE_GROUP);

        $db_cache_dir = $this->cache_dir;
        if(!is_dir($db_cache_dir))
            mkdir($db_cache_dir, 0771, TRUE);

        /* log directory */
        $log_dir = MIDGARD_SETUP_DIRECTORY_VAR."/log/midgard";
        if(!is_dir($log_dir))
            mkdir($log_dir, 0700, TRUE);

        /* midcom log directory */
        $midcom_log_dir = MIDGARD_SETUP_DIRECTORY_VAR."/log/midgard/midcom";
        if(!is_dir($midcom_log_dir))
            mkdir($midcom_log_dir, 0771, TRUE);
   
        chown($midcom_log_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($midcom_log_dir, MIDGARD_SETUP_APACHE_GROUP);

        /* rcs directory */
        $rcs_dir = MIDGARD_SETUP_DIRECTORY_VAR."/lib/midgard/rcs";
        if(!is_dir($rcs_dir))
            mkdir($rcs_dir, 0771, TRUE);

        chown($rcs_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($rcs_dir, MIDGARD_SETUP_APACHE_GROUP);

        /* spool directory, required for replication */
        $spool_dir = MIDGARD_SETUP_DIRECTORY_VAR."/spool/midgard/replicator_queue";
        if(!is_dir($spool_dir))
            mkdir($spool_dir, 0771, TRUE);

        chown($spool_dir, MIDGARD_SETUP_APACHE_USER);
        chgrp($spool_dir, MIDGARD_SETUP_APACHE_GROUP);

        /* vhost individual directories */
        if(!is_dir($this->host_dir))
            mkdir($this->host_dir, 0711, TRUE);
        
        /* Change the group of the DocumentRoot (to be able to restrict permissions) */
        chgrp($this->host_dir, MIDGARD_SETUP_APACHE_GROUP);
        chmod($this->host_dir, 0550);

        /* Create midcom-static symlink */
        /* I do not see any other choice. midgard_setup_vhost object can 
         * be created if there's no "pear context". 
         * I create new midgard_setup_pear object to get midcom's static dir 
         */
        $msp = new midgard_setup_pear();
        $midcom_static = $this->host_dir . "/midcom-static";

        if(!is_link($midcom_static) && !file_exists($midcom_static))
        {
        
            $_link = symlink($msp->midcom_static_dir(), $midcom_static);
            
            if(!$_link) 
            {
                $this->setup_config->ui->warning(_("Failed to create midcom static symlink!"));
            }
        }
    }

    public function get_config_path()
    {
        if($this->name == NULL
            || $this->name == "") {
            
            midgard_setup_ui_cli::warning(_("Can not build configuration file path. Empty host name"));
            return NULL;
        }

        $config = MIDGARD_SETUP_DIRECTORY_ETC."/midgard/apache/vhosts";

        return $config;
    }

    private function check_host()
    {
        $protocol = "http://";
        $port = "";

        if($this->port == '443') {
            
            $protocol = "https://";
            $port = ":".$this->port;
        
        } elseif ($this->port != '80' && $this->port != '443'){
        
            $port = ":".$this->port;
        }

        /* do we need urlencode here? */

        $url = $protocol.$this->name.$port.$this->prefix;
       
        $code = 401;
        /* Note that 403 is forbidden but in our case means success since authorization is required */
	    $allowed_codes = array(200, 301, 302, 403);

	    require_once('HTTP/Request.php');
	    $req = new HTTP_Request($url);
	    if($req->sendRequest(true))
	    {
		    $code = $req->getResponseCode();
	    }

	    if(!in_array($code, $allowed_codes))
	    {
            $this->setup_config->ui->warning(_("Can not request newly created host ") . " ( {$url} ) ");
            $this->setup_config->ui->error(_("Please, analyze apache server log files"));
        }

        $this->setup_config->ui->message(_("Congratulations! Midgard host is already configured. Open {$url} with your favourite web browser")); 
    }

    public function apache_reload()
    {
        $this->setup_config->ui->message(_("Now you should stop httpd server and start it again."));

        return;

        /* TODO:
         * We need derived, distro related classes, like midgard_setup_vhost_debian.
         * We need factory method, which should basically invoke particular constructor.
         * Then we can ask if to restart apache and do it proper way. */

        /*
        $this->setup_config->ui->message(_("Please wait..."));
        
        $retval = 0;
        $cmd = MIDGARD_SETUP_APACHE_CMD . " -k graceful";
       
        midgard_setup_log::write("Executing '${cmd}'");
        system($cmd, $retval);

        if($retval != 0) 
        {
            $this->setup_config->ui->error(_("Can not continue due to previous httpd errors"));
        }

        $this->check_host();
        */
    }
}

?>
