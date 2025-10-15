<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dean’s Honor List — Certificate Ready</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f7f7f8;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f8;">
    <tr>
      <td align="center" style="padding:24px;">
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e6e6e6;">
          <tr>
            <td style="padding:24px 28px 0 28px;">
              <h1 style="margin:0 0 8px 0;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:28px;color:#111827;">
                Congratulations, {{ $studentName }}!
              </h1>
              <p style="margin:0 0 16px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;color:#374151;">
                Your application has been <strong>Approved</strong> for the <strong>Dean’s Honor List</strong>.
                Your digital certificate has been added to your account and is ready to claim.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:0 28px 0 28px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
                <tr><td style="height:10px"></td></tr>
                <tr>
                  <td style="padding:0 16px;">
                    <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#111827;">
                      <strong>SRCODE:</strong> {{ $studentId }}<br>
                      <strong>Program:</strong> {{ $program }}<br>
                      <strong>College:</strong> {{ $college }}<br>
                      <strong>Year Level:</strong> {{ $yearLevel }}<br>
                      <strong>GWA:</strong> {{ $gwa }}<br>
                      <strong>Distinction:</strong> {{ $rankOrDistinction }}<br>
                      <strong>Term / A.Y.:</strong> {{ $term }} / {{ $ay }}
                    </p>
                  </td>
                </tr>
                <tr><td style="height:10px"></td></tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:24px 28px;">
              <a href="{{ $downloadLink }}"
                 style="display:inline-block;text-decoration:none;background:#14532d;color:#ffffff;
                        padding:12px 18px;border-radius:8px;font-family:Arial,Helvetica,sans-serif;
                        font-size:14px;line-height:20px;">
                 Claim Certificate
              </a>
              <p style="margin:12px 0 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
                If the button doesn’t work, copy and paste this link into your browser:<br>
                <span style="word-break:break-all;color:#374151;">{{ $downloadLink }}</span>
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:0 28px 24px 28px;">
              <p style="margin:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111827;">
                — {{ $deanName }}, {{ $deanTitle }}<br>
                {{ $universityName }}
              </p>
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
                Need help? Contact us at {{ $supportEmail }}.
              </p>
            </td>
          </tr>
        </table>

        <p style="margin:14px 0 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9ca3af;">
          Sent by {{ $systemName }} • Please do not reply to this automated message.
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
