<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
require_once 'midgard_setup_globals.php';

class midgard_setup_vhost_config 
{
    private $directives;
    private $directories;
    private $name;
    private $port;

    function __construct($name, $port = '80')
    {
        $this->name = $name;
        $this->port = $port;

        $this->directives = array();
        $this->directories = array();
        $this->locations = array();

        $this->add_directive("ServerName", array($name));
        $this->add_directive("AddDefaultCharset", array("utf-8"));
        $this->add_directive("RLimitCPU", array("20", "60"));
        $this->add_directive("RLimitMem", array("67108864", "134217728"));
    }

    public function add_directory($directory, array $options)
    {
        $this->directories[$directory] = $options;
    }

    public function add_directive($directive, array $options)
    {
        $this->directives[][$directive] = $options;
    }

    public function add_location($location, array $options)
    {
        $this->locations[$location] = $options;
    }

    private function format_block($tag, array $params)
    {
        $block = "";

        if(count($params) == 0)
            return $block;

        foreach ($params as $tag_prop => $value)
        { 
            $block .= "<{$tag} {$tag_prop}>\n";

            foreach($value as $line)
            {
                $block .= "\t$line\n";
            }

            $block .= "</{$tag}>\n\n";
        }

        $block .= "\n\n";

        return $block;
    }

    private function format_line($dir, array $params)
    {
        $block = "{$dir} ";
       
        foreach($params as $value)
        {
            $block .= "{$value} ";
        }

        $block .= "\n";

        return $block;
    }

    public function get_configuration()
    {
        if (count($this->directives) == 0)
        {
            return NULL;
        }

        if (count($this->directories) == 0)
        {
            return NULL;
        }

        $config = "\n# This configuration is created by midgard_setup\n\n";
        $config .= "<VirtualHost *:{$this->port}>\n";

        /* Add directives */
        $config .= "\n# DIRECTIVES #\n";
       
        foreach($this->directives as $key => $directive_array) 
        {
            foreach($directive_array as $dir => $params)
            {
                $config .= self::format_line($dir, $params);
            }
        }

        /* Add directories */
        $config .= "\n# DIRECTORIES #\n";
        $config .= self::format_block("Directory", $this->directories);

        /* Add locations */
        $config .= "\n# LOCATIONS #\n";
        $config .= self::format_block("Location", $this->locations);

        $config .= "</VirtualHost>\n";

        return $config;
    }

    public function get_directory($name)
    {   
        /* TODO */
    }

    public function get_directive($name)
    {
        /* TODO */
    }
}

?>
