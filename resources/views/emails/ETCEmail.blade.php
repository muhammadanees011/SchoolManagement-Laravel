<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentPay Daily Sync Report</title>
</head>

<body style="margin:0;padding:0;background-color:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#334155;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2f5;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:680px;background-color:#ffffff;border:1px solid #dbe3ea;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#235667;padding:22px 24px;text-align:center;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;color:#ffffff;font-weight:700;">
                                StudentPay Daily Sync Report
                            </h1>
                            <p style="margin:8px 0 0;font-size:13px;color:#d7e8ee;">
                                {{ \Carbon\Carbon::today()->format('l, F j, Y') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#475569;">
                                Here is a summary of today's synchronisation. New student and staff accounts created during this sync are listed below.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                                <tr>
                                    <td width="50%" style="padding:0 6px 12px 0;vertical-align:top;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f7fa;border:1px solid #cfe0e8;border-radius:8px;">
                                            <tr>
                                                <td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;letter-spacing:0.4px;text-transform:uppercase;color:#5f7f8c;">New Students Today</p>
                                                    <p style="margin:0;font-size:28px;line-height:1.2;font-weight:700;color:#235667;">{{ $today_students }}</p>
                                                    <p style="margin:6px 0 0;font-size:13px;color:#64748b;">Total students: <strong style="color:#235667;">{{ $total_students }}</strong></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="50%" style="padding:0 0 12px 6px;vertical-align:top;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f7fa;border:1px solid #cfe0e8;border-radius:8px;">
                                            <tr>
                                                <td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;letter-spacing:0.4px;text-transform:uppercase;color:#5f7f8c;">New Staff Today</p>
                                                    <p style="margin:0;font-size:28px;line-height:1.2;font-weight:700;color:#235667;">{{ $today_staff }}</p>
                                                    <p style="margin:6px 0 0;font-size:13px;color:#64748b;">Total staff: <strong style="color:#235667;">{{ $total_staff }}</strong></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 10px;font-size:16px;color:#235667;border-bottom:2px solid #d7e8ee;padding-bottom:8px;">
                                New Students ({{ $today_students }})
                            </h2>

                            @if (!empty($new_students_list))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">#</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Name</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Email</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Student ID</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Site</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($new_students_list as $index => $student)
                                            <tr style="background-color:{{ $index % 2 === 0 ? '#ffffff' : '#f8fafc' }};">
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#64748b;">{{ $index + 1 }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#1e293b;font-weight:600;">{{ $student['name'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $student['email'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $student['student_id'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $student['site'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p style="margin:0 0 24px;font-size:14px;color:#64748b;">No new students were created today.</p>
                            @endif

                            <h2 style="margin:0 0 10px;font-size:16px;color:#235667;border-bottom:2px solid #d7e8ee;padding-bottom:8px;">
                                New Staff ({{ $today_staff }})
                            </h2>

                            @if (!empty($new_staff_list))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">#</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Name</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Email</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Staff ID</th>
                                            <th align="left" style="background-color:#235667;color:#ffffff;padding:10px 8px;border:1px solid #1c4654;">Site</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($new_staff_list as $index => $staff)
                                            <tr style="background-color:{{ $index % 2 === 0 ? '#ffffff' : '#f8fafc' }};">
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#64748b;">{{ $index + 1 }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#1e293b;font-weight:600;">{{ $staff['name'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $staff['email'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $staff['staff_id'] }}</td>
                                                <td style="padding:9px 8px;border:1px solid #e2e8f0;color:#334155;">{{ $staff['site'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p style="margin:0 0 24px;font-size:14px;color:#64748b;">No new staff were created today.</p>
                            @endif

                            @if (!empty($new_schools))
                                <h2 style="margin:0 0 10px;font-size:16px;color:#b45309;border-bottom:2px solid #fde68a;padding-bottom:8px;">
                                    Schools Not Found
                                </h2>
                                <p style="margin:0 0 10px;font-size:14px;line-height:1.5;color:#92400e;">
                                    Users linked to the following schools were skipped because these schools are not set up on the StudentPay portal:
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
                                    <tr>
                                        <td style="padding:12px 16px;">
                                            <ul style="margin:0;padding-left:18px;color:#78350f;font-size:14px;">
                                                @foreach ($new_schools as $school)
                                                    <li style="margin-bottom:4px;">{{ $school }}</li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px 22px;border-top:1px solid #e2e8f0;text-align:center;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;font-style:italic;">
                                This is an auto-generated email. Please do not reply.
                                <br><br>
                                Best regards,<br>
                                <strong style="color:#235667;font-style:normal;">StudentPay Team</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
