<?php

function validate(array $data, array $rules, array $labels = []): array
{
    $errors = [];

    foreach ($rules as $field => $ruleText) {
        $value = $data[$field] ?? '';
        $value = is_string($value) ? trim($value) : $value;
        $label = $labels[$field] ?? $field;
        $ruleList = explode('|', $ruleText);
        $isEmpty = $value === '' || $value === null || $value === [];

        if ($isEmpty && !in_array('required', $ruleList, true)) {
            continue;
        }

        foreach ($ruleList as $rule) {
            [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
            $message = validation_message($name, $argument, $value, $label, $data);

            if ($message !== null) {
                $errors[$field] = $message;
                break;
            }
        }
    }

    return $errors;
}

function validation_message(string $rule, ?string $argument, mixed $value, string $label, array $data): ?string
{
    $text = is_scalar($value) ? (string) $value : '';

    return match ($rule) {
        'required' => $text === '' ? "$label wajib diisi." : null,
        'email' => filter_var($text, FILTER_VALIDATE_EMAIL) ? null : "$label harus berupa alamat email yang valid.",
        'min' => mb_strlen($text) < (int) $argument ? "$label minimal $argument karakter." : null,
        'max' => mb_strlen($text) > (int) $argument ? "$label maksimal $argument karakter." : null,
        'numeric' => is_numeric($text) ? null : "$label harus berupa angka.",
        'integer' => filter_var($text, FILTER_VALIDATE_INT) !== false ? null : "$label harus berupa bilangan bulat.",
        'min_value' => is_numeric($text) && (float) $text < (float) $argument ? "$label minimal " . format_qty($argument) . '.' : null,
        'max_value' => is_numeric($text) && (float) $text > (float) $argument ? "$label maksimal " . format_qty($argument) . '.' : null,
        'gt' => is_numeric($text) && (float) $text <= (float) $argument ? "$label harus lebih dari " . format_qty($argument) . '.' : null,
        'in' => in_array($text, explode(',', (string) $argument), true) ? null : "Pilihan $label tidak dikenali.",
        'date' => is_valid_date($text) ? null : "$label harus berupa tanggal yang valid.",
        'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $text) ? null : "$label harus berupa jam yang valid (JJ:MM).",
        'same' => $text === (string) ($data[$argument] ?? '') ? null : "$label tidak sama.",
        default => null,
    };
}
