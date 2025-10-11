@extends('student.studentlayout')

@section('content')
<div class="container py-5">
    <h3 class="mb-4">Player Prop Checklist</h3>
    <div class="progress mb-3">
        <div id="progressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%">0%</div>
    </div>
    <form id="propForm">
        <div class="row">
            @php
                $props = [
                    'Player points' => [
                        'Shai Gilgeous-Alexander 25+',
                        'P. Siakam 12+',
                        'Tyrese Haliburton 15+',
                        'Chet Holmgren 10+',
                        'T.J. McConnell 4+',
                        'Jalen Williams 18+',
                    ],
                    'Player rebounds' => [
                        'Jalen Williams 4+',
                        'P. Siakam 4+',
                        'Chet Holmgren 5+',
                        'O. Toppin 4+',
                    ],
                    'Player assists' => [
                        'Tyrese Haliburton 5+',
                    ],
                    'Player three pointers' => [
                        'Luguentz Dort 1+',
                        'Andrew Nembhard 1+',
                        'A. Caruso 1+',
                        'A. Nesmith 1+',
                    ],
                ];
                $total = collect($props)->flatten()->count();
            @endphp

            @foreach($props as $category => $items)
                <div class="col-md-6">
                    <h5>{{ $category }}</h5>
                    <ul class="list-group mb-3">
                        @foreach($items as $item)
                            <li class="list-group-item">
                                <input type="checkbox" class="form-check-input me-2 prop-check" id="prop{{ $loop->parent->index }}{{ $loop->index }}">
                                <label class="form-check-label" for="prop{{ $loop->parent->index }}{{ $loop->index }}">{{ $item }}</label>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const checkboxes = document.querySelectorAll('.prop-check');
        const progressBar = document.getElementById('progressBar');
        const total = {{ $total }};

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checkedCount = document.querySelectorAll('.prop-check:checked').length;
                const percent = Math.round((checkedCount / total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.innerText = percent + '%';
            });
        });
    });
</script>
@endsection
