<?php

declare(strict_types=1);

namespace App\Parsing;

use InvalidArgumentException;

final class FixedWidthLineParser
{
    private const EXPECTED_LENGTH = 95;

    private const FIELDS = [
        'user_id'       => 10,
        'name'          => 45,
        'order_id'      => 10,
        'product_id'    => 10,
        'product_value' => 12,
        'purchase_date' => 8,
    ];

    public function parse(string $line): array
    {
        $length = strlen($line);
        if ($length !== self::EXPECTED_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Invalid line length: expected %d characters, received %d.',
                self::EXPECTED_LENGTH,
                $length
            ));
        }

        $offset = 0;
        $raw = [];
        foreach (self::FIELDS as $field => $size) {
            $raw[$field] = substr($line, $offset, $size);
            $offset += $size;
        }

        return [
            'user_id'       => $this->parseInteger($raw['user_id'], 'user_id'),
            'name'          => trim($raw['name']),
            'order_id'      => $this->parseInteger($raw['order_id'], 'order_id'),
            'product_id'    => $this->parseInteger($raw['product_id'], 'product_id'),
            'product_value' => $this->parseDecimal($raw['product_value'], 'product_value'),
            'purchase_date' => $this->parseDate($raw['purchase_date']),
        ];
    }

    private function parseInteger(string $raw, string $field): int
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || !ctype_digit($trimmed)) {
            throw new InvalidArgumentException("Field '{$field}' contains an invalid numeric value: '{$raw}'.");
        }

        return (int) $trimmed;
    }

    private function parseDecimal(string $raw, string $field): float
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || !is_numeric($trimmed)) {
            throw new InvalidArgumentException("Field '{$field}' contains an invalid decimal value: '{$raw}'.");
        }

        return round((float) $trimmed, 2);
    }

    private function parseDate(string $raw): string
    {
        $trimmed = trim($raw);

        if (!preg_match('/^\d{8}$/', $trimmed)) {
            throw new InvalidArgumentException("Invalid field 'purchase_date': '{$raw}'. Expected yyyymmdd format.");
        }

        $year = (int) substr($trimmed, 0, 4);
        $month = (int) substr($trimmed, 4, 2);
        $day = (int) substr($trimmed, 6, 2);

        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("Field 'purchase_date' is not a valid date: '{$raw}'.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
