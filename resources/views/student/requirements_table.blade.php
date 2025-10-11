<div class="card">
  <div class="card-header">Requirements</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0 align-middle">
      <thead>
        <tr>
          <th>Requirement</th>
          <th>Status</th>
          <th>File</th>
          <th style="width:260px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($app->requirements as $req)
          <tr>
            <td>{{ optional($req->type)->name ?? 'Document' }}</td>
            <td>
              <span class="badge
                @if($req->status==='approved') bg-success
                @elseif($req->status==='rejected' || $req->status==='returned') bg-danger
                @else bg-secondary @endif">
                {{ $req->status }}
              </span>
            </td>
            <td>
              @if($req->file_path)
                <a href="{{ asset('storage/'.$req->file_path) }}" target="_blank">
                  {{ $req->file_name }}
                </a>
              @else
                <em class="text-muted">none</em>
              @endif
            </td>
            <td>
              <form method="POST" action="{{ route('student.graduation.upload', $req->GradReq_id) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="file" class="form-control form-control-sm" required>
                <button class="btn btn-sm btn-primary">Upload</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="text-center text-muted py-4">
              No requirements seeded for this application yet.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
