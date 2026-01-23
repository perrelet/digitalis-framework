<?php

namespace Digitalis;

use Digitalis\Field_Group;
use Digitalis\Field\Number;
use Digitalis\Field\Submit;
use Digitalis\Field\Hidden;

abstract class Logs_Page extends Admin_Sub_Page {

    protected $slug           = 'digitalis-logs';
    protected $parent         = 'tools.php';
    protected $title          = 'Logs';
    protected $menu_title     = 'Logs';
    protected $capability     = 'manage_options';
    protected $permissions    = [];
    protected $bytes_per_page = 200000;

    protected $logs = [];
    protected $_logs;

    protected function filter_logs (&$logs) {
    
        $logs[] = [
            'slug'   => 'error',
            'name'   => 'Error Log',
            'path'   => ini_get('error_log'),
            'theme' => ['basic', 'php'],
        ];
    
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

                $allowed_themes = array_keys($this->get_syntax_rules());
                $theme_input = sanitize_text_field(wp_unslash($_GET['theme'] ?? 'basic'));
                $theme_values = array_filter(
                    explode(',', $theme_input),
                    function($t) use ($allowed_themes) { return in_array($t, $allowed_themes, true); }
                );
                if (empty($theme_values)) $theme_values = ['basic'];

                $log = wp_parse_args($log, [
                    'name'        => 'Log',
                    'slug'        => 'log',
                    'path'        => '',
                    'theme'       => $theme_values,
                    'permissions' => [],
                    'instance'    => null,
                ]);

                if (!is_array($log['theme'])) $log['theme'] = [$log['theme']];

                $log['permissions'] = wp_parse_args($log['permissions'], $this->permissions);

                if (!file_exists($log['path'])) continue;

                $this->_logs[$log['slug']] = $log;

            }

            $this->logs = &$this->_logs;

        }

        return $this->logs;

    }

    public function get_syntax_rules () {
    
        return [
            'basic' => [
                'single-quote'      => "\'.*?\'",                   // '...'
                'double-quote'      => "&quot;.*?&quot;",           // "..."
                'path'              => "\s\/[^ ]*",                 // /some/path
                'square-brackets'   => "\[.*?\]",                   // [...]
                //'brackets'        => "\(.*?\)",                   // (...)
                'hash-integer'      => "#\d+",                      // #n
            ],
            'php' => [
                'function'          => "\s[^ \/.]+\(.*?\)",         // function(...)
                'static-method'     => "\b[^ ]*::[^ ]*\(.*?\)",     // class::method(...)
                'method'            => "\b[^ ]*-&gt;[^ ]*\(.*?\)",  // class->method(...)
                'error'             => [
                    "PHP Parse error:",
                    "PHP Fatal error:"
                ],
                'warning'           => ["PHP Warning:"],
            ],
        ];
    
    }

    public function get_log_syntax_rules ($log) {

        $all_rules    = $this->get_syntax_rules();
        $syntax_rules = [];

        if ($log['theme']) foreach ($log['theme'] as $key) if (isset($all_rules[$key])) {

            $syntax_rules = array_merge($syntax_rules, $all_rules[$key]);

        }

        return $syntax_rules;
    
    }

    public function callback () {

        $this->css();

        echo "<h1>Logs</h1>";

        if (!$logs = $this->get_logs()) return;

        $slug           = $_GET['log'] ?? null;
        $bytes_per_page = (int) ($_GET['bpp'] ?? $this->bytes_per_page);
        $page           = (int) ($_GET['paged'] ?? 1);
        $clear          = $_POST['clear-log'] ?? null;

        $current = $slug ? ($logs[$slug] ?? $logs[array_key_first($logs)]) : $logs[array_key_first($logs)];
        if ($slug) foreach ($logs as $log) if ($log['slug'] == $slug) $current = $log;

        if ($clear == 1) {

            if (!($current['permissions']['clear'] ?? 0) || !current_user_can($current['permissions']['clear'])) {

                echo "<div class='notice notice-error'><p>🔒 You do no thave permission to clear this log file.</p></div>";

            } elseif (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'clear-log')) {
                
                echo "<div class='notice notice-error'><p>❌ The link you followed has expired, please try again.</p></div>";
    
            } else {

                $empty = ($current['instance'] && $current['instance']->get_export_vars()) ? "\$log = [];\n" : '';

                echo "<div class='notice notice-success'><p>🧹 Successfully cleared '" . esc_html($current['name']) . "'.</p></div>";
                file_put_contents($current['path'], $empty);

            }

        }

        $bytes        = 0;
        $lines        = $this->get_lines($current['path'], $bytes_per_page, $page, $bytes);
        $filesize     = filesize($current['path']);
        $more         = ($filesize > $bytes);
        $syntax_rules = $this->get_log_syntax_rules($current);

        $pagination = $more ? paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'current'   => $page,
            'total'     => floor($filesize / $bytes_per_page) + 1,
            'prev_text' => "&laquo;",
            'next_text' => "&raquo;",
        ]) : false;

        if ($pagination) $pagination = "<span class='pagination-links'>{$pagination}</span>";

        echo "<div class='digitalis-logs'>";

            echo "<span id='hash-top'></span>";
            
            if (count($logs) > 1) $this->render_menu($logs, $current);

            if (!($current['permissions']['view'] ?? 0) || !current_user_can($current['permissions']['view'])) {

                echo "<div class='notice notice-error'><p>You do no thave permission to view this log file.</p></div>";
                return;
    
            }

            if ($pagination && ($bytes >= 5000)) echo $pagination;

            echo "<div class='digitalis-log'>";

                $size = "{$bytes}";
                if ($more) $size .= " of {$filesize}";
                $size .= " bytes";

                echo "<div class='log-info'>";
                    echo "<div class='path'>" . esc_html($current['path']) . "</div>";
                    echo "<div class='size'>" . esc_html($size) . "<span class='goto bottom' onclick='window.scrollTo(0, document.querySelector(`#hash-bottom`).offsetTop);'></span></div>";
                echo "</div>";

                echo "<div class='lines'>";

                    if ($lines) foreach ($lines as &$line) {

                        $this->format_line($line, $syntax_rules);
                    
                        echo "<pre class='line'>{$line}</pre>";
                    
                    }
                    
                echo "</div>";

                echo "<div class='log-info'>";
                    echo "<div class='path'>" . esc_html($current['path']) . "</div>";
                    echo "<div class='size'>" . esc_html($size) . "<span class='goto top' onclick='window.scrollTo(0, document.querySelector(`#hash-top`).offsetTop);'></span></div>";
                echo "</div>";

                echo "<span id='hash-bottom'></span>";

                echo "<script>window.addEventListener('load', function() { setTimeout(() => window.scrollTo(0, document.querySelector('#hash-bottom').offsetTop), 100) });</script>";

            echo "</div>";

            if ($pagination) echo $pagination;

            echo "<form method='post' class='actions'>";

                echo "<input type='hidden' name='nonce' value='" . wp_create_nonce('clear-log') . "'></input>";
                echo "<button classs='button' onclick='location.reload(); return false;'>♻️ Refresh Log</button>";
                
                if (($current['permissions']['clear'] ?? 0) && current_user_can($current['permissions']['clear'])) {

                    echo "<button classs='button' type='submit' name='clear-log' value='1' onclick='return confirm(`Are you sure you want to empty this log file?`)'>🗑️ Clear Log</button>";
        
                }
                
            echo "</form>";

        echo "</div>";
    
    }

    protected function render_menu ($logs, $current = null) {
    
        echo "<nav class='digitalis-logs-nav'>";

            foreach ($logs as $log) {
        
                $url = add_query_arg('log', $log['slug'], remove_query_arg(['paged', 'clear-log']));

                $class = ($current['slug'] == $log['slug']) ? 'selected' : 'none';

                echo "<a href='" . esc_url($url) . "' class='" . esc_attr($class) . "'>" . esc_html($log['name']) . "</a>";
            
            }

        echo "</nav>";
    
    }

    protected function get_lines ($path, $bytes_per_page, $page = 1, &$bytes = 0, $overflow = 500) {

        $filesize   = filesize($path);
        $min_offset = -1 * $filesize;
        $max_pages  = max(ceil($filesize / $bytes_per_page), 1);

        if ($page > $max_pages) $page = $max_pages;

        $offset = -1 * $page * $bytes_per_page;
        $maxlen = min($bytes_per_page, $filesize + $offset + $bytes_per_page);
        $offset = max($min_offset, $offset);

        $look_bwd = $overflow && ($offset != $min_offset);
        $look_fwd = $overflow && ($page > 1);

        $over_bwd = $look_bwd ? min($overflow, $filesize + $offset) : 0;
        $over_fwd = $look_fwd ? $overflow : 0;

        if ($look_bwd) {
            $offset = max($min_offset, $offset - $over_bwd);
            $maxlen += $over_bwd;
        }
        if ($look_fwd) $maxlen += $over_fwd;

        $lines = file_get_contents($path, false, null, $offset, $maxlen);

        //dprint($offset);

        if ($look_bwd || $look_fwd) {

            $next_nl = $look_fwd ? strpos($lines, "\n", -1 * $over_fwd + 1) : strlen($lines);
            $prev_nl = $look_bwd ? strrpos($lines, "\n", -1 * ($bytes_per_page + $over_fwd)) : 0;
            $lines   = substr($lines, $prev_nl, $next_nl - $prev_nl);

        }

        $bytes = strlen($lines);

        $lines = explode("\n", $lines);

        return $lines; 
    
    }

    protected function format_line (&$line, $syntax_rules) {

        $line = htmlspecialchars($line);

        if ($syntax_rules) foreach ($syntax_rules as $class => $patterns) {

            $this->match_pattern($patterns, $class, $line);

        }
    
    }

    protected function match_pattern ($patterns, $class, &$line) {
    
        $pattern = is_array($patterns) ? implode("|", $patterns) : $patterns;

        $matches = null;
        preg_match_all("/{$pattern}/", $line, $matches, PREG_OFFSET_CAPTURE);

        $offset = 0;

        if ($matches[0] ?? 0) foreach ($matches[0] as $match) {

            $open  = "<span class='x-{$class}'>";
            $close = "</span>";

            $line = 
                substr($line, 0, $match[1] + $offset) .
                "{$open}{$match[0]}{$close}" .
                substr($line, $match[1] + strlen($match[0]) + $offset)
            ;

            $offset += strlen($open) + strlen($close);

        }
    
    }

    protected function css () {
    
        ?><style>

            @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap');

            .digitalis-logs {

                display: flex;
                flex-direction: column;
                gap: 1rem;

                .digitalis-logs-nav {

                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;

                    a {

                        padding: 0.25rem 1rem;
                        border: 0;
                        background: #dfdfdf;
                        border-radius: 4px;
                        color: inherit;
                        text-decoration: none;

                        &:hover {

                            background: #ffffff;

                        }

                        &.selected {

                            background: #606060;
                            color: white;

                            &:hover {

                                background: #404040;

                            }

                        }

                    }

                }

                .pagination-links {

                    font-size: 1.2em;
                    font-weight: bold;

                    .page-numbers {

                        text-decoration: none;
                        color: inherit;
                        padding: 0.25rem 0.5rem;
                        border-radius: 2px;

                    }

                    a.page-numbers:hover {

                        background: #dfdfdf;

                    }

                    .current {

                        background: #606060;
                        color: white;


                    }

                }

                .digitalis-log {

                    background: #1d2327;
                    border-radius: 4px;
                    font-family: "Roboto Mono", monospace;
                    color: #f4f4f4;
                    overflow: hidden;

                    .log-info {

                        display: flex;
                        gap: 1rem;
                        justify-content: space-between;
                        padding: 0.5rem;
                        background: #3b3b3b;

                        .goto {

                            --c: currentColor;

                            display: inline-block;
                            border: 0.5em solid transparent;
                            margin-left: 0.5rem;
                            cursor: pointer;

                            &:hover {

                                --c: #999;

                            }

                            &.bottom {

                                border-top-color: var(--c);
                                margin-bottom: -0.4em;

                            }

                            &.top {

                                border-bottom-color: var(--c);

                            }

                        }

                    }

                    .lines {

                        padding: 0.5rem;
                        border-bottom: 1px solid #5c5c5c;
                        border-top: 1px solid #5c5c5c;

                        .line {

                            line-height: 1.2;
                            text-wrap: wrap;
                            font-family: inherit;
                            margin-top: 0;
                            margin-bottom: 0.25em;

                            span:before {

                                padding-right: 0.2em;
                                font-size: 0.9em;

                            }

                            .x-single-quote    { color: #f7e6bd; }
                            .x-double-quote    { color: #fff5ce; }
                            .x-path            { color: #cacac0; font-style: italic; }
                            .x-function        { color: #aad7ff; }
                            .x-static-method   { color: #49dcf9; }
                            .x-method          { color: #56ffd8; }
                            .x-square-brackets { color: #c6a6ff; }
                            .x-brackets        { color: #b9eff6; }
                            .x-hash-integer    { color: #dcd8ac; }
                            .x-error           { background: #fb7c7c; color: #1d2327; font-weight: bold; border-radius: 2px; padding: 0px 0.2em; }
                            .x-error:before    { content: '❌' }
                            .x-warning         { background: #daba54; color: #1d2327; font-weight: bold; border-radius: 2px; padding: 0px 0.2em; }
                            .x-warning:before  { content: '⚠️' }

                        }

                    }

                }

                form.actions {

                    display: flex;
                    gap: 0.5rem;

                    button {

                        background: white;
                        border: 1px solid #ddd;
                        padding: 0.5rem;
                        border-radius: 0.25rem;
                        cursor: pointer;

                        &:focus,
                        &:hover {

                            background: #fbfbfb;
                            border-color: #aaa;

                        }

                        &:active {

                            background: #f6f6f6;

                        }

                    }

                }

            }
        </style><?php
    
    }

}