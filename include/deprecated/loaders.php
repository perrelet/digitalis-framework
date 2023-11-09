<?php

namespace Digitalis;

trait Can_Load {

    protected $object_groups = [];

    public function autoload ($group_key, $dir_path, $ext = 'php', $instantiate = true) {

        foreach (glob($dir_path . '/*.' . $ext) as $path) {

            $this->load_class($group_key, $path, $instantiate);

        }

        return $this->get_object_group($group_key);

    }

    public function load_class ($group_key, $file_path, $instantiate = true) {

        if (!is_file($file_path)) return false;

        include $file_path;

        if ($instantiate !== false) {
         
            if (!$class_name = $this->extract_class_name($file_path)) return false;
            if (!apply_filters('Digitalis/Instantiate/' . ltrim($class_name, '\\'), true, $file_path)) return false;

            if (!isset($this->object_groups[$group_key])) $this->object_groups[$group_key] = [];

            if ($instantiate === true) {

                $this->object_groups[$group_key][$class_name] = new $class_name();

            } else {

                $call = $class_name . "::" . $instantiate;
                if (is_callable($call)) $this->object_groups[$group_key][$class_name] = call_user_func($call);

            }

            

        }

        return true;

    }

    public function get_object_groups () {

        return $this->object_groups;

    }

    public function get_object_group ($group_key) {

        return isset($this->object_groups[$group_key]) ? $this->object_groups[$group_key] : null;

    }

    public function get_object ($group_key, $class_name) {

        if (!isset($this->object_groups[$group_key]) || !isset($this->object_groups[$group_key][$class_name])) return null;

        return $this->object_groups[$group_key][$class_name];

    }

    //

    protected function extract_class_name ($file) {

        // https://stackoverflow.com/questions/7153000/get-class-name-from-file

        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (!$class) {
            if (feof($fp)) break;

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) continue;

            for (;$i<count($tokens);$i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j=$i+1;$j<count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\'.$tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j=$i+1;$j<count($tokens);$j++) {
                        if ($tokens[$j] === '{') {
                            $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return $namespace ? $namespace . "\\" . $class : $class;

    }

}

trait Has_Components {

    protected $components = [];

    public function get_component_namespace() { return __NAMESPACE__; }

    public function load_components ($path) {

        foreach (glob($path . '/*.component.php') as $component_path) {
            include $component_path;
        }

    }
    
    public function component ($class_name, $params = [], $render = true) {

        $class_name = $this->get_component_namespace() . '\\' . $class_name;

        if (!class_exists($class_name)) return;

        $component = new $class_name($params);
        $component->init();

        if ($render) $component->render();

        $key = $class_name . "-" . $component->get_instance();
        $this->components[$key] = $component;

        return $component;

    }

    public function get_component ($class_name, $instance = 1) {

        $class_name = $this->get_component_namespace() . '\\' . $class_name;
        $key = $class_name . "-" . $instance;

        return isset($this->components[$key]) ? $this->components[$key] : null;

    }

}

trait Has_Integrations {

    use Can_Load;

    public function load_integrations ($path) {

        $this->autoload('integration', $path, 'integration.php', 'get_instance');

    }

    public function get_integration ($class_name) {

        return $this->get_object('integration', $class_name);

    }

    public function get_integrations () {

        return $this->get_object_group('integration');

    }

}

trait Has_Post_Types {

    use Can_Load;

    public function load_post_types ($path) {

        $this->autoload('post-type', $path, 'post-type.php', 'get_instance');

    }

    public function get_post_type ($class_name) {

        return $this->get_object('post-type', $class_name);

    }

    public function get_post_types () {

        return $this->get_object_group('post-type');

    }

}

trait Has_Taxonomies {

    use Can_Load;

    public function load_taxonomies ($path) {

        $this->autoload('taxonomy', $path, 'taxonomy.php', 'get_instance');

    }

    public function get_taxonomy ($class_name) {

        return $this->get_object('taxonomy', $class_name);

    }

    public function get_taxonomies () {

        return $this->get_object_group('taxonomy');

    }

}