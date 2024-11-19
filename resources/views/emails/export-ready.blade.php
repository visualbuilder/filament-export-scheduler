
<x-export-scheduler::email>
<p>Dear {{$name}},</p>
<p>Your {{$exportSchedule->frequency}} {{$exportSchedule->name}} for {{$exportSchedule->date_range_label}}:-</p>
<p>
    <strong>From</strong>: {{$exportSchedule->starts_at_formatted}}<br>
    <strong>Until</strong>: {{$exportSchedule->ends_at_formatted}}
</p>
<p>Has completed and is ready for download.</p>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center" style="padding: 20px 30px 20px 30px;">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="center" style="border-radius: 3px;">
                        <a href="{{$url}}" target="_blank" style="font-size: 20px;
                            font-family: Helvetica, Arial, sans-serif;
                            color: white;
                            text-decoration: none;
                            padding: 15px 25px;
                            border-radius: 2px;
                            border: 1px solid #5d36ff; display: inline-block;">
                           Download Export
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p>Best Regards
<br>{{config('app.name') }} elves and pixies.</p>
</x-export-scheduler::email>

