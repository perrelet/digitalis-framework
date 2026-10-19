<?php

namespace Lattice;

use DateTime;

class Log extends Service {

    protected static $cache_group    = self::class;  
    protected static $cache_property = 'file';  

    protected $file        = 'log.log';
    protected $directory   = null;
    protected $name        = null;
    protected $date_format = null;
    protected $export_vars = false;

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

        return static::read_page($this->get_path(), (int) $page, wp_parse_args($args), $bytes); // a null page (page 1 now) and a query-string $args still type-check

    }

    // One paging reader for any file: get_page() reads this log through it, Logs_Page its chosen file.
    public static function read_page (string $path, int $page = 1, array $args = [], &$bytes = 0) : string {

        $bytes    = 0;
        $defaults = ['bpp' => 200000, 'overflow' => 500];
        $args     = wp_parse_args($args, $defaults);

        $path = realpath($path);
        if (!is_string($path) || !is_readable($path)) return '';

        clearstatcache(true, $path); // a write earlier in the request leaves PHP's stat cache at the old size
        $filesize = filesize($path);
        if ($filesize === false || $filesize <= 0) return '';

        $min_offset = -1 * $filesize;
        $bpp       = ((int) $args['bpp'] > 0) ? (int) $args['bpp'] : $defaults['bpp']; // 0 divided; a one-byte page is a newline, so the default rather than a clamp
        $overflow  = max(0, (int) $args['overflow']);
        $max_pages = max((int) ceil($filesize / $bpp), 1);

        if ($page < 0) $page = $max_pages + $page + 1;   // -1 is the last page: the file's head
        $page = min(max($page, 1), $max_pages);

        $offset = -1 * $page * $bpp;
        $maxlen = min($bpp, $filesize + $offset + $bpp); // the last page holds the remainder: derived before the offset is clamped, which re-read a full page from the start
        $offset = max($min_offset, $offset);

        $look_bwd = $overflow && ($offset != $min_offset);
        $look_fwd = $overflow && ($page > 1);

        $over_bwd = $look_bwd ? min($overflow, $filesize + $offset) : 0;
        $over_fwd = $look_fwd ? min($overflow, ($page - 1) * $bpp) : 0; // only the bytes after the page exist to read; past the end, the read fell short and the cut found the file's first line

        if ($look_bwd) {
            $offset = max($min_offset, $offset - $over_bwd);
            $maxlen += $over_bwd;
        }
        if ($look_fwd) $maxlen += $over_fwd;

        $lines = file_get_contents($path, false, null, $offset, $maxlen);
        if ($lines === false) return '';

        if ($look_bwd || $look_fwd) {

            if ($look_fwd) {

                $start   = max(0, strlen($lines) - $over_fwd - 1); // from the page's last byte: a page ending on a newline ends there, or the next page's first line would print on both
                $pos     = strpos($lines, "\n", $start);
                $next_nl = ($pos === false) ? strlen($lines) : $pos;

            } else {

                $next_nl = strlen($lines);

            }

            if ($look_bwd) {

                $cut = max(0, strlen($lines) - ($bpp + $over_fwd));

                if (($cut > 0) && ($lines[$cut - 1] === "\n")) {

                    $prev_nl = $cut; // the previous page's line ended before this page: it starts here, an empty line of its own included

                } else {

                    $pos     = strrpos(substr($lines, 0, $cut + 1), "\n"); // a line runs into this page: start where it starts, or just after a newline that ends it at the page's first byte
                    $prev_nl = ($pos === false) ? 0 : ($pos + 1);

                }

            } else {

                $prev_nl = 0;

            }

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