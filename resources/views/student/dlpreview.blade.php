@extends('student.studentlayout')

@section('content')
<div class="container py-5">
  <div class="card shadow rounded-4 p-5" style="max-height: 80vh; overflow-y: auto;">

    <!-- Title -->
    <h3 class="fw-bold text-center mb-1">REPORT OF RATINGS</h3>
    <h6 class="text-center mb-4">{{ strtoupper($semesterAy ?? 'SEMESTER, AY XXXX-XXXX') }}</h6>

    <!-- Student Info -->
    <div class="mb-4">
      <table style="width: 100%; font-size: 16px; margin-bottom: 8px;">
        <tr>
          <td style="width: 10%; vertical-align: bottom;"><strong>Name:</strong></td>
          <td style="width: 30%; border-bottom: 2px solid black; text-align: center; font-weight: bold;">
            {{ strtoupper($lastname) }}
          </td>
          <td style="width: 30%; border-bottom: 2px solid black; text-align: center; font-weight: bold;">
            {{ strtoupper($firstname) }}
          </td>
          <td style="width: 30%; border-bottom: 2px solid black; text-align: center; font-weight: bold;">
            {{ strtoupper($middlename) }}.
          </td>
        </tr>
        <tr>
          <td></td>
          <td style="text-align: center;">Last</td>
          <td style="text-align: center;">First</td>
          <td style="text-align: center;">M.I.</td>
        </tr>
      </table>

      <p><strong>Contact Number:</strong> {{ $contact ?? '___________' }}</p>
      <p><strong>Course:</strong> <u>{{ strtoupper(str_replace('BS', 'BACHELOR OF SCIENCE', $course)) }}</u></p>
      <p><strong>Yr./Sec.:</strong> <u>{{ strtoupper($Year) }}</u>&nbsp;&nbsp;
         <strong>Track:</strong> <u>{{ strtoupper($track ?? 'N/A') }}</u></p>
      <p><strong>Scholarship Grant:</strong> <u>{{ strtoupper($scholarship ?? 'N/A') }}</u></p>
    </div>

    <!-- Grades Table -->
    <table class="table table-bordered text-center align-middle" style="font-size: 14px;">
      <thead class="table-light">
        <tr>
          <th>COURSES</th>
          <th>FINAL GRADE</th>
          <th>UNITS</th>
          <th>WEIGHTED GRADE</th>
        </tr>
      </thead>
      <tbody>
        @for($i = 0; $i < count($grades['Course Code']); $i++)
          <tr>
            <td class="text-start">
              {{ strtoupper($grades['Course Code'][$i] . ' ' . $grades['Course Title'][$i]) }}
            </td>
            <td>
              @if(is_numeric($grades['Grade'][$i]))
                {{ number_format((float)$grades['Grade'][$i], 2) }}
              @else
                Invalid
              @endif
            </td>
            <td>
              @if(is_numeric($grades['Units'][$i]))
                {{ number_format((float)$grades['Units'][$i], 2) }}
              @else
                0.00
              @endif
            </td>
            <td>
              @if(is_numeric($grades['Units'][$i]) && is_numeric($grades['Grade'][$i]))
                {{ number_format((float)$grades['Units'][$i] * (float)$grades['Grade'][$i], 2) }}
              @else
                0.00
              @endif
            </td>
          </tr>
        @endfor
      </tbody>
      <tfoot class="text-center">
        <tr>
          <th colspan="2">TOTAL</th>
          <td>{{ $totalUnits }}</td>
          <td>{{ number_format($totalWG, 2) }}</td>
        </tr>
        <tr>
          <th colspan="3">WEIGHTED AVERAGE</th>
          <td><strong>{{ number_format($gwa, 4) }}</strong></td>
        </tr>
        <tr>
          <th colspan="3">RANK</th>
          <td><strong>{{ strtoupper($rank) }}</strong></td>
        </tr>
      </tfoot>
    </table>

    <!-- Upload Confirmation Form -->
    <form method="POST" action="{{ route('upload.confirm') }}" class="text-end mt-4">
      @csrf
      <input type="hidden" name="firstname" value="{{ $firstname }}">
      <input type="hidden" name="middlename" value="{{ $middlename }}">
      <input type="hidden" name="lastname" value="{{ $lastname }}">
      <input type="hidden" name="Year" value="{{ $Year }}">
      <input type="hidden" name="contact" value="{{ $contact }}">
      <input type="hidden" name="track" value="{{ $track }}">
      <input type="hidden" name="gwa" value="{{ $gwa }}">
      <input type="hidden" name="rank" value="{{ $rank }}">
      <input type="hidden" name="courses" value="{{ $courses }}">
      <input type="hidden" name="curriculum_id" value="{{ $curriculum_id }}">
      <input type="hidden" name="post_id" value="{{ $post_id }}">
      <input type="hidden" name="file_name" value="{{ $file_name }}">
      <input type="hidden" name="file_data" value="{{ $file_data }}">
      <input type="hidden" name="status" value="Pending">

      <button type="submit" class="btn btn-success">✔ Confirm & Upload</button>
    </form>

  </div>
</div>
@endsection
