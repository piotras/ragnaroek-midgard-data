<?php

require_once 'midgard_setup_cron.php';

try
{
    // Start a database connection
    $msc = new midgard_setup_cron();
}
catch (Exception $e)
{
    midgard_setup_ui_cli::warning("Failed to set up MidCOM cron connection: " . $e->getMessage());
}

// Run the crons
$msc->execute();
?>
