<?php

namespace Digitalis;

interface Page_Builder_Interface {

    /**
     * Condition to determine if the builder has been initialised.
     * The builder manager needs to know which builders are active before they are loaded. This allows hooks to be registered that are applied by the builder during initialisation.
     * It's therefore important to avoid checking against defined constants, functions or classes as these are only available when the plugin / theme files are loaded.
     * Instead, use `is_plugin_active` for plugins and `wp_get_theme` for themes.
     *
     * @return boolean
     */
    public static function instance_condition () : bool;

    /**
     * Returns true when the builder is open.
     *
     * @return boolean
     */
    public function is_backend () : bool;

    /**
     * Returns true when the builder is open and we are inside of the builder preview (normally an iframe).
     *
     * @return boolean
     */
    public function is_backend_content () : bool;

    /**
     * Returns true when the builder is open and we are inside the builder user interface.
     *
     * @return boolean
     */
    public function is_backend_ui () : bool;

    /**
     * Return array of classes saved within the builder
     * 
     * @return array {
     *     @type string                $name       Name of the css class.
     *     @type string                $builder    Slug of the builder that defines this class.
     *     @type string                $id         Optional. Unique ID representing the class.
     *     @type array<string,string>  $styles     Optional. List of css property => value pairs associated with the class.
     *     @type string                $folder     Optional. ID of folder this class belongs to.
     * }
     */
    public function get_classes () : array;

    /**
     * Add css classes to the builder.
     * 
     * @param array $classes<string|array> Entries may be either class names or full class items as described in 'Builder_Interface::get_classes'.
     * 
     * @param array $args {
     *     @type bool $save      Optional. Whether to permanently save the classes to the database. Default false.
     *     @type bool $overwrite Optional. Whether to overwrite the classes if they already exists. Default false.
     *     @type bool $lock      Optional. Whether to lock the class from editting in the builder. Default true.
     *     @type string $folder  Optional. The default folder the classes belongs to. Default false.
     * }
     * 
     * @return WP_Error|bool true on success, WP_Error or false on error.
     * 
     */
    public function add_classes ($classes, $args = []);

    /**
     * Remove css classes from the builder.
     * 
     * @param array $classes<string> List of class names to be removed.
     * 
     * @return WP_Error|bool true on success, WP_Error or false on error.
     * 
     */
    public function remove_classes ($classes, $args = []);

    /**
     * Return array of colors
     * 
     * @return array {
     *     @type string         $name       Name of the css color.
     *     @type string         $value      Value of the css color.
     *     @type string         $builder    Slug of the builder that defines this color.
     *     @type string         $id         Optional. Unique ID representing the variable.
     *     @type array|string   $folder     Optional. ID of folder this variable belongs to.
     * }
     */
    public function get_colors () : array;

    public function add_colors ($colors, $args = []);
    public function remove_colors ($colors, $args = []);

    /**
     * Return array of css variables saved within the builder
     * 
     * @return array {
     *     @type string     $name       Name of the css variable excluding the '--' prefix.
     *     @type string     $value      Value of the css variable.
     *     @type string     $builder    Slug of the builder that defines this variable.
     *     @type string     $id         Optional. Unique ID representing the variable.
     *     @type string     $folder     Optional. ID of folder this variable belongs to.
     * }
     */
    public function get_variables () : array;

    /**
     * Add css variables to the builder.
     * 
     * @param array $variables<string,string|array> Entries may be either variable => value pairs, or full variable items as described in 'Builder_Interface::get_variables'.
     * 
     * @param array $args {
     *     @type bool $save           Optional. Whether to permanently save the variables to the database. Default false.
     *     @type bool $overwrite      Optional. Whether to overwrite the variables if they already exists. Default false.
     *     @type bool|string $folder  Optional. The default folder the variables belongs to. Default false.
     * }
     * 
     * @return WP_Error|bool true on success, WP_Error or false on error.
     * 
     */
    public function add_variables ($variables, $args = []);

    /**
     * Remove css variables from the builder.
     * 
     * @param array $variables<string> List of variable names to be removed.
     * 
     * @return WP_Error|bool true on success, WP_Error or false on error.
     * 
     */
    public function remove_variables ($variables, $args = []);

    public function get_variable_folders () : array;
    public function add_variable_folders ($folders, $args = []);
    public function remove_variable_folders ($folders, $args = []);

}