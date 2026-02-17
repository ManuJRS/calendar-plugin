<?php
if (!defined('ABSPATH')) exit;

function apl_register_cpt() {
  register_post_type('apl_appointment', [
    'labels' => [
      'name' => 'Citas',
      'singular_name' => 'Cita',
    ],
    'public' => false,
    'show_ui' => true,
    'menu_icon' => 'dashicons-calendar-alt',
    'supports' => ['title'],
  ]);
}
add_action('init', 'apl_register_cpt');
