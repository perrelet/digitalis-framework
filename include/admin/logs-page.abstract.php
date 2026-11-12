<?php

declare(strict_types=1);

namespace Lattice;

use Lattice\Component\Field_Group;
use Lattice\Field\Number;
use Lattice\Field\Submit;
use Lattice\Field\Hidden;

abstract class Logs_Page extends Admin_Sub_Page {

    protected $slug           = 'digitalis-logs';
    protected $parent         = 'tools.php';
    protected $title          = 'Logs';
    protected $menu_title     = 'Logs';
    protected $capability     = 'manage_options';
    protected $permissions    = [];

    const DEFAULT_BYTES_PER_PAGE = 200000;
    protected $bytes_per_page = self::DEFAULT_BYTES_PER_PAGE;

    protected $logs = [];
    protected $_logs;

    public function __construct () {

        parent::__construct();
        add_action('admin_enqueue_scripts', [$this, 'maybe_enqueue_style']);
        add_action('admin_init', [$this, 'handle_clear']);
        add_filter('removable_query_args', [$this, 'filter_removable_query_args']);

    }

    // The screen's own request gets the stylesheet in the head; a host rendering callback() elsewhere gets it from the footer.
    public function maybe_enqueue_style () {

        if (($_GET['page'] ?? 0) == $this->slug) $this->enqueue_style();

    }

    public function enqueue_style () {

        wp_enqueue_style('lattice-tokens', LATTICE_URI . 'assets/css/admin-tokens.css', [], LATTICE_VERSION);
        wp_enqueue_style('lattice-logs', LATTICE_URI . 'assets/css/logs.css', ['lattice-tokens'], LATTICE_VERSION);

    }

    protected function filter_logs (&$logs) {
    
        $logs[] = [
            'slug'   => 'error',
            'name'   => 'Error Log',
            'path'   => static::resolve_error_log(ini_get('error_log'), defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false, WP_CONTENT_DIR),
            'theme' => ['basic', 'php'],
        ];
    
    }

    // The order WordPress itself resolves the debug log in (wp_debug_mode()), after PHP's own setting.
    public static function resolve_error_log ($ini, $debug_log, string $content_dir) : string {

        if (is_string($ini) && ($ini !== '')) return $ini;

        if (in_array(strtolower((string) $debug_log), ['true', '1'], true)) return $content_dir . '/debug.log';

        return is_string($debug_log) ? $debug_log : '';

    }

    public function filter_removable_query_args ($args) {

        return array_merge((array) $args, ['cleared', 'refused']);

    }

    public function get_page_url () : string {

        $url = function_exists('menu_page_url') ? menu_page_url($this->slug, false) : '';
        if ($url) return $url;

        $parent = str_contains((string) $this->parent, '.php') ? $this->parent : 'admin.php';

        return admin_url(add_query_arg('page', $this->slug, $parent));

    }

    public function find_log (?string $slug, bool $exact = false) : ?array {

        if (!$logs = $this->get_logs()) return null;

        if (!is_null($slug)) foreach ($logs as $key => $log) if (($key === $slug) || ($log['slug'] == $slug)) return $log;

        return ($exact && !is_null($slug)) ? null : $logs[array_key_first($logs)];

    }

    public function handle_clear () {

        if (($_GET['page'] ?? null) !== $this->slug) return;
        if (($_POST['clear-log'] ?? null) != 1)      return;

        if (!$this->get_logs()) return;

        // Clear names its log or empties none: the first log is a fallback for reading only.
        $slug    = $_POST['log'] ?? $_GET['log'] ?? null;
        $log     = $this->find_log(is_scalar($slug) ? (string) $slug : null, true);
        $outcome = $log ? $this->clear_log($log, sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''))) : 'failed';
        $flag    = ($outcome == 'cleared') ? ['cleared' => $log['slug']] : ['refused' => $outcome];

        wp_safe_redirect(add_query_arg(($log ? ['log' => $log['slug']] : []) + $flag, $this->get_page_url()));
        exit;

    }

    public function clear_log (array $log, string $nonce) : string {

        if ($log['state'] != 'ready') return 'failed';

        if (!($log['permissions']['clear'] ?? 0) || !current_user_can($log['permissions']['clear'])) return 'permission';

        if (!wp_verify_nonce($nonce, 'clear-log')) return 'expired';

        $empty = ($log['instance'] && $log['instance']->get_export_vars()) ? "\$log = [];\n" : '';

        return (file_put_contents($log['path'], $empty) === false) ? 'failed' : 'cleared';

    }

    public function get_logs () {

        if (is_null($this->_logs)) {

            if (!$this->permissions) $this->permissions = $this->capability;

            if (!is_array($this->permissions)) $this->permissions = [$this->permissions];

            $this->permissions = wp_parse_args($this->permissions, [
                'view'  => $this->capability,
                'clear' => $this->capability,
            ]);

            $this->_logs = [];
            $this->filter_logs($this->logs);

            if ($this->logs) foreach ($this->logs as $key => $log) {

                if ($log instanceof Log) $log = [
                    'name'     => $log->get_name(),
                    'slug'     => $key,
                    'path'     => $log->get_path(),
                    'theme'    => $log->get_export_vars() ? ['basic', 'php'] : ['basic'],
                    'instance' => $log,
                ];

                if (!is_array($log)) $log = [
                    'name' => $key,
                    'slug' => $key,
                    'path' => $log
                ];

                $log = wp_parse_args($log, [
                    'name'        => 'Log',
                    'slug'        => 'log',
                    'path'        => '',
                    'theme'       => ['basic'],
                    'permissions' => [],
                    'instance'    => null,
                ]);

                $log['theme'] = (array) ($log['theme'] ?: ['basic']);

                $log['permissions'] = wp_parse_args($log['permissions'], $this->permissions);

                $path         = (string) $log['path'];
                $log['state'] = match (true) {
                    $path === ''        => 'unconfigured',
                    !file_exists($path) => 'missing',
                    !is_readable($path) => 'unreadable',
                    default             => 'ready',
                };

                $this->_logs[$log['slug']] = $log;

            }

            $this->logs = &$this->_logs;

        }

        return $this->logs;

    }

    // Per theme: the patterns that start an entry, the patterns that give its head a level and the token rules. All are written
    // for the raw line and delimited by `/`; a token rule holds non-capturing groups only, and the earlier of two rules wins.
    public function get_syntax_rules () {

        return [
            'basic' => [
                'entry'  => [
                    "^\[[^\]]*\d\d:\d\d[^\]]*\]",                 // [... hh:mm ...]: a slow-log frame or a [debug] word starts nothing
                    "^\d{4}\/\d\d\/\d\d \d\d:\d\d:\d\d",          // nginx
                ],
                'levels' => [
                    'error'   => ["\[(?:error|crit|alert|emerg)\]"],
                    'warning' => ["\[warn\]"],
                    'notice'  => ["\[notice\]"],
                ],
                'tokens' => [
                    'timestamp'       => "^\[[^\]]*\d\d:\d\d[^\]]*\]|^\d{4}\/\d\d\/\d\d \d\d:\d\d:\d\d", // before square-brackets, or it never fires
                    'url'             => "\bhttps?:\/\/[^\s\"'<>)\]]+",
                    'single-quote'    => "(?<!\w)'.*?'(?!\w)",    // an apostrophe in prose opens nothing
                    'double-quote'    => "\".*?\"",
                    'path'            => "(?<![\w.\/])\/(?:[\w.@~+-]+\/[^\s:\"',()\]]*|[\w@~+-][\w.@~+-]*\.\w+)", // a second slash or an extension
                    'square-brackets' => "\[.*?\]",
                    'hash-integer'    => "#\d+",
                ],
            ],
            'php' => [
                'entry'  => ["^\\\$log\b(?:\[[^\]]*\])?"],        // an exported log; cleared, it begins `$log = [];`
                'levels' => [
                    'error'      => ["PHP (?:Parse|Fatal) error:", "WordPress database error"],
                    'warning'    => ["PHP Warning:"],
                    'notice'     => ["PHP Notice:"],
                    'deprecated' => ["PHP Deprecated:"],
                    'strict'     => ["PHP Strict Standards:", "\] Lattice: "],
                ],
                'tokens' => [
                    'static-method' => "\b[\w\\\\]+::\w+\(.*?\)",
                    'method'        => "[\w\\\\\$]+->\w+\(.*?\)",
                    'function'      => "(?<![\w.\$>:\\\\])[A-Za-z_][\w\\\\]*(?=\()",
                ],
            ],
        ];

    }

    public function get_log_syntax_rules ($log) {

        return Log_Format::merge($this->get_syntax_rules(), (array) ($log['theme'] ?? ['basic']), static::class);

    }

    public function callback () {

        $this->enqueue_style();

        echo "<h1>" . esc_html($this->title) . "</h1>";

        if (!$logs = $this->get_logs()) return;

        $slug           = is_scalar($_GET['log'] ?? null) ? (string) $_GET['log'] : null;
        $default        = (int) $this->bytes_per_page;

        if ($default < 1) { // a subclass property of 0 divides by zero: a declaration error, named under strict on every request, the default size in production

            $error = 'declares $bytes_per_page as ' . var_export($this->bytes_per_page, true) . ', which is not a positive integer, so the log cannot be paged.';

            Strict::violation(static::class, $error, 'Declare a positive number of bytes per page, or leave the default of ' . self::DEFAULT_BYTES_PER_PAGE . '.');
            Strict::log_once(static::class, $error);

            $default = self::DEFAULT_BYTES_PER_PAGE;

        }

        $request        = is_scalar($_GET['bpp'] ?? null) ? (int) $_GET['bpp'] : 0;
        $bytes_per_page = $request > 0 ? $request : $default; // ?bpp=0 divides by zero too; the default rather than a clamp, since a one-byte page is empty
        $page           = is_scalar($_GET['paged'] ?? null) ? max(1, (int) $_GET['paged']) : 1;

        $current = $this->find_log($slug);

        $this->render_notice($logs);

        if ($current['state'] != 'ready') {

            $this->render_unready($logs, $current);
            return;

        }

        clearstatcache(true, $current['path']); // a write earlier in the request leaves the stat cache at the old size
        $filesize     = (int) filesize($current['path']);
        $total        = max(1, (int) ceil($filesize / $bytes_per_page));
        $page         = min($page, $total); // read_page() clamps too; the pagination must mark the page it shows
        $bytes        = 0;
        $start        = 0;
        $chunk        = Log::read_page($current['path'], $page, ['bpp' => $bytes_per_page, 'overflow' => 500, 'size' => $filesize], $bytes, $start);
        $more         = ($filesize > $bytes);
        $plain        = !empty($_GET['plain']);
        $format       = new Log_Format($this->get_log_syntax_rules($current));
        $head         = $start ? (string) file_get_contents($current['path'], false, null, 0, Log_Format::HEAD_BYTES) : '';

        $pagination = $more ? paginate_links([
            'base'      => add_query_arg('paged', '%#%', remove_query_arg(['cleared', 'refused'])),
            'current'   => $page,
            'total'     => $total,
            'prev_text' => "&laquo;",
            'next_text' => "&raquo;",
        ]) : false;

        if ($pagination) $pagination = "<span class='pagination-links'>{$pagination}</span>";

        echo "<div class='lattice'><div class='digitalis-logs'>"; // the token scope: admin-tokens.css styles nothing outside it

            echo "<span id='hash-top'></span>";
            
            if (count($logs) > 1) $this->render_menu($logs, $current);

            if (!($current['permissions']['view'] ?? 0) || !current_user_can($current['permissions']['view'])) {

                echo "<div class='notice notice-error'><p>You do not have permission to view this log file.</p></div>";
                echo "</div></div>";
                return;
    
            }

            if ($pagination && ($bytes >= 5000)) echo $pagination;

            echo "<div class='digitalis-log'>";

                $size = (string) size_format($bytes);
                if ($more) $size .= ' of ' . size_format($filesize);
                $size .= ', modified ' . wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) filemtime($current['path']));

                echo "<div class='log-info'>";
                    echo "<div class='path'>" . esc_html($current['path']) . "</div>";
                    echo "<div class='size'>" . esc_html($size) . "<a class='goto bottom' href='#hash-bottom' aria-label='Jump to bottom'></a></div>";
                echo "</div>";

                echo "<div class='lines'>";

                    foreach ($format->parse($chunk, $start, $head) as $entry) echo $this->render_entry($entry, $format, $plain);

                echo "</div>";

                echo "<div class='log-info'>";
                    echo "<div class='path'>" . esc_html($current['path']) . "</div>";
                    echo "<div class='size'>" . esc_html($size) . "<a class='goto top' href='#hash-top' aria-label='Jump to top'></a></div>";
                echo "</div>";

                echo "<span id='hash-bottom'></span>";

                echo "<script>window.addEventListener('load', function() { setTimeout(() => window.scrollTo(0, document.querySelector('#hash-bottom').offsetTop), 100) });</script>";

            echo "</div>";

            if ($pagination) echo $pagination;

            echo "<form method='post' class='actions' action='" . esc_url($this->get_page_url()) . "'>";

                echo "<input type='hidden' name='nonce' value='" . wp_create_nonce('clear-log') . "'>";
                echo "<input type='hidden' name='log' value='" . esc_attr($current['slug']) . "'>";
                echo "<button type='button' class='button' onclick='location.reload();'>♻️ Refresh Log</button>";
                
                if (($current['permissions']['clear'] ?? 0) && current_user_can($current['permissions']['clear'])) {

                    echo "<button class='button clear' type='submit' name='clear-log' value='1' onclick='return confirm(`Are you sure you want to empty this log file?`)'>🗑️ Clear Log</button>";
        
                }
                
            echo "</form>";

        echo "</div></div>";
    
    }

    protected function render_notice (array $logs) {

        $cleared = is_scalar($_GET['cleared'] ?? null) ? (string) $_GET['cleared'] : null;
        $refused = is_scalar($_GET['refused'] ?? null) ? (string) $_GET['refused'] : null;

        if (!is_null($cleared) && ($log = $logs[$cleared] ?? null)) {

            echo "<div class='notice notice-success'><p>🧹 Cleared '" . esc_html($log['name']) . "'.</p></div>";

        } elseif (!is_null($refused)) {

            $reason = match ($refused) {
                'permission' => '🔒 You do not have permission to clear this log file.',
                'expired'    => '❌ The link you followed has expired, please try again.',
                default      => '❌ The log file could not be cleared.',
            };

            echo "<div class='notice notice-error'><p>{$reason}</p></div>";

        }

    }

    protected function render_unready (array $logs, array $log) {

        $reason = match ($log['state']) {
            'unconfigured' => 'No file is configured for this log.',
            'missing'      => 'Nothing logged yet.',
            default        => 'This file is not readable by the web server.',
        };

        echo "<div class='lattice'><div class='digitalis-logs'>";

            if (count($logs) > 1) $this->render_menu($logs, $log);

            if (!($log['permissions']['view'] ?? 0) || !current_user_can($log['permissions']['view'])) {

                echo "<div class='notice notice-error'><p>You do not have permission to view this log file.</p></div>";

            } else {

                echo "<div class='digitalis-log'>";
                    echo "<div class='log-info'>";
                        echo "<div class='path'>" . esc_html($log['path']) . "</div>";
                        echo "<div class='size'>" . esc_html($reason) . "</div>";
                    echo "</div>";
                echo "</div>";

            }

        echo "</div></div>";

    }

    protected function render_menu ($logs, $current = null) {
    
        echo "<nav class='digitalis-logs-nav nav-tab-wrapper'>";

            foreach ($logs as $log) {
        
                $url = add_query_arg('log', $log['slug'], remove_query_arg(['paged', 'clear-log', 'cleared', 'refused']));

                $class = ($current['slug'] == $log['slug']) ? 'nav-tab nav-tab-active' : 'nav-tab';

                echo "<a href='" . esc_url($url) . "' class='" . esc_attr($class) . "'>" . esc_html($log['name']) . "</a>";
            
            }

        echo "</nav>";
    
    }

    private function render_entry (array $entry, Log_Format $format, bool $plain) : string {

        $paint = fn ($line) => $plain ? htmlspecialchars($line, Log_Format::ESCAPE) : $format->tokenize($line);
        $body  = implode("\n", array_map($paint, $entry['body']));
        $more  = count($entry['body']);

        if (is_null($entry['head'])) {

            return "<div class='entry continued' id='b{$entry['offset']}'><p class='note'>continued from the previous page</p><pre class='body'>{$body}</pre></div>";

        }

        $level = $entry['level'] ? " data-level='" . esc_attr($entry['level']) . "'" : '';
        $fold  = '';

        if ($entry['count'] > 1) {

            $level .= " data-end='{$entry['end']}'";
            $fold   = "<span class='fold'><b>&times;{$entry['count']}</b> last " . htmlspecialchars($entry['last'], Log_Format::ESCAPE) . "</span>";

        }

        $html = "<div class='entry' id='b{$entry['offset']}'{$level}><pre class='head'>" . $paint($entry['head']) . "</pre>{$fold}";

        if ($more) $html .= "<details><summary>{$more} more " . ($more > 1 ? 'lines' : 'line') . "</summary><pre class='body'>{$body}</pre></details>";

        return "{$html}</div>";

    }

}
