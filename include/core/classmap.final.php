<?php

namespace Lattice;

use RuntimeException;
use WP_CLI;

final class Classmap {

    private static array $registry = [];
    private static bool  $notified = false;

    private string $file;
    private string $dir;
    private ?array $data     = null;
    private array  $recorded = [];

    public function __construct (string $file) {

        $this->file = $file;
        $this->dir  = realpath(dirname($file));

        self::$registry[] = $this;

    }

    public static function all () : array {

        return self::$registry;

    }

    public static function enabled () : bool {

        if (defined('LATTICE_CLASSMAP')) return (bool) LATTICE_CLASSMAP;

        return !(defined('WP_DEBUG') && WP_DEBUG);

    }

    public function key (string $path, bool $recursive, string $ext) : string {

        return $this->relative($path) . '|' . (int) $recursive . '|' . $ext;

    }

    // The walk always uses the files on disk; the cache only supplies their declarations, and only when the two file sets match exactly. It can be ignored but never trusted wrongly.
    public function read (string $key, array $files) : ?array {

        if (!self::enabled())                   return null;
        if (is_null($this->data))               $this->load();
        if (!isset($this->data['calls'][$key])) return null;

        $cached = [];

        foreach ($this->data['calls'][$key] as $relative => $names) $cached[$this->absolute($relative)] = $names;

        $disk = $files;             sort($disk);
        $kept = array_keys($cached); sort($kept);

        if ($disk !== $kept) {

            $this->stale("files changed under {$key}");
            return null;

        }

        return $cached;

    }

    public function record (string $key, array $declarations) : void {

        foreach ($declarations as $absolute => $names) $this->recorded[$key][$this->relative($absolute)] = $names;

    }

    public function write () : string {

        if (is_null($this->data)) $this->load();

        $calls = array_merge($this->data['calls'] ?? [], $this->recorded);
        ksort($calls);

        $export = "<?php\n\nreturn " . var_export(['version' => LATTICE_VERSION, 'calls' => $calls], true) . ";\n";
        $temp   = $this->file . '.' . getmypid() . '.tmp';

        if ((file_put_contents($temp, $export) === false) || !rename($temp, $this->file)) {
            throw new RuntimeException("Cannot write classmap {$this->file}");
        }

        return $this->file;

    }

    private function load () : void {

        $this->data = ['calls' => []];

        if (!is_file($this->file)) return;

        $data = include $this->file;

        if (!is_array($data) || (($data['version'] ?? null) !== LATTICE_VERSION)) {

            $this->stale('version mismatch');
            return;

        }

        $this->data = $data;

    }

    private function stale (string $why) : void {

        $message = "Classmap {$this->file} is stale ({$why}). Run `wp lattice classmap`.";

        if (defined('WP_DEBUG') && WP_DEBUG) throw new RuntimeException($message);
        if (self::$notified)                 return;

        self::$notified = true;

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::warning($message);
            return;
        }

        add_action('admin_notices', fn () => printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message)));

    }

    private function relative (string $absolute) : string {

        $from = explode('/', trim($this->dir, '/'));
        $to   = explode('/', trim($absolute, '/'));

        while ($from && $to && ($from[0] === $to[0])) { array_shift($from); array_shift($to); }

        $relative = str_repeat('../', count($from)) . implode('/', $to);

        return $relative === '' ? '.' : $relative;

    }

    private function absolute (string $relative) : string {

        $path = $this->dir . '/' . $relative;

        return realpath($path) ?: $path;

    }

}
