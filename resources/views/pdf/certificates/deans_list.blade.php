<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 0; }
    html, body { margin:0; padding:0; font-family: "DejaVu Sans", sans-serif; }
    .sheet {
      position: relative;
      width: 100vw; height: 100vh;
      background-image: url("{{ public_path('img/cert/dean-template.png') }}");
      background-repeat: no-repeat;
      background-position: center center;
      background-size: cover;
    }
    .name {
      position: absolute;
      top: 38.5%;
      left: 58%;
      width: 36%;
      transform: translateY(-50%);
      font-weight: 700;
      font-size: 36px;
      color: #d71920;
      line-height: 1.1;
      word-break: break-word;
    }
    .degree {
      position: absolute;
      top: 46.5%;
      left: 58%;
      width: 36%;
      font-style: italic;
      font-weight: 700;
      font-size: 18px;
      color: #333;
    }
    .citation {
      position: absolute;
      top: 58%;
      left: 58%;
      width: 36%;
      font-size: 16px;
      line-height: 1.5;
      color: #222;
      text-align: left;
    }
    .date {
      position: absolute;
      top: 74%;
      left: 58%;
      width: 36%;
      font-size: 16px;
    }
    .signature {
      position: absolute;
      top: 78%;
      left: 58%;
      width: 24%;
      height: 70px;
      background: url("{{ file_exists(public_path('img/cert/dean-signature.png')) ? public_path('img/cert/dean-signature.png') : '' }}") no-repeat left bottom;
      background-size: contain;
    }
    .dean-line {
      position: absolute;
      top: 88%;
      left: 58%;
      width: 36%;
      font-size: 14px;
      font-weight: 700;
      color: #d71920;
    }
    .dean-title {
      position: absolute;
      top: 91%;
      left: 58%;
      width: 36%;
      font-size: 13px;
      color: #111;
    }
  </style>
</head>
<body>
  <div class="sheet">
    {{-- Big name --}}
    <div class="name">{{ strtoupper($student_name) }}</div>

    {{-- Degree / program --}}
    @if($degree_line)
      <div class="degree">{{ $degree_line }}</div>
    @endif

    {{-- Paragraph citation (fixed) --}}
    <div class="citation">
      In recognition of outstanding academic achievement:
      <strong>DEAN’S HONORS LIST {{ $honor_tier ? "({$honor_tier})" : "" }}</strong>
      {{ $gwa ? " who achieved a General Weighted Average of {$gwa}" : " who achieved outstanding academic performance" }}
      @php
        $parts = [];
        if (!empty($semester)) $parts[] = $semester;
        if (!empty($school_year) && $school_year !== '—') $parts[] = 'AY '.$school_year;
      @endphp
      for the {{ implode(', ', $parts) }}.
    </div>

    {{-- Date conferred --}}
    <div class="date">Date Conferred: {{ $date_conferred }}</div>

    {{-- Signature (optional) + Dean block --}}
    <div class="signature"></div>
    <div class="dean-line">{{ $dean_name }}</div>
    <div class="dean-title">{{ $dean_title }}</div>
  </div>
</body>
</html>
