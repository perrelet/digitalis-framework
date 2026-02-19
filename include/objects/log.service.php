<?php

namespace Digitalis;

use DateTime;

class Log extends Service {

    protected static $cache_group = self::class;  

    protected $file        = 'log.log';
    protected $directory   = null;
    protected $name        = null;
    protected $date_format = null;
    protected $export_vars = false;

    protected function get_cache_key () {

        return realpath($this->get_path());
    
    }

    public function __invoke ($msg) {

        return $this->log($msg);

    }

    public function log ($msg) {

        if ($this->get_export_vars()) {

            $msg = var_export($msg, true);

        } else {

            if (!is_scalar($msg)) $msg = $this->flatten_object($msg);

        }

        return $this->write($this->format_line($msg));
    
    }

    public function write ($text) {

        $path = $this->get_path();
        $dir  = dirname($path);
        if (!file_exists($dir)) wp_mkdir_p($dir);

        return file_put_contents($this->get_path(), $text . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    }

    public function get_file () {
    
        return $this->file ?: basename(ini_get('error_log'));
    
    }

    public function get_directory () {
    
        return $this->directory ?: dirname(ini_get('error_log'));
    
    }

    public function get_path () {
    
        return trailingslashit($this->get_directory()) . $this->get_file();
    
    }

    public function get_name () {
    
        return $this->name ?? static::class;
    
    }

    public function get_date_format () {
    
        return $this->date_format ?? ($this->get_export_vars() ? 'Y-m-d H:i:s.u' : 'd-M-Y H:i:s e');
    
    }

    public function get_export_vars () {
    
        return $this->export_vars;
    
    }

    public function set_export_vars ($export_vars) {
    
        $this->export_vars = (bool) $export_vars;
        return $this;
    
    }

    public function format_line ($msg) {

        if ($this->get_export_vars()) {

            if ($date_format = $this->get_date_format()) {

                $date = DateTime::createFromFormat('0.u00 U', microtime());
                return "\$log['{$date->format($date_format)}'] = {$msg};";

            } else {

                return "\$log[] = {$msg};";

            }

        } else {

            if ($date_format = $this->get_date_format()) {

                $date = new DateTime;
                return "[{$date->format($date_format)}] {$msg}";

            } else {

                return $msg;

            }

        }
    
    }

    public function flatten_object ($msg) {

        return print_r($msg, true);

    }

    public function read () {
    
        return file_get_contents($this->get_path());
    
    }

    public function get_page ($page = 1, $args = [], &$bytes = 0) {

        $args = wp_parse_args($args, [
            'bpp'      => 200000,
            'overflow' => 500,
        ]);

        $path = realpath($this->get_path());
        if (!is_string($path) || $path === '' || !is_readable($path)) return '';

        $filesize = filesize($path);
        if ($filesize === false || $filesize <= 0) return '';

        $min_offset = -1 * $filesize;
        $max_pages  = max((int) ceil($filesize / $args['bpp']), 1);

        if ($page < 0) $page = $max_pages - abs((int) $page) + 1;

        $offset = -1 * $page * (int) $args['bpp'];
        $offset = max($min_offset, $offset);

        $maxlen = min((int) $args['bpp'], $filesize + $offset + (int) $args['bpp']);

        $look_bwd = !empty($args['overflow']) && ($offset != $min_offset);
        $look_fwd = !empty($args['overflow']) && ($page > 1);

        $over_bwd = $look_bwd ? min((int) $args['overflow'], $filesize + $offset) : 0;
        $over_fwd = $look_fwd ? (int) $args['overflow'] : 0;

        if ($look_bwd) {
            $offset = max($min_offset, $offset - $over_bwd);
            $maxlen += $over_bwd;
        }
        if ($look_fwd) $maxlen += $over_fwd;

        $lines = file_get_contents($path, false, null, $offset, $maxlen);
        if ($lines === false) return '';

        if ($look_bwd || $look_fwd) {

            if ($look_fwd) {

                $start   = max(0, strlen($lines) - $over_fwd);
                $pos     = strpos($lines, "\n", $start);
                $next_nl = ($pos === false) ? strlen($lines) : $pos;

            } else {

                $next_nl = strlen($lines);

            }

            if ($look_bwd) {

                $cut     = max(0, strlen($lines) - ((int) $args['bpp'] + $over_fwd));
                $chunk   = substr($lines, 0, $cut);
                $pos     = strrpos($chunk, "\n");
                $prev_nl = ($pos === false) ? 0 : ($pos + 1);

            } else {

                $prev_nl = 0;

            }

            if ($prev_nl < 0)        $prev_nl = 0;
            if ($next_nl < $prev_nl) $next_nl = $prev_nl;

            $lines = substr($lines, $prev_nl, $next_nl - $prev_nl);

        }

        $bytes = strlen($lines);

        return $lines; 
    
    }

    //

    public static function r ($file = null) {
    
        return ($log = static::get_instance($file)) ? $log->read() : false;
    
    }

    public static function w ($msg, $file = null) {
    
        return ($log = static::get_instance($file)) ? $log->log($msg) : false;
    
    }

}