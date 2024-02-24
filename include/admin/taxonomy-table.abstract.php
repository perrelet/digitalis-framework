<?php

namespace Digitalis;

abstract class Taxonomy_Table extends Screen_Table {

    protected $taxonomy = 'taxonomy';

    public function run () {

        $this->slug = $this->taxonomy;

        parent::run();

    }

    protected function get_columns_hook ($slug) {
    
        return "manage_edit-{$slug}_columns";
    
    }

    protected function get_sortable_hook ($slug) {
    
        return "manage_edit-{$slug}_sortable_columns";
    
    }

}