<?php

namespace Lattice\Component;

class Table extends \Lattice\Component {

    protected static $template = 'table';

    protected static $defaults = [
        'rows'           => [],
        'first_row'      => true,
        'first_col'      => false,
        'last_col'       => false,
        'last_row'       => false,
        'data_labels'    => false,
        'row_classes'    => [],
        'row_atts'       => [],
        'col_classes'    => [],
        'col_atts'       => [],
        'cell_atts'      => [],
        'attributes'     => [
            'role' => 'presentation',
        ],
    ];

    protected static $merge = ['row_classes', 'row_atts', 'col_classes', 'col_atts'];

    public function params (&$p) {

        if (!empty($p['data_titles']) && empty($p['data_labels'])) $p['data_labels'] = $p['data_titles']; // Back-compat: `data_titles` was renamed to `data_labels`

        $this->generate_col_atts($p);
        $this->generate_row_atts($p);
        $this->generate_cell_atts($p);

        parent::params($p);

    }

    public function generate_col_atts (&$p) {
    
        $this->generate_shelf_classes($p, 'col');
        $this->gather_shelf_atts($p, 'col');
        $this->generate_shelf_atts($p, 'col');
    
    }

    public function generate_row_atts (&$p) {
    
        $this->generate_shelf_classes($p, 'row');
        $this->gather_shelf_atts($p, 'row');
        $this->generate_shelf_atts($p, 'row');
    
    }

    public function generate_cell_atts (&$p) {

        $atts = [];

        if ($p["cell_atts"]) foreach ($p["cell_atts"] as $i => $row) {

            if ($row) foreach ($row as $j => $cell_atts) {
            
                if (!isset($atts[$i])) $atts[$i] = [];

                $atts[$i][$j] = ($s = (string) new \Lattice\Attributes((array) $cell_atts)) ? " {$s}" : '';
            
            }

        }
        
        $p["cell_atts"] = $atts;
    
    }

    public function generate_shelf_classes (&$p, $shelf = 'col') {
    
        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as &$classes) {
        
            if (!is_array($classes)) $classes = [$classes];
            $classes = implode(' ', $classes);
        
        }
    
    }

    public function gather_shelf_atts (&$p, $shelf = 'col') {

        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as $i => &$classes) {
        
            if (!isset($p["{$shelf}_atts"][$i])) $p["{$shelf}_atts"][$i] = [];

            $p["{$shelf}_atts"][$i]['class'] = $classes;
        
        }

        if ($p['rows'] && ($shelf == 'col') && $p['data_labels']) {

            $attribute = is_string($p['data_labels']) ? $p['data_labels'] : 'data-label';

            foreach ($p['rows'][0] as $i => $cell) $p["{$shelf}_atts"][$i][$attribute] = wp_strip_all_tags((string) $cell);

        }
    
    }

    public function generate_shelf_atts (&$p, $shelf = 'col') {

        $atts = [];

        if ($p["{$shelf}_atts"]) foreach ($p["{$shelf}_atts"] as $i => $shelf_atts) {

            $atts[$i] = ($s = (string) new \Lattice\Attributes((array) $shelf_atts)) ? " {$s}" : ''; // Leading space: the template concatenates these strings.

        }
        
        $p["{$shelf}_atts"] = $atts;

    }

    public function condition () {
        
        return is_array($this->rows);
    
    }

    public function add_rows ($rows, $class = [], $atts = []) {
    
        foreach ($rows as $row) if (is_array($row) && isset($row['row'])) {

            $this->add_row(
                $row['row'],
                $row['class'] ?? ($row['classes']    ?? ($class[$i] ?? [])),
                $row['atts']  ?? ($row['attributes'] ?? ($atts[$i]  ?? [])),
            );

        } else {

            $this->add_row($row);

        }
    
    }

    public function add_row ($row, $class = [], $atts = []) {

        $i = count($this->rows);

        $this->rows[$i] = $row;

        $class = (array) $class;
        $atts  = (array) $atts;

        if ($class) $this->row_classes[$i] = array_merge($this->row_classes[$i] ?? [], $class);
        if ($atts)  $this->row_atts[$i]    = array_merge($this->row_atts[$i]    ?? [], $atts);
    
    }

    public function remove_headers () {
    
        $this->first_row = false;
        $this->first_col = false;
        $this->last_col  = false;
        $this->last_row  = false;
    
    }

}