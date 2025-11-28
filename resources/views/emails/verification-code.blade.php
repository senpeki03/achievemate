<!DOCTYPE html>
<html>
<head>
    <title>Verification Code</title>
</head>
<body>
    <h2>Batangas State University - Verification Code</h2>
    <p>Hello {{ $username }},</p>
    <p>Your verification code for viewing all grades is:</p>
    <h1 style="font-size: 32px; letter-spacing: 5px; text-align: center;">{{ $code }}</h1>
    <p>This code will expire in {{ $expires_in }}.</p>
    <p>If you didn't request this code, please ignore this email.</p>
    <br>
    <p>Best regards,<br>Batangas State University</p>
</body>
</html>