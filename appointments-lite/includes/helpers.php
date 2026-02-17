<?php
if (!defined('ABSPATH')) exit;

function apl_get_timezone_string(): string {
  $tz = get_option('timezone_string');
  return $tz ? $tz : 'America/Merida';
}

function apl_now(): DateTimeImmutable {
  return new DateTimeImmutable('now', new DateTimeZone(apl_get_timezone_string()));
}

function apl_opt(string $key, $default = null) {
  $opts = get_option('apl_settings', []);
  return $opts[$key] ?? $default;
}

function apl_weekday_map(): array {
  // PHP: 1=Mon ... 7=Sun
  return [
    1 => 'mon',
    2 => 'tue',
    3 => 'wed',
    4 => 'thu',
    5 => 'fri',
    6 => 'sat',
    7 => 'sun',
  ];
}

function apl_is_working_day(DateTimeImmutable $d): bool {
  $working = (array) apl_opt('working_days', ['mon', 'tue', 'wed', 'thu', 'fri']);
  $phpN = (int) $d->format('N');
  $map = apl_weekday_map();
  return in_array($map[$phpN] ?? '', $working, true);
}

function apl_parse_time(string $hhmm): array {
  $parts = explode(':', $hhmm);
  $h = isset($parts[0]) ? (int) $parts[0] : 9;
  $m = isset($parts[1]) ? (int) $parts[1] : 0;
  return [$h, $m];
}

/**
 * Canonical unique slot key in UTC ISO format: 2026-02-18T15:00:00Z
 */
function apl_slot_key(DateTimeImmutable $start): string {
  return $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

/**
 * Returns ALL day slots with isBlocked flag (UI can disable occupied ones).
 */
function apl_get_day_slots(DateTimeImmutable $day): array {
  if (!apl_is_working_day($day)) return [];

  $slot_minutes = (int) apl_opt('slot_minutes', 60);
  $start_time = (string) apl_opt('work_start', '09:00');
  $end_time = (string) apl_opt('work_end', '18:00');

  [$sh, $sm] = apl_parse_time($start_time);
  [$eh, $em] = apl_parse_time($end_time);

  $tz = new DateTimeZone(apl_get_timezone_string());

  $start = $day->setTime($sh, $sm, 0, 0)->setTimezone($tz);
  $end = $day->setTime($eh, $em, 0, 0)->setTimezone($tz);

  $slots = [];
  for ($cur = $start; $cur < $end; $cur = $cur->modify("+{$slot_minutes} minutes")) {
    $slots[] = [
      'start' => $cur,
      'end' => $cur->modify("+{$slot_minutes} minutes"),
    ];
  }

  // UI/UX block by start_local (more robust than slot_key)
  $blocked_starts = apl_get_blocked_start_locals_for_day($day);

  $out = [];
  foreach ($slots as $s) {
    $start_local = $s['start']->format('Y-m-d H:i');
    $isBlocked = in_array($start_local, $blocked_starts, true);

    $out[] = [
      'key' => apl_slot_key($s['start']),
      'start' => $start_local,
      'end' => $s['end']->format('Y-m-d H:i'),
      'label' => $s['start']->format('D d M, H:i'),
      'dayLabel' => $s['start']->format('D d M'),
      'timeLabel' => $s['start']->format('H:i'),
      'isBlocked' => $isBlocked,
    ];
  }

  return $out;
}

/**
 * Returns start_local values (Y-m-d H:i) that are pending/confirmed for the given day.
 * This is robust even if old slot_key values are inconsistent.
 */
function apl_get_blocked_start_locals_for_day(DateTimeImmutable $day): array {
  $tz = new DateTimeZone(apl_get_timezone_string());
  $dayLocal = $day->setTimezone($tz)->format('Y-m-d'); // "2026-02-18"

  $args = [
    'post_type' => 'apl_appointment',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
      [
        'key' => 'status',
        'value' => ['pending', 'confirmed'],
        'compare' => 'IN',
      ],
      [
        'key' => 'start_local',
        'value' => $dayLocal,
        'compare' => 'LIKE',
      ],
    ],
  ];

  $ids = get_posts($args);

  $starts = [];
  foreach ($ids as $id) {
    $start_local = (string) get_post_meta($id, 'start_local', true);
    if ($start_local) $starts[] = $start_local;
  }

  return array_values(array_unique($starts));
}

/**
 * Legacy helper (optional): returns slot_key values blocked for the day.
 * Not used by UI blocking anymore, but kept for compatibility.
 */
function apl_get_blocked_slot_keys_for_day(DateTimeImmutable $day): array {
  $tz = new DateTimeZone(apl_get_timezone_string());
  $dayLocal = $day->setTimezone($tz)->format('Y-m-d');

  $args = [
    'post_type' => 'apl_appointment',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => [
      [
        'key' => 'status',
        'value' => ['pending', 'confirmed'],
        'compare' => 'IN',
      ],
      [
        'key' => 'start_local',
        'value' => $dayLocal,
        'compare' => 'LIKE',
      ],
    ],
  ];

  $ids = get_posts($args);

  $keys = [];
  foreach ($ids as $id) {
    $k = (string) get_post_meta($id, 'slot_key', true);
    if ($k) $keys[] = $k;
  }

  return array_values(array_unique($keys));
}

/**
 * Finds an existing slot by canonical slot_key (UTC ISO) if it's pending/confirmed.
 */
function apl_find_existing_slot(string $slot_key): int {
  $args = [
    'post_type' => 'apl_appointment',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_query' => [
      [
        'key' => 'slot_key',
        'value' => $slot_key,
        'compare' => '='
      ],
      [
        'key' => 'status',
        'value' => ['pending', 'confirmed'],
        'compare' => 'IN'
      ],
    ],
  ];
  $ids = get_posts($args);
  return $ids ? (int) $ids[0] : 0;
}
