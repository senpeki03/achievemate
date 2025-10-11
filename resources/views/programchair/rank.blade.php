@extends('programchair.programchairsidebar')

@section('content')
<div class="container py-4">

  <!-- Title Bar -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Creation Rank</h3>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" id="addRowBtn" title="Add row">
        <i class="bi bi-plus-lg"></i>
      </button>
      <button class="btn btn-primary px-4" form="rankRulesForm">
        <i class="bi bi-save me-1"></i> Save Rules
      </button>
    </div>
  </div>

  <form id="rankRulesForm" method="POST" action="{{ route('programchair.rank.save') }}">
    @csrf

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">

        @if (session('success'))
          <div class="alert alert-success m-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
          <div class="alert alert-danger m-3">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 360px;">GWA (min → max)</th>
                <th>Rank</th>
                <th style="width: 70px;"></th>
              </tr>
            </thead>
            <tbody id="rulesBody">
              @php
                $defaults = [
                  ['min_gwa' => '1.0000', 'max_gwa' => '1.2500', 'rank_name' => 'Tech Savant'],
                  ['min_gwa' => '1.2501', 'max_gwa' => '1.5000', 'rank_name' => 'Tech Virtuoso'],
                  ['min_gwa' => '1.5001', 'max_gwa' => '1.7500', 'rank_name' => 'Tech Prodigy'],
                ];
                $rows = (isset($rules) && count($rules)) ? $rules : $defaults;
              @endphp

              @foreach ($rows as $i => $r)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <input type="number" step="0.0001" min="0" max="5"
                             class="form-control" name="rules[{{ $i }}][min_gwa]"
                             value="{{ old("rules.$i.min_gwa", $r['min_gwa']) }}"
                             placeholder="Min (e.g., 1.0000)" required>
                      <span class="text-muted">→</span>
                      <input type="number" step="0.0001" min="0" max="5"
                             class="form-control" name="rules[{{ $i }}][max_gwa]"
                             value="{{ old("rules.$i.max_gwa", $r['max_gwa']) }}"
                             placeholder="Max (e.g., 1.2500)" required>
                    </div>
                  </td>
                  <td>
                    <input type="text" class="form-control"
                           name="rules[{{ $i }}][rank_name]"
                           value="{{ old("rules.$i.rank_name", $r['rank_name']) }}"
                           placeholder="Rank title (e.g., Tech Savant)" required>
                  </td>
                  <td class="text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Remove">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="p-3 small text-muted">
          Tips:
          <ul class="mb-0">
            <li>Use 4 decimal places for GWA (e.g., <code>1.2500</code>).</li>
            <li>Ranges must not overlap and <em>min ≤ max</em>.</li>
            <li>Lower GWA is better (1.0000 is top).</li>
          </ul>
        </div>

      </div>
    </div>
  </form>
</div>

<script>
(function () {
  const body = document.getElementById('rulesBody');
  const addBtn = document.getElementById('addRowBtn');

  function nextIndex() { return body.querySelectorAll('tr').length; }

  function addRow(prefill = { min_gwa: '', max_gwa: '', rank_name: '' }) {
    const i = nextIndex();
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <div class="d-flex align-items-center gap-2">
          <input type="number" step="0.0001" min="0" max="5"
                 class="form-control" name="rules[${i}][min_gwa]"
                 value="${prefill.min_gwa}" placeholder="Min (e.g., 1.0000)" required>
          <span class="text-muted">→</span>
          <input type="number" step="0.0001" min="0" max="5"
                 class="form-control" name="rules[${i}][max_gwa]"
                 value="${prefill.max_gwa}" placeholder="Max (e.g., 1.2500)" required>
        </div>
      </td>
      <td>
        <input type="text" class="form-control"
               name="rules[${i}][rank_name]"
               value="${prefill.rank_name}" placeholder="Rank title" required>
      </td>
      <td class="text-end">
        <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Remove">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
    `;
    body.appendChild(tr);
  }

  addBtn?.addEventListener('click', () => addRow());
  body.addEventListener('click', (e) => {
    if (e.target.closest('.remove-row')) e.target.closest('tr')?.remove();
  });
})();
</script>
@endsection
