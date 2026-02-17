<?php
if (!defined('ABSPATH')) exit;

function apl_maybe_create_woo_product(): int {
  if (!class_exists('WooCommerce')) return 0;

  $saved = (int) apl_opt('woo_product_id', 0);
  if ($saved && get_post($saved)) return $saved;

  $product = new WC_Product_Simple();
  $product->set_name('Reserva de cita');
  $product->set_status('publish');
  $product->set_catalog_visibility('hidden');
  $product->set_virtual(true);
  $product->set_price('100');
  $product->set_regular_price('100');
  $id = $product->save();

  if ($id) {
    $opts = get_option('apl_settings', []);
    $opts['woo_product_id'] = $id;
    update_option('apl_settings', $opts);
    return (int)$id;
  }

  return 0;
}

add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
  if (!empty($cart_item['apl_start_local'])) {
    $item_data[] = ['name' => 'Cita', 'value' => esc_html($cart_item['apl_start_local'])];
  }
  return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
  if (!empty($values['apl_appointment_id'])) {
    $item->add_meta_data('apl_appointment_id', (int)$values['apl_appointment_id'], true);
    $item->add_meta_data('apl_start_local', sanitize_text_field($values['apl_start_local'] ?? ''), true);
    $item->add_meta_data('apl_end_local', sanitize_text_field($values['apl_end_local'] ?? ''), true);
    $item->add_meta_data('apl_slot_key', sanitize_text_field($values['apl_slot_key'] ?? ''), true);
  }
}, 10, 4);

add_action('woocommerce_order_status_changed', function ($order_id, $old, $new) {
  if (!in_array($new, ['processing', 'completed'], true)) return;

  $order = wc_get_order($order_id);
  if (!$order) return;

  foreach ($order->get_items() as $item) {
    $appointment_id = (int) $item->get_meta('apl_appointment_id');
    if (!$appointment_id) continue;

    $status = (string)get_post_meta($appointment_id, 'status', true);
    if ($status !== 'pending') continue;

    $expires = (string)get_post_meta($appointment_id, 'expires_at', true);
    if ($expires && apl_now()->format('Y-m-d H:i:s') > $expires) {
      update_post_meta($appointment_id, 'status', 'expired');
      continue;
    }

    update_post_meta($appointment_id, 'status', 'confirmed');
    $start_local = (string) get_post_meta($appointment_id, 'start_local', true);
    $tz = new DateTimeZone(apl_get_timezone_string());
    $start_dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $start_local, $tz);
    if ($start_dt) {
  update_post_meta($appointment_id, 'slot_key', apl_slot_key($start_dt));
}

    update_post_meta($appointment_id, 'order_id', (int)$order_id);

    update_post_meta($appointment_id, 'customer_email', $order->get_billing_email());
    update_post_meta($appointment_id, 'customer_name', trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
  }
}, 10, 3);

add_action('woocommerce_order_status_changed', function ($order_id, $old, $new) {
  if (!in_array($new, ['cancelled', 'failed', 'refunded'], true)) return;
  $order = wc_get_order($order_id);
  if (!$order) return;

  foreach ($order->get_items() as $item) {
    $appointment_id = (int) $item->get_meta('apl_appointment_id');
    if (!$appointment_id) continue;

    $status = (string)get_post_meta($appointment_id, 'status', true);
    if ($status === 'pending') {
      update_post_meta($appointment_id, 'status', 'cancelled');
      update_post_meta($appointment_id, 'order_id', (int)$order_id);
    }
  }
}, 10, 3);
