@props([
    'title',
    'url' => null,
    'isPdf' => false,
])

<div {{ $attributes->class(['card h-100']) }}>
  <div class="card-header fw-semibold">{{ $title }}</div>
  <div class="card-body">
    @if($url)
      @if($isPdf)
        <iframe src="{{ $url }}" title="{{ $title }}" class="w-100 border rounded" style="height: 360px;"></iframe>
      @else
        <img src="{{ $url }}" alt="{{ $title }}" class="img-fluid rounded border">
      @endif
      <div class="mt-3">
        <a href="{{ $url }}" target="_blank" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-up-right-from-square me-2"></i>Open Document
        </a>
      </div>
    @else
      <div class="text-muted small">Document not uploaded.</div>
    @endif
  </div>
</div>
