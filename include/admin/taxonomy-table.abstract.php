<?php

namespace Digitalis;

abstract class Taxonomy_Table extends Screen_Table {

    protected $taxonomy = 'taxonomy';

    public function run () {

        $this->screen = $this->taxonomy;

        parent::run();

    }

    protected function get_columns_hook ($screen) {
    
        return "manage_edit-{$screen}_columns";
    
    }

}