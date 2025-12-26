@props([
    'title' => null,
    'show' => false,
    'size' => 'md', // sm, md, lg, xl, 2xl
    'closeable' => true,
    'noWrapper' => false, // Set to true if using external wrapper
    'showFooter' => true, // Show footer with close button
    'footerText' => 'Tutup', // Footer button text
])

@php
    $sizeClasses = match($size) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-4xl',
        '2xl' => 'max-w-6xl',
        default => 'max-w-md',
    };
    
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
    $hasWireModel = $wireModel !== null;
    $otherAttributes = $attributes->except(['wire:model', 'size', 'title', 'show', 'closeable', 'x-data', 'noWrapper', 'showFooter', 'footerText']);
@endphp

@if(!$noWrapper)
<div 
    x-data="{
        @if($hasWireModel)
            show: @entangle($wireModel),
        @else
            show: @js($show),
        @endif
        close() {
            this.show = false;
        }
    }"
    x-show="show"
    {!! $otherAttributes->merge([]) !!}
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-2 sm:p-4"
    style="display: none;"
    @keydown.escape.window="if (show && closeable) close()"
    @click.self="if (closeable) close()"
>
@endif
    <div 
        class="w-full {{ $sizeClasses }} max-w-[95vw] sm:max-w-[95vw] max-h-[95vh] sm:max-h-[90vh] rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800 flex flex-col"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
    >
        @if($title || $closeable)
            <div class="border-b border-neutral-200 px-4 py-3 sm:px-6 sm:py-4 dark:border-neutral-700 flex-shrink-0 flex items-center justify-between">
                @if($title)
                    <h3 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ $title }}</h3>
                @else
                    <div></div>
                @endif
                @if($closeable)
                    <button
                        type="button"
                        @click="
                            const parent = $el.closest('[x-data]');
                            if (parent && parent.__x && parent.__x.$data && typeof parent.__x.$data.close === 'function') {
                                parent.__x.$data.close();
                            } else if (typeof close === 'function') {
                                close();
                            }
                        "
                        class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 focus:outline-none focus:ring-2 focus:ring-neutral-500 dark:hover:bg-neutral-700 dark:hover:text-neutral-300"
                        aria-label="Tutup"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @endif
            </div>
        @endif
        
        <!-- Content Area - Scrollable -->
        <div class="flex-1 overflow-y-auto min-h-0 p-4 sm:p-6">
            {{ $slot }}
        </div>
        
        <!-- Footer with Close Button -->
        @if($showFooter && $closeable)
            <div class="border-t border-neutral-200 px-4 py-3 sm:px-6 sm:py-4 dark:border-neutral-700 flex-shrink-0 flex justify-end">
                <x-ui.button
                    type="button"
                    variant="outline"
                    @click="
                        const parent = $el.closest('[x-data]');
                        if (parent && parent.__x && parent.__x.$data && typeof parent.__x.$data.close === 'function') {
                            parent.__x.$data.close();
                        } else if (typeof close === 'function') {
                            close();
                        }
                    "
                >
                    {{ $footerText }}
                </x-ui.button>
            </div>
        @endif
    </div>
@if(!$noWrapper)
</div>
@endif
