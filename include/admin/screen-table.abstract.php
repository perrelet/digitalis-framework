<?php

namespace Digitalis;

abstract class Screen_Table extends Admin_Table {

    use Dependency_Injection;

    protected $slug     = '';
    protected $priority = 10;

    protected $columns;
    protected $sortable;

    public function run () {

        if (!is_array($this->slug)) $this->slug = [$this->slug];

        foreach ($this->slug as $slug) {

            $columns_hook     = $this->get_columns_hook($slug);
            $column_hook      = $this->get_column_hook($slug);
            $sortable_hook    = $this->get_sortable_hook($slug);
            $row_actions_hook = $this->get_row_actions_hook($slug);

            if ($columns_hook)     add_filter($columns_hook,     [$this, 'columns_wrap'],     $this->priority);
            if ($column_hook)      add_filter($column_hook,      [$this, 'column'],           $this->priority, 3);
            if ($sortable_hook)    add_filter($sortable_hook,    [$this, 'sortable_wrap'],    $this->priority);
            if ($row_actions_hook) add_filter($row_actions_hook, [$this, 'row_actions_wrap'], $this->priority, 2);

        }
    
    }
    
    public function columns (&$columns) {

        // ...

    }

    public function sortable (&$columns) {

        // ...

    }

    public function row_actions (&$actions) {
    
        // ..
    
    }

    protected function get_columns_hook ($slug) {
    
        return "manage_{$slug}_columns";
    
    }

    protected function get_column_hook ($slug) {
    
        return "manage_{$slug}_custom_column";
    
    }

    protected function get_sortable_hook ($slug) {
    
        return "manage_{$slug}_sortable_columns";
    
    }

    protected function get_row_actions_hook ($slug) {
    
        return false;
    
    }

    public function columns_wrap ($columns) {

        $this->columns = &$columns;
        $this->columns($this->columns);

        return $columns;
        
    }

    public function sortable_wrap ($columns) {

        $this->sortable = &$columns;
        $this->sortable($this->sortable);

        return $columns;
        
    }

    public function row_actions_wrap ($actions, $object) {

        return $actions;
    
    }

    protected function remove_column ($key) {
    
        if (isset($this->columns[$key])) unset($this->columns[$key]);
    
    }

    protected function insert_column ($entry, $position = 0, $after = true) {

        if (!is_int($position)) $position = array_search($position, array_keys($this->columns));

        if ($position === false) return;
        if (!is_array($entry))   return;
        if ($after) $position++;

        $this->columns =
            array_slice($this->columns, 0, $position, true) +
            $entry +
            array_slice($this->columns, $position, count($this->columns) - 1, true)
        ;
    
    }

    protected function append_column ($entry, $label = null) {

        if (!is_array($entry)) $entry = [$entry => $label];
        $this->columns += $entry;

    }

    protected function prepend_column ($entry, $label) {

        if (!is_array($entry)) $entry = [$entry => $label];
        $this->insert_column($entry, 1, false);

    }

    public function column ($output, $column, $object_id) {

        $call = [$this, "column_" . str_replace('-', '_', $column)];

        return is_callable($call) ? static::inject($call, [$output, $object_id]) : $output;

    }

}