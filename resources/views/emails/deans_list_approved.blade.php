{{-- resources/views/emails/deans_list_approved.blade.php --}}
<!doctype html>
<html>
  <body style="font-family: Arial, sans-serif;">
    <h2>Congratulations, {{ $studentName }}!</h2>
    <p>You have been approved for the <strong>Dean’s Honor List</strong>.</p>
    <p>Your badge and certificate are ready to claim in AchieveMate.</p>
    @if($claimUrl)
      <p><a href="{{ $claimUrl }}" style="background:#0d6efd;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;">Claim your award</a></p>
    @endif
    <p>Keep up the great work!</p>
  </body>
</html>
