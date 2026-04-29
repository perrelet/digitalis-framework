<?php

namespace Digitalis;

class Request_Resolver extends Singleton {

    public function resolve_layout () {

        return $this->resolve(Layout::class, true);

    }

    public function resolve_page () {

        return $this->resolve(Page_View::class);

    }

    protected function resolve (string $base_class, bool $return_class = false) {

        $contexts   = $this->get_current_context();
        $queried    = get_queried_object();
        $is_tax     = is_tax() || is_category() || is_tag();
        $taxonomy   = $is_tax && $queried ? $queried->taxonomy : null;
        $term       = $is_tax && $queried ? $queried->slug     : null;
        $post_types = $is_tax && $taxonomy
            ? get_taxonomy($taxonomy)->object_type
            : [(string) get_post_type(get_queried_object_id())];

        $candidates = array_filter(
            View::get_loaded_views(),
            fn ($class) => is_subclass_of($class, $base_class) && ($class !== $base_class)
                && (!$class::get_context()   || array_intersect((array) $class::get_context(), $contexts))
                && (!$class::get_post_type() || array_intersect((array) $class::get_post_type(), $post_types))
                && (!$class::get_taxonomy()  || in_array($taxonomy, (array) $class::get_taxonomy()))
                && (!$class::get_term()      || in_array($term, (array) $class::get_term()))
        );

        usort($candidates, fn ($a, $b) => $b::get_specificity($contexts) <=> $a::get_specificity($contexts));

        foreach ($candidates as $class) {
            $view = new $class();
            if ($view->condition()) return $return_class ? $class : $view;
        }

        return null;

    }

    protected function get_current_context () {

        $contexts = [];

        $is_tax = is_tax() || is_category() || is_tag();

        if (is_404())        $contexts[] = '404';
        if (is_search())     $contexts[] = 'search';
        if (is_front_page()) $contexts[] = 'front_page';
        if (is_home())       $contexts[] = 'home';
        if (is_page())       $contexts[] = 'page';
        if (is_singular())   $contexts[] = 'single';
        if ($is_tax)         $contexts[] = 'taxonomy';
        if (is_author())     $contexts[] = 'author';
        if (is_archive())    $contexts[] = 'archive';

        return $contexts;

    }

}
