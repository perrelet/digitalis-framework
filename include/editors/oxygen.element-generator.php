<?php

namespace Digitalis;

class Oxygen_Element_Generator extends Editor_Element_Generator {

    protected function get_control_code (array $controls, $level = 0, $section = '$this') {

        $code = new PHP_Code;

        foreach ($controls as $i => $control) {

            if (isset($control['children'])) {

                $code->line("\$section = \$this->addControlSection('{$control['name']}', __('{$control['label']}'), '{$control['icon']}', \$this);" . PHP_EOL);
                $code->append($this->get_control_code($control['children'], $level++, '$section'));

            } else {

                $code->line("{$section}->addOptionControl(" . $code->export_var($control, true) . ");" . PHP_EOL);

            }

        }

        return $code;

    }

    protected function generate_php_code (string $class_name, $view_class) : string {

        $name = $view_class::get_name();
        $slug = $this->generate_slug_name($view_class);

        $control_code = $this->get_control_code($this->get_controls($view_class));

        //$
        //foreach ($view_class::get_controls() as $control)

        return <<<PHP
<?php

class {$class_name} extends \OxyEl {

    public function name () {

        return __('{$name}', 'text-domain');

    }

    public function slug () {

        return '{$slug}';

    }

    public function controls () {

{$control_code}

    }

    public function render (\$options, \$defaults, \$content) {

        // dlog(\$options);
        // dlog(\$defaults);
        // dlog(\$content);

        // \$defaults['content'] = \$content;
        // {$view_class}::render(\$defaults);

    }

}
PHP;

    }

}