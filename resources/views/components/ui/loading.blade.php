@props([
    'size' => 'md',
    'text' => null,
])

@php
    $sizeClasses = match($size) {
        'sm' => 'h-4 w-4 border-2',
        'md' => 'h-8 w-8 border-2',
        'lg' => 'h-12 w-12 border-4',
        default => 'h-8 w-8 border-2',
    };
@endphp

<div class="flex items-center justify-center gap-2">
    <div 
        class="animate-spin rounded-full border-neutral-300 border-t-neutral-600 dark:border-neutral-600 dark:border-t-neutral-300 {{ $sizeClasses }}"
        {{ $attributes }}
    ></div>
    @if($text)
        <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ $text }}</span>
    @endif
</div>

