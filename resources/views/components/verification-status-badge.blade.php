@props(['status'])

@php
  $status = strtolower((string) $status);
  $classes = match ($status) {
      'approved' => 'bg-success-subtle text-success-emphasis',
      'rejected' => 'bg-danger-subtle text-danger-emphasis',
      default => 'bg-warning-subtle text-warning-emphasis',
  };
@endphp

<span {{ $attributes->class(['badge rounded-pill '.$classes]) }}>
  {{ ucfirst($status ?: 'pending') }}
</span>
