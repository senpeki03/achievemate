{{-- resources/views/reports/deans_honor_list.blade.php --}}

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dean’s Honors List</title>
  <style>
    /* ===== Base ===== */
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 6px 8px; }
    th { text-align: center; }
    .t-center { text-align: center; }
    .t-right  { text-align: right; }

    /* Equal page gutter so spacing left/right is balanced */
    .page-gutter { padding: 0 25pt; }

    /* ===== Header Table ===== */
    .header-table { width: 100%; border: none; margin: 0 auto 6px; table-layout: fixed; }
    .header-table td { border: none; vertical-align: middle; }

    /* Equal side cells so center text is truly centered */
    .logo-cell { width: 150pt; text-align: center; vertical-align: middle; }

    /* --- Split sizing per logo (DOMPDF-safe) --- */
    /* LEFT (BatStateU): slightly smaller */
    .logo-left-box  {
      width: 82pt; height: 82pt; margin: 0 auto; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .logo-left-box img  {
      height: 92%; width: auto; display: block; object-fit: contain;
    }

    /* RIGHT (CICS): zoom a bit to compensate transparent padding */
    .logo-right-box {
      width: 82pt; height: 82pt; margin: 0 auto; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .logo-right-box img {
      height: 108%; width: auto; display: block; object-fit: contain;
    }

    /* TIP: if di pa eksaktong pantay sa mata, adjust these only:
       .logo-left-box  img { height: 90–95%; }
       .logo-right-box img { height: 105–112%; }
    */

    /* ===== Header Text ===== */
    .header-text { text-align: center; line-height: 1.18; white-space: nowrap; }
    .uni      { font-size: 15pt; font-weight: 800; letter-spacing: .3px; }
    .national { font-size: 11pt; font-weight: 800; color: #b91c1c; }
    .campus   { font-size: 10pt; font-weight: 700; }
    .college  { font-size: 10.5pt; font-weight: 700; }

    /* ===== Red divider line + dots (unchanged) ===== */
    .rule-table { width: 92%; margin: 8px auto 12px; border: none; }
    .rule-table td { border: none; vertical-align: middle; }
    .rule-dot { width: 8px; height: 8px; border: 2px solid #b91c1c; border-radius: 50%; display: inline-block; }
    .rule-bar { height: 2px; background: #b91c1c; width: 100%; display: block; }

    /* ===== Title Block ===== */
    .title-main { font-size: 20pt; font-weight: 800; margin: 2px 0 6px; }
    .title-dept { font-size: 12pt; font-weight: 800; letter-spacing: .2px; white-space: nowrap; }
    .title-term { font-size: 11pt; }

    /* ===== Data Table Column Widths ===== */
    .w-no { width: 6%; } .w-name { width: 34%; } .w-prog { width: 16%; }
    .w-year { width: 14%; } .w-gwa { width: 12%; } .w-rank { width: 18%; }
  </style>
</head>
<body>

  {{-- ===== HEADER (logos split sizing) ===== --}}
  <div class="page-gutter">
    <table class="header-table">
      <tr>
        <td class="logo-cell">
          <div class="logo-left-box">
            <img src="{{ public_path('img/Batangas_State_Logo.png') }}" alt="Batangas State University Logo">
          </div>
        </td>

        <td class="header-text">
          <div class="uni">BATANGAS STATE UNIVERSITY</div>
          <div class="national">THE NATIONAL ENGINEERING UNIVERSITY</div>
          <div class="campus">ARASOF - Nasugbu Campus</div>
          <div class="college">College of Informatics and Computing Sciences</div>
        </td>

        <td class="logo-cell">
          <div class="logo-right-box">
            <img src="{{ public_path('img/cics_logo.png') }}" alt="CICS Logo">
          </div>
        </td>
      </tr>
    </table>
  </div>

  {{-- ===== Red divider (unchanged) ===== --}}
  <table class="rule-table">
    <tr>
      <td style="width:12px;"><span class="rule-dot"></span></td>
      <td><span class="rule-bar"></span></td>
      <td style="width:12px; text-align:right;"><span class="rule-dot"></span></td>
    </tr>
  </table>

  {{-- ===== Report Title Block ===== --}}
  <div class="t-center">
    <div class="title-main">{{ $header['title'] ?? "DEAN’S HONORS LIST" }}</div>
    <div class="title-dept">{{ $header['department'] ?? 'INFORMATION TECHNOLOGY EDUCATION PROGRAMS DEPARTMENT' }}</div>
    <div class="title-term">
      {{ $header['term'] ?? 'SECOND SEMESTER' }}, {{ $header['ay'] ?? 'A.Y 2024-2025' }}
    </div>
  </div>

  {{-- ===== Data Table ===== --}}
  <table>
    <thead>
      <tr>
        <th class="w-no">No.</th>
        <th class="w-name">Name</th>
        <th class="w-prog">Program</th>
        <th class="w-year">Year Level</th>
        <th class="w-gwa">GWA</th>
        <th class="w-rank">Rank</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="t-center">{{ $r['no'] }}</td>
          <td>{{ $r['name'] }}</td>
          <td class="t-center">{{ $r['program'] }}</td>
          <td class="t-center">{{ $r['year'] }}</td>
          <td class="t-center">{{ $r['gwa'] }}</td>
          <td class="t-center">{{ $r['rank'] }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="t-center">No approved students.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="t-right" style="margin-top: 10px;">Page 1</div>
</body>
</html>
