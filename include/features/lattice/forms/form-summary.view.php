<?php

namespace Digitalis;

// What a refused form says above its fields: announced, with a link to each field at fault.
class Form_Summary extends View {

    protected static $defaults = [
        'message'  => '',
        'problems' => [],
        'links'    => [],
    ];

    public function view () {

        echo "<div class='form-summary' role='alert'>";

        if ($this['message']) echo '<p>' . esc_html($this['message']) . '</p>';

        if ($this['problems']) {

            echo '<ul>';

            foreach ($this['problems'] as $name => $problem) echo '<li>' . (isset($this['links'][$name]) ? "<a href='#" . esc_attr($this['links'][$name]) . "'>" . esc_html($problem) . '</a>' : esc_html($problem)) . '</li>';

            echo '</ul>';

        }

        echo '</div>';

    }

}
