<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Graduating Report</title>

    <style>
        @page {
            margin: 40px 45px 40px 45px;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
        }

        .t-center { text-align: center; }
        .t-right  { text-align: right; }
        .t-left   { text-align: left; }
    </style>
</head>
<body>

@php
    $abbr = $program->Abbreviation ?? $program->Program_name;
    $count = (int) $students->count();
    $countLabel = str_pad($count, 2, '0', STR_PAD_LEFT) . ' student applicant' . ($count > 1 ? 's' : '');
@endphp


{{-- ====================== PAGE 1 ====================== --}}
<div style="position:relative; min-height:900px;"> {{-- PAGE 1 WRAPPER --}}

    {{-- HEADER --}}
    <div class="t-center" style="margin-top:5px; line-height:1.05; position:relative;">
        <img src="{{ public_path('img/Batangas_State_Logo.png') }}"
             style="width:90px; position:absolute; top:5px; left:20px;">

        <div style="font-size:11pt; font-weight:800;">Republic of the Philippines</div>

        <div style="font-size:18pt; font-weight:800;">
            BATANGAS STATE UNIVERSITY
        </div>

        <div style="font-size:12pt; font-weight:800; color:#b91c1c;">
            The National Engineering University
        </div>

        <div style="font-size:10.5pt; font-weight:700;">
            ARASOF-Nasugbu Campus
        </div>

        <div style="font-size:9pt; margin-top:2px;">
            R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231<br>
            Tel No.: (+63 43) 416-0350 local 207
        </div>

        <div style="font-size:9pt; margin-top:2px;">
            E-mail Address: cics.nasugbu@g.batstate-u.edu.ph | Website Address: http://www.batstate-u.edu.ph
        </div>
    </div>

    <hr style="margin-top:10px; border-top:1px solid #000;">

    {{-- COLLEGE --}}
    <div style="font-weight:bold; font-size:12pt; margin-top:5px; margin-left:40px">
        {{ $college->College_name }}
    </div>

    {{-- DATE --}}
    <div style="margin-top:70px; margin-left:40px;">
        {{ $today }}
    </div>

    {{-- RECIPIENT --}}
    <div style="margin-top:30px; margin-left:40px;">
        Mr. ERWIN R. ABIAD<br>
        Head, Registration Services Office<br>
        This University
    </div>

    {{-- SIR --}}
    <div style="margin-top:40px; margin-left:40px;">
        Sir:
    </div>

    {{-- PARAGRAPH 1 --}}
    <p style="margin-left:40px; margin-top:20px; width:85%; text-align:justify;">
        Respectfully forwarding to your good office the {{ $countLabel }} accomplished application
        forms for graduation for the Second Semester of Academic Year 2024–2025 of fourth year CICS
        students broken down as follows:
    </p>

    {{-- PROGRAM LINE --}}
    <div style="text-align:center; margin-top:20px; font-size:12pt;">
        a. {{ $abbr }}
        @if($major)
            - {{ $major->Major_name }}
        @endif

        <span style="display:inline-block; width:220px;"></span>

        - {{ $countLabel }}
    </div>

    {{-- PARAGRAPH 2 --}}
    <p style="margin-left:40px; margin-top:30px; width:85%; text-align:justify;">
        Likewise, forwarding to you the accomplished forms and related documents for the evaluation of
        academic records of these graduating students.
    </p>

    {{-- PARAGRAPH 3 --}}
    <p style="margin-left:40px; margin-top:20px; width:85%; text-align:justify;">
        Attached is the complete list of the names of student applicants. For your evaluation, please.
        Thank you very much.
    </p>

    {{-- SIGNATURE BLOCK --}}
    <div style="margin-top:80px; margin-left:40px;">
        <p>Respectfully yours,</p>

        <p style="margin-top:45px; font-weight:bold;">
            {{ $signatories['dept_chair_name'] }}
        </p>
        <p style="margin-top:-5px;">
            {{ $signatories['dept_chair_title'] }}
        </p>

        <p style="margin-top:55px;">Noted:</p>

        <p style="margin-top:40px; font-weight:bold;">
            {{ $signatories['dean_name'] }}
        </p>
        <p style="margin-top:-5px;">
            {{ $signatories['dean_title'] }}
        </p>
    </div>


    {{-- PAGE 1 FOOTER (FIXED BOTTOM) --}}
    <div style="position:absolute; bottom:20px; right:0; font-size:11pt; text-align:right;">
        Page 1 of 2: Endorsement Letter, Application Forms<br>
        and List of CICS Student Applicants for Graduation, 2nd Sem 2024–2025
    </div>

    <div style="position:absolute; bottom:0; left:0; right:0;
                text-align:center; font-size:11pt; color:#b91c1c; font-weight:bold;">
        Leading Innovations, Transforming Lives, Building the Nation
    </div>

</div> {{-- END PAGE 1 WRAPPER --}}



{{-- ====================== PAGE 2 ====================== --}}
<div style="page-break-before: always;"></div>

<div style="position:relative; min-height:900px;"> {{-- PAGE 2 WRAPPER --}}

    {{-- PAGE 2 CONTENT --}}
    <div style="margin-left:60px; margin-top:40px;">

        {{-- FULL PROGRAM NAME --}}
        <div style="font-size:14pt; font-weight:700;">
            {{ $program->Program_name }}
        </div>

        {{-- MAJOR NAME --}}
        @if($major)
            <div style="font-size:12pt; font-style:italic; margin-top:2px;">
                {{ $major->Major_name }}
            </div>
        @endif

        {{-- STUDENT LIST --}}
        <ol style="margin-top:25px; font-size:12pt;">
            @foreach($students as $s)
                <li style="margin-bottom:6px;">
                    {{ strtoupper($s->Last_name) }}, {{ strtoupper($s->First_name) }}
                    @if($s->Middle_name)
                        {{ strtoupper(mb_substr($s->Middle_name,0,1)) }}.
                    @endif
                </li>
            @endforeach
        </ol>

        {{-- SIGNATORIES --}}
        <div style="margin-top:60px;">
            <p>Prepared by:</p>
            <p style="margin-top:25px; font-weight:bold;">
                {{ $signatories['dept_chair_name'] }}
            </p>
            <p style="margin-top:-5px;">
                {{ $signatories['dept_chair_title'] }}
            </p>

            <p style="margin-top:40px;">Reviewed by:</p>
            <p style="margin-top:25px; font-weight:bold;">
                {{ $signatories['dean_name'] }}
            </p>
            <p style="margin-top:-5px;">
                {{ $signatories['dean_title'] }}
            </p>
        </div>
    </div>


    {{-- PAGE 2 FOOTER (FIXED BOTTOM) --}}
    <div style="position:absolute; bottom:20px; right:0; font-size:11pt; text-align:right;">
        Page 2 of 2: Endorsement Letter, Application Forms<br>
        and List of CICS Student Applicants for Graduation, 2nd Sem 2024–2025
    </div>

    <div style="position:absolute; bottom:0; left:0; right:0;
                text-align:center; font-size:11pt; color:#b91c1c; font-weight:bold;">
        Leading Innovations, Transforming Lives, Building the Nation
    </div>

</div> {{-- END PAGE 2 WRAPPER --}}
</body>
</html>
