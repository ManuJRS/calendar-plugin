<?php
/**
 * Plugin Name: Appointments Lite (Calendar + Woo Checkout)
 * Description: Calendario de citas colapsado/expandible + checkout WooCommerce + bloqueo de fechas.
 * Version: 1.0.0
 * Author: Manu
 */

if (!defined('ABSPATH')) exit;

define('APL_PATH', plugin_dir_path(__FILE__));
define('APL_URL', plugin_dir_url(__FILE__));
define('APL_VER', '1.0.2');

require_once APL_PATH . 'includes/helpers.php';
require_once APL_PATH . 'includes/cpt.php';
require_once APL_PATH . 'includes/settings.php';
require_once APL_PATH . 'includes/ajax.php';
require_once APL_PATH . 'includes/woocommerce.php';
require_once APL_PATH . 'includes/cron.php';

register_activation_hook(__FILE__, function () {
  apl_register_cpt();
  flush_rewrite_rules();

  apl_schedule_cron();
  apl_maybe_create_woo_product();
});

register_deactivation_hook(__FILE__, function () {
  apl_unschedule_cron();
  flush_rewrite_rules();
});

add_action('wp_enqueue_scripts', function () {
  if (!is_singular() && !is_front_page() && !is_home()) return;

  wp_enqueue_style('apl-front', APL_URL . 'assets/css/front.css', [], APL_VER);
  wp_enqueue_script('apl-front', APL_URL . 'assets/js/front.js', ['jquery'], APL_VER, true);

  wp_localize_script('apl-front', 'APL', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('apl_nonce'),
    'tz' => apl_get_timezone_string(),
    'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
  ]);
});

add_shortcode('apl_calendar', 'apl_render_calendar_shortcode');
