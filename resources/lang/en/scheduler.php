<?php

return [
    'name'                   => 'Schedule Name',
    'name_placeholder'       => 'eg My Monthly Sales Report',
    'exporter'               => 'Exporter',
    'exporter_hint'          => 'Choose from the defined Exporters',
    'columns'                => 'Columns',
    'schedule_time'          => 'Schedule Time',
    'schedule_time_hint'     => 'How often should it run?',
    'schedule_day_of_week'   => 'Day of the Week',
    'schedule_day_of_month'  => 'Day of the Month',
    'schedule_month'         => 'Month',
    'schedule_timezone'      => 'Timezone',
    'formats'                => 'Formats',
    'cron'                   => 'Custom Cron',
    'date_range'             => 'Date Range',
    'date_range_tooltip'     => 'Leave blank for all records.  Attribute will be created_at unless changed in the Exporter',
    'date_range_placeholder' => 'Select a relative date range query. Blank for all records',
    'owner'                  => 'Recipient User',
    'custom_cron_expression' => 'Custom Cron Expression',
    'cron_example'           => 'e.g., 0 0 * * * (midnight daily)',
    'schedule_frequency'     => 'Frequency',
    'recipient'              => 'Recipient',
    'last_run'               => 'Last Run',
    'last_success'           => 'Last Successful Run',
    'next_due'               => 'Next Due',
    'enabled'                => 'Enabled',

    // Additional translations for days of the week
    'Monday'                 => 'Monday',
    'Tuesday'                => 'Tuesday',
    'Wednesday'              => 'Wednesday',
    'Thursday'               => 'Thursday',
    'Friday'                 => 'Friday',
    'Saturday'               => 'Saturday',
    'Sunday'                 => 'Sunday',

    // Additional translations for months
    'January'                => 'January',
    'February'               => 'February',
    'March'                  => 'March',
    'April'                  => 'April',
    'May'                    => 'May',
    'June'                   => 'June',
    'July'                   => 'July',
    'August'                 => 'August',
    'September'              => 'September',
    'October'                => 'October',
    'November'               => 'November',
    'December'               => 'December',

    // Additional translation
    'Last day of the month'  => 'Last day of the month',
    'CSV'                    => 'CSV',
    'XLSX'                   => 'XLSX',

    'started' => [
        'title' => 'Export started',
        'body'  => 'The export has begun and 1 row will be processed in the background. An email notification with the download link will be sent to the owner when it is complete.|The export has begun and :count rows will be processed in the background. The owner will receive a notification with the download link when it is complete.',
    ],

    'logout_warning' => 'This export is for someone else and will be emailed to them. You will be logged out  if you run this export. To prevent this message change from the sync queue to database or other.'


];
