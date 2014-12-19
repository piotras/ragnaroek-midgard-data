<?php

$midgard_configuration_file = "midgard";
$midgard_root_user = "admin";
$midgard_root_password = "password";
$old_guids_file = 'midgard_old_guids.txt';

$cnc = new midgard_connection();
$opened = $cnc->open($midgard_configuration_file);

if(!$opened)
    die("Couldn't open given configuration");

$user = midgard_user::auth($midgard_root_user, $midgard_root_password, null);

ini_set("memory_limit", -1);

if(!file_exists($old_guids_file))
    die("Can not read ${old_guids_file}");

$guids = file($old_guids_file, FILE_IGNORE_NEW_LINES);

if(empty($guids))
    die("${old_guids_file} do not contain guids");

$delay = 30;

echo "Did you backup your database? \n";
echo count($guids) . " objects will be removed in ${delay} seconds. \n";

for($i = 0; $i < $delay ; $i++) 
{
    echo ".";
    sleep(1);
}

foreach($guids as $guid) 
{
    try 
    {    
        $obj = midgard_object_class::get_object_by_guid($guid);
    } 
    catch (midgard_error_exception $e) {
        /* Do nothing */
        echo $guid . " " . midgard_connection::get_error_string() . "\n";
        continue;
    }

    echo $guid . " Removing " . get_class($obj) . "\n"; 
    $obj->purge();
}

?>
