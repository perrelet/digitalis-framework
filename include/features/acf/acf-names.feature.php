<?php

// Display ACF field names next to labels in the admin, with click-to-copy.

namespace Digitalis;

class ACF_Names extends Feature {

    public function __construct () {

        if (!class_exists('acf') || !is_admin()) return;

        $this->add_filter('acf/get_field_label', 'get_field_label');

    }

    public function get_field_label ($label, $field, $context) {

        if (!current_user_can('administrator')) return $label;

        $name = $field['_name'] ?? '';

        $label = "<div class='digit-acf-label'>{$label}</div>"
               . "<div class='digit-acf-name'><span>name: </span><span>{$name}</span></div>";

        static $styled;
        if (!$styled) {
            $styled = true;
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.digit-acf-name > span:last-child').forEach(function (el) {
                        el.addEventListener('click', () => {
                            navigator.clipboard.writeText(el.innerText);
                            el.parentElement.classList.add('data-copied');
                            setTimeout(() => el.parentElement.classList.remove('data-copied'), 1000);
                        });
                    });
                });
            </script>
            <style>
                @keyframes digit-acf-name { 0% { opacity: 0.25; } 100% { opacity: 1; } }
                .acf-label.acf-label > label { display: flex; flex-direction: row; justify-content: space-between; }
                .digit-acf-name > span { opacity: 0.25; }
                .digit-acf-name > span:last-child { cursor: pointer; display: inline-block; }
                .digit-acf-name > span:last-child:hover { opacity: 1; }
                .digit-acf-name.data-copied > span:last-child { opacity: 1; color: #0ac100; animation: digit-acf-name 0.75s ease-in-out infinite; }
            </style>
            <?php
        }

        return $label;

    }

}
