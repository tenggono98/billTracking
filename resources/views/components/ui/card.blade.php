@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800']) }}>
    @if($title || $subtitle)
        <div class="border-b border-neutral-200 px-4 py-3 sm:px-6 sm:py-4 dark:border-neutral-700">
            @if($title)
                <h3 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="mt-1 text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    
    <div class="p-4 sm:p-6">
        {{ $slot }}
    </div>
</div>

