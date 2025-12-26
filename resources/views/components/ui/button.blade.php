@props([
    'variant' => 'primary',
    'type' => 'button',
    'size' => 'md',
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    
    $variantClasses = match($variant) {
        'primary' => 'bg-neutral-800 text-white hover:bg-neutral-700 focus:ring-neutral-500 dark:bg-neutral-200 dark:text-neutral-800 dark:hover:bg-neutral-300',
        'secondary' => 'bg-neutral-200 text-neutral-800 hover:bg-neutral-300 focus:ring-neutral-500 dark:bg-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-600',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'outline' => 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700',
        default => 'bg-neutral-800 text-white hover:bg-neutral-700 focus:ring-neutral-500',
    };
    
    $sizeClasses = match($size) {
        'sm' => 'px-2.5 py-1.5 text-xs sm:px-3 sm:text-sm min-h-[44px] sm:min-h-0',
        'md' => 'px-3 py-2 text-xs sm:px-4 sm:text-sm min-h-[44px] sm:min-h-0',
        'lg' => 'px-4 py-2.5 text-sm sm:px-6 sm:py-3 sm:text-base min-h-[44px] sm:min-h-0',
        default => 'px-3 py-2 text-xs sm:px-4 sm:text-sm min-h-[44px] sm:min-h-0',
    };
    
    $classes = "$baseClasses $variantClasses $sizeClasses";
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>

