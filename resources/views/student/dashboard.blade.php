@extends('student.studentsidebar')

@section('content')
<div class="container py-4">

  <style>
    .carousel-inner { background: transparent !important; }

    .caption-glass{
      background: rgba(0,0,0,0.35);
      color: #fff;
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 14px;
      padding: 10px 14px;
      box-shadow:0 8px 24px rgba(0,0,0,.25);
      backdrop-filter: blur(3px);
    }
    .carousel-caption{ left: 1rem; right: auto; bottom: 1rem; text-align: left; }
    .caption-glass h6{ margin: 0 0 4px; font-weight: 700; }
    .caption-glass p{ margin: 0; font-size: .925rem; opacity: .95; }

    /* Announcement image: show full image (no crop) */
    .post-image-wrap{
      width: 100%;
      background: transparent;
      text-align: center;
    }
    .post-image{
      width: 100%;
      height: auto;
      max-height: 420px;
      object-fit: contain;
      display: inline-block;
    }
  </style>

  <!-- Title -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-white">Students</h3>
    <div class="d-flex align-items-center gap-2"></div>
  </div>

  <!-- Hero: Image Carousel -->
  <div class="card border-0 shadow mb-4" style="border-radius: 16px; overflow: hidden;">
    <div id="campusCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>

      <div class="carousel-inner text-center">
        <div class="carousel-item active">
          <img src="{{ asset('img/main_entrance.jpg') }}" alt="Main Entrance" class="d-block mx-auto" style="width:100%; max-height:500px; object-fit:contain;">
        </div>
        <div class="carousel-item">
          <img src="{{ asset('img/BSU.jpg') }}" alt="BSU" class="d-block mx-auto" style="width:100%; max-height:500px; object-fit:contain;">
        </div>
        <div class="carousel-item">
          <img src="{{ asset('img/BSU1.jpg') }}" alt="BSU1" class="d-block mx-auto" style="width:100%; max-height:500px; object-fit:contain;">
        </div>
      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#campusCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#campusCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
    </div>
  </div>

  <!-- Posts -->
  <div class="mb-3 d-flex align-items-center justify-content-between">
    <h5 class="mb-0 fw-bold">Latest Announcements</h5>
    @if(isset($posts) && method_exists($posts, 'total'))
      <span class="text-muted small">{{ $posts->total() }} total</span>
    @endif
  </div>

  <div class="row">
    @forelse($posts as $post)
      @php
        $now = \Carbon\Carbon::now();

        $active = $post->Start_date && ($post->End_date ?? true)
                  ? $now->between(\Carbon\Carbon::parse($post->Start_date), \Carbon\Carbon::parse($post->End_date ?? $now->copy()->addCentury()))
                  : false;

        // Default placeholder
        $imgSrc = asset('img/placeholders/post-placeholder.svg');

        if (!empty($post->image)) {
            $val = $post->image;

            if (is_string($val) && \Illuminate\Support\Str::startsWith($val, ['data:image/'])) {
                $imgSrc = $val;

            } elseif (is_string($val) && \Illuminate\Support\Str::startsWith($val, ['http://','https://','/'])) {
                $imgSrc = $val;

            } elseif (is_string($val) && !\Illuminate\Support\Str::contains($val, "\0")
                      && preg_match('/^[\x20-\x7E\x0A\x0D\t]+$/', $val)) {
                // Looks like relative storage path (uploads/posts/xyz.jpg)
                $imgSrc = \Illuminate\Support\Facades\Storage::url($val);

            } else {
                // Legacy BLOB -> base64
                $bin  = (string) $val;
                $mime = 'image/jpeg';
                if (strncmp($bin, "\x89PNG", 4) === 0)        $mime = 'image/png';
                elseif (strncmp($bin, "GIF8", 4) === 0)       $mime = 'image/gif';
                elseif (strncmp($bin, "\xFF\xD8\xFF", 3) === 0)$mime = 'image/jpeg';
                $imgSrc = 'data:'.$mime.';base64,'.base64_encode($bin);
            }
        }

        $rcpt = $post->recipients->first() ?? null;
      @endphp

      <div class="col-12 col-md-6 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 14px; overflow:hidden;">
          <div class="post-image-wrap">
            <img src="{{ $imgSrc }}" alt="Post Image" class="post-image">
          </div>

          <div class="card-body">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <h6 class="mb-0 fw-bold">{{ $post->Title }}</h6>
              @if($active)
                <span class="badge bg-success">Active</span>
              @endif
              @if($rcpt && $rcpt->is_read)
                <span class="badge bg-secondary">Read</span>
              @endif
            </div>

            <div class="text-muted small mb-2">
              @if($post->Start_date)
                <span><i class="bi bi-calendar-event me-1"></i>{{ \Carbon\Carbon::parse($post->Start_date)->format('M d, Y') }}</span>
              @endif
              @if($post->End_date)
                <span class="ms-1">– {{ \Carbon\Carbon::parse($post->End_date)->format('M d, Y') }}</span>
              @endif
            </div>

            {{-- Announcement is plain text; preserve line breaks --}}
            <p class="mb-0" style="white-space: pre-line;">{{ $post->Announcement }}</p>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12 text-center text-muted py-4">
        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
        No announcements yet.
      </div>
    @endforelse
  </div>

  @if(isset($posts) && method_exists($posts, 'links'))
    <div class="mt-3">
      {{ $posts->links('pagination::bootstrap-5') }}
    </div>
  @endif

</div>
@endsection
