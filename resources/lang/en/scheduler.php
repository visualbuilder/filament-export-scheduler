<?php

return [
    'name'                              => 'Report Name',
    'name_placeholder'                  => 'eg My Monthly Sales Report',
    'exporter'                          => 'Exporter',
    'exporter_hint'                     => 'Choose from the defined Exporters',
    'columns'                           => 'Columns to include in report',
    'available_columns'                 => 'Available Columns',
    'schedule_time'                     => 'Schedule Time',
    'schedule_time_hint'                => 'What time should it run?',
    'schedule_day_of_week'              => 'Day of the Week',
    'schedule_day_of_week_placeholder'  => 'On which day should it run?',
    'schedule_day_of_month'             => 'Day of the Month',
    'schedule_day_of_month_placeholder' => 'On which day of the month should it run?',
    'schedule_start_month'              => 'Starting month',
    'schedule_start_month_placeholder'  => 'On  month should it start?',

    'schedule_month'             => 'Month',
    'schedule_month_placeholder' => 'On which month should it run?',
    'schedule_timezone'          => 'Timezone',
    'formats'                    => 'Formats',
    'cron'                       => 'Custom Cron',
    'cron_expression_hint'       => "A Cron expression defines a schedule for tasks to run automatically.<br>It uses a format like * * * * * representing minutes, hours, days, months, and weekdays.</p><p>For example:<br>&nbsp;&nbsp;&nbsp;0 9 * * * runs every day at 9:00 AM.<br>&nbsp;&nbsp;&nbsp;0 0 1 * * runs on the 1st of every month at midnight.<br>You can use an online tool to generate Cron expressions<br><a class='underline' href='https://crontab.guru'>https://crontab.guru</a>",

    'date_range'             => 'Date Range',
    'date_range_tooltip'     => 'Leave blank for all records.  Attribute will be created_at unless changed in the Exporter',
    'date_range_placeholder' => 'Select a relative date range query. Blank for all records',
    'owner'                  => 'Report Owner',
    'cc'                     => 'Copy To Users',
    'cc_add_label'           => 'Add Recipient',
    'cc_placeholder'         => 'Search for an email address',
    'cc_warning'             => 'To ensure data security reports are not sent as attachments.<br>All recipients must login to download the report.<br>The export will be run with permissions of the owner set above. <br><br>To prevent accidental breaches, you can only send to another :owner_type user.',
    'custom_cron_expression' => 'Custom Cron Expression',
    'cron_example'           => 'e.g., 0 0 * * * (midnight daily)',
    'schedule_frequency'     => 'Frequency',
    'recipient'              => 'Recipient',
    'last_run'               => 'Last Run',
    'last_success'           => 'Last Successful Run',
    'next_run_at'            => 'Next Run',
    'enabled'                => 'Enabled',
    'Last day of the month'  => 'Last day of the month',
    'logout_warning'         => 'This export is for someone else and will be emailed to them. You will be logged out  if you run this export. To prevent this message change from the sync queue to database or other.',
    'run_modal_heading'      => 'Run Export for Other User',
    'run_export'             => 'Run the Export',
    'notification_title'     => ':name started',


    // Additional translations for days of the week
    'monday'                 => 'Monday',
    'tuesday'                => 'Tuesday',
    'wednesday'              => 'Wednesday',
    'thursday'               => 'Thursday',
    'friday'                 => 'Friday',
    'saturday'               => 'Saturday',
    'sunday'                 => 'Sunday',

    // Additional translations for months
    'january'                => 'January',
    'february'               => 'February',
    'march'                  => 'March',
    'april'                  => 'April',
    'may'                    => 'May',
    'june'                   => 'June',
    'july'                   => 'July',
    'august'                 => 'August',
    'september'              => 'September',
    'october'                => 'October',
    'november'               => 'November',
    'december'               => 'December',

    //FileTypes
    'CSV'                    => 'CSV',
    'XLSX'                   => 'XLSX',

    'started' => [
        'title' => 'Export started',
        'body'  => 'The export has begun processing :count rows in the background. An email notification with the download link will be sent to the owner when it is complete.|The export has begun processing :count rows in the background. An email notification with the download link will be sent to the owner and :cc_count others will receive a notification with the download link when it is complete.',
    ],


];
