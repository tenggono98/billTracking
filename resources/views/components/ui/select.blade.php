@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'error' => null,
])

<div class="grid gap-1.5 sm:gap-2">
    @if($label)
        <label for="{{ $name }}" class="text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-sm sm:px-3 sm:py-2 text-neutral-900 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-1 sm:focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:focus:border-neutral-500 dark:focus:ring-neutral-500'
        ]) }}
    >
        {{ $slot }}
    </select>
    
    @if($error)
        <p class="text-xs sm:text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>

