<?php
if (!defined('ABSPATH')) exit;

function apl_schedule_cron() {
  if (!wp_next_scheduled('apl_cron_expire_pending')) {
    wp_schedule_event(time() + 60, 'minute', 'apl_cron_expire_pending');
  }
}
function apl_unschedule_cron() {
  $ts = wp_next_scheduled('apl_cron_expire_pending');
  if ($ts) wp_unschedule_event($ts, 'apl_cron_expire_pending');
}

// Add "minute" interval
add_filter('cron_schedules', function ($schedules) {
  if (!isset($schedules['minute'])) {
    $schedules['minute'] = ['interval' => 60, 'display' => 'Every minute'];
  }
  return $schedules;
});

add_action('apl_cron_expire_pending', function () {
  $now = apl_now()->format('Y-m-d H:i:s');

  $args = [
    'post_type' => 'apl_appointment',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
      ['key' => 'status', 'value' => 'pending', 'compare' => '='],
      ['key' => 'expires_at', 'value' => $now, 'compare' => '<', 'type' => 'CHAR'],
    ],
  ];
  $ids = get_posts($args);

  foreach ($ids as $id) {
    update_post_meta($id, 'status', 'expired');
  }
});
