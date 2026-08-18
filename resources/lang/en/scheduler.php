<?php

return [
    'name' => 'Report Name',
    'name_placeholder' => 'eg My Monthly Sales Report',
    'exporter' => 'Exporter',
    'exporter_hint' => 'Choose from the defined Exporters',
    'columns' => 'Columns to include in report',
    'available_columns' => 'Available Columns',
    'schedule_time' => 'Schedule Time',
    'schedule_time_hint' => 'What time should it run?',
    'schedule_day_of_week' => 'Day of the Week',
    'schedule_day_of_week_placeholder' => 'On which day should it run?',
    'schedule_day_of_month' => 'Day of the Month',
    'schedule_day_of_month_placeholder' => 'On which day of the month should it run?',
    'schedule_start_month' => 'Starting month',
    'schedule_start_month_placeholder' => 'On  month should it start?',

    'schedule_month' => 'Month',
    'schedule_month_placeholder' => 'On which month should it run?',
    'schedule_timezone' => 'Timezone',
    'formats' => 'Formats',
    'cron' => 'Custom Cron',
    'cron_expression_hint' => "A Cron expression defines a schedule for tasks to run automatically.<br>It uses a format like * * * * * representing minutes, hours, days, months, and weekdays.</p><p>For example:<br>&nbsp;&nbsp;&nbsp;0 9 * * * runs every day at 9:00 AM.<br>&nbsp;&nbsp;&nbsp;0 0 1 * * runs on the 1st of every month at midnight.<br>You can use an online tool to generate Cron expressions<br><a class='underline' href='https://crontab.guru'>https://crontab.guru</a>",

    'date_range' => 'Date Range',
    'since' => 'Since',
    'date_range_tooltip' => 'Leave blank for all records.  Attribute will be created_at unless changed in the Exporter',
    'date_range_placeholder' => 'Select a relative date range query. Blank for all records',
    'owner' => 'Report Owner',
    'cc' => 'Copy To Users',
    'cc_add_label' => 'Add Recipient',
    'cc_placeholder' => 'Search for a user',
    'cc_warning' => 'To ensure data security reports are not sent as attachments, all recipients must login to download the report.<br>The export will be run with permissions of the owner set above. <br><br>To prevent accidental breaches, you can only send to another :owner_type user.',
    'automatic_recipients' => 'Automatic Recipients',
    'dynamic_owner_enabled' => 'Enable Automatic Recipients',
    'dynamic_owner_attribute' => 'Recipient Attribute',
    'custom_cron_expression' => 'Custom Cron Expression',
    'cron_example' => 'e.g., 0 0 * * * (midnight daily)',
    'schedule_frequency' => 'Frequency',
    'recipient' => 'Recipient',
    'last_run' => 'Last Run',
    'last_success' => 'Last Successful Run',
    'next_run_at' => 'Next Run',
    'enabled' => 'Enabled',
    'Last day of the month' => 'Last day of the month',
    'logout_warning' => 'This export is for someone else and will be emailed to them. You will be logged out  if you run this export. To prevent this message change from the sync queue to database or other.',
    'run_modal_heading' => 'Run Export for Other User',
    'run_export' => 'Run the Export',
    'notification_title' => ':name started',

    /*
     * Reports
     */
    'report' => 'Report',
    'schedules' => 'Schedules',
    'search_reports' => 'Search reports by name',

    /*
     * Ownership
     */
    'ownership' => 'Ownership',
    'ownership_description' => 'The owner is the only person who can edit, delete and schedule this report.',
    'owner_type' => 'Owner type',
    'owner_helper' => 'Defaults to you. Assigning someone else hands over editing, deleting and scheduling, and you will lose that access unless the report is also shared with you.',

    /*
     * Sharing and visibility
     */
    'sharing' => 'Sharing',
    'sharing_description' => 'Sharing grants viewing and downloading only. Editing, deleting, running and scheduling stay with you.',
    'visibility' => 'Visibility',
    'visibility_owner' => 'Owner Only',
    'visibility_owner_description' => 'Nobody else can see this report.',
    'visibility_user_type' => 'Specific user type',
    'visibility_user_type_description' => 'Every user from the specified user type you can view and download it.',
    'visibility_named_users' => 'Specific people',
    'visibility_named_users_description' => 'Only users from the specified user type can view and download it.',
    'visible_to_type' => 'User type',
    'visible_to_ids' => 'People who can view this report',
    'visible_to_ids_placeholder' => 'Search for a user',

    /*
     * Custom Reports
     */
    'custom_reports' => 'Custom Reports',
    'create_custom_report' => 'Create Custom Report',
    'edit_custom_report' => 'Edit Report',
    'delete_custom_report' => 'Delete Report',
    'confirm_delete_custom_report' => 'Are you sure you want to delete this report? Any schedules associated with it will also be deleted.',
    'bulk_delete_custom_report' => 'Delete selected Report(s)',
    'confirm_bulk_delete_custom_report' => 'Are you sure you want to delete the selected report(s)? Any schedules associated with the report(s) will also be deleted.',

    /*
     * Schedules
     */
    'new_schedule' => 'New Scheduled Report',
    'scheduled_reports' => 'Scheduled Reports',
    'create_scheduled_report' => 'Create Schedule',
    'edit_scheduled_report' => 'Edit Scheduled Report',
    'delete_scheduled_report' => 'Delete Scheduled Report',
    'confirm_delete_scheduled_report' => 'Are you sure you want to delete this scheduled report? The report will not be sent according to this schedule anymore.',
    'bulk_delete_scheduled_report' => 'Delete selected Scheduled Report(s)',
    'confirm_bulk_delete_scheduled_report' => 'Are you sure you want to delete the selected scheduled report(s)? The report(s) will not be sent according to this schedule anymore.',
    'schedule_action' => 'Schedule',
    'recipient_type' => 'Send to',
    'when_to_run' => 'When to Run',
    'when_to_send' => 'When to Send',
    'schedule_output' => 'Date Range and File Format',
    'schedule_output_description' => 'Which records this schedule covers, and the file it produces.',
    'format' => 'File Format',
    'no_reports_yet' => 'You have no reports yet. Build one under Custom Reports first.',
    'no_schedules' => 'No schedules yet',
    'no_schedules_description' => 'This report is not emailed to anyone. Add a schedule to have it delivered.',

    'preview' => 'Preview',
    'preview_row' => 'row',
    'preview_empty_heading' => 'No rows to show',
    'preview_empty_description' => 'This report would not include any rows if it ran now.',
    'search_placeholder' => 'Search all fields',
    'viewer_truncated' => 'Showing the first :count rows. Download the report to see all of the results.',

    'download' => 'Download',
    'download_modal_heading' => 'Download report',
    'download_format' => 'Select file format',
    'download_started_title' => 'Preparing :name',
    'download_started_body' => 'Your report is being prepared in the background. You will be notified with a download link when it is ready.',
    'download_ready_body' => 'Your report is ready to download.',
    'download_complete_title' => ':name is ready',
    'download_complete_body' => 'Your report has finished. Use the button below to download it.',
    'download_failed_title' => 'Report could not be run',
    'download_failed_body' => 'The report could not be prepared. Check the application logs for details.',

    'send_empty_report' => 'Send empty report',
    'send_empty_report_true_label' => 'Always send the report (Default)',
    'send_empty_report_false_label' => 'Only send when there are results',
    'send_empty_report_true_description' => 'The report will be sent even if it contains no data rows.',
    'send_empty_report_false_description' => 'The report will not be sent if no data rows are returned.',

    // Additional translations for days of the week
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
    'sunday' => 'Sunday',

    // Additional translations for months
    'january' => 'January',
    'february' => 'February',
    'march' => 'March',
    'april' => 'April',
    'may' => 'May',
    'june' => 'June',
    'july' => 'July',
    'august' => 'August',
    'september' => 'September',
    'october' => 'October',
    'november' => 'November',
    'december' => 'December',

    // Relative date units
    'days' => 'Days',
    'weeks' => 'Weeks',
    'months' => 'Months',
    'years' => 'Years',

    // FileTypes
    'CSV' => 'CSV',
    'XLSX' => 'XLSX',

    'started' => [
        'title' => 'Export started',
        'body' => 'The export has begun processing :count rows in the background. An email notification with the download link will be sent to the owner when it is complete.|The export has begun processing :count rows in the background. An email notification with the download link will be sent to the owner and :cc_count others will receive a notification with the download link when it is complete.',
    ],

];
