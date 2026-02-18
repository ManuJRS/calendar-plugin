<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_apl_get_month', 'apl_ajax_get_month');
add_action('wp_ajax_nopriv_apl_get_month', 'apl_ajax_get_month');

add_action('wp_ajax_apl_get_day_slots', 'apl_ajax_get_day_slots');
add_action('wp_ajax_nopriv_apl_get_day_slots', 'apl_ajax_get_day_slots');

add_action('wp_ajax_apl_hold_and_checkout', 'apl_ajax_hold_and_checkout');
add_action('wp_ajax_nopriv_apl_hold_and_checkout', 'apl_ajax_hold_and_checkout');

function apl_ajax_get_month() {
  check_ajax_referer('apl_nonce', 'nonce');

  $ym = sanitize_text_field($_POST['ym'] ?? '');
  if (!preg_match('/^\d{4}\-\d{2}$/', $ym)) {
    wp_send_json_error(['message' => 'Mes inválido.']);
  }

  $tz = new DateTimeZone(apl_get_timezone_string());
  $first = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01', $tz);
  if (!$first) wp_send_json_error(['message' => 'Fecha inválida.']);

  $daysInMonth = (int)$first->format('t');

  $days = [];
  for ($i = 1; $i <= $daysInMonth; $i++) {
    $day = $first->setDate((int)$first->format('Y'), (int)$first->format('m'), $i);
    $slots = apl_get_day_slots($day);
    $days[] = [
      'date' => $day->format('Y-m-d'),
      'isWorking' => apl_is_working_day($day),
      'availableCount' => count($slots),
    ];
  }

  wp_send_json_success(['days' => $days]);
}

function apl_ajax_get_day_slots() {
  check_ajax_referer('apl_nonce', 'nonce');

  $date = sanitize_text_field($_POST['date'] ?? '');
  if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date)) {
    wp_send_json_error(['message' => 'Fecha inválida.']);
  }

  $tz = new DateTimeZone(apl_get_timezone_string());
  $day = DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
  if (!$day) wp_send_json_error(['message' => 'Fecha inválida.']);

  $slots = apl_get_day_slots($day);
  wp_send_json_success(['slots' => $slots]);
}

function apl_ajax_hold_and_checkout() {
  check_ajax_referer('apl_nonce', 'nonce');

  if (!class_exists('WooCommerce')) {
    wp_send_json_error(['message' => 'WooCommerce no está activo.']);
  }

  $start_local = sanitize_text_field($_POST['start_local'] ?? '');
  $end_local = sanitize_text_field($_POST['end_local'] ?? '');

if (!$start_local || !$end_local) {
  wp_send_json_error(['message' => 'Datos incompletos.']);
}

// Recalcular slot_key canónico desde start_local (NO usar el que viene del cliente)
$tz = new DateTimeZone(apl_get_timezone_string());
$start_dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $start_local, $tz);

if (!$start_dt) {
  wp_send_json_error(['message' => 'Fecha/hora inválida.']);
}

$slot_key = apl_slot_key($start_dt);

  $existing = apl_find_existing_slot($slot_key);
  if ($existing) {
    wp_send_json_error(['message' => 'Ese horario ya fue tomado. Elige otro.']);
  }

  $hold_minutes = (int) apl_opt('hold_minutes', 15);
  $expires_at = apl_now()->modify("+{$hold_minutes} minutes")->format('Y-m-d H:i:s');

  $post_id = wp_insert_post([
    'post_type' => 'apl_appointment',
    'post_status' => 'publish',
    'post_title' => 'Cita ' . $start_local,
  ]);

  if (is_wp_error($post_id) || !$post_id) {
    wp_send_json_error(['message' => 'No se pudo crear la reserva.']);
  }

  update_post_meta($post_id, 'slot_key', $slot_key);
  update_post_meta($post_id, 'start_local', $start_local);
  update_post_meta($post_id, 'end_local', $end_local);
  update_post_meta($post_id, 'status', 'pending');
  update_post_meta($post_id, 'expires_at', $expires_at);

  $product_id = (int) apl_opt('woo_product_id', 0);
  if (!$product_id) $product_id = apl_maybe_create_woo_product();

  if (!$product_id) {
    wp_send_json_error(['message' => 'No hay producto de WooCommerce configurado.']);
  }

  WC()->cart->empty_cart();

  $cart_item_data = [
    'apl_appointment_id' => $post_id,
    'apl_slot_key' => $slot_key,
    'apl_start_local' => $start_local,
    'apl_end_local' => $end_local,
  ];

  $added = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
  if (!$added) {
    wp_send_json_error(['message' => 'No se pudo agregar al carrito.']);
  }

  wp_send_json_success([
    'checkoutUrl' => wc_get_checkout_url(),
  ]);
}

if (!defined('ABSPATH')) exit;

function apl_render_calendar_shortcode() {
  $now = apl_now();
  $ym = $now->format('Y-m');
  $preview_count = (int) apl_opt('preview_count', 6);

  ob_start(); ?>
  <div class="apl-wrap" data-ym="<?php echo esc_attr($ym); ?>" data-preview="<?php echo esc_attr($preview_count); ?>">
    <div class="apl-header">
      <div class="apl-title">
        <h3>Reserva tu cita</h3>
        <p>Selecciona un horario disponible y paga para confirmar.</p>
      </div>
      <button class="apl-toggle" type="button" aria-expanded="false">Ver calendario</button>
    </div>

  <!--Lo oculte para que no salga las proximas fehcas disponibles en el front-->
    <!-- <div class="apl-collapsed">
      <div class="apl-preview">
        <div class="apl-preview-title">Próximos horarios disponibles</div>
        <div class="apl-preview-list" data-preview-list>
          <div class="apl-loading">Cargando...</div>
        </div>
      </div>
    </div> -->

    <div class="apl-expanded" hidden>
      <div class="apl-controls">
        <button class="apl-month-prev" type="button">◀</button>
        <div class="apl-month-label" data-month-label><?php echo esc_html($now->format('F Y')); ?></div>
        <button class="apl-month-next" type="button">▶</button>
      </div>

      <div class="apl-calendar" data-calendar></div>

    <div class="apl-slots">
      <div class="apl-slots-title" data-slots-title>Selecciona un día</div>
      <div class="apl-slots-list" data-slots-list></div>

      <div class="apl-actions" hidden>
        <button type="button" class="apl-reserve-btn" disabled>
          Reservar sesión
        </button>
      </div>
    </div>

    </div>

    <div class="apl-toast" data-toast hidden></div>
  </div>
  <?php
  return ob_get_clean();
}
