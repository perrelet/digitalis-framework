<?php

namespace Digitalis;

class Menu extends Component {

    protected static $template = 'menu';

    protected static $defaults = [

        // Items / sources
        'items'   => [],
        'source'  => null,  // menu id / slug / name → Nav_Menu::get_instance()
        'adapter' => null,  // stage 5 — callable

        // Landmark + label
        'aria_label' => 'Menu',
        'landmark'   => true,

        // Pattern + layout
        'pattern'     => 'disclosure',  // 'disclosure' | 'menubar'
        'orientation' => 'horizontal',  // 'horizontal' | 'vertical'

        // Interaction (read by JS at stage 2)
        'trigger'    => ['click'],
        'multi_open' => false,

        // Active state (computed at stage 3)
        'expand_ancestor'     => false,
        'ancestor_taxonomies' => null,
        'match_current'       => true,
        'match_ancestor'      => true,

        // i18n
        'toggle_label_format' => 'Toggle %s submenu',

        // Internal / coercion-time
        'id'           => null,
        'level'        => 1,
        'parent_li_id' => null,
        'item_class'   => Menu_Item::class,
        'menu_class'   => Menu::class,

        // Component-base
        'tag'      => 'ul',
        'list_tag' => 'ul',

    ];

    protected static $skip_inject = ['adapter', 'item_class', 'menu_class'];
    protected static $elements    = ['list'];

    const PROPAGATING_PARAMS = [
        'pattern', 'trigger', 'multi_open', 'expand_ancestor',
        'ancestor_taxonomies', 'match_current', 'match_ancestor',
        'toggle_label_format', 'item_class', 'menu_class',
    ];

    public function params (&$p) {

        // Step 1: source loading — load via Nav_Menu when 'source' is set and items is empty.
        if ($p['source'] !== null && empty($p['items'])) {
            $p['items'] = Nav_Menu::get_instance($p['source'])?->get_items_tree() ?? [];
        }

        // Step 2: adapter mapping — deferred to stage 5.

        // Step 3: Eagerly coerce the full tree.
        foreach ($p['items'] as &$entry) {

            if ($entry instanceof Menu_Item) continue;
            if (!is_array($entry))           continue;

            $entry = $this->coerce_item($entry, $p);

        }
        unset($entry);

        // Step 4: Set each item's level.
        foreach ($p['items'] as $item) {

            if ($item instanceof Menu_Item) $item->level = $p['level'];

        }

        // Step 5: Active-state pipeline — root only.
        if ($p['level'] === 1) {

            Menu_Active_State::resolve($p['items'], [
                'ancestor_taxonomies' => $p['ancestor_taxonomies'],
                'match_current'       => $p['match_current'],
                'match_ancestor'      => $p['match_ancestor'],
            ]);

        }

        // Step 6: ID derivation.
        if ($p['id'] === null) {

            $p['id'] = ($p['level'] === 1)
                ? "menu-" . $this->get_index()
                : "{$p['parent_li_id']}-submenu";

        }

        // Pass per-child config to each Menu_Item.
        foreach ($p['items'] as $i => $item) {

            if ($item instanceof Menu_Item) {

                $item->item_index          = $i;
                $item->parent_ul_id        = $p['id'];
                $item->toggle_label_format = $p['toggle_label_format'];
                $item->expand_ancestor     = $p['expand_ancestor'];

            }

        }

        // Step 7: Element setup. Tag depends on landmark.
        $is_landmark_root = ($p['level'] === 1) && $p['landmark'];
        $p['tag']         = $is_landmark_root ? 'nav' : 'ul';

        // When landmark is on, the <ul> (list element) carries the id — not the <nav>.
        // Stash the id and null $p['id'] so Component::create_element doesn't put it on <nav>.
        $list_id = $p['id'];
        if ($is_landmark_root) $p['id'] = null;

        parent::params($p);

        if ($is_landmark_root) {

            // <nav class='menu' aria-label='...'> wraps <ul role='list' id data-*>
            $p['element']->add_class('menu');
            $p['element']['aria-label'] = $p['aria_label'];

            $p['list']['role']             = 'list';
            $p['list']['id']               = $list_id;
            $p['list']['data-pattern']     = $p['pattern'];
            $p['list']['data-orientation'] = $p['orientation'];
            $p['list']['data-trigger']     = implode(' ', $p['trigger']);
            $p['list']['data-multi-open']  = $p['multi_open'] ? 'true' : 'false';

            $p['id'] = $list_id;  // restore for downstream consumers

        } else {

            // <ul> is the root or a nested submenu.
            $p['element']['role'] = 'list';
            $p['element']['id']   = $p['id'];
            $p['element']['data-orientation'] = $p['orientation'];

            if ($p['level'] === 1) {

                // landmark off at level 1: <ul> carries pattern attrs + .menu class.
                $p['element']->add_class('menu');
                $p['element']['data-pattern']    = $p['pattern'];
                $p['element']['data-trigger']    = implode(' ', $p['trigger']);
                $p['element']['data-multi-open'] = $p['multi_open'] ? 'true' : 'false';

            }

            // Nested (level > 1): only role, id, data-orientation. Done.

        }

    }

    protected function coerce_item ($entry, $parent_params) {

        // Recurse into the entry's submenu array if present.
        if (isset($entry['submenu']) && is_array($entry['submenu'])) {

            $submenu_array = $entry['submenu'];

            // Coercion-time transformations.
            $submenu_array['level']    = $parent_params['level'] + 1;
            $submenu_array['landmark'] = false;

            if (!isset($submenu_array['orientation'])) $submenu_array['orientation'] = 'vertical';

            // Propagate tree-wide params (only when not overridden by the submenu array).
            foreach (self::PROPAGATING_PARAMS as $key) {

                if (!array_key_exists($key, $submenu_array) && array_key_exists($key, $parent_params)) {

                    $submenu_array[$key] = $parent_params[$key];

                }

            }

            // Eagerly coerce the nested Menu's items so the full tree is ready
            // before the root's active-state pipeline runs.
            if (isset($submenu_array['items']) && is_array($submenu_array['items'])) {

                $coerced = [];

                foreach ($submenu_array['items'] as $sub_entry) {

                    if      ($sub_entry instanceof Menu_Item) $coerced[] = $sub_entry;
                    else if (is_array($sub_entry))            $coerced[] = $this->coerce_item($sub_entry, $submenu_array);

                }

                $submenu_array['items'] = $coerced;

            }

            $menu_class       = $entry['menu_class'] ?? $parent_params['menu_class'];
            $entry['submenu'] = new $menu_class($submenu_array);

        }

        $item_class    = $parent_params['item_class'];
        $menu_item     = new $item_class($entry);
        $menu_item->level = $parent_params['level'];

        return $menu_item;

    }

    public function condition () {

        return count($this['items']) > 0;

    }

    public function before_first () {

        wp_enqueue_style(
            'lattice-menu',
            DIGITALIS_FRAMEWORK_URI . 'assets/css/menu.css',
            [],
            DIGITALIS_FRAMEWORK_VERSION
        );

        wp_enqueue_script(
            'lattice-menu',
            DIGITALIS_FRAMEWORK_URI . 'assets/js/menu.js',
            [],
            DIGITALIS_FRAMEWORK_VERSION,
            ['in_footer' => true]
        );

    }

}
