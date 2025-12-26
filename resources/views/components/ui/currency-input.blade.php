@props([
    'label' => null,
    'name' => null,
    'value' => null,
    'required' => false,
    'error' => null,
    'wireModel' => null,
])

@php
    // Format initial value to Indonesian format
    $formattedValue = $value ? \App\Helpers\CurrencyHelper::format($value) : '';
@endphp

<div class="grid gap-1.5 sm:gap-2" 
     wire:key="currency-wrapper-{{ $wireModel }}"
     x-data="{
        formattedValue: @js($formattedValue),
        wireModel: @js($wireModel),
         formatCurrency(value) {
             if (!value || value === '') return '';
             
             // Remove all non-numeric except comma and dot
             let cleaned = String(value).replace(/[^\d,.]/g, '');
             
             // Handle Indonesian format: dot (.) = thousand, comma (,) = decimal
             // Convert to number for processing
             let numStr = cleaned;
             
             // If has comma, treat as decimal separator
             if (cleaned.includes(',')) {
                 numStr = cleaned.replace(/\./g, ''); // Remove dots (thousand separators)
                 numStr = numStr.replace(',', '.'); // Convert comma to dot for parsing
             } else if (cleaned.includes('.')) {
                 // Check if dot is decimal or thousand separator
                 const parts = cleaned.split('.');
                 if (parts.length === 2 && parts[1].length <= 2) {
                     // Decimal separator
                     numStr = cleaned;
                 } else {
                     // Thousand separator - remove all dots
                     numStr = cleaned.replace(/\./g, '');
                 }
             }
             
             const num = parseFloat(numStr) || 0;
             
             // Format with Indonesian format: dot (.) = thousand, comma (,) = decimal
             const parts = num.toFixed(2).split('.');
             const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
             const decimalPart = parts[1];
             
             if (decimalPart === '00' || decimalPart === '0') {
                 return integerPart;
             }
             return integerPart + ',' + decimalPart;
         },
        handleInput(event) {
            let input = event.target.value;
            
            // Allow only numbers, dots, and commas
            input = input.replace(/[^\d,.]/g, '');
            
            // Format the display value
            this.formattedValue = this.formatCurrency(input);
            
            // Extract numeric value for Livewire
            let numStr = input;
            if (numStr.includes(',')) {
                // Indonesian format: comma is decimal
                numStr = numStr.replace(/\./g, '').replace(',', '.');
            } else if (numStr.includes('.')) {
                const parts = numStr.split('.');
                if (parts.length === 2 && parts[1].length <= 2) {
                    // Decimal separator - keep as is
                } else {
                    // Thousand separator - remove all dots
                    numStr = numStr.replace(/\./g, '');
                }
            }
            
            const num = parseFloat(numStr) || 0;
            
            // Update Livewire model
            if (this.wireModel && $wire) {
                $wire.set(this.wireModel, num);
            }
        },
        handleKeyPress(event) {
            // Allow only numbers, comma, and dot
            const char = String.fromCharCode(event.which || event.keyCode);
            if (!/[0-9,.]/.test(char)) {
                event.preventDefault();
            }
        },
        handleBlur() {
            // Re-format on blur to ensure consistency
            if (this.formattedValue) {
                this.formattedValue = this.formatCurrency(this.formattedValue);
            }
        }
     }"
     x-init="
        // Sync with Livewire value when it changes externally
        if (wireModel) {
            // Function to update formatted value from numeric value
            const updateFromValue = (value) => {
                // Handle all cases: null, undefined, empty string, 0, and positive numbers
                if (value === null || value === undefined || value === '') {
                    formattedValue = '';
                    const inputElement = $el.querySelector('input[type=\'text\']');
                    if (inputElement) {
                        inputElement.value = '';
                        // Force Alpine.js to update
                        inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    return;
                }
                
                // Convert to number for processing
                const numValue = parseFloat(value) || 0;
                
                // Format the value (including 0)
                const formatted = numValue > 0 ? formatCurrency(String(numValue)) : (numValue === 0 ? '0' : '');
                
                // CRITICAL: Update Alpine.js reactive property first - this is the main data binding
                formattedValue = formatted;
                
                // Direct DOM manipulation to ensure input field is updated
                const inputElement = $el.querySelector('input[type=\'text\']');
                if (inputElement) {
                    // Update DOM value
                    inputElement.value = formatted;
                    
                    // Force Alpine.js reactivity by accessing the reactive property
                    // This ensures x-model binding is updated
                    if (inputElement._x_model) {
                        // Alpine.js v3 model binding
                        inputElement._x_model.set(formatted);
                    }
                    
                    // Also update via Alpine's data stack if available
                    if (inputElement._x_dataStack && inputElement._x_dataStack[0]) {
                        inputElement._x_dataStack[0].formattedValue = formatted;
                    }
                    
                    // Trigger events to ensure Alpine.js detects the change
                    inputElement.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    inputElement.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    
                    // Multiple fallback updates with delays to ensure it works
                    setTimeout(() => {
                        formattedValue = formatted;
                        if (inputElement) {
                            inputElement.value = formatted;
                            if (inputElement._x_model) {
                                inputElement._x_model.set(formatted);
                            }
                            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }, 50);
                    
                    setTimeout(() => {
                        formattedValue = formatted;
                        if (inputElement) {
                            inputElement.value = formatted;
                            if (inputElement._x_model) {
                                inputElement._x_model.set(formatted);
                            }
                            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }, 150);
                    
                    setTimeout(() => {
                        formattedValue = formatted;
                        if (inputElement) {
                            inputElement.value = formatted;
                            if (inputElement._x_model) {
                                inputElement._x_model.set(formatted);
                            }
                        }
                    }, 300);
                }
            };
             
            // Watch for changes in the Livewire property - use multiple approaches for reliability
            // In Livewire 3, use polling and event-based updates for nested properties
            const modelParts = wireModel.split('.');
            if (modelParts.length >= 3) {
                const arrayName = modelParts[0]; // 'bills' or 'manualBills'
                const index = parseInt(modelParts[1]);
                const fieldName = modelParts[2];
                
                // Approach 1: Poll the Livewire value periodically to catch updates
                // This is more reliable than watching nested properties in Alpine.js
                let lastValue = null;
                const checkValue = () => {
                    try {
                        const currentValue = $wire.get(wireModel);
                        // Check if value changed (including from null/undefined to a number)
                        if (currentValue !== lastValue && currentValue !== undefined && currentValue !== null) {
                            // Only update if value actually changed
                            if (currentValue !== lastValue) {
                                lastValue = currentValue;
                                // Always call updateFromValue to ensure input is updated
                                updateFromValue(currentValue);
                            }
                        }
                    } catch(e) {
                        // Ignore errors
                    }
                };
                
                // Poll every 200ms to catch updates quickly
                const valuePollInterval = setInterval(checkValue, 200);
                
                // Stop polling after component is destroyed
                $el.addEventListener('livewire:unload', () => {
                    if (valuePollInterval) {
                        clearInterval(valuePollInterval);
                    }
                });
                
                // Also try direct watch on the nested property path using $wire.get()
                // In Livewire 3, we can watch nested properties directly
                try {
                    $watch(() => {
                        try {
                            return $wire.get(wireModel);
                        } catch(e) {
                            return null;
                        }
                    }, (value) => {
                        if (value !== undefined && value !== null && value !== lastValue) {
                            lastValue = value;
                            updateFromValue(value);
                        }
                    });
                } catch(e) {
                    // Fallback if direct watch fails
                }
            } else {
                // Fallback for non-nested properties
                try {
                    $watch(() => $wire.get(wireModel), value => {
                        updateFromValue(value);
                    });
                } catch(e) {
                    console.warn('Watch failed, using fallback:', e);
                }
            }
             
            // Approach 3: Listen for amount-extracted event and directly update
            // In Livewire 3, use $wire.on() for component-level events
            $wire.on('amount-extracted', (event) => {
                const eventData = Array.isArray(event) ? event[0] : event;
                if (eventData && eventData.index !== undefined && eventData.amount !== undefined && eventData.type) {
                    const eventIndex = parseInt(eventData.index);
                    const modelParts = wireModel.split('.');
                    
                    if (modelParts.length >= 3) {
                        const arrayName = modelParts[0];
                        const modelIndex = parseInt(modelParts[1]);
                        const fieldName = modelParts[2];
                        
                        // Check if this event is for our field
                        if (modelIndex === eventIndex) {
                            const isMatch = (eventData.type === 'total' && fieldName === 'total_amount') ||
                                          (eventData.type === 'payment' && fieldName === 'payment_amount');
                            
                            if (isMatch && eventData.amount > 0) {
                                // In Livewire 3, use $wire.set() for nested properties
                                // Update Livewire property first
                                try {
                                    $wire.set(wireModel, eventData.amount);
                                } catch(e) {
                                    console.warn('Failed to set wireModel:', e);
                                }
                                
                                // Immediately update formatted value - this is the main update
                                updateFromValue(eventData.amount);
                                
                                // Force multiple updates to ensure it works
                                setTimeout(() => {
                                    updateFromValue(eventData.amount);
                                }, 10);
                                
                                setTimeout(() => {
                                    updateFromValue(eventData.amount);
                                }, 50);
                                
                                setTimeout(() => {
                                    updateFromValue(eventData.amount);
                                }, 150);
                                
                                setTimeout(() => {
                                    // Check current value from Livewire and update
                                    try {
                                        const currentValue = $wire.get(wireModel);
                                        if (currentValue && currentValue > 0) {
                                            updateFromValue(currentValue);
                                        } else {
                                            // If Livewire value is not updated, force update again
                                            $wire.set(wireModel, eventData.amount);
                                            updateFromValue(eventData.amount);
                                        }
                                    } catch(e) {
                                        // Force update if get fails
                                        updateFromValue(eventData.amount);
                                    }
                                }, 300);
                                
                                setTimeout(() => {
                                    // Final check and update
                                    updateFromValue(eventData.amount);
                                }, 500);
                            }
                        }
                    }
                }
            });
             
            // Approach 4: Listen for update-currency-input event (direct update command)
            $wire.on('update-currency-input', (event) => {
                const eventData = Array.isArray(event) ? event[0] : event;
                if (eventData && eventData.model === wireModel && eventData.value !== undefined && eventData.value > 0) {
                    // Update Livewire property first
                    $wire.set(wireModel, eventData.value);
                    // Then update formatted value immediately
                    updateFromValue(eventData.value);
                    
                    // Multiple fallback updates
                    setTimeout(() => updateFromValue(eventData.value), 10);
                    setTimeout(() => updateFromValue(eventData.value), 50);
                    setTimeout(() => updateFromValue(eventData.value), 150);
                }
            });
            
            // Approach 5: Listen for force-currency-update event (most reliable)
            $wire.on('force-currency-update', (event) => {
                const eventData = Array.isArray(event) ? event[0] : event;
                if (eventData && eventData.model === wireModel && eventData.value !== undefined && eventData.value > 0) {
                    // Update Livewire property first
                    $wire.set(wireModel, eventData.value);
                    // Then update formatted value immediately
                    updateFromValue(eventData.value);
                    
                    // Multiple fallback updates to ensure it works
                    setTimeout(() => updateFromValue(eventData.value), 10);
                    setTimeout(() => updateFromValue(eventData.value), 50);
                    setTimeout(() => updateFromValue(eventData.value), 150);
                    
                    setTimeout(() => {
                        // Check current value from Livewire
                        try {
                            const currentValue = $wire.get(wireModel);
                            if (currentValue && currentValue > 0) {
                                updateFromValue(currentValue);
                            } else {
                                // Force update again if Livewire value is not updated
                                $wire.set(wireModel, eventData.value);
                                updateFromValue(eventData.value);
                            }
                        } catch(e) {
                            updateFromValue(eventData.value);
                        }
                    }, 300);
                }
            });
             
            // Approach 6: Listen for browser event currency-value-updated (fallback)
            // Listen for Livewire event that gets converted to browser event
            if (typeof Livewire !== 'undefined') {
                Livewire.on('currency-value-updated', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (data && data.model === wireModel && data.value !== undefined && data.value > 0) {
                        // Update Livewire property first
                        $wire.set(wireModel, data.value);
                        // Then update formatted value
                        updateFromValue(data.value);
                    }
                });
            }
            
            // Also listen for native browser event (fallback)
            window.addEventListener('currency-value-updated', (e) => {
                const eventData = e.detail || e;
                if (eventData && eventData.model === wireModel && eventData.value !== undefined && eventData.value > 0) {
                    // Update Livewire property first
                    $wire.set(wireModel, eventData.value);
                    // Then update formatted value
                    updateFromValue(eventData.value);
                }
            });
             
            // Polling fallback - check value during extraction
            let pollInterval = null;
            let pollAttempts = 0;
            const maxPollAttempts = 20; // Poll for up to 6 seconds (20 * 300ms)
            
            $wire.on('extraction-started', (event) => {
                const eventData = Array.isArray(event) ? event[0] : event;
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
                pollAttempts = 0;
                
                // Check if this extraction is for our field
                const modelParts = wireModel.split('.');
                if (modelParts.length >= 3 && eventData) {
                    const modelIndex = parseInt(modelParts[1]);
                    const fieldName = modelParts[2];
                    const eventIndex = parseInt(eventData.index);
                    
                    if (modelIndex === eventIndex) {
                        const isMatch = (eventData.type === 'total' && fieldName === 'total_amount') ||
                                      (eventData.type === 'payment' && fieldName === 'payment_amount');
                        
                        if (isMatch) {
                            pollInterval = setInterval(() => {
                                pollAttempts++;
                                try {
                                    const currentValue = $wire.get(wireModel);
                                    if (currentValue !== null && currentValue !== undefined && currentValue > 0) {
                                        updateFromValue(currentValue);
                                        clearInterval(pollInterval);
                                        pollInterval = null;
                                        pollAttempts = 0;
                                    } else if (pollAttempts >= maxPollAttempts) {
                                        // Stop polling after max attempts
                                        clearInterval(pollInterval);
                                        pollInterval = null;
                                        pollAttempts = 0;
                                    }
                                } catch(e) {
                                    // Ignore errors but stop polling if too many attempts
                                    if (pollAttempts >= maxPollAttempts) {
                                        clearInterval(pollInterval);
                                        pollInterval = null;
                                        pollAttempts = 0;
                                    }
                                }
                            }, 300);
                        }
                    }
                }
            });
            
            $wire.on('extraction-completed', (event) => {
                const eventData = Array.isArray(event) ? event[0] : event;
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    pollAttempts = 0;
                }
                
                // Final check if this completion is for our field
                if (eventData) {
                    const modelParts = wireModel.split('.');
                    if (modelParts.length >= 3) {
                        const modelIndex = parseInt(modelParts[1]);
                        const eventIndex = parseInt(eventData.index);
                        
                        if (modelIndex === eventIndex) {
                            // Try multiple times with increasing delays
                            setTimeout(() => {
                                try {
                                    const currentValue = $wire.get(wireModel);
                                    if (currentValue !== null && currentValue !== undefined && currentValue > 0) {
                                        updateFromValue(currentValue);
                                    }
                                } catch(e) {
                                    // Ignore errors
                                }
                            }, 100);
                            
                            setTimeout(() => {
                                try {
                                    const currentValue = $wire.get(wireModel);
                                    if (currentValue !== null && currentValue !== undefined && currentValue > 0) {
                                        updateFromValue(currentValue);
                                    }
                                } catch(e) {
                                    // Ignore errors
                                }
                            }, 500);
                            
                            setTimeout(() => {
                                try {
                                    const currentValue = $wire.get(wireModel);
                                    if (currentValue !== null && currentValue !== undefined) {
                                        updateFromValue(currentValue);
                                    }
                                } catch(e) {
                                    // Ignore errors
                                }
                            }, 1000);
                        }
                    }
                }
            });
         }
     ">
    @if($label)
        <label for="{{ $name }}" class="text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $name }}"
        wire:key="currency-input-{{ $wireModel }}"
        x-model="formattedValue"
        @input="handleInput($event)"
        @keypress="handleKeyPress($event)"
        @blur="handleBlur()"
        inputmode="numeric"
        pattern="[0-9.,]*"
        autocomplete="off"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge([
            'class' => 'w-full rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-sm sm:px-3 sm:py-2 text-neutral-900 placeholder-neutral-400 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-1 sm:focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-500'
        ]) }}
    />
    
    @if($error)
        <p class="text-xs sm:text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>

