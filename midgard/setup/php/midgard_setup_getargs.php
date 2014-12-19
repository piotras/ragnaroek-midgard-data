<?php

require_once 'Console/Getargs.php';

class midgard_setup_getargs
{
    protected $setup_types = array('wizard', 'w', 'quick', 'q');
    
    protected $setup_actions = array('install', 'upgrade', 'vhost', 'midcom3vhost', 'dbinstall', 'dbupdate', 'pear', 'sitegroup', 'config-set');

    protected $all_args = array();

    protected $args;

    function __construct()
    {
        /* Setup type. Quick or interactive one */
        $this->all_args['type'] = array (
            'short' => 't',
            'max'   => 1,
            'min'   => 0,
            'desc'  => 'The type of installation: ' . implode(", ",$this->setup_types),
            'default' => 'wizard',
        );

        /* Action to perform. Install, update, install pear packages, etc */
        $this->all_args['action'] = array (
            'short' => 'a',
            'max'   => 1,
            'min'   => 0,
            'desc'  => 'Setup action to perform: ' . implode(", ",$this->setup_actions),
            'default' => 'install',
        );

        /* Configuration name , by default we should keep config file named from database name */
        $this->all_args['configuration'] = array (
            'short' => 'c',
            'max'   => 1,
            'min'   => 0,
            'desc'  => 'Midgard configuration name.',
            'default' => 'midgard',
        );

        /* Set configuration key's value */
        $this->all_args['config-set'] = array (
            'short' => 's',
            'max'   => 2,
            'min'   => 2,
            'desc'  => 'Sets key value for the named configuration.',
            'default' => ""
        );

        /* Set verbose level */
        $this->all_args['verbose'] = array (
            'short' => 'v',
            'max'   => 1,
            'min'   => 0,
            'desc'  => 'Sets verbose level.',
            'default' => 0
        );

        /* Package (application/component) name(s) */
        $this->all_args['package|application|component'] = array (
            'short' => 'p',
            'max'   => -1,
            'min'   => 1,
            'desc'  => 'Midgard package (application/component) name(s)',
            'default' => ""
        );

        $this->args = Console_Getargs::factory($this->all_args);
        if (PEAR::isError($this->args))
        {
            midgard_setup_ui_cli::args_error($this->all_args, $this->args);
        }

        /* Check if user's values are allowed.
         I have no idea if Console_Getargs supports such feature.
         Also, we need to get defaults even if they are absent. */
        $arg_type = $this->get_setup_type();
        if($arg_type != "" && !in_array($arg_type, $this->setup_types))
            midgard_setup_ui_cli::error("'{$arg_type}'" . _(" is not supported type of installation"));

        $arg_action = $this->get_setup_action();
        if($arg_action != "" && !in_array($arg_action, $this->setup_actions))
            midgard_setup_ui_cli::error("'{$arg_action}'" . _(" is not supported type of action")); 

        $arg_config_set = $this->get_config_set();
        if (!empty($arg_config_set) && $arg_action != "config-set")
            midgard_setup_ui_cli::error(_("Argument '-s / --config-set' requires action to be 'config-set'"));
    }

    protected function get_arg_value($name = null)
    {
        if($name == null)
        {
            return "";
        }

        return $this->args->getValue($name);
    }

    function get_argstr($skip_action = false)
    {
        $arg_action = $this->get_setup_action();
        $argstr = "";
        $skipping_action = false;
        foreach ($_SERVER["argv"] as $i => $arg) {
            if ($i == 0) continue;
            if ($skip_action) {
                if ($skipping_action) {
                    $skipping_action = false;
                    continue;
                }
                if ($arg == "--action" || $arg == "-a") {
                    $skipping_action = true;
                    continue;
                }
            }
            $argstr .= " " . escapeshellarg($arg);
        }
        return $argstr;
    }

    function get_setup_type()
    {
        $type = $this->args->getValue('type');

        if($type == "")
        {
            $type = "wizard";
        }

        return $type;
    }

    function get_setup_action()
    {
        $action = $this->args->getValue('action');

        if($action == "")
        {
            $action = "install";
        }

        return $action;
    }

    function get_debug_level()
    {
        return $this->args->getValue('verbose');
    }

    function get_config_name()
    {
        $config = $this->args->getValue('configuration');

        if($config == "")
        {
            $config = "midgard";
        }

        return $config;
    }

    function get_config_set()
    {
        return $this->args->getValue('config-set');
    }

    function get_package()
    {
        return $this->args->getValue('package');
    }
}

?>
