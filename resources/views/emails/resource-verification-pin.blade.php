<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fafaf9; margin: 0; padding: 40px 20px;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; border: 1px solid #e7e5e4; padding: 32px; text-align: center;">
        <h2 style="color: #1c1917; font-size: 20px; font-weight: 700; margin-top: 0;">Resource Verification Code</h2>
        <p style="color: #78716c; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
            Here is your 6-digit verification code to access your requested learning resource:
        </p>
        <div style="background-color: #f5f5f4; border-radius: 12px; padding: 16px; font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #d97706; margin-bottom: 24px;">
            {{ $pin }}
        </div>
        <p style="color: #a8a29e; font-size: 12px; margin-bottom: 0;">
            This code will expire in 10 minutes. If you did not request this resource, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
