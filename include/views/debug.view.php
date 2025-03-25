<?php

namespace Digitalis {

    class Debug extends View {

        protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/debug/";

        protected static $defaults = [
            'values'    => [],
            'arg_names' => [],
        ];

        public static function write (...$args) {

            $debug  = new Debug;
            $values = [];
        
            foreach ($args as $i => $arg) if ($arg instanceof Debug_Options) {

                $debug->set_options($arg);

            } else {

                $values[$i] = $arg;

            }

            $debug->set_param('values', $values);
            $debug->print();

        }

        //

        protected static function console ($value, $options = []) {

            $options = wp_parse_args($options, [
                'method' => 'debug',
                'label'  => false,
                'style'  => false,
            ]);

            $options['label'] = str_replace("\\", "\\\\", $options['label']);
            $options['style'] = str_replace("\\", "\\\\", $options['style']);

            if ($options['style'] == 'label') $options['style'] = 'color: #ccc; font-size: 0.8em;';

            if (is_scalar($value)) {
                $value = str_replace("\\", "\\\\", $value);
                $value = $options['style'] ? "'%c{$value}'" : "'{$value}'";
            } else {
                $value = json_encode($value);
            }

            $params = [];
            if ($options['label']) $params[] = "`{$options['label']}`";
            if ($value)            $params[] = $value;
            if ($options['style']) $params[] = "`{$options['style']}`";
            $params = implode(', ', $params);

            return "<script>console.{$options['method']}({$params});</script>";
        
        }

        //

        protected $debug_options;

        public function print ($return = false) {

            if (is_null($this->debug_options)) $this->debug_options = new Debug_Options;

            return parent::print($return);

        }

        public function set_options (Debug_Options $options) {
        
            $this->debug_options = $options;
        
        }

        public function get_options () {
        
            return $this->debug_options;
        
        }

        public function condition () {

            return current_user_can('administrator');

        }
    
        public function get_template () {
    
            switch ($this['view']) {
    
                case 'debugger':
    
                    static $debugger_rendered;

                    if (!$debugger_rendered) {

                        echo "<style>" . file_get_contents(DIGITALIS_FRAMEWORK_PATH . 'assets/css/debugger.css') . "</style>";
                        echo "<script>" . file_get_contents(DIGITALIS_FRAMEWORK_PATH . 'assets/js/debugger.js') . "</script>";

                    } else if ($this['append']) {

                        return null;

                    }

                    $debugger_rendered = true;

                    return 'debugger';

    
            }
            
            return null;
        
        }

        public function params (&$p) {

            $p = array_merge($p, (array) $this->get_options());
    
            $p['backtrace'] = debug_backtrace(0, $p['backtrace_limit']);
            $offset         = 0;

            foreach ($p['backtrace'] as $i => $frame) {

                if (!isset($frame['file'])) continue;
                if (strpos($frame['file'], DIGITALIS_FRAMEWORK_PATH) !== false) continue;

                $offset = $i;
                break;

            }

            $p['backtrace']  = array_slice($p['backtrace'], $offset);
            $p['debug_path'] = $p['backtrace'][0]['file'] ?? '';
            $p['debug_line'] = $p['backtrace'][0]['line'] ?? 0;
            $p['debug_func'] = $p['backtrace'][0]['function'] ?? 0;
            $p['debug_file'] = basename($p['debug_path']);

            if ($p['view'] == 'debugger') {

                $p['bt_html']    = '';

                foreach ($p['backtrace'] as $i => &$frame) {
    
                    if (isset($frame['file'])) $p['bt_html'] .= "<line trace-file data-line='" . ($i + 1) . "'>{$frame['file']}:" . ($frame['line'] ?? '??') . "</line>";
    
                    if (isset($frame['class'])) {
                        $p['bt_html'] .= "<line><span data-type='object'>{$frame['class']}</span>{$frame['type']}<span data-type='function'>{$frame['function']}(</span></line>";
                    } else {
                        $p['bt_html'] .= "<line><span data-type='function'>{$frame['function']}(</span></line>";
                    }
    
                    if (isset($frame['args'])) foreach ($frame['args'] as $j => $arg) {
    
                        $type = gettype($arg);
    
                        if (is_scalar($arg)) {
    
                            if (is_string($arg)) {
                                $name = "\"{$arg}\"";
                            } else {
                                $name = $arg;
                            }
    
                            $p['bt_html'] .= "<line>  <span data-type='{$type}'>{$name}</span></line>";
    
                        } else {
    
                            $p['bt_html'] .= $this->wrap_lines($arg, [
                                'open'   => false,
                                'indent' => 1,
                                'expand' => 'print_r',
                                'type'   => $type,
                            ]);
    
                        }
    
                    }
    
                    $p['bt_html'] .= "<line><span data-type='function'>)</span>;</line>";
                    $p['bt_html'] .= "<line> </line>";
    
                    continue;
    
                }

            }

            $this->extract_arg_names($p);
            
            if (!$p['title']) $p['title'] = $p['debug_file'] . "::" . $p['debug_line'];
        
            //

            foreach ($p['values'] as &$value) {

                if (($p['view'] != 'js')) {
        
                    ob_start();
                    @$p['expand']($value); 
                    $value = ob_get_contents();
                    ob_end_clean();
        
                }

                if ($p['view'] == 'debugger') {

                    $value = $this->wrap_lines($value, [
                        'expand' => $p['expand'],
                    ]);
                
                }

            }
        
        }

        protected function extract_arg_names (&$p) {

            if ($p['debug_path'] && $p['debug_line'] && $p['debug_func'] && $file = @file($p['debug_path'])) {

                $start = $p['debug_line'] - 1;
                $end   = $start;

                for ($i = $start; $i < count($file); $i++) if (strpos($file[$i], ';') !== false) { $end = $i; break; }

                $file = array_slice($file, $start, $end - $start + 1, false);
                $file = implode($file);
                $file = substr($file, strpos($file, $p['debug_func']));
                $lvl  = 0;
                $args = [''];
                $arg  = 0;

                for ($i = 0; $i < strlen($file); $i++) {

                    $c = $file[$i];

                    if (in_array($c, ['(', '['])) { $lvl++; continue; }
                    if (in_array($c, [')', ']'])) { $lvl--; continue; }

                    if ($lvl != 1) continue;

                    if ($c == ',') {
                            
                        $arg++;
                        $args[$arg] = '';
                        continue;
                    
                    }

                    $args[$arg] .= $c;

                }

                $args  = array_map('trim', $args);
                $names = [];

                foreach ($args as $i => $arg) {

                    if (!isset($p['values'][$i])) continue;

                    $value     = $p['values'][$i];
                    $type      = $this->get_type($value, $p['view'] == 'debugger');
                    $names[$i] = (($arg[0] ?? '') == '$') ? "{$arg} ({$type})" : $type;

                }

                $p['arg_names'] = $names;

            }

        }

        protected function get_type ($value, $html = true) {

            $type = gettype($value);
            $name = $type;

            if ($type == 'object') {

                $name = $value::class;
                if ($parent = get_parent_class($value)) $name .= " extends {$parent}";

            } elseif ($type == 'array') {

                $name .= "[" . count($value) . "]";

            } elseif ($type == 'string') {

                $name .= "[" . strlen($value) . "]";

            }

            return $html ? "<span data-type='{$type}'>{$name}</span>" : $name;
        
        }

        protected function wrap_lines ($value, $options = []) {

            $options = wp_parse_args($options, [
                'open'   => true,
                'indent' => 0,
                'expand' => 'print_r',
                'type'   => null,
            ]);

            ob_start();
            @$options['expand']($value); 
            $value = ob_get_contents();
            ob_end_clean();

            $indent = match ($options['expand']) {
                'var_export' => 2,
                'var_dump'   => 4,
                'print_r'    => 8,
                default      => 8,
            };
        
            $lines = array_values(array_map('htmlentities', array_filter(explode("\n", $value))));
            $next = -1;
            $html = '';

            foreach ($lines as $i => $line) {

                if ($options['indent']) $line = str_pad('', 2 * $options['indent'], ' ', STR_PAD_LEFT) . $line;

                $level = $next;
                $next  = (count($lines) > $i + 1) ? floor(strspn($lines[$i + 1], ' ') / $indent) : -1;
                $n     = count($lines) > 1 ? $i + 1 : '>';

                $html .= "<line data-level='{$level}' data-line='{$n}'";
                if ($level < $next)   $html .= " onclick='this.nextElementSibling.toggleAttribute(`open`)'";
                if ($options['type']) $html .= " data-type='{$options['type']}'";
                $html .= ">{$line}</line>";

                if ($level < $next) $html .= "<lines" . ($options['open'] ? ' open' : '') . ">";
                if ($level > $next) $html .= "</lines>";

            }

            return $html;
        
        }

        public function view () {
        
            switch ($this['view']) {

                case 'debugger':

                    $html = '';

                    foreach ($this['values'] as $i => $value) $html .= Debug_Code_Block::render([
                        'label' => $this['arg_names'][$i] ?? false,
                        'code'  => $value,
                    ], false);

                    $html = str_replace('`', '\`', $html);
                    
                    echo "<script>DigitalisDebugger.find().append(`{$html}`);</script>";

                    break;

                case 'js':

                    if ($this['title']) echo static::console("> {$this['title']}", [
                        'style' => 'label',
                    ]);

                    foreach ($this['values'] as $i => $value) {

                        $options = [];
                        if (isset($this['arg_names'][$i])) $options['label'] = $this['arg_names'][$i];
                        echo static::console($value, $options);

                    }
                    break;

                default:
                case 'inline':
                    foreach ($this['values'] as $value) echo "<pre>{$value}</pre>";
                    break;

            }
        
        }

        public function after () {

            if ($this['die']) die;

        }
    
    }

    class Debug_Options {

        public $view            = 'debugger';
        public $expand          = 'print_r';
        public $append          = false;
        public $title           = null;
        public $open            = false;
        public $closable        = true;
        public $backtrace_limit = 40;
        public $die             = null;

        public function __construct ($props = []) {

            if (is_array($props)) foreach ($props as $prop => $value) if (property_exists($this, $prop)) $this->$prop = $value;
        
        }

    }

}

namespace {

    if (!defined('DIGITALIS_GLOBAL_FUNCTIONS') || !DIGITALIS_GLOBAL_FUNCTIONS) {

        if (!function_exists('dump')) {

            function dump (...$values) {
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }

        }

        if (!function_exists('damp')) {
    
            function damp (...$values) {

                $values[] = new Digitalis\Debug_Options([
                    'append' => true,
                ]);
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }
    
        }

        if (!function_exists('dd')) {
    
            function dd (...$values) {

                $values[] = new Digitalis\Debug_Options([
                    'open'       => true,
                    'closable'   => false,
                    'die'        => true,
                ]);
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }
    
        }

        if (!function_exists('dprint')) {

            function dprint (...$values) {

                $values[] = new Digitalis\Debug_Options([
                    'view' => 'inline',
                ]);
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }

        }

        if (!function_exists('dexp')) {

            function dexp (...$values) {

                $values[] = new Digitalis\Debug_Options([
                    'expand' => 'var_export',
                ]);
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }

        }

        if (!function_exists('js_log')) {

            function js_log (...$values) {

                $values[] = new Digitalis\Debug_Options([
                    'view' => 'js',
                ]);
    
                Digitalis\Call::static_array(Digitalis\Debug::class, 'write', $values);
        
            }

        }
    
    }

}
