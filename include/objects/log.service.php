<?php

namespace Digitalis;

use DateTime;

class Log extends Service {

    protected static $cache_property = 'file';

    protected $directory   = ABSPATH . '../logs';
    protected $file        = 'log.log';
    protected $date_format = 'd-M-Y H:i:s e';

    public function log ($msg) {

        if (is_array($msg) || is_object($msg)) $msg = $this->flatten_object($msg);

        $this->write($this->format_line($msg));
    
    }

    public function write ($text) {
    
        $path = $this->get_path();

        return file_put_contents($path, $text . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    }

    public function get_directory () {
    
        return $this->directory;
    
    }

    public function get_file () {
    
        return $this->file;
    
    }

    public function get_path () {
    
        return $this->get_directory() . '/' . $this->get_file();
    
    }

    public function format_line ($msg) {

        //return sprintf("[{%1$s}] {%2$s}", (new DateTime)->format(static::$date_format), $msg);

        return "[" . (new DateTime)->format($this->date_format) . "] " . $msg;
    
    }

    public function flatten_object ($msg) {
    
        return print_r($msg, true);
    
    }

}