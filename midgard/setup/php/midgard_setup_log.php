<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

require_once 'midgard_setup_globals.php';

global $user_log_file;
$user_log_file = FALSE;

class midgard_setup_log
{
    public function __construct($log_file)
    {
        global $user_log_file;
        $user_log_file = $log_file;
    }

    private function logdate()
    {
        return date("o-m-d H:i:s");
    }

    private function format_log($message)
    {
        $content = "midgard-setup ";
        $content .= self::logdate()." ";
        $content .= "(pid:".getmypid().")";
        $content .= " - ";
        $content .= $message."\n";

        return $content;
    }

    public function write($message)
    {
        global $user_log_file;

        if(!$user_log_file)
            $log_file = MIDGARD_SETUP_LOG_FILE;
        else 
            $log_file = &$user_log_file;

        /* Check if file exists, and create it if needed */
        if(!file_exists($log_file))
        {
            $log_directory = dirname($log_file);
            if(!is_dir($log_directory))
            {
                if(!mkdir($log_directory, 0711, TRUE))
                {
                    echo "Can not create log directory '".$log_directory."' \n";
                    exit;
                }   
            }
            
            if(!touch($log_file))
            {
                echo "Can not create log file '".$log_file."' \n";
                exit;
            }
        }

        $fh = fopen($log_file, "a");

        if(!$fh)
        {
            /* FIXME */
            echo "Can not open log file \n";
            exit;
        }
        
        $content = self::format_log($message);

        if(!fwrite($fh , $content)) 
        {
            /* FIXME */
            echo "Can not write log messages \n";
            exit;
        }

        fclose($fh);
    }

    public function write_exception($message)
    {
        $exception_message = "Exception thrown: ".$message;
        self::write($exception_message);
    }
}

?>
