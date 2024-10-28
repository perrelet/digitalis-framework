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

                if (!is_array($log)) $log = [
                    'name' => $key,
                    'slug' => $key,
                    'path' => $log
                ];

                $log = wp_parse_args($log, [
                    'name'        => 'Log',
                    'slug'        => 'log',
                    'path'        => '',
                    'permissions' => [],
                ]);

                $log['permissions'] = wp_parse_args($log['permissions'], $this->permissions);

                if (!file_exists($log['path'])) continue;

                $this->_logs[$log['slug']] = $log;

            }

            $this->logs = &$this->_logs;

        }

        return $this->logs;

    }

    protected function filter_logs (&$logs) {
    
        $logs[] = [
            'slug' => 'error',
            'name' => 'Error Log',
            'path' => ini_get('error_log'),
        ];
    
    }

    public function callback () {

        $this->css();

        echo "<h1>Logs</h1>";

        if (!$logs = $this->get_logs()) return;

        $slug           = $_GET['log'] ?? null;
        $bytes_per_page = (int) ($_GET['bpp'] ?? $this->bytes_per_page);
        $page           = (int) ($_GET['paged'] ?? 1);
        $clear          = $_GET['clear-log'] ?? null;

        $current = $slug ? ($logs[$slug] ?? $logs[array_key_first($logs)]) : $logs[array_key_first($logs)];
        if ($slug) foreach ($logs as $log) if ($log['slug'] == $slug) $current = $log;

        if ($clear == 1) {

            if (!($current['permissions']['clear'] ?? 0) || !current_user_can($current['permissions']['clear'])) {

                echo "<div class='notice notice-error'><p>🔒 You do no thave permission to clear this log file.</p></div>";

            } elseif (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clear-log')) {
                
                echo "<div class='notice notice-error'><p>❌ The link you followed has expired, please try again.</p></div>";
    
            } else {

                echo "<div class='notice notice-success'><p>🧹 Successfully cleared '{$current['name']}'.</p></div>";
                file_put_contents($current['path'], '');

            }

        }

        $bytes    = 0;
        $lines    = $this->get_lines($current['path'], $bytes_per_page, $page, $bytes);
        $filesize = filesize($current['path']);
        $more     = ($filesize > $bytes);

        $pagination = $more ? paginate_links([
            'base'      => add_query_arg('paged', '%#%', remove_query_arg('clear-log')),
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
                    echo "<div class='path'>{$current['path']}</div>";
                    echo "<div class='size'>{$size}<span class='goto bottom' onclick='window.scrollTo(0, document.querySelector(`#hash-bottom`).offsetTop);'></span></div>";
                echo "</div>";

                echo "<div class='lines'>";

                    if ($lines) foreach ($lines as &$line) {

                        $this->format_line($line);
                    
                        echo "<div class='line'>{$line}</div>";
                    
                    }
                    
                echo "</div>";

                echo "<div class='log-info'>";
                    echo "<div class='path'>{$current['path']}</div>";
                    echo "<div class='size'>{$size}<span class='goto top' onclick='window.scrollTo(0, document.querySelector(`#hash-top`).offsetTop);'></span></div>";
                echo "</div>";

                echo "<span id='hash-bottom'></span>";

                echo "<script>window.addEventListener('load', function() { setTimeout(() => window.scrollTo(0, document.querySelector('#hash-bottom').offsetTop), 100) });</script>";

            echo "</div>";

            if ($pagination) echo $pagination;

            echo "<div class='actions'>";

                echo "<a class='refresh-log' href='" . add_query_arg('log', $current['slug'], remove_query_arg(['paged', 'clear-log'])) . "'>♻️ Refresh Log</a>";           
                
                if (($current['permissions']['clear'] ?? 0) && current_user_can($current['permissions']['clear'])) {

                    $url     = wp_nonce_url(add_query_arg('clear-log', 1, remove_query_arg(['paged'])), 'clear-log');
                    $confirm = "Are you sure you want to empty this log file?";

                    echo "<a class='clear-log' href='{$url}' onclick='return confirm(`{$confirm}`)'>🗑️ Clear Log</a>";
        
                }
                
            echo "</div>";

        echo "</div>";
    
    }

    protected function render_menu ($logs, $current = null) {
    
        echo "<nav class='digitalis-logs-nav'>";

            foreach ($logs as $log) {
        
                $url = add_query_arg('log', $log['slug'], remove_query_arg(['paged', 'clear-log']));

                $class = ($current['slug'] == $log['slug']) ? 'selected' : 'none';

                echo "<a href='{$url}' class='{$class}'>{$log['name']}</a>";
            
            }

        echo "</nav>";
    
    }

    protected function get_lines ($path, $bytes_per_page, $page = 1, &$bytes = 0, $overflow = 500) {

        $filesize   = filesize($path);
        $min_offset = -1 * $filesize;

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

        if ($look_bwd || $look_fwd) {

            $next_nl = $look_fwd ? strpos($lines, "\n", -1 * $over_fwd + 1) : strlen($lines);
            $prev_nl = $look_bwd ? strrpos($lines, "\n", -1 * ($bytes_per_page + $over_fwd)) : 0;
            $lines   = substr($lines, $prev_nl, $next_nl - $prev_nl);

        }

        $bytes = strlen($lines);

        $lines = explode("\n", $lines);
        $lines = array_map("trim", $lines);

        return $lines; 
    
    }

    protected function format_line (&$line) {

        $line = htmlspecialchars($line);

        $rules = [
            'color: #d70000' => "#\d+",
            'color: #b3b3b3' => "\[.*?\]",
        ];

        foreach ($rules as $style => $patterns) {

            if (!is_array($patterns)) $patterns = [$patterns];

            $this->match_pattern($patterns, $style, $line);

        }
    
    }

    protected function match_pattern ($patterns, $style, &$line) {
    
        $pattern = implode("|", $patterns);

        $matches = null;

        if (preg_match_all("/{$pattern}/", $line, $matches, PREG_OFFSET_CAPTURE)) foreach ($matches as $match) {

            $line = 
                substr($line, 0, $match[0][1]) .
                "<span style='{$style}'>{$match[0][0]}</span>" .
                substr($line, $match[0][1] + strlen($match[0][0]))
            ;

            //dprint(substr($line, $match[0][1], strlen($match[0][0])));

        }
    
    }

    protected function css () {
    
        ?><style>
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

                    background: #fcfcfc;
                    border-radius: 4px;
                    font-family: monospace;

                    .log-info {

                        display: flex;
                        gap: 1rem;
                        justify-content: space-between;
                        padding: 0.5rem;

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
                        border-bottom: 1px solid #e0e0e0;
                        border-top: 1px solid #e0e0e0;

                        .line {

                            line-height: 1em;

                            &:not(:last-child) {

                                margin-bottom: 0.25em;

                            }

                        }

                    }

                }

                .actions {

                    display: flex;
                    gap: 1rem;

                    a {

                        font-weight: bold;
                        text-decoration: none;

                        &:hover {

                            text-decoration: underline;

                        }

                        &.clear-log {

                            color: #a70000;

                        }

                        &.refresh-log {

                            color: #729700;

                        }

                    }

                }

            }
        </style><?php
    
    }

}