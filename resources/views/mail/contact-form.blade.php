<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 15px; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 20px; }
        .card { background: #fff; border-radius: 8px; padding: 32px; max-width: 580px; margin: 0 auto; border: 1px solid #e5e5e5; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 4px; }
        .value { font-size: 15px; color: #111827; margin-bottom: 20px; }
        .message-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; font-size: 15px; color: #374151; line-height: 1.6; white-space: pre-wrap; }
        .footer { margin-top: 24px; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin: 0 0 24px; font-size: 20px; color: #111827;">New contact form message</h2>

        <div class="label">From</div>
        <div class="value">{{ $data['name'] }} &lt;{{ $data['email'] }}&gt;</div>

        <div class="label">Subject</div>
        <div class="value">{{ $data['subject'] }}</div>

        <div class="label">Message</div>
        <div class="message-box">{{ $data['message'] }}</div>

        <div class="footer">
            Sent via the contact form on {{ config('church.name') }} website.
            Reply directly to this email to respond to {{ $data['name'] }}.
        </div>
    </div>
</body>
</html>
