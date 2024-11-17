<?php

namespace VisualBuilder\ExportScheduler\Services;


use Carbon\Carbon;

class ExportFormatter
{
    public static function formatters(): array
    {
        return [
            'date'          => [self::class, 'formatDate'],
            'datetime'      => [self::class, 'formatDateTime'],
            'currency'      => [self::class, 'formatCurrency'],
            'sessionStatus' => [self::class, 'formatSessionStatus'],
            'invoiceStatus' => [self::class, 'formatInvoiceStatus'],

        ];
    }

    public static function formatDate($state)
    {
        return $state ? Carbon::parse($state)->format('Y-m-d') : '';
    }

    public static function formatDateTime($state)
    {
        return $state ? Carbon::parse($state)->format(config('company.defaultDateTimeDisplayFormat')) : '';
    }

    public static function formatCurrency($state)
    {
        return "£".number_format($state, 2);
    }

    public static function formatSessionStatus($state)
    {
        return CoachingSessionStatus::from($state->value)->getLabel();
    }

    public static function formatInvoiceStatus($state)
    {
        return OrderInvoiceStatus::from($state)->getLabel();
    }

}
