{{-- resources/views/reports/deans_honor_list.blade.php --}}

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dean’s Honors List</title>
  <style>
    /* ===== Base ===== */
    body { 
      font-family: DejaVu Sans, sans-serif; 
      font-size: 12px; 
      margin: 0; 
      text-align: center; /* Centering content */
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin: 0 auto; /* Centering table */
    }
    th, td { 
      border: 1px solid #000; 
      padding: 6px 8px; 
    }
    th { text-align: center; }
    .t-center { text-align: center; }
    .t-right  { text-align: right; }
    .t-left   { text-align: left; }

    .page-gutter { padding: 0 15pt; }

    /* ===== Header Table ===== */
    .header-table {
      width: 100%;
      border: none;
      margin: 0 auto; /* Centering */
      table-layout: auto; /* Allow text wrapping */
    }
    .header-table td { border: none; vertical-align: middle; }

    .logo-cell {
      width: 15%; /* Decreased the logo width */
      text-align: left; /* Align logo to the left */
      padding-top: -15px; /* Moves logo up */
    }
    .text-cell {
      width: 70%; /* Increased the text width */
      text-align: center; /* Center text */
      position: relative; /* Ensure text aligns under the logo */
      z-index: 1; /* Ensure the text is above the background */
    }
    .spacer-cell {
      width: 15%; /* Adjusted spacing to ensure proper centering */
    }

    .logo-left-box {
      width: 85pt; height: 85pt; /* Increased logo size */
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto;
      position: absolute; /* Logo stays fixed */
      left: 0;
      top: 0;
    }
    .logo-left-box img {
      height: 100%; width: auto; display: block; object-fit: contain;
    }

    /* ===== Header Text (gaya ng sample mo) ===== */
    .header-text { 
      text-align: center; 
      line-height: 1.0; /* Reduced line height */
      margin-top: 10px; /* Adjusted to move text down */
    }
    .gov       { font-size: 9pt;}
    .uni       { font-size: 16pt; font-weight: 800; letter-spacing: .5px; }
    .national  { font-size: 11pt; font-weight: 800; color: #b91c1c; }
    .campus    { font-size: 10pt; font-weight: 700; margin-top: 4px; }
    .addr      { font-size: 9pt; margin-top: 4px; }
    .contact   { font-size: 9pt; margin-top: 4px; }

    /* Divider + college line */
    .divider-line {
      width: 100%;
      border-top: 1px solid #000;
      margin: 10px 0 0;
    }
    .college-line {
      font-size: 10pt;
      font-weight: 700;
      margin: 4px 0 12px;
    }

    /* ===== Title Block ===== */
    .title-main { font-size: 14pt; font-weight: 800; margin: 4px 0 2px; }
    .title-dept { font-size: 11pt; font-weight: 800; letter-spacing: .2px; }
    .title-term { font-size: 10pt; margin-top: 2px; }

    /* ===== Data Table Column Widths ===== */
    .w-no   { width: 6%; }
    .w-name { width: 36%; }
    .w-prog { width: 14%; }
    .w-year { width: 14%; }
    .w-gwa  { width: 10%; }
    .w-rank { width: 20%; }

    /* ===== Footer ===== */
    .footer-table { width: 100%; border: none; margin-top: 18px; }
    .footer-table td { border: none; vertical-align: top; font-size: 10pt; }

    .footer-label { font-weight: 600; margin-bottom: 18px; }
    .footer-name  { font-weight: 700; text-transform: uppercase; }
    .footer-title { font-size: 9pt; }

    .tagline {
      margin-top: 25px;
      font-size: 9.5pt;
      font-weight: 700;
      color: #b91c1c;
      text-align: center;
    }

    .single-line {
      margin-top: 2px;
      margin-bottom: 0;
      line-height: 1.1;
      white-space: nowrap; /* para hindi mag wrap */
    }
  </style>
</head>
<body>

  {{-- ===== HEADER ===== --}}
  <div class="page-gutter">
    <table class="header-table">
      <tr>
        {{-- logo sa kaliwa --}}
        <td class="logo-cell">
          <div class="logo-left-box">
            <img src="{{ public_path('img/Batangas_State_Logo.png') }}" alt="Batangas State University Logo">
          </div>
        </td>

        {{-- text sa gitna --}}
        <td class="text-cell">
          <div class="header-text">
            <div class="gov">Republic of the Philippines</div>
            <div class="uni">{{ $header['university'] ?? 'BATANGAS STATE UNIVERSITY' }}</div>
            <div class="national">The National Engineering University</div>
            <div class="campus">ARASOF - Nasugbu Campus</div>

            <div class="addr">
              R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231
            </div>
            <div class="contact">
              Tel No.: (+63 43) 416-0350 local 207
            </div>
            <div class="contact single-line">
              E-mail Address: cics.nasugbu@g.batstate-u.edu.ph | Website Address: http://www.batstate-u.edu.ph
            </div>
          </div>
        </td>

        {{-- spacer lang para ma-center yung text block --}}
        <td class="spacer-cell"></td>
      </tr>
    </table>

    <div class="divider-line"></div>
    <div class="t-center college-line">
      {{ $header['college'] ?? 'College of Informatics and Computing Sciences' }}
    </div>
  </div>

  {{-- ===== Report Title Block ===== --}}
  <div class="t-center">
    <div class="title-main">
      {{ $header['title'] ?? "DEAN'S HONORS LIST" }}
    </div>
    <div class="title-dept">
      {{ $header['department'] ?? 'Information Technology Education Programs Department' }}
    </div>
    <div class="title-term">
      {{ $header['term'] ?? 'SECOND SEMESTER' }},
      AY {{ $header['ay'] ?? '2024-2025' }}
    </div>
  </div>

  {{-- ===== Data Table ===== --}}
  <table>
    <thead>
      <tr>
        <th class="w-no">#</th>
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
        <tr>
          <td colspan="6" class="t-center">No approved students.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  {{-- ===== Footer ===== --}}
  <table class="footer-table">
    <tr>
      <td class="t-left">
        <div class="footer-label">Prepared by:</div>
        <div class="footer-name">
          {{ $footer['prepared_name'] ?? 'ASST. PROF. BENJIE R. SAMONTE' }}
        </div>
        <div class="footer-title">
          {{ $footer['prepared_title'] ?? 'Program Chairperson' }}
        </div>
        <div class="footer-title" style="margin-top:8px;">
          Date Signed: {{ now()->format('m/d/Y') }}
        </div>
      </td>

      <td class="t-right">
        <div class="footer-label">Certified Correct:</div>
        <div class="footer-name">
          {{ $footer['certified_name'] ?? 'DR. LORISSA JOANA BUENAS' }}
        </div>
        <div class="footer-title">
          {{ $footer['certified_title'] ?? 'Dean, CICS' }}
        </div>
        <div class="footer-title" style="margin-top:8px;">
          Date Signed: {{ now()->format('m/d/Y') }}
        </div>
      </td>
    </tr>
  </table>

  <div class="tagline">
    Leading Innovations, Transforming Lives, Building the Nation
  </div>
</body>
</html>
