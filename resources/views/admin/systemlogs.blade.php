  @extends('admin.adminlayout')

  @section('content')
  <div class="container py-4">
    <div class="card shadow-sm rounded-4">
      <div class="card-body">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="fw-bold mb-0">System Logs</h4>
          <i class="bi bi-clipboard-data fs-4 text-primary"></i>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>UserId</th>
                <th>Firstname</th>
                <th>Middlename</th>
                <th>Lastname</th>
                <th>Action</th>
                <th>Ip Address</th>
                <th>Created_at</th>
              </tr>
            </thead>
            <tbody>
              @foreach($logs as $log)
                <tr>
                  <td>{{ $log->userId }}</td>
                  <td>{{ $log->firstname }}</td>
                  <td>{{ $log->middlename }}</td>
                  <td>{{ $log->lastname }}</td>
                  <td>{{ $log->action }}</td>
                  <td>{{ $log->ip_address }}</td>
                  <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d h:i A') }}</td>
                </tr>
              @endforeach
            </tbody>

          </table>
        </div>

        @if($logs->isEmpty())
          <div class="text-center text-muted mt-4">No logs found.</div>
        @endif

      </div>
    </div>
  </div>
  @endsection
