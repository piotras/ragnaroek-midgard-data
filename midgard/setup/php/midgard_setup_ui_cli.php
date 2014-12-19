<?php

require_once 'midgard_setup_ui.php';

class midgard_setup_cli_exception extends Exception 
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

class midgard_setup_ui_cli extends midgard_setup_ui
{
    public $exception;

    public function __construct()
    {
        parent::__construct();

        $this->exception = "midgard_setup_cli_exception";

        /* TODO, print this in debug mode */
	    /* midgard_setup_log::write(_("Initialize new ").__CLASS__); */
    }

    static public function get_text($question, $default = "", $available = "")
    {
        $qs = "{$question} (default: '{$default}')";
        
        if($available != "")
        {
            $qs .= "[ " . _("Available: ") . $available . "]";
        }
        
        $qs .= ": ";

        $qlen = strlen($qs);
        $qlen = $qlen - 26;

        fwrite (STDOUT, "# MIDGARD SETUP QUESTION ");

        for ($i = 0; $i < $qlen; $i++)
        {
            fwrite (STDOUT, "#");
        }

        fwrite (STDOUT, "\n");

        fwrite(STDOUT, "${qs} \n");
        
        $value = fgets(STDIN); 

        if(($value == "\n" || $value == "") && $default != "")
        {
            $value = $default;
        }

        return trim($value);
    }

    static public function get_key_from_list($question, $list = array(), $ignore_empty = true)
    {
        $selection = "";

        if (empty($list))
        {
            $selection = "No select list has been provided \n";
        }
        else {

            foreach ($list as $k => $v)
            {
                $selection .= " [{$k}]: {$v} \n";
            }
        }
        
        $qs = $question . ": \n";
        $qs .= $selection;

        fwrite (STDOUT, $qs);

        $value = rtrim(fgets(STDIN));

        if (!$ignore_empty)
        {
            if (!array_key_exists($value, $list))
            {
                self::warning($value . " " . _("Doesn't exist in given list."));
                self::get_key_from_list($question, $list, $ignore_empty);
            }
        }

        return $value;
    }

    static public function get_boolean($question, $default = "y")
    {
        fwrite(STDOUT, "{$question} (default: '{$default}'): \n");
        fwrite(STDOUT, " y: Yes \n");
        fwrite(STDOUT, " n: No \n");
        fwrite(STDOUT, " q: Quit \n");
        
        do {

            do {
            
                $answer = fgets(STDIN);
            
            } while ( trim($answer) == '' );
            
            $selected = trim(strtolower($answer));
        
            if($selected == 'n' || $selected == 'y') {
        
                return $selected;
        
            } else {
            
                if($selected == 'q')
                    return NULL;
                
                fwrite(STDOUT, "Please, select 'y' or 'n' \n");
            }

        } while($answer != 'q');
    }
    
    static public function get_password($question)
    {
        fwrite(STDOUT, "This is not secure!");
        fwrite(STDOUT, "{$question}\n");
         
        $value = fgets(STDIN);
       
        return $value;
    }

    static public function monty()
    {
        fwrite(STDOUT, "What Is Your Favourite Colour?\n");
        sleep(1);
        fwrite(STDOUT, "Red");
        sleep(1);
        fwrite(STDOUT, "...No! No! Blue! \n");
        sleep(1);
        fwrite(STDOUT, "a\n a\n  a\n   a\n    a\n     a\n     \n");
        exit;
    }

    static public function message($text, $ignore_prefix = false, $newline = true)
    {
    	midgard_setup_log::write($text);
        $ignore_prefix ? $prefix = "" : $prefix = "MIDGARD_SETUP:";
        $newline ? $nl = "\n" : $nl = "";
        fwrite(STDOUT, $prefix . " $text {$nl}");
    }

    static public function warning($text)
    {
    	midgard_setup_log::write("\n ! WARNING ! {$text}");
        fwrite(STDOUT, "\nMIDGARD SETUP: ! WARNING ! : {$text} \n");
    }

    static public function error($text)
    {
    	midgard_setup_log::write("ERROR {$text} \n SETUP FAILED");
        fwrite(STDOUT, "\nMIDGARD SETUP ERROR: {$text} \nQuitting...\n");
        exit(1);
    }

    static private function help($config) {
        fwrite(STDOUT, Console_Getargs::getHelp($config)."\n");
        fwrite(STDOUT, "Try `man ".basename($_SERVER["PHP_SELF"])."' for more information.\n");
    }

    static public function args_error($config, $args)
    {
        if ($args->getCode() === CONSOLE_GETARGS_ERROR_USER) {
            fwrite(STDOUT, basename($_SERVER["PHP_SELF"]).": ".$args->getMessage()."\n");
            midgard_setup_ui_cli::help($config);
            exit(1);
        } else if ($args->getCode() === CONSOLE_GETARGS_HELP) {
            midgard_setup_ui_cli::help($config);
            exit;
        }
        midgard_setup_ui_cli::error($args->getMessage());
    }
}
?>
