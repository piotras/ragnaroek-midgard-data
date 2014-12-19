<?php

class midgard_admin_sitewizard_exception extends Exception 
{
    private $error_message;

    public function __construct($message = '')
    {
        $this->error_message = $message;
    }

    public function error()
    {
        echo "Error: " . $this->error_message . "\n";
    }
}

?>
