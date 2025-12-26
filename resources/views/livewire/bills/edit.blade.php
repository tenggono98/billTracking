<?php

use App\Models\Branch;
use App\Models\Bill;
use App\Services\AiExtractionService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $bill;
    public $branch_id = '';
    public $new_branch_name = '';
    public $show_new_branch = false;
    public $bill_image;
    public $payment_proof_image;
    public $total_amount = '';
    public $payment_amount = '';
    public $date;
    public $bill_image_preview = null;
    public $payment_proof_preview = null;
    public $existing_bill_image = null;
    public $existing_payment_image = null;
    public $extracting_bill = false;
    public $extracting_payment = false;
    public $validation_warning = '';
    public $extraction_step_bill = '';
    public $extraction_step_payment = '';
    public $upload_progress_bill = 0;
    public $upload_progress_payment = 0;

    public function mount($bill)
    {
        $this->bill = Bill::with(['branch'])->findOrFail($bill);
        
        // Check authorization
        if ($this->bill->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        
        // Load existing data
        $this->branch_id = $this->bill->branch_id;
        $this->total_amount = $this->bill->total_amount;
        $this->payment_amount = $this->bill->payment_amount;
        $this->date = $this->bill->date->format('Y-m-d');
        
        // Set existing image previews
        if ($this->bill->bill_image_path) {
            $this->existing_bill_image = Storage::url($this->bill->bill_image_path);
            $this->bill_image_preview = $this->existing_bill_image;
        }
        
        if ($this->bill->payment_proof_image_path) {
            $this->existing_payment_image = Storage::url($this->bill->payment_proof_image_path);
            $this->payment_proof_preview = $this->existing_payment_image;
        }
    }

    public function updatedBillImage()
    {
        if ($this->bill_image) {
            $this->bill_image_preview = $this->bill_image->temporaryUrl();
            // Auto-trigger AI extraction
            $this->extractBillAmount();
        } else {
            // Revert to existing image if available
            $this->bill_image_preview = $this->existing_bill_image;
        }
    }

    public function updatedPaymentProofImage()
    {
        if ($this->payment_proof_image) {
            $this->payment_proof_preview = $this->payment_proof_image->temporaryUrl();
            // Auto-trigger AI extraction
            $this->extractPaymentAmount();
        } else {
            // Revert to existing image if available
            $this->payment_proof_preview = $this->existing_payment_image;
        }
    }

    public function removeBillImage()
    {
        $this->bill_image = null;
        $this->bill_image_preview = $this->existing_bill_image;
    }

    public function removePaymentImage()
    {
        $this->payment_proof_image = null;
        $this->payment_proof_preview = $this->existing_payment_image;
    }

    public function extractBillAmount()
    {
        if (!$this->bill_image) {
            return;
        }

        $this->extracting_bill = true;
        $this->extraction_step_bill = 'Membaca file gambar...';
        
        try {
            $this->extraction_step_bill = 'Membaca file gambar...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_bill]);
            
            $tempPath = $this->bill_image->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->bill_image->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->extraction_step_bill = 'Mengekstrak teks dari gambar (OCR)...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_bill]);
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->extraction_step_bill = 'Menganalisis dengan AI untuk mengekstrak total tagihan...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_bill]);
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'bill');
            
            if ($amount && $amount > 0) {
                $this->total_amount = $amount;
                $this->extraction_step_bill = 'Ekstraksi berhasil diselesaikan!';
                $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_bill]);
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($amount));
            } else {
                $this->extraction_step_bill = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Bill extraction error: ' . $e->getMessage());
            $this->extraction_step_bill = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->extracting_bill = false;
            $this->extraction_step_bill = '';
        }
    }

    public function extractPaymentAmount()
    {
        if (!$this->payment_proof_image) {
            return;
        }

        $this->extracting_payment = true;
        $this->extraction_step_payment = 'Membaca file gambar...';
        
        try {
            $this->extraction_step_payment = 'Membaca file gambar...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_payment]);
            
            $tempPath = $this->payment_proof_image->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->payment_proof_image->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->extraction_step_payment = 'Mengekstrak teks dari gambar (OCR)...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_payment]);
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->extraction_step_payment = 'Menganalisis dengan AI untuk mengekstrak jumlah pembayaran...';
            $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_payment]);
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'transfer');
            
            if ($amount && $amount > 0) {
                $this->payment_amount = $amount;
                $this->extraction_step_payment = 'Ekstraksi berhasil diselesaikan!';
                $this->dispatch('extraction-step-updated', ['step' => $this->extraction_step_payment]);
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($amount));
            } else {
                $this->extraction_step_payment = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Payment extraction error: ' . $e->getMessage());
            $this->extraction_step_payment = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->extracting_payment = false;
            $this->extraction_step_payment = '';
        }
    }

    public function updatedTotalAmount()
    {
        $this->checkValidation();
    }

    public function updatedPaymentAmount()
    {
        $this->checkValidation();
    }

    public function checkValidation()
    {
        $this->validation_warning = '';
        
        if ($this->total_amount > 0 && $this->payment_amount > 0) {
            if (abs($this->total_amount - $this->payment_amount) > 0.01) {
                $this->validation_warning = 'Total pembayaran tidak sama dengan total tagihan. Pastikan data sudah benar.';
            }
        }
    }

    public function update()
    {
        try {
            $this->validate([
                'branch_id' => $this->show_new_branch ? 'nullable' : 'required|exists:branches,id',
                'new_branch_name' => $this->show_new_branch ? 'required|string|max:255|unique:branches,name' : 'nullable',
                'bill_image' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:5120',
                'payment_proof_image' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:5120',
                'total_amount' => 'nullable|numeric|min:0',
                'payment_amount' => 'required|numeric|min:0.01',
                'date' => 'required|date',
            ], [
                'branch_id.required' => 'Pilih cabang atau buat cabang baru',
                'branch_id.exists' => 'Cabang yang dipilih tidak valid',
                'new_branch_name.required' => 'Nama cabang baru harus diisi',
                'new_branch_name.unique' => 'Cabang dengan nama ini sudah ada',
                'payment_amount.required' => 'Jumlah pembayaran wajib diisi',
                'payment_amount.numeric' => 'Jumlah pembayaran harus berupa angka',
                'payment_amount.min' => 'Jumlah pembayaran harus lebih dari 0',
                'date.required' => 'Tanggal wajib diisi',
                'date.date' => 'Format tanggal tidak valid',
            ]);

            // Create branch if new
            if ($this->show_new_branch && $this->new_branch_name) {
                $branch = Branch::create(['name' => $this->new_branch_name]);
                $this->branch_id = $branch->id;
            }

            if (empty($this->branch_id)) {
                throw new \Exception('Cabang harus dipilih atau dibuat');
            }

            // Handle image updates
            $billImagePath = $this->bill->bill_image_path;
            if ($this->bill_image) {
                // Delete old image if exists
                if ($this->bill->bill_image_path && Storage::exists($this->bill->bill_image_path)) {
                    Storage::delete($this->bill->bill_image_path);
                }
                // Store new image
                $newPath = $this->bill_image->store('bills', 'public');
                $billImagePath = 'public/' . $newPath;
            }
            
            $paymentProofPath = $this->bill->payment_proof_image_path;
            if ($this->payment_proof_image) {
                // Delete old image if exists
                if ($this->bill->payment_proof_image_path && Storage::exists($this->bill->payment_proof_image_path)) {
                    Storage::delete($this->bill->payment_proof_image_path);
                }
                // Store new image
                $newPath = $this->payment_proof_image->store('payments', 'public');
                $paymentProofPath = 'public/' . $newPath;
            }

            // Sanitize amounts from Indonesian currency format
            $totalAmount = \App\Helpers\CurrencyHelper::sanitize($this->total_amount) ?? 0;
            $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($this->payment_amount);
            
            if ($paymentAmount <= 0) {
                throw new \Exception('Jumlah pembayaran harus lebih dari 0');
            }

            // Calculate status
            $status = 'pending';
            if ($totalAmount > 0) {
                if ($paymentAmount >= $totalAmount) {
                    $status = 'paid';
                } elseif ($paymentAmount > 0) {
                    $status = 'partial';
                }
            } else {
                $status = 'paid';
            }

            // Update bill
            $this->bill->update([
                'branch_id' => $this->branch_id,
                'bill_image_path' => $billImagePath,
                'total_amount' => $totalAmount,
                'payment_proof_image_path' => $paymentProofPath,
                'payment_amount' => $paymentAmount,
                'status' => $status,
                'date' => $this->date,
            ]);

            session()->flash('success', 'Bill berhasil diperbarui!');
            
            return $this->redirect(route('bills.index'), navigate: true);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating bill: ' . $e->getMessage());
            session()->flash('error', 'Gagal memperbarui bill: ' . $e->getMessage());
        }
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Edit Bill'];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">Edit Bill</h1>
        <p class="text-sm sm:text-base text-neutral-600 dark:text-neutral-400">Perbarui informasi bill yang sudah ada</p>
    </div>

    @if (session('success'))
        <div class="mb-4 sm:mb-6 rounded-lg bg-green-50 border border-green-200 p-3 sm:p-4 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm sm:text-base font-medium">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 sm:mb-6 rounded-lg bg-red-50 border border-red-200 p-3 sm:p-4 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm sm:text-base font-medium">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <form wire:submit="update" class="space-y-6 sm:space-y-8">
        <!-- Branch Selection -->
        <div class="bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">Informasi Cabang</h2>
            
            <div class="space-y-4">
                <div>
                    <x-ui.select-searchable 
                        label="Pilih Cabang" 
                        name="branch_id" 
                        wire:model="branch_id"
                        :required="!$show_new_branch"
                        placeholder="Pilih cabang..."
                    >
                        <option value="">Pilih cabang...</option>
                        @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </x-ui.select-searchable>
                </div>

                <div class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        id="new_branch"
                        wire:model.live="show_new_branch"
                        class="w-4 h-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label for="new_branch" class="text-sm font-medium text-neutral-700 dark:text-neutral-300 cursor-pointer">
                        Tambah cabang baru
                    </label>
                </div>

                @if($show_new_branch)
                    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                        <x-ui.input 
                            label="Nama Cabang Baru" 
                            name="new_branch_name" 
                            wire:model="new_branch_name"
                            placeholder="Masukkan nama cabang"
                            required
                        />
                    </div>
                @endif
            </div>
        </div>

        <!-- Images Upload Section -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Payment Proof (Optional) -->
            <div class="bg-white dark:bg-neutral-800 rounded-xl border-2 border-neutral-200 dark:border-neutral-700 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">Bukti Pembayaran</h2>
                    <p class="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-1">Opsional</p>
                </div>
                <span class="px-2 py-1 text-xs font-semibold bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
            </div>

            <!-- Upload Area -->
            <div 
                x-data="{ 
                    isDragging: false,
                    handleDrop(e) {
                        this.isDragging = false;
                        if (e.dataTransfer.files.length) {
                            @this.upload('payment_proof_image', e.dataTransfer.files[0]);
                        }
                    },
                    handleDragOver(e) {
                        e.preventDefault();
                        this.isDragging = true;
                    },
                    handleDragLeave() {
                        this.isDragging = false;
                    }
                }"
                @drop.prevent="handleDrop"
                @dragover.prevent="handleDragOver"
                @dragleave.prevent="handleDragLeave"
                class="relative"
            >
                @if($payment_proof_preview)
                    <!-- Preview Image (di luar label) -->
                    <div class="relative w-full h-64 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-3">
                        <div 
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="relative w-full h-full overflow-hidden flex items-center justify-center"
                        >
                            <x-ui.image-lightbox :src="$payment_proof_preview" alt="Pratinjau bukti pembayaran">
                                <img 
                                    src="{{ $payment_proof_preview }}" 
                                    alt="Pratinjau bukti pembayaran" 
                                    class="w-full h-full object-contain rounded-lg" 
                                />
                            </x-ui.image-lightbox>
                            <div class="absolute top-2 right-2 flex gap-2">
                                <div class="bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                    Gambar terunggah
                                </div>
                                @if($payment_proof_image)
                                    <button 
                                        type="button"
                                        wire:click="removePaymentImage"
                                        class="bg-red-600 text-white text-xs px-2 py-1 rounded hover:bg-red-700"
                                    >
                                        Hapus
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div wire:loading wire:target="payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tombol Upload di bawah preview -->
                    <label 
                        for="payment_proof_image"
                        class="flex items-center justify-center w-full px-4 py-2 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800"
                    >
                        <svg class="w-5 h-5 mr-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <span class="text-sm text-neutral-600 dark:text-neutral-400 font-medium">Upload Gambar Baru</span>
                        <input 
                            type="file" 
                            id="payment_proof_image"
                            wire:model="payment_proof_image"
                            wire:loading.attr="disabled"
                            accept="image/*"
                            class="hidden"
                        />
                    </label>
                @else
                    <!-- Placeholder (tetap di dalam label) -->
                    <label 
                        for="payment_proof_image"
                        class="relative flex flex-col items-center justify-center w-full h-64 max-h-64 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                        :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                    >
                        <div class="relative w-full h-full">
                            <div 
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-4"
                            >
                                <svg class="w-10 h-10 mb-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="mb-1 text-sm text-neutral-500 dark:text-neutral-400">
                                    <span class="font-semibold">Klik untuk upload</span> atau drag and drop
                                </p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">PNG, JPG, GIF maksimal 5MB</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 font-medium">Sistem akan otomatis mengekstrak jumlah transfer</p>
                            </div>
                        </div>
                        <input 
                            type="file" 
                            id="payment_proof_image"
                            wire:model="payment_proof_image"
                            wire:loading.attr="disabled"
                            accept="image/*"
                            class="hidden"
                        />
                        <div wire:loading wire:target="payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                            </div>
                        </div>
                    </label>
                @endif
            </div>
            
            @if($extracting_payment)
                <div class="mt-4 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-blue-900 dark:text-blue-100 mb-2">Sedang memproses gambar...</p>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Gambar berhasil diunggah</span>
                                </div>
                                @if($extraction_step_payment)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                        <span wire:key="step-payment-{{ $extraction_step_payment }}">{{ $extraction_step_payment }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                        <span>Memproses gambar...</span>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">Mohon tunggu, proses ini memakan waktu beberapa detik.</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(!$extracting_payment)
                <div class="mt-4">
                    <x-ui.currency-input 
                        label="Jumlah Pembayaran" 
                        name="payment_amount" 
                        :value="$payment_amount"
                        wireModel="payment_amount"
                        placeholder="0"
                        required
                    />
                    @if($payment_amount > 0)
                        <div class="mt-2 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Jumlah yang diekstrak:</p>
                            <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                Rp {{ \App\Helpers\CurrencyHelper::format($payment_amount) }}
                            </p>
                        </div>
                    @endif
                    @if($payment_amount > 0)
                        <div class="mt-2 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                            <div class="flex items-start gap-2 text-green-800 dark:text-green-200">
                                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="font-medium text-sm">Jumlah berhasil diekstrak!</p>
                                    <p class="text-xs mt-1">Anda dapat mengedit jumlah ini jika hasil ekstraksi tidak sesuai.</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

            <!-- Bill Proof (Optional) -->
            <div class="bg-white dark:bg-neutral-800 rounded-xl border-2 border-neutral-200 dark:border-neutral-700 p-4 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">Bukti Tagihan</h2>
                        <p class="text-xs sm:text-sm text-neutral-500 dark:text-neutral-400 mt-1">Opsional</p>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
                </div>

                <!-- Upload Area -->
                <div 
                    x-data="{ 
                        isDragging: false,
                        handleDrop(e) {
                            this.isDragging = false;
                            if (e.dataTransfer.files.length) {
                                @this.upload('bill_image', e.dataTransfer.files[0]);
                            }
                        },
                        handleDragOver(e) {
                            e.preventDefault();
                            this.isDragging = true;
                        },
                        handleDragLeave() {
                            this.isDragging = false;
                        }
                    }"
                    @drop.prevent="handleDrop"
                    @dragover.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                    class="relative"
                >
                    @if($bill_image_preview)
                        <!-- Preview Image (di luar label) -->
                        <div class="relative w-full h-64 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-3">
                            <div 
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="relative w-full h-full overflow-hidden flex items-center justify-center"
                            >
                                <x-ui.image-lightbox :src="$bill_image_preview" alt="Pratinjau bukti tagihan">
                                    <img src="{{ $bill_image_preview }}" alt="Pratinjau bukti tagihan" class="w-full h-full object-contain rounded-lg" />
                                </x-ui.image-lightbox>
                                <div class="absolute top-2 right-2 flex gap-2">
                                    <div class="bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                        Gambar terunggah
                                    </div>
                                    @if($bill_image)
                                        <button 
                                            type="button"
                                            wire:click="removeBillImage"
                                            class="bg-red-600 text-white text-xs px-2 py-1 rounded hover:bg-red-700"
                                        >
                                            Hapus
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div wire:loading wire:target="bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tombol Upload di bawah preview -->
                        <label 
                            for="bill_image"
                            class="flex items-center justify-center w-full px-4 py-2 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800"
                        >
                            <svg class="w-5 h-5 mr-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span class="text-sm text-neutral-600 dark:text-neutral-400 font-medium">Upload Gambar Baru</span>
                            <input 
                                type="file" 
                                id="bill_image"
                                wire:model="bill_image"
                                wire:loading.attr="disabled"
                                accept="image/*"
                                class="hidden"
                            />
                        </label>
                    @else
                        <!-- Placeholder (tetap di dalam label) -->
                        <label 
                            for="bill_image"
                            class="relative flex flex-col items-center justify-center w-full h-64 max-h-64 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                            :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                        >
                        <div class="relative w-full h-full">
                            <div 
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-4"
                            >
                                <svg class="w-10 h-10 mb-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="mb-1 text-sm text-neutral-500 dark:text-neutral-400">
                                    <span class="font-semibold">Klik untuk upload</span> atau drag and drop
                                </p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">PNG, JPG, GIF maksimal 5MB</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 font-medium">Sistem akan otomatis mengekstrak total tagihan</p>
                            </div>
                        </div>
                        <input 
                            type="file" 
                            id="bill_image"
                            wire:model="bill_image"
                            wire:loading.attr="disabled"
                            accept="image/*"
                            class="hidden"
                        />
                        <div wire:loading wire:target="bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                            </div>
                        </div>
                        </label>
                    @endif
                </div>

                @if($extracting_bill)
                <div class="mt-4 p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-blue-900 dark:text-blue-100 mb-2">Sedang memproses gambar...</p>
                            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Gambar berhasil diunggah</span>
                                </div>
                                @if($extraction_step_bill)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                        <span wire:key="step-bill-{{ $extraction_step_bill }}">{{ $extraction_step_bill }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                        <span>Memproses gambar...</span>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">Mohon tunggu, proses ini memakan waktu beberapa detik.</p>
                        </div>
                    </div>
                </div>
            @endif

                @if($bill_image_preview && !$extracting_bill)
                    <div class="mt-4">
                        <x-ui.currency-input 
                            label="Total Tagihan" 
                            name="total_amount" 
                            :value="$total_amount"
                            wireModel="total_amount"
                            placeholder="0"
                        />
                        @if($total_amount > 0)
                            <div class="mt-2 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Jumlah yang diekstrak:</p>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                    Rp {{ \App\Helpers\CurrencyHelper::format($total_amount) }}
                                </p>
                            </div>
                        @endif
                        @if($total_amount > 0)
                            <div class="mt-2 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                <div class="flex items-start gap-2 text-green-800 dark:text-green-200">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <p class="font-medium text-sm">Jumlah berhasil diekstrak!</p>
                                        <p class="text-xs mt-1">Anda dapat mengedit jumlah ini jika hasil ekstraksi tidak sesuai.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Validation Warning -->
        @if($validation_warning)
            <div class="rounded-lg bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ $validation_warning }}</p>
                        <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-300">Anda masih dapat melanjutkan, tetapi pastikan jumlahnya benar.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Date -->
        <div class="bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">Tanggal</h2>
            <x-ui.input 
                label="Tanggal Transaksi" 
                name="date" 
                type="date"
                wire:model="date"
                required
            />
        </div>

        <!-- Submit Buttons -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <a href="{{ route('bills.index') }}" wire:navigate class="w-full sm:w-auto">
                <x-ui.button type="button" variant="outline" class="w-full sm:w-auto px-6">
                    Batal
                </x-ui.button>
            </a>
            <x-ui.button 
                type="submit" 
                variant="primary" 
                class="w-full sm:w-auto px-6"
                wire:loading.attr="disabled"
                wire:target="update"
            >
                <span wire:loading.remove wire:target="update">Perbarui Bill</span>
                <span wire:loading wire:target="update" class="flex items-center gap-2 justify-center">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memperbarui...
                </span>
            </x-ui.button>
        </div>
    </form>
</div>

