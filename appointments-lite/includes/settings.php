<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_options_page('Appointments Lite', 'Appointments Lite', 'manage_options', 'apl-settings', 'apl_settings_page');
});

add_action('admin_init', function () {
  register_setting('apl_settings_group', 'apl_settings', [
    'type' => 'array',
    'sanitize_callback' => function ($input) {
      $out = [];
      $out['work_start'] = sanitize_text_field($input['work_start'] ?? '09:00');
      $out['work_end'] = sanitize_text_field($input['work_end'] ?? '18:00');
      $out['slot_minutes'] = max(15, (int)($input['slot_minutes'] ?? 60));
      $out['hold_minutes'] = max(5, (int)($input['hold_minutes'] ?? 15));
      $out['working_days'] = array_values(array_filter((array)($input['working_days'] ?? []), 'is_string'));

      $out['woo_product_id'] = isset($input['woo_product_id']) ? (int)$input['woo_product_id'] : 0;
      $out['preview_count'] = max(3, (int)($input['preview_count'] ?? 6));
      return $out;
    }
  ]);
});

function apl_settings_page() {
  $opts = get_option('apl_settings', []);
  $days = ['mon'=>'Lun','tue'=>'Mar','wed'=>'Mié','thu'=>'Jue','fri'=>'Vie','sat'=>'Sáb','sun'=>'Dom'];
  ?>
  <div class="wrap">
    <h1>Appointments Lite</h1>
    <form method="post" action="options.php">
      <?php settings_fields('apl_settings_group'); ?>

      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">Horario de trabajo</th>
          <td>
            <label>Inicio <input type="time" name="apl_settings[work_start]" value="<?php echo esc_attr($opts['work_start'] ?? '09:00'); ?>"></label>
            <label style="margin-left:12px;">Fin <input type="time" name="apl_settings[work_end]" value="<?php echo esc_attr($opts['work_end'] ?? '18:00'); ?>"></label>
          </td>
        </tr>

        <tr>
          <th scope="row">Duración del slot (min)</th>
          <td><input type="number" min="15" step="15" name="apl_settings[slot_minutes]" value="<?php echo esc_attr($opts['slot_minutes'] ?? 60); ?>"></td>
        </tr>

        <tr>
          <th scope="row">Hold (min) para pending</th>
          <td><input type="number" min="5" step="5" name="apl_settings[hold_minutes]" value="<?php echo esc_attr($opts['hold_minutes'] ?? 15); ?>"></td>
        </tr>

        <tr>
          <th scope="row">Días laborables</th>
          <td>
            <?php foreach ($days as $key => $label): ?>
              <label style="margin-right:10px;">
                <input type="checkbox" name="apl_settings[working_days][]" value="<?php echo esc_attr($key); ?>"
                  <?php checked(in_array($key, (array)($opts['working_days'] ?? ['mon','tue','wed','thu','fri']), true)); ?>>
                <?php echo esc_html($label); ?>
              </label>
            <?php endforeach; ?>
          </td>
        </tr>

        <tr>
          <th scope="row">Cantidad vista colapsada</th>
          <td><input type="number" min="3" max="30" name="apl_settings[preview_count]" value="<?php echo esc_attr($opts['preview_count'] ?? 6); ?>"></td>
        </tr>

        <tr>
          <th scope="row">Producto WooCommerce (ID)</th>
          <td>
            <input type="number" min="0" name="apl_settings[woo_product_id]" value="<?php echo esc_attr($opts['woo_product_id'] ?? 0); ?>">
            <p class="description">Si está vacío, el plugin intentará crear uno “Reserva de cita” al activar (si Woo está activo).</p>
          </td>
        </tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}
