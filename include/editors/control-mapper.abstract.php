<?php

namespace Digitalis;

abstract class Control_Mapper implements Control_Mapper_Interface {

    protected string $editor_slug;

    public function __construct (string $editor_slug) {

        $this->editor_slug = $editor_slug;

    }

    public function map_controls (array $controls) : array {

        $mapped = [];

        foreach ($controls as $name => $control) {

            if ($editors = (array) ($control['editors'] ?? [])) {

                $exclude = array_filter($editors, fn ($e) => str_starts_with($e, "!"));
                $include = array_diff($editors, $exclude);

                if ($exclude) {

                    if (in_array('!' . $this->editor_slug, $exclude)) continue;

                } else {

                    if (!in_array($this->editor_slug, $include)) continue;

                }

            }

            if (!isset($control['name']) && is_string($name)) $control['name'] = $name;

            if (isset($control['children'])) {

                $group             = $this->map_group($control);
                $group['children'] = $this->map_controls($control['children'] ?? []);
                $mapped[]          = $group;

            } else {

                $mapped[] = $this->map_control($control);

            }

        }

        return $mapped;

    }

    protected function get_value (array $control, $key, $default = '') {

        if (isset($control["{$this->editor_slug}_{$key}"])) return $control["{$this->editor_slug}_{$key}"];

        if (isset($control[$key])) {

            return $control[$key];

        } else {

            if (is_callable($default)) $default = $default();
            return $default;

        }

    }

    protected function generate_label_from_name (string $name) : string {

        return ucfirst($name);

    }

}