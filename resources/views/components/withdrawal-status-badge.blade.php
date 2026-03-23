@props(['status'])

@php
  $classes = [
      'pending' => 'bg-warning text-dark',
      'approved' => 'bg-info text-dark',
      'rejected' => 'bg-danger',
      'completed' => 'bg-success',
  ];
@endphp

<span {{ $attributes->class(['badge', $classes[$status] ?? 'bg-secondary']) }}>
  {{ ucwords(str_replace('_', ' ', $status)) }}
</span>
