<?php

namespace Digitalis;

use Digitalis\Component\Table;

abstract class Profile_Section extends Feature {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'id';

    protected $id       = 'digitalis-profile-section';
    protected $title    = 'Digitalis Profile Section';
    protected $priority = 10;
    protected $view     = null;
    protected $callback = null;

    public function get_hooks () {

        return [
            'show_user_profile' => ['render_section', $this->get_priority()],
            'edit_user_profile' => ['render_section', $this->get_priority()],
        ];

    }

    public function render_section ($wp_user) {

        if (!$this->condition($wp_user)) return;

        $this->render_wrap($wp_user);

    }

    public function condition ($wp_user) {

        return true;

    }

    public function render ($wp_user) {

        if (!($rows = $this->get_rows($wp_user))) return;

        $this->render_heading();

        echo new Table([
            'rows'      => $rows,
            'classes'   => ['form-table'],
            'first_row' => false,
            'first_col' => true,
        ]);

    }

    public function get_rows ($wp_user) {

        return [];

    }

    protected function render_heading () {

        if ($title = $this->get_title()) {

            $id = esc_attr($this->get_id());
            echo "<h2 id='{$id}'>" . esc_html($title) . "</h2>";

        }

    }

    public function render_wrap (\WP_User $wp_user) {

        if ($this->view) {

            $view_class = $this->view;
            echo new $view_class(['wp_user' => $wp_user]);

        } else {

            if (is_null($this->callback))     $this->callback = [$this, 'render'];
            if (is_callable($this->callback)) static::inject($this->callback, [$wp_user]);

        }

    }

    //

    public function get_id () {

        return $this->id;

    }

    public function get_title () {

        return __($this->title);

    }

    public function get_priority () {

        return $this->priority;

    }

}
