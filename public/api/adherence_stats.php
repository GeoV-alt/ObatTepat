<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
$patientId = (int) $user['id'];

$preset = query_param('preset', '7');
$customFrom = query_param('from_date', '');
$customTo = query_param('to_date', '');
[$fromDate, $toDate, $activePreset] = AdherenceService::resolveRange($preset, $customFrom, $customTo);

json_response([
    'ok' => true,
    'preset' => $activePreset,
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'summary' => AdherenceService::summary($patientId, $fromDate, $toDate),
    'series' => AdherenceService::dailySeries($patientId, $fromDate, $toDate),
]);
