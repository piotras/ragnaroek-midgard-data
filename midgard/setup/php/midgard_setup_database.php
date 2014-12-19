<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 */

require_once 'midgard_setup_ui_cli.php';

if (!defined('MIDGARD_SETUP_MYSQLADMIN_CMD'))
{
    require_once 'midgard_setup_globals.php';
}

class midgard_setup_database_exception extends Exception
{
    public function __construct($message, $code = 0) 
    {
        parent::__construct($message, $code);
    }
}

class midgard_setup_database 
{
    private $midgard_config;
    private $midgard_setup_config;
    private $config_name;
    private $connected;
    private $mysql_admin_name;
    private $mysql_admin_password;

    private $dir_xml;

    /**
     *
     * @param   midgard_config  $config 
     * @param   string          $config_name
     */
    public function __construct(midgard_setup_config &$setup_config, $config_name = NULL)
    {
        midgard_setup_log::write(_("Initialize new ").__CLASS__);

        $this->setup_config = &$setup_config;
        $config = &$setup_config->midgard_config;
        
    	if ($config->database == '') 
        {
            $msg = _("Database name not defined in midgard_config");
            midgard_setup_log::write_exception($msg);
            throw new midgard_setup_database_exception($msg, 0);
        }

        if ($config->host == '')
        {
            $msg = _("Database host not defined in midgard_config");
            midgard_setup_log::write_exception($msg);
            throw new midgard_setup_database_exception($msg, 0);
        }
        
        if ($config->dbuser == '')
        {
            $msg = _("Database username not defined in midgard_config");
            midgard_setup_log::write_exception($msg);
            throw new midgard_setup_database_exception($msg, 0);
        }
    
        if ($config->dbpass == '')
        {
            $msg = _("Database password not defined in midgard_config");
            midgard_setup_log::write_exception($msg);
            throw new midgard_setup_database_exception($msg, 0);
        }

        $this->midgard_config = &$config;
        $this->config_name = $config_name;

        $this->dir_xml = MIDGARD_SETUP_DIRECTORY_USR . "/share/midgard/setup/xml/import";
    }    

    private function warn_ifnotdir($warn)
    {
        midgard_setup_ui_cli::warning(_("Can not create directory") . $warn);
        return FALSE;
    }

    private function warn_ifnotch($type, $warn)
    {
        midgard_setup_ui_cli::warning(_("Can not change permission") . $type . " on " . $warn);
        return FALSE;
    }

    /**
     * @todo use midgard_config::create_blobdir() instead
     */
    public function create_database_blobdir()
    {
        midgard_setup_log::write(_("Check database blobs' directories"));

        $dirs = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
        $subdirs = $dirs;

        $old_umask = umask();
        umask(006);

        /* check if midgard directory itself exists, if not , create it */
        $midgard_dir = MIDGARD_SETUP_DIRECTORY_VAR."/lib/midgard";
        if (!is_dir($midgard_dir))
        {
            if (!mkdir($midgard_dir, 0711, TRUE))
            {
                umask($old_umask);
                return self::warn_ifnotdir($midgard_dir);
            }
        }

        /* check root directory for all blobs */
        $blobdir_root = $midgard_dir."/blobs";
        if (!is_dir($blobdir_root))
        {
            if (!mkdir($blobdir_root, 0711, TRUE))
            {
                umask($old_umask);
                return self::warn_ifnotdir($blobdir_root);
            }
        }

        $blobdir_db = $blobdir_root."/".$this->midgard_config->database;

	    if (!is_dir($blobdir_db))
    	{
		    if (!mkdir($blobdir_db, 0711, TRUE))
    		{   
                umask($old_umask);
	    		return self::warn_ifnotdir($blobdir_db);
		    }
	    }

        foreach ($dirs as $dir)
        {
            $blobdir = $blobdir_db."/".$dir;

            if (!is_dir($blobdir))
            {
                if (!mkdir($blobdir, 0771, TRUE)) 
                {   
                    umask($old_umask);
                    return self::warn_ifnotdir($blobdir);
                }
            }
                
            if (!chown($blobdir, MIDGARD_SETUP_APACHE_USER))
            {
                return self::warn_ifnotch("owner", $blobdir);
            }

            if (!chgrp($blobdir, MIDGARD_SETUP_APACHE_GROUP))
            {
                return self::warn_ifnotch("group", $blobdir);
            }
            
            foreach($subdirs as $subdir)
            {
                $subblobdir = $blobdir."/".$subdir;
                
                if (!is_dir($subblobdir))
                {
                    if (!mkdir($subblobdir, 0771, TRUE))
                    {
                        umask($old_umask);
                        return self::warn_ifnotdir($subblobdir);
                    }
                }
                
                if (!chown($subblobdir, MIDGARD_SETUP_APACHE_USER))
                {
                    return self::warn_ifnotch("owner", $subblobdir);
                }
                
                if (!chgrp($subblobdir, MIDGARD_SETUP_APACHE_GROUP))
                {
                    return self::warn_ifnotch("group", $subblobdir);
                }
            }
        }

        umask($old_umask);
        return TRUE;
    }

    final public function set_admin_name($name)
    {
        $this->mysql_admin_name = $name;
    }

    final public function create_database()
    {
        if (!isset($this->mysql_admin_name))
        {
            $value = midgard_setup_ui_cli::get_text(_("What is MySQL admin username?"), "root");
            
            if(    !$value 
                && $value == '')
            {
                $value = 'root';
            }
                
            midgard_setup_ui_cli::message(_("Preparing to create database. You will be asked for password if needed."));
        } 
        else 
        {    
            $value = $this->mysql_admin_name;
            midgard_setup_ui_cli::message(_("Preparing to create database with default admin account ") . "'{$value}'");
        }

        /* Try to create database and grant privileges in one run.
         * This way we avoid asking for MySQL's admin password twice */
        $_mcmd = MIDGARD_SETUP_MYSQL_CMD;
        $_mcmd .= " --host {$this->midgard_config->host}";
        
        $mysqlcmd = $_mcmd . " --user {$value} -p ";
        $mysqlcmdnopass = $_mcmd . " --user {$value} ";

        /* Ensure database doesn't exist before trying to create it. */
        $output = array();
        $retval = 0;
        
        $checkdb_cmd = " -e \"SELECT IF(EXISTS (SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$this->midgard_config->database}'), 'Yes','No');\"";
        $cmd = $mysqlcmdnopass . $checkdb_cmd;

        exec($cmd, $output, $retval);
        unset($cmd);

        if ($output[1] === "Yes") 
        {
            midgard_setup_ui_cli::message(_("Configured database exists. Skipping create."));    
            return;
        }
        
        /* Create database */
        $sqlcmd = " -e \"CREATE DATABASE {$this->midgard_config->database} CHARACTER SET utf8;";
    	
        /* Grant all privileges */
        $sqlcmd .= " GRANT all ON {$this->midgard_config->database}.* ";
        $sqlcmd .= " to '{$this->midgard_config->dbuser}'@'{$this->midgard_config->host}'";
        $sqlcmd .= " identified by '{$this->midgard_config->dbpass}';";

        /* flush privileges */
        $sqlcmd .= " FLUSH PRIVILEGES \"";

        $cmd = $mysqlcmdnopass . $sqlcmd;

        $output = array();
    	$retval = 0;
	
        /* Try to execute command without password */
        exec($cmd, $output, $retval);
        unset($cmd);

        /* Probably command failed because we didn't use password.
         * Let's try again with password.
         * 
         * WARNING! 
         * http://php.net/manual/en/function.exec.php
         * $output in my case is *always* an empty array
         */
        if ($retval != 0)
        {
            unset($output);
            $output = array();
            $retval = 0;

            $cmd = $mysqlcmd . $sqlcmd; 

            exec($cmd, $output, $retval);
        }

    	if ($retval != 0) 
        {
            midgard_setup_ui_cli::error(_("Couldn't create {$this->midgard_config->database} database"));
        }

        /* Connect to midgard database */      
        $_cnf_name = "";
        $this->config_name == NULL 
            ?  $_cnf_name = $this->midgard_config->database 
                : $_cnf_name = $this->config_name;

        $midgard = new midgard_connection();
        
        /* connect */
        midgard_setup_ui_cli::message(_("Creating database connection"));
        if (!$midgard->open($_cnf_name)) 
        {
            midgard_setup_ui_cli::error(_("Couldn't connect to Midgard database"));
        }

        /* Create internal tables */
        if (!midgard_config::create_midgard_tables()) 
        {
            midgard_setup_ui_cli::error(_("Couldn't create internal tables"));
        }
        
        /* I am not sure how to handle errors here */
        $msg = _("Creating database ") . $this->midgard_config->database . _(", please wait...");
        midgard_setup_ui_cli::message($msg);
        self::update_database();
        midgard_setup_ui_cli::message(_("Successfully created database ") . $this->midgard_config->database);

        $user = midgard_connection::get_user();

        if (   !$user 
            || !$user->is_root())
        {
            unset($user);
            $user = midgard_user::auth("root", "password", null);
        }

        if (!$user) 
        {
            midgard_setup_ui_cli::warning(_("Could not login to midgard database!"));
            midgard_setup_ui_cli::error(_("Can not import required xml packages"));
        } 
        else 
        { 
            if ($user->is_root())
            {
                $root_login = $this->setup_config->midgard_config->midgardusername;
                $root_pass  = $this->setup_config->midgard_config->midgardpassword;
                $user->password($root_login, $root_pass);
            }

            midgard_setup_ui_cli::message(_("Importing all required xml packages"));
            
            /* midgard_language */
            $filepath = $this->dir_xml . "/midgard_languages.xml";
            $xml = file_get_contents($filepath);
            /* unfortunatelly we can not depend on returned value :/ */
            $rv = midgard_replicator::import_from_xml($xml);
        }
    }

    private function remove_duplicated_indexes()
    {
        $mysqlcmd = MIDGARD_SETUP_MYSQL_CMD;
        $mysqlcmd .= "  --host {$this->midgard_config->host} --user={$this->midgard_config->dbuser} --password={$this->midgard_config->dbpass} -D {$this->midgard_config->database}";

        $tables = explode("\n", `$mysqlcmd -e 'show tables' | grep -Ev 'Tables_in_.+'` );
        foreach ($tables as $table) 
        {
            $table = trim($table);
            if (empty($table))
            {
                continue;
            }

            $keylines = explode("\n", `$mysqlcmd -e "show keys from {$table}" | grep -Ev 'Key_name'`);

            foreach ($keylines as $keyline)
            {
                $keyline = trim($keyline);
                if (empty($keyline))
                {
                    continue;
                }

                $regex =  "/{$table}\t[0-1]\t(\w+)\t/";
                if (!preg_match($regex, $keyline, $matches))
                {
                    midgard_setup_ui_cli::warning("Could not match '$regex' in \n===\n{$keyline}\n===\n");
                    continue;
                }

                $key_name =& $matches[1];
                if ($key_name == 'PRIMARY')
                {
                    continue;
                }
                
                if (!preg_match('/_([2-9]|[1-9][0-9]+)$/', $key_name))
                {
                    continue;
                }
                
                midgard_setup_ui_cli::message("Found (likely) duplicate key '{$table}.{$key_name}', removing");
                $cmd = "$mysqlcmd -e \"ALTER TABLE $table DROP KEY {$key_name}\"";
                system($cmd);
            }
        }
    }

    final public function update_database()
    {
        midgard_setup_log::write(_("Updating database"));
        
        if(!is_array($_MIDGARD['schema']['types'])) 
        {    
            midgard_setup_ui_cli::warning(_("No midgard classes"));
            return FALSE;
        }
        
        if(count($_MIDGARD['schema']['types']) == 0) 
        {    
            midgard_setup_ui_cli::warning(_("No midgard classes"));
            return FALSE;
        }

        midgard_setup_ui_cli::message(_("Looking for duplicated indexes.") . " " .  _("Please wait..."));
        $this->remove_duplicated_indexes();

        foreach($_MIDGARD['schema']['types'] as $class_name => $val) 
        {
            if(!midgard_config::class_table_exists($class_name))
            {
                midgard_setup_ui_cli::message(_("Creating storage for: ") . "'{$class_name}'");
                if(!midgard_config::create_class_table($class_name))
                    midgard_setup_ui_cli::warning(_("Storage was not created for: ") . "'{$class_name}'");
            }
            midgard_setup_ui_cli::message(_("Updating storage for: ") . "'{$class_name}'");
            if (!midgard_config::update_class_table($class_name))
                midgard_setup_ui_cli::warning(_("Storage was not updated for: ") . "'{$class_name}'");
        }

        return TRUE;
    }

    final public function import_sql_file($path)
    {
        if(!is_string($path))
            return FALSE;
        
        $sql_content = file_get_contents($path);
        
        midgard_setup_database::import_sql_query($sql_content);

        return TRUE;
    }

    final public function import_sql_query($sql)
    {
        if(!is_string($sql))
            return FALSE;

        $cmd = "midgard2-query";
        $cmd .= " -c {$this->config_name} ";
        $cmd .= " -q \"{$sql_content}\" ";
        
        $output = 0;
        $retval = array();
        
        exec($m, $output, $retval);

        if ($retval != 0)
        {
            midgard_setup_ui_cli::error(_("Couldn't import sql"));
        }

        return TRUE;
    }
}
?>
