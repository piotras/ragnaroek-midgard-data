<?php

require_once 'midgard_setup_ui.php';

class midgard_setup_ui_midcom_exception extends Exception 
{
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function message()
    {
        $msg = "{$this->message} ( {$this->file}:{$this->line} )";
        midgard_setup_log::write_exception($msg);
        parent::getMessage();
    }
}

class midgard_setup_ui_midcom extends midgard_setup_ui
{
    public $exception;

    public function __construct()
    {
        parent::__construct();

        $this->exception = "midgard_setup_ui_web_exception";

    }

    static public function get_text($question, $default = "", $available = "")
    {
        /* TODO */
        return null;
    }

    static public function get_boolean($question, $default = "y")
    {
        /* TODO */
        return false;
    }
    
    static public function get_password($question)
    {
       /* TODO */
       return null;
    }

    static public function monty()
    {
        echo "<br />< a href='http://www.sacred-texts.com/neu/mphg/mphg.htm#Scene%2035'>Did you mean this?</a> <br />";    
    }

    static public function message($text)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add($text, MIDCOM_LOG_INFO);
        debug_pop();

        $_MIDCOM->uimessages->add("Midgard Setup", $text, 'info');
    }

    static public function warning($text)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add($text, MIDCOM_LOG_WARN);
        debug_pop();

        $_MIDCOM->uimessages->add("Midgard Setup", $text, 'warning');
    }

    static public function error($text)
    {
        debug_push_class(__CLASS__, __FUNCTION__);
        debug_add($text, MIDCOM_LOG_ERROR);
        debug_pop();

        $_MIDCOM->generate_error(MIDCOM_ERRCRIT, $text);
        // This will exit
    }
}
?>
