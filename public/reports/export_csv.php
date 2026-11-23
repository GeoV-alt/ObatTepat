<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$fromDate = query_param('from_date', date('Y-m-d', strtotime('-13 days')));
$toDate = query_param('to_date', today());
if (!is_valid_date($fromDate)) {
    $fromDate = date('Y-m-d', strtotime('-13 days'));
}
if (!is_valid_date($toDate)) {
    $toDate = today();
}

$rows = ScheduleService::history($patientId, $fromDate, $toDate);

$out = fopen('php://temp', 'r+');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Jadwal', 'Nama Obat', 'Dosis', 'Satuan', 'Status', 'Waktu Diminum', 'Terlambat', 'Alasan Dilewati']);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['scheduled_at'],
        $row['medication_name'],
        format_qty($row['actual_dose'] ?? $row['planned_dose']),
        $row['dose_unit'],
        label('schedule_status', $row['display_status']),
        $row['taken_at'] ?: '-',
        !empty($row['is_late']) ? format_minutes((int) $row['delay_minutes']) : ($row['status'] === 'taken' ? 'Tepat waktu' : '-'),
        $row['skip_reason'] ?: '-',
    ]);
}

rewind($out);
$csv = (string) stream_get_contents($out);
fclose($out);

clear_output_buffers();
header('Content-Disposition: attachment; filename="riwayat-obat-' . $fromDate . '-sd-' . $toDate . '.csv"');
send_buffered_response($csv, 'text/csv; charset=utf-8');
exit;
