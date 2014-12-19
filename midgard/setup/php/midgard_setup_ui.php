<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 */

abstract class midgard_setup_ui
{
    public $extension;
    public $log;

    public function __construct() 
    {
        /* Do nothing */
    }

    static public function get_text($question, $default = "", $available = "")
    {
    
    }

    static public function get_boolean($question, $default = "y")
    {
   
    }

    static public function get_password($question)
    {

    }

    static public function monty()
    {
       
    }

    static public function message($text)
    {
    	
    }

    static public function warning($text)
    {
    	
    }

    static public function error($text)
    {
    	
    }
}
?>
