<?php

require_once 'midgard_setup_vhost.php';
require_once 'midgard_setup_config.php';
require_once 'midgard_setup_vhost_config.php';
require_once 'midgard_setup_log.php';
require_once 'midgard_setup_pear.php';
require_once 'midgard_quick_setup.php';

$mqs = new midgard_quick_setup();
$config = &$mqs->midgard_config;
$config->midgardusername = "admin";
$config->midgardpassword = "password";
$config->save_file("midgard");
$mqs->install_database();
$mqs->install_pear();
$mqs->set_vhost("www.midgard-project.org");
$mqs->install_vhost();

?>
