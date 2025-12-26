@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'error' => null,
    'placeholder' => 'Pilih atau cari...',
])

@php
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
    $selectId = $name ?? 'select-' . uniqid();
    $wireModelKey = $wireModel ? str_replace('wire:model', '', $wireModel) : null;
@endphp

<div 
    class="grid gap-1.5 sm:gap-2"
    x-data="{
        open: false,
        search: '',
        selectedValue: '',
        selectedText: '',
        options: [],
        filteredOptions: [],
        highlightedIndex: -1,
        
        init() {
            // Extract options from slot
            const selectElement = this.$refs.hiddenSelect;
            if (selectElement) {
                this.options = Array.from(selectElement.options).map(option => ({
                    value: option.value,
                    text: option.text,
                    disabled: option.disabled
                }));
                
                // Get initial value from hidden select
                this.selectedValue = selectElement.value || '';
                
                // Set initial selected text
                const selectedOption = this.options.find(opt => opt.value == this.selectedValue);
                if (selectedOption) {
                    this.selectedText = selectedOption.text;
                }
            }
            
            // Update filtered options
            this.filterOptions();
            
            // Watch for changes in hidden select (from Livewire)
            const hiddenSelect = this.$refs.hiddenSelect;
            if (hiddenSelect) {
                // Use MutationObserver to watch for value changes
                const observer = new MutationObserver(() => {
                    const value = hiddenSelect.value || '';
                    if (value !== this.selectedValue) {
                        this.selectedValue = value;
                        const option = this.options.find(opt => opt.value == value);
                        if (option) {
                            this.selectedText = option.text;
                        } else {
                            this.selectedText = '';
                        }
                    }
                });
                
                observer.observe(hiddenSelect, {
                    attributes: true,
                    attributeFilter: ['value']
                });
                
                // Also listen to change events
                hiddenSelect.addEventListener('change', () => {
                    const value = hiddenSelect.value || '';
                    this.selectedValue = value;
                    const option = this.options.find(opt => opt.value == value);
                    if (option) {
                        this.selectedText = option.text;
                    } else {
                        this.selectedText = '';
                    }
                });
            }
        },
        
        filterOptions() {
            if (!this.search) {
                this.filteredOptions = this.options.filter(opt => !opt.disabled);
            } else {
                const searchLower = this.search.toLowerCase();
                this.filteredOptions = this.options.filter(opt => 
                    !opt.disabled && opt.text.toLowerCase().includes(searchLower)
                );
            }
            this.highlightedIndex = -1;
        },
        
        selectOption(option) {
            this.selectedValue = option.value;
            this.selectedText = option.text;
            this.search = '';
            this.open = false;
            this.filterOptions();
            
            // Update hidden select to trigger Livewire
            if (this.$refs.hiddenSelect) {
                this.$refs.hiddenSelect.value = option.value;
                // Trigger change event for Livewire
                this.$refs.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
                this.$refs.hiddenSelect.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        
        handleKeydown(event) {
            if (!this.open) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    this.open = true;
                }
                return;
            }
            
            switch(event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    if (this.highlightedIndex < this.filteredOptions.length - 1) {
                        this.highlightedIndex++;
                    }
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    if (this.highlightedIndex > 0) {
                        this.highlightedIndex--;
                    }
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.highlightedIndex >= 0 && this.filteredOptions[this.highlightedIndex]) {
                        this.selectOption(this.filteredOptions[this.highlightedIndex]);
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    this.open = false;
                    this.search = '';
                    this.filterOptions();
                    break;
            }
        },
        
        clearSelection() {
            this.selectedValue = '';
            this.selectedText = '';
            this.search = '';
            this.filterOptions();
            
            // Update hidden select
            if (this.$refs.hiddenSelect) {
                this.$refs.hiddenSelect.value = '';
                this.$refs.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
                this.$refs.hiddenSelect.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    }"
    @click.away="open = false; search = ''; filterOptions()"
    @keydown="handleKeydown($event)"
>
    @if($label)
        <label for="{{ $selectId }}" class="text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <!-- Hidden select for form submission and Livewire binding -->
    <select
        name="{{ $name }}"
        id="{{ $selectId }}"
        x-ref="hiddenSelect"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'hidden']) }}
        style="display: none;"
        x-on:change.window="if ($event.target.id === '{{ $selectId }}') { selectedValue = $event.target.value; const option = options.find(opt => opt.value == selectedValue); selectedText = option ? option.text : ''; }"
    >
        {{ $slot }}
    </select>
    
    <!-- Custom dropdown -->
    <div class="relative">
        <!-- Button trigger -->
        <button
            type="button"
            @click="open = !open; if (open) { $refs.searchInput?.focus(); }"
            :class="open ? 'ring-2 ring-neutral-500 dark:ring-neutral-500' : ''"
            class="w-full rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-sm sm:px-3 sm:py-2 text-left text-neutral-900 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-1 sm:focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:focus:border-neutral-500 dark:focus:ring-neutral-500 flex items-center justify-between"
        >
            <span class="truncate" x-text="selectedText || '{{ $placeholder }}'" :class="!selectedText ? 'text-neutral-400 dark:text-neutral-500' : ''"></span>
            <svg class="h-4 w-4 text-neutral-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        
        <!-- Dropdown menu -->
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-1 w-full rounded-md border border-neutral-300 bg-white shadow-lg dark:border-neutral-600 dark:bg-neutral-800"
            style="display: none;"
        >
            <!-- Search input -->
            <div class="border-b border-neutral-200 p-2 dark:border-neutral-700">
                <input
                    type="text"
                    x-ref="searchInput"
                    x-model="search"
                    @input="filterOptions()"
                    @keydown.enter.prevent="if (filteredOptions.length > 0) { selectOption(filteredOptions[0]); }"
                    placeholder="Cari..."
                    class="w-full rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-sm text-neutral-900 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:focus:border-neutral-500 dark:focus:ring-neutral-500"
                />
            </div>
            
            <!-- Options list -->
            <div class="max-h-60 overflow-auto p-1">
                <template x-if="filteredOptions.length === 0">
                    <div class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400">
                        Tidak ada hasil
                    </div>
                </template>
                
                <template x-for="(option, index) in filteredOptions" :key="option.value">
                    <button
                        type="button"
                        @click="selectOption(option)"
                        :class="{
                            'bg-neutral-100 dark:bg-neutral-700': highlightedIndex === index || selectedValue == option.value,
                            'bg-white dark:bg-neutral-800': highlightedIndex !== index && selectedValue != option.value
                        }"
                        class="w-full rounded-md px-3 py-2 text-left text-sm text-neutral-900 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-700 flex items-center justify-between"
                    >
                        <span x-text="option.text"></span>
                        <template x-if="selectedValue == option.value">
                            <svg class="h-4 w-4 text-neutral-600 dark:text-neutral-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </template>
                    </button>
                </template>
            </div>
        </div>
    </div>
    
    @if($error)
        <p class="text-xs sm:text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>

