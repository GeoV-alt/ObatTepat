<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

$user = require_role('patient');
require_post();

$patientId = (int) $user['id'];
$medicationId = (int) input('medication_id', '0');
$addedQty = (float) input('added_qty', '0');
$notes = input('notes');

try {
    $result = RefillService::recordRefill($medicationId, $patientId, $addedQty, $notes);
    $message = 'Stok ' . $result['medication_name'] . ' bertambah ' . format_qty($result['added_qty']) . ' ' . $result['dose_unit'] . '. Sisa stok kini ' . format_qty($result['new_stock']) . ' ' . $result['dose_unit'] . '.';
    respond_action(true, $message, ['result' => $result], 'refills/index.php');
} catch (DomainException $error) {
    respond_action(false, $error->getMessage(), [], 'refills/index.php');
}
