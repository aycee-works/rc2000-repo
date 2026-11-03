<?php
declare(strict_types=1);

function required_text(string $value, int $maxLength = 2000): bool
{
    $value = trim($value);
    return $value !== '' && mb_strlen($value) <= $maxLength;
}

function optional_text(string $value, int $maxLength = 2000): bool
{
    $value = trim($value);
    return $value === '' || mb_strlen($value) <= $maxLength;
}

function valid_email(string $email): bool
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

function valid_quantity($value): bool
{
    if (!is_numeric($value)) {
        return false;
    }
    $n = (int) $value;
    return $n > 0 && $n <= 10000;
}

function valid_amount($value): bool
{
    if (!is_numeric($value)) {
        return false;
    }
    $n = (float) $value;
    return $n > 0 && $n <= 9999999999.99;
}

function valid_date_time(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    $dt = date_create($value);
    return $dt instanceof DateTimeInterface;
}

function valid_phone(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }
    return (bool) preg_match('/^[0-9+\-\s()]{7,30}$/', $value);
}


function inquiry_categories(): array
{
    return [
        'inquiry'  => 'Inquiry',
        'request'  => 'Request',
        'technical' => 'Technical',
        'report'   => 'Report',
    ];
}

