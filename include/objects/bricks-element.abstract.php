<?php 

namespace Digitalis;

abstract class Bricks_Element extends \Bricks\Element {

    protected $slug   = 'custom-element';
    protected $folder = 'general';
    protected $view   = View::class;
    protected $fields = [];

    public $icon = 'ion-md-browsers';

    public function __construct ($element = null) {

        $this->name     = $this->slug;
        $this->category = $this->folder;

        parent::__construct($element);

    }

    public function get_label () {

        return 'Custom Element';

    }

    public function render () {

        $params = [];

        if ($this->fields) foreach ($this->fields as $slug => $field) {
        
            if (isset($this->settings[$slug])) $params[$slug] = $this->settings[$slug];
        
        }

        echo "<div {$this->render_attributes( '_root' )}>";

            call_user_func("{$this->view}::render", $params);

        echo "</div>";

    }

    public function set_control_groups () {
    
        $this->control_groups['params'] = [
            'title' => 'Params',
            'tab'   => 'content',
        ];

      }

    public function set_controls () {

        $defaults = call_user_func("{$this->view}::get_defaults");

        if ($this->fields) foreach ($this->fields as $slug => $field) {
        
            if (!is_array($field)) {

                $label = is_null($field) ? ucwords(str_replace('_', ' ', $slug)) : $field;

                $this->controls[$slug] = [
                    'tab'     => 'content',
                    'group'   => 'params',
                    'label'   => $label,
                    'type'    => 'text',
                    'default' => $defaults[$slug] ?? '',
                ];

            } else {

                $this->controls[$slug] = $field;

            }
        
        }

    }

}