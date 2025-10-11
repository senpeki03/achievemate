<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Designation Email</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f2f5; font-family: Arial, sans-serif;">

  <div style="background-color: #eef1f7; padding: 40px 0;">
    <div style="max-width: 700px; margin: auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">

        <!-- 🏫 Logo -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="{{ $logo }}" alt="AchieveMate Logo" style="max-height: 120px;">
        </div>

        <!-- ✅ Email Body -->
        <div style="padding: 40px 30px; color: #333;">
          <h2 style="color: #222; font-size: 22px; font-weight: bold; margin-bottom: 20px;">
            Your Registration on AchieveMate Online Services
          </h2>

          <p>Dear <strong>{{ $title }} {{ $fullname }}</strong>,</p>

          <p>
            Welcome! You are now registered in the AchieveMate Online Services of Batangas State University.
          </p>

          <p>
            Simply log in at 
            <a href="{{ $loginUrl }}" style="color: #0d6efd;">{{ $loginUrl }}</a>
            and view your designation, program, and activities related to the university. If you need help with your password, click the "Forgot Password?" link on the login screen.
          </p>

          <p>
            We hope this online service helps you monitor your active involvement in various university academic and management activities.
          </p>

          <p><strong>Login Credentials:</strong></p>
          <ul>
            <li><strong>Username:</strong> {{ $username }}</li>
            <li><strong>Password:</strong> {{ $password }}</li>
            <li><strong>Designation:</strong> {{ $usertype }}</li>
          </ul>

          <p style="margin-top: 30px;">
            Sincerely,<br>
            AchieveMate Administrator<br>
            <a href="{{ $loginUrl }}" style="color: #0d6efd;">{{ $loginUrl }}</a>
          </p>

          <p style="font-size: 12px; color: #777;">This is an automatically generated email. Please do not reply.</p>
        </div>
    </div>
  </div>

</body>
</html>
