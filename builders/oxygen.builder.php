<?php

namespace Digitalis;

class Oxygen extends Builder {

    public static function get_name () {

        return "Oxygen Builder";

    }

    public static function is_loaded () {

        return defined("CT_VERSION");

    }

    public static function is_backend () {

        return self::is_loaded() && defined("SHOW_CT_BUILDER");

    }

    public static function is_backend_iframe () {

        return self::is_backend() && defined("OXYGEN_IFRAME");

    }

}