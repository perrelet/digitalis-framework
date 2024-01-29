<?php

namespace Digitalis;

abstract class Screen_Table extends Admin_Table {

    use Dependency_Injection;

    protected $screen = 'screen';
    protected $columns;
    protected $priority = 10;

    public function run () {

        if (!is_array($this->screen)) $this->screen = [$this->screen];

        foreach ($this->screen as $screen) {

            add_filter($this->get_columns_hook($screen), [$this, 'columns_wrap'], $this->priority);
            add_filter($this->get_column_hook($screen),  [$this, 'column'],       $this->priority, 3);

        }
    
    }

    protected function get_columns_hook ($screen) {
    
        return "manage_{$screen}_columns";
    
    }

    protected function get_column_hook ($screen) {
    
        return "manage_{$screen}_custom_column";
    
    }

    public function columns_wrap ($columns) {

        $this->columns = &$columns;
        $this->columns($this->columns);

        return $columns;
        
    }

    public function columns (&$columns) {

        // ...

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