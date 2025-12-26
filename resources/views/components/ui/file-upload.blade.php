@props([
    'label' => null,
    'name' => null,
    'accept' => 'image/*',
    'required' => false,
    'error' => null,
    'preview' => null,
])

<div class="grid gap-1.5 sm:gap-2">
    @if($label)
        <label class="text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <div class="relative">
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $name }}"
            accept="{{ $accept }}"
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge([
                'class' => 'block w-full text-xs sm:text-sm text-neutral-500 file:mr-2 sm:file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-2 file:py-1.5 sm:file:px-4 sm:file:py-2 file:text-xs sm:file:text-sm file:font-medium file:text-neutral-700 hover:file:bg-neutral-200 dark:file:bg-neutral-700 dark:file:text-neutral-200 dark:hover:file:bg-neutral-600'
            ]) }}
        />
    </div>
    
    @if($preview)
        <div class="mt-2">
            <img src="{{ $preview }}" alt="Preview" class="max-h-48 sm:max-h-64 w-full object-contain rounded-md border border-neutral-300 dark:border-neutral-600" />
        </div>
    @endif
    
    @if($error)
        <p class="text-xs sm:text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>

