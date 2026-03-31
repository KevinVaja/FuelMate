<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name', 'FuelMate') }} verification code</title>
</head>
<body style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,sans-serif;color:#14213d;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dbe5f0;border-radius:16px;overflow:hidden;">
        <div style="padding:24px 28px;background:#0d6efd;color:#ffffff;">
            <div style="font-size:20px;font-weight:700;">{{ config('app.name', 'FuelMate') }}</div>
            <div style="font-size:14px;opacity:0.9;margin-top:6px;">{{ ucfirst($purposeLabel) }} verification</div>
        </div>
        <div style="padding:28px;">
            <p style="margin-top:0;font-size:15px;line-height:1.6;">
                @if($recipientName)
                    Hello {{ $recipientName }},
                @else
                    Hello,
                @endif
            </p>
            <p style="font-size:15px;line-height:1.6;">
                Use the verification code below to complete your {{ $purposeLabel }}.
            </p>
            <div style="margin:24px 0;padding:18px;border:1px dashed #9bbcf8;border-radius:14px;text-align:center;background:#f7faff;">
                <div style="font-size:13px;text-transform:uppercase;letter-spacing:1.6px;color:#5c6f91;margin-bottom:8px;">Verification code</div>
                <div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#0d6efd;">{{ $code }}</div>
            </div>
            <p style="font-size:14px;line-height:1.6;margin-bottom:8px;">
                This code expires in {{ $ttlMinutes }} minutes.
            </p>
            <p style="font-size:14px;line-height:1.6;margin-bottom:0;color:#5c6f91;">
                If you did not request this code, you can safely ignore this email.
            </p>
        </div>
    </div>
</body>
</html>
