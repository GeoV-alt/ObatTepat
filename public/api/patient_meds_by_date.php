<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$date = query_param('date', today());

if (!is_valid_date($date)) {
    json_response(['ok' => false, 'message' => 'Format tanggal tidak valid.'], 422);
}

$rows = ScheduleService::takenOrScheduledOnDate((int) $user['id'], $date);
$items = array_map(fn (array $row) => [
    'id' => (int) $row['id'],
    'medication_id' => (int) $row['medication_id'],
    'medication_name' => $row['medication_name'],
    'label' => format_time($row['scheduled_at']) . ' - ' . $row['medication_name'] . ' (' . strtolower(label('schedule_status', $row['status'])) . ')',
], $rows);

json_response(['ok' => true, 'date' => $date, 'items' => $items]);
