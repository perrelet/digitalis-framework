<?php

namespace Digitalis;

use Digitalis\Field\Hidden;
use Digitalis\Field\Input;
use Digitalis\Field\Submit;
use Digitalis\Element\Table;

abstract class Commands_Page extends Admin_Sub_Page {

    protected $slug       = 'digitalis-commands';
    protected $parent     = 'tools.php';
    protected $title      = 'Commands';
    protected $menu_title = 'Commands';

    protected $commands   = [
        'clear_log' => 'Clear Debug Log',
        'php_info'  => 'PHP Info',
    ];

    protected $_commands;

    public function get_commands () {

        if (is_null($this->_commands)) {

            $this->_commands = [];

            if ($this->commands) foreach ($this->commands as $key => $command) {

                if (!is_array($command)) $command = ['name' => $command];

                $this->_commands[$key] = wp_parse_args($command, [
                    'name'     => 'Command',
                    'call'     => [$this, $key],
                    'fields'   => [],
                ]);

            }

            $this->commands = &$this->_commands;

        }

        return $this->commands;

    }

    protected function maybe_execute () {

        if (!$command = ($_GET['command'] ?? false)) return;

        foreach ($this->get_commands() as $key => $command) {

            if (!isset($_GET[$key])) continue;

            $args = [];

            if ($command['fields']) foreach ($command['fields'] as $field_name => $field_data) {

                if (isset($_GET[$field_name])) $args[$field_name] = $_GET[$field_name];

            }

            if (!$response = call_user_func_array($command['call'], $args)) $response = "The command returned an empty response.";

            $call = is_array($command['call']) ? get_class($command['call'][0]) . '::' . $command['call'][1] : $command['call'];

            echo "<div class='commands-output'>";
                echo "<pre class='command-args'><b>Command:</b> {$call}(" . implode(', ', $args). ");</pre>";
                echo "<pre class='command-response'><b>Response:</b>\n\n" . print_r($response, true) . "</pre>";
            echo "</div>";

            return true;

        }

    }

    public function callback () {
    
        echo "<h1>Commands</h1>";

        $this->maybe_execute();

        $rows = [];

        foreach ($this->get_commands() as $key => $command) {

            $fields = [
                [
                    'field' => Hidden::class,
                    'key'   => 'page',
                    'value' => $this->slug,
                ],
                [
                    'field' => Hidden::class,
                    'key'   => 'command',
                    'value' => 1,
                ],
                [
                    'field' => Hidden::class,
                    'key'   => $key,
                    'value' => 1,
                ],
            ];

            if ($command['fields']) foreach ($command['fields'] as $field) {
            
                if (!is_array($field)) $field = ['key' => $field];

                $fields[] = wp_parse_args($field, [
                    'field'       => Input::class,
                    'placeholder' => $key,
                ]);

            }

            $fields[] = [
                'field' => Submit::class,
                'text'  => 'Run',
            ];

            $rows[] = [
                $command['name'],
                Field_Group::render([
                    'tag'        => 'form',
                    'attributes' => [
                        '_target' => 'blank',
                    ],
                    'fields'     => $fields,
                ], false)
            ];

        }

        Table::render([
            'rows'    => $rows,
            'classes' => ['commands-table'],
            'first_row' => false,
            'first_col' => true,
        ]);

        ?><style><?= file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/commands.css');?></style><?php
    
    }

    //

    public function clear_log () {

        if (($path = ini_get('error_log')) && file_exists($path)) {

            unlink($path);

            return "Deleted: " . ini_get('error_log');

        } else {

            return "Error: Unable to locate the error log.";

        }

    }

    public function php_info () {

        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();
        
        $phpinfo = str_replace('body {',   '#phpinfo body {',   $phpinfo);
        $phpinfo = str_replace('a:link {', '#phpinfo a:link {', $phpinfo);
        $phpinfo = str_replace('pre {',    '#phpinfo pre {',    $phpinfo);

        //$phpinfo = str_replace('<style type="text/css">', '<style type="text/css"> #phpinfo {', $phpinfo);
        //$phpinfo = str_replace('</style>', '} </style>', $phpinfo);

        return "<div id='phpinfo'>{$phpinfo}</div>";

    }


}