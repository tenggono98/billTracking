<?php

use App\Models\Branch;
use App\Models\Bill;
use App\Services\AiExtractionService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithFileUploads;

    public $showModal = false;
    public $showBranchModal = false;
    public $inputText = '';
    public $extracting = false;
    public $extractedBills = [];
    public $branchAssignments = []; // Format: [bill_index => [branch_ids]]
    public $errorMessage = '';
    public $successMessage = '';
    
    // Manual bill properties
    public $manualBills = []; // Format: [['total_amount' => '', 'payment_amount' => '', 'date' => '', 'bill_image' => null, 'payment_proof_image' => null, ...]]
    public $showManualForm = false;

    protected function getBackgroundClass($index)
    {
        $backgrounds = [
            'bg-white dark:bg-neutral-800',
            'bg-neutral-50 dark:bg-neutral-700/50',
            'bg-blue-50 dark:bg-blue-900/20',
            'bg-green-50 dark:bg-green-900/20',
            'bg-purple-50 dark:bg-purple-900/20',
        ];
        return $backgrounds[$index % count($backgrounds)];
    }

    protected function getBorderClass($index)
    {
        $borders = [
            'border-neutral-200 dark:border-neutral-700',
            'border-blue-200 dark:border-blue-800',
            'border-green-200 dark:border-green-800',
            'border-purple-200 dark:border-purple-800',
            'border-orange-200 dark:border-orange-800',
        ];
        return $borders[$index % count($borders)];
    }

    public function openAiHelper()
    {
        $this->showModal = true;
        $this->inputText = '';
        $this->errorMessage = '';
        $this->successMessage = '';
        $this->extractedBills = [];
        $this->branchAssignments = [];
        $this->manualBills = [];
        $this->showManualForm = false;
    }

    public function closeModals()
    {
        $this->showModal = false;
        $this->showBranchModal = false;
        $this->inputText = '';
        $this->extractedBills = [];
        $this->branchAssignments = [];
        $this->manualBills = [];
        $this->showManualForm = false;
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function extractAndPopulateForm()
    {
        try {
            $this->errorMessage = '';
            $this->successMessage = '';
            
            if (empty(trim($this->inputText))) {
                $this->errorMessage = 'Silakan masukkan teks yang berisi informasi tagihan.';
                return;
            }

            $this->extracting = true;

            $service = app(AiExtractionService::class);
            $extractedBills = $service->extractBillsFromText($this->inputText);

            if (empty($extractedBills)) {
                $this->errorMessage = 'Tidak ada tagihan yang berhasil diekstrak dari teks. Pastikan teks berisi informasi tagihan yang jelas.';
                $this->extracting = false;
                return;
            }

            $this->extracting = false;
            $this->showModal = false;
            
            // Store extracted bills in session for create component to pick up
            session()->put('ai_extracted_bills', $extractedBills);
            
            // Redirect to create page
            return $this->redirect(route('bills.create'), navigate: true);

        } catch (\Exception $e) {
            $this->extracting = false;
            $this->errorMessage = 'Terjadi kesalahan: ' . $e->getMessage();
            Log::error('AI Helper extraction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function extractAndCreate()
    {
        try {
            $this->errorMessage = '';
            $this->successMessage = '';
            
            if (empty(trim($this->inputText))) {
                $this->errorMessage = 'Silakan masukkan teks yang berisi informasi tagihan.';
                return;
            }

            $this->extracting = true;

            $service = app(AiExtractionService::class);
            $this->extractedBills = $service->extractBillsFromText($this->inputText);

            if (empty($this->extractedBills)) {
                $this->errorMessage = 'Tidak ada tagihan yang berhasil diekstrak dari teks. Pastikan teks berisi informasi tagihan yang jelas.';
                $this->extracting = false;
                return;
            }

            // Initialize branch assignments - each bill starts with empty array
            $this->branchAssignments = [];
            foreach ($this->extractedBills as $index => $bill) {
                $this->branchAssignments[$index] = [];
                // Initialize image properties for extracted bills
                if (!isset($this->extractedBills[$index]['bill_image'])) {
                    $this->extractedBills[$index]['bill_image'] = null;
                }
                if (!isset($this->extractedBills[$index]['payment_proof_image'])) {
                    $this->extractedBills[$index]['payment_proof_image'] = null;
                }
                if (!isset($this->extractedBills[$index]['bill_image_preview'])) {
                    $this->extractedBills[$index]['bill_image_preview'] = null;
                }
                if (!isset($this->extractedBills[$index]['payment_proof_preview'])) {
                    $this->extractedBills[$index]['payment_proof_preview'] = null;
                }
                if (!isset($this->extractedBills[$index]['extracting_bill'])) {
                    $this->extractedBills[$index]['extracting_bill'] = false;
                }
                if (!isset($this->extractedBills[$index]['extracting_payment'])) {
                    $this->extractedBills[$index]['extracting_payment'] = false;
                }
                if (!isset($this->extractedBills[$index]['extraction_step_bill'])) {
                    $this->extractedBills[$index]['extraction_step_bill'] = '';
                }
                if (!isset($this->extractedBills[$index]['extraction_step_payment'])) {
                    $this->extractedBills[$index]['extraction_step_payment'] = '';
                }
            }

            $this->extracting = false;
            $this->showModal = false;
            $this->showBranchModal = true;

        } catch (\Exception $e) {
            $this->extracting = false;
            $this->errorMessage = 'Terjadi kesalahan: ' . $e->getMessage();
            Log::error('AI Helper extraction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function addManualBill()
    {
        $this->manualBills[] = [
            'total_amount' => '',
            'payment_amount' => '',
            'date' => now()->format('Y-m-d'),
            'bill_image' => null,
            'payment_proof_image' => null,
            'bill_image_preview' => null,
            'payment_proof_preview' => null,
            'extracting_bill' => false,
            'extracting_payment' => false,
            'extraction_step_bill' => '',
            'extraction_step_payment' => '',
        ];
        
        // Initialize branch assignment for this manual bill
        $manualIndex = count($this->extractedBills) + count($this->manualBills) - 1;
        if (!isset($this->branchAssignments[$manualIndex])) {
            $this->branchAssignments[$manualIndex] = [];
        }
        
        $this->showManualForm = true;
    }

    public function removeManualBill($index)
    {
        if (isset($this->manualBills[$index])) {
            unset($this->manualBills[$index]);
            $this->manualBills = array_values($this->manualBills);
            
            // Reindex branch assignments
            $allBills = array_merge($this->extractedBills, $this->manualBills);
            $newAssignments = [];
            foreach ($allBills as $newIndex => $bill) {
                $oldIndex = $newIndex < count($this->extractedBills) ? $newIndex : $newIndex;
                if (isset($this->branchAssignments[$oldIndex])) {
                    $newAssignments[$newIndex] = $this->branchAssignments[$oldIndex];
                } else {
                    $newAssignments[$newIndex] = [];
                }
            }
            $this->branchAssignments = $newAssignments;
        }
    }

    public function handleBillImageUpdate($billType, $index)
    {
        if ($billType === 'manual') {
            if (isset($this->manualBills[$index]['bill_image']) && $this->manualBills[$index]['bill_image']) {
                try {
                    $this->manualBills[$index]['bill_image_preview'] = $this->manualBills[$index]['bill_image']->temporaryUrl();
                    $this->extractBillAmountManual($index);
                } catch (\Exception $e) {
                    Log::error('Error handling manual bill image update: ' . $e->getMessage());
                }
            }
        } elseif ($billType === 'extracted') {
            if (isset($this->extractedBills[$index]['bill_image']) && $this->extractedBills[$index]['bill_image']) {
                try {
                    $this->extractedBills[$index]['bill_image_preview'] = $this->extractedBills[$index]['bill_image']->temporaryUrl();
                    $this->extractBillAmountExtracted($index);
                } catch (\Exception $e) {
                    Log::error('Error handling extracted bill image update: ' . $e->getMessage());
                }
            }
        }
    }

    public function handlePaymentImageUpdate($billType, $index)
    {
        if ($billType === 'manual') {
            if (isset($this->manualBills[$index]['payment_proof_image']) && $this->manualBills[$index]['payment_proof_image']) {
                try {
                    $this->manualBills[$index]['payment_proof_preview'] = $this->manualBills[$index]['payment_proof_image']->temporaryUrl();
                    $this->extractPaymentAmountManual($index);
                } catch (\Exception $e) {
                    Log::error('Error handling manual payment image update: ' . $e->getMessage());
                }
            }
        } elseif ($billType === 'extracted') {
            if (isset($this->extractedBills[$index]['payment_proof_image']) && $this->extractedBills[$index]['payment_proof_image']) {
                try {
                    $this->extractedBills[$index]['payment_proof_preview'] = $this->extractedBills[$index]['payment_proof_image']->temporaryUrl();
                    $this->extractPaymentAmountExtracted($index);
                } catch (\Exception $e) {
                    Log::error('Error handling extracted payment image update: ' . $e->getMessage());
                }
            }
        }
    }
    
    public function updateManualCurrencyValue($index, $field, $value)
    {
        if (isset($this->manualBills[$index])) {
            $this->manualBills[$index][$field] = (float) $value;
        }
    }
    
    public function updateExtractedCurrencyValue($index, $field, $value)
    {
        if (isset($this->extractedBills[$index])) {
            $this->extractedBills[$index][$field] = (float) $value;
        }
    }

    public function extractBillAmountManual($index)
    {
        if (!isset($this->manualBills[$index]['bill_image']) || !$this->manualBills[$index]['bill_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->manualBills[$index]['total_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->manualBills[$index]['extracting_bill'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->manualBills[$index]['total_amount'] = 0;
        }
        
        $this->manualBills[$index]['extraction_step_bill'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'total', 'index' => $index]);
        
        try {
            $tempPath = $this->manualBills[$index]['bill_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->manualBills[$index]['bill_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->manualBills[$index]['extraction_step_bill'] = 'Mengekstrak teks dari gambar (OCR)...';
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->manualBills[$index]['extraction_step_bill'] = 'Menganalisis dengan AI untuk mengekstrak total tagihan...';
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'bill');
            
            if ($amount && $amount > 0) {
                // Ensure amount is a clean numeric value
                $cleanAmount = (float) $amount;
                
                // Update the amount - ensure Livewire detects the change by reassigning the array
                $bill = $this->manualBills[$index];
                $bill['total_amount'] = $cleanAmount;
                $bill['extraction_step_bill'] = 'Ekstraksi berhasil diselesaikan!';
                $this->manualBills[$index] = $bill;
                
                // Force Livewire to detect the change
                $this->dispatch('extraction-completed', ['type' => 'total', 'index' => $index]);
                $this->dispatch('amount-extracted', ['type' => 'total', 'index' => $index, 'amount' => $cleanAmount]);
                
                // Direct JavaScript update to ensure currency input gets the value
                $this->dispatch('update-currency-input', [
                    'model' => "manualBills.{$index}.total_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also dispatch browser event for direct JavaScript execution
                $this->dispatch('currency-value-updated', [
                    'model' => "manualBills.{$index}.total_amount",
                    'value' => $cleanAmount
                ], to: 'browser');
                
                // Force update by dispatching a custom event that will be caught by Alpine.js
                $this->dispatch('force-currency-update', [
                    'model' => "manualBills.{$index}.total_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also call direct update method
                $this->updateManualCurrencyValue($index, 'total_amount', $cleanAmount);
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                // Restore previous value if extraction failed and there was a previous value
                if ($hasPreviousValue) {
                    $this->manualBills[$index]['total_amount'] = $previousAmount;
                }
                $this->manualBills[$index]['extraction_step_bill'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            // Restore previous value if extraction failed and there was a previous value
            if ($hasPreviousValue) {
                $this->manualBills[$index]['total_amount'] = $previousAmount;
            }
            Log::error('Manual bill extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'index' => $index
            ]);
            $this->manualBills[$index]['extraction_step_bill'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->manualBills[$index]['extracting_bill'] = false;
            $this->manualBills[$index]['extraction_step_bill'] = '';
        }
    }

    public function extractPaymentAmountManual($index)
    {
        if (!isset($this->manualBills[$index]['payment_proof_image']) || !$this->manualBills[$index]['payment_proof_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->manualBills[$index]['payment_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->manualBills[$index]['extracting_payment'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->manualBills[$index]['payment_amount'] = 0;
        }
        
        $this->manualBills[$index]['extraction_step_payment'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'payment', 'index' => $index]);
        
        try {
            $tempPath = $this->manualBills[$index]['payment_proof_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->manualBills[$index]['payment_proof_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->manualBills[$index]['extraction_step_payment'] = 'Mengekstrak teks dari gambar (OCR)...';
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->manualBills[$index]['extraction_step_payment'] = 'Menganalisis dengan AI untuk mengekstrak jumlah pembayaran...';
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'transfer');
            
            if ($amount && $amount > 0) {
                // Ensure amount is a clean numeric value
                $cleanAmount = (float) $amount;
                
                // Update the amount - ensure Livewire detects the change by reassigning the array
                $bill = $this->manualBills[$index];
                $bill['payment_amount'] = $cleanAmount;
                $bill['extraction_step_payment'] = 'Ekstraksi berhasil diselesaikan!';
                $this->manualBills[$index] = $bill;
                
                // Force Livewire to detect the change
                $this->dispatch('extraction-completed', ['type' => 'payment', 'index' => $index]);
                $this->dispatch('amount-extracted', ['type' => 'payment', 'index' => $index, 'amount' => $cleanAmount]);
                
                // Direct JavaScript update to ensure currency input gets the value
                $this->dispatch('update-currency-input', [
                    'model' => "manualBills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also dispatch browser event for direct JavaScript execution
                $this->dispatch('currency-value-updated', [
                    'model' => "manualBills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ], to: 'browser');
                
                // Force update by dispatching a custom event that will be caught by Alpine.js
                $this->dispatch('force-currency-update', [
                    'model' => "manualBills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also call direct update method
                $this->updateManualCurrencyValue($index, 'payment_amount', $cleanAmount);
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                // Restore previous value if extraction failed and there was a previous value
                if ($hasPreviousValue) {
                    $this->manualBills[$index]['payment_amount'] = $previousAmount;
                }
                $this->manualBills[$index]['extraction_step_payment'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            // Restore previous value if extraction failed and there was a previous value
            if ($hasPreviousValue) {
                $this->manualBills[$index]['payment_amount'] = $previousAmount;
            }
            Log::error('Manual payment extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'index' => $index
            ]);
            $this->manualBills[$index]['extraction_step_payment'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->manualBills[$index]['extracting_payment'] = false;
            $this->manualBills[$index]['extraction_step_payment'] = '';
        }
    }

    public function extractBillAmountExtracted($index)
    {
        if (!isset($this->extractedBills[$index]['bill_image']) || !$this->extractedBills[$index]['bill_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->extractedBills[$index]['total_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->extractedBills[$index]['extracting_bill'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->extractedBills[$index]['total_amount'] = 0;
        }
        
        $this->extractedBills[$index]['extraction_step_bill'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'total', 'index' => $index]);
        
        try {
            $tempPath = $this->extractedBills[$index]['bill_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->extractedBills[$index]['bill_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->extractedBills[$index]['extraction_step_bill'] = 'Mengekstrak teks dari gambar (OCR)...';
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->extractedBills[$index]['extraction_step_bill'] = 'Menganalisis dengan AI untuk mengekstrak total tagihan...';
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'bill');
            
            if ($amount && $amount > 0) {
                $cleanAmount = (float) $amount;
                $this->extractedBills[$index]['total_amount'] = $cleanAmount;
                $this->extractedBills[$index]['extraction_step_bill'] = 'Ekstraksi berhasil diselesaikan!';
                
                $this->dispatch('extraction-completed', ['type' => 'total', 'index' => $index]);
                $this->dispatch('amount-extracted', ['type' => 'total', 'index' => $index, 'amount' => $cleanAmount]);
                
                $this->dispatch('update-currency-input', [
                    'model' => "extractedBills.{$index}.total_amount",
                    'value' => $cleanAmount
                ]);
                
                $this->updateExtractedCurrencyValue($index, 'total_amount', $cleanAmount);
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                if ($hasPreviousValue) {
                    $this->extractedBills[$index]['total_amount'] = $previousAmount;
                }
                $this->extractedBills[$index]['extraction_step_bill'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            if ($hasPreviousValue) {
                $this->extractedBills[$index]['total_amount'] = $previousAmount;
            }
            Log::error('Extracted bill extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'index' => $index
            ]);
            $this->extractedBills[$index]['extraction_step_bill'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->extractedBills[$index]['extracting_bill'] = false;
            $this->extractedBills[$index]['extraction_step_bill'] = '';
        }
    }

    public function extractPaymentAmountExtracted($index)
    {
        if (!isset($this->extractedBills[$index]['payment_proof_image']) || !$this->extractedBills[$index]['payment_proof_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->extractedBills[$index]['payment_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->extractedBills[$index]['extracting_payment'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->extractedBills[$index]['payment_amount'] = 0;
        }
        
        $this->extractedBills[$index]['extraction_step_payment'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'payment', 'index' => $index]);
        
        try {
            $tempPath = $this->extractedBills[$index]['payment_proof_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->extractedBills[$index]['payment_proof_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->extractedBills[$index]['extraction_step_payment'] = 'Mengekstrak teks dari gambar (OCR)...';
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->extractedBills[$index]['extraction_step_payment'] = 'Menganalisis dengan AI untuk mengekstrak jumlah pembayaran...';
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'transfer');
            
            if ($amount && $amount > 0) {
                $cleanAmount = (float) $amount;
                $this->extractedBills[$index]['payment_amount'] = $cleanAmount;
                $this->extractedBills[$index]['extraction_step_payment'] = 'Ekstraksi berhasil diselesaikan!';
                
                $this->dispatch('extraction-completed', ['type' => 'payment', 'index' => $index]);
                $this->dispatch('amount-extracted', ['type' => 'payment', 'index' => $index, 'amount' => $cleanAmount]);
                
                $this->dispatch('update-currency-input', [
                    'model' => "extractedBills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ]);
                
                $this->updateExtractedCurrencyValue($index, 'payment_amount', $cleanAmount);
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                if ($hasPreviousValue) {
                    $this->extractedBills[$index]['payment_amount'] = $previousAmount;
                }
                $this->extractedBills[$index]['extraction_step_payment'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            if ($hasPreviousValue) {
                $this->extractedBills[$index]['payment_amount'] = $previousAmount;
            }
            Log::error('Extracted payment extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'index' => $index
            ]);
            $this->extractedBills[$index]['extraction_step_payment'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->extractedBills[$index]['extracting_payment'] = false;
            $this->extractedBills[$index]['extraction_step_payment'] = '';
        }
    }

    public function toggleBranch($billIndex, $branchId)
    {
        if (!isset($this->branchAssignments[$billIndex])) {
            $this->branchAssignments[$billIndex] = [];
        }

        $index = array_search($branchId, $this->branchAssignments[$billIndex]);
        
        if ($index !== false) {
            // Remove branch
            unset($this->branchAssignments[$billIndex][$index]);
            $this->branchAssignments[$billIndex] = array_values($this->branchAssignments[$billIndex]);
        } else {
            // Add branch
            $this->branchAssignments[$billIndex][] = $branchId;
        }
    }

    public function assignBranches()
    {
        try {
            $this->errorMessage = '';
            
            // Combine extracted bills and manual bills
            $allBills = array_merge($this->extractedBills, $this->manualBills);
            
            // Validate that all bills have at least one branch selected
            foreach ($allBills as $index => $bill) {
                if (empty($this->branchAssignments[$index]) || count($this->branchAssignments[$index]) === 0) {
                    $this->errorMessage = 'Silakan pilih minimal satu cabang untuk setiap tagihan.';
                    return;
                }
            }
            
            // Validate extracted bills data
            foreach ($this->extractedBills as $index => $bill) {
                $totalAmount = \App\Helpers\CurrencyHelper::sanitize($bill['total_amount']) ?? 0;
                $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($bill['payment_amount']) ?? 0;
                
                if ($totalAmount <= 0) {
                    $this->errorMessage = "Total tagihan wajib diisi untuk tagihan #" . ($index + 1);
                    return;
                }
                
                if ($paymentAmount <= 0) {
                    $this->errorMessage = "Jumlah pembayaran wajib diisi untuk tagihan #" . ($index + 1);
                    return;
                }
                
                if (empty($bill['date'])) {
                    $this->errorMessage = "Tanggal wajib diisi untuk tagihan #" . ($index + 1);
                    return;
                }
            }
            
            // Validate manual bills data
            foreach ($this->manualBills as $index => $bill) {
                $totalAmount = \App\Helpers\CurrencyHelper::sanitize($bill['total_amount']) ?? 0;
                $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($bill['payment_amount']) ?? 0;
                
                if ($totalAmount <= 0) {
                    $this->errorMessage = "Total tagihan wajib diisi untuk tagihan manual #" . (count($this->extractedBills) + $index + 1);
                    return;
                }
                
                if ($paymentAmount <= 0) {
                    $this->errorMessage = "Jumlah pembayaran wajib diisi untuk tagihan manual #" . (count($this->extractedBills) + $index + 1);
                    return;
                }
                
                if (empty($bill['date'])) {
                    $this->errorMessage = "Tanggal wajib diisi untuk tagihan manual #" . (count($this->extractedBills) + $index + 1);
                    return;
                }
            }

            DB::beginTransaction();
            $createdCount = 0;

            // Create bills for each extracted bill and selected branches
            foreach ($this->extractedBills as $index => $billData) {
                $branchIds = $this->branchAssignments[$index] ?? [];
                
                // Sanitize amounts
                $totalAmount = \App\Helpers\CurrencyHelper::sanitize($billData['total_amount']) ?? 0;
                $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($billData['payment_amount']) ?? 0;
                
                // Store images
                $billImagePath = null;
                if (isset($billData['bill_image']) && $billData['bill_image']) {
                    $billImagePath = $billData['bill_image']->store('bills', 'public');
                }
                
                $paymentProofPath = null;
                if (isset($billData['payment_proof_image']) && $billData['payment_proof_image']) {
                    $paymentProofPath = $billData['payment_proof_image']->store('payments', 'public');
                }
                
                foreach ($branchIds as $branchId) {
                    // Calculate status
                    $status = 'pending';
                    if ($totalAmount > 0) {
                        if ($paymentAmount >= $totalAmount) {
                            $status = 'paid';
                        } elseif ($paymentAmount > 0) {
                            $status = 'partial';
                        }
                    } else {
                        if ($paymentAmount > 0) {
                            $status = 'paid';
                        }
                    }

                    Bill::create([
                        'branch_id' => $branchId,
                        'user_id' => auth()->id(),
                        'bill_image_path' => $billImagePath ? 'public/' . $billImagePath : null,
                        'total_amount' => $totalAmount,
                        'payment_proof_image_path' => $paymentProofPath ? 'public/' . $paymentProofPath : null,
                        'payment_amount' => $paymentAmount,
                        'status' => $status,
                        'date' => $billData['date'],
                    ]);
                    
                    $createdCount++;
                }
            }
            
            // Create bills for manual bills
            foreach ($this->manualBills as $manualIndex => $billData) {
                $index = count($this->extractedBills) + $manualIndex;
                $branchIds = $this->branchAssignments[$index] ?? [];
                
                // Sanitize amounts
                $totalAmount = \App\Helpers\CurrencyHelper::sanitize($billData['total_amount']) ?? 0;
                $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($billData['payment_amount']) ?? 0;
                
                // Store images
                $billImagePath = null;
                if (isset($billData['bill_image']) && $billData['bill_image']) {
                    $billImagePath = $billData['bill_image']->store('bills', 'public');
                }
                
                $paymentProofPath = null;
                if (isset($billData['payment_proof_image']) && $billData['payment_proof_image']) {
                    $paymentProofPath = $billData['payment_proof_image']->store('payments', 'public');
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
                    if ($paymentAmount > 0) {
                        $status = 'paid';
                    }
                }
                
                foreach ($branchIds as $branchId) {
                    Bill::create([
                        'branch_id' => $branchId,
                        'user_id' => auth()->id(),
                        'bill_image_path' => $billImagePath ? 'public/' . $billImagePath : null,
                        'total_amount' => $totalAmount,
                        'payment_proof_image_path' => $paymentProofPath ? 'public/' . $paymentProofPath : null,
                        'payment_amount' => $paymentAmount,
                        'status' => $status,
                        'date' => $billData['date'],
                    ]);
                    
                    $createdCount++;
                }
            }

            DB::commit();
            
            $this->successMessage = "Berhasil membuat {$createdCount} tagihan!";
            session()->flash('success', $this->successMessage);
            
            $this->closeModals();
            
            // Redirect to bills index to show the new bills
            return $this->redirect(route('bills.index'), navigate: true);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = 'Terjadi kesalahan saat menyimpan tagihan: ' . $e->getMessage();
            Log::error('AI Helper bill creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
}; ?>

<div>
    <!-- AI Helper Button -->
    <x-ui.button
        type="button"
        wire:click="openAiHelper"
        variant="outline"
        class="w-full sm:w-auto flex items-center justify-center gap-2"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
        </svg>
        AI Helper
    </x-ui.button>

    <!-- AI Helper Input Modal -->
    <x-ui.modal 
        wire:model="showModal"
        title="AI Helper - Buat Tagihan dari Teks"
        size="2xl"
        :closeable="true"
        :showFooter="false"
    >
        <div class="space-y-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Cara Menggunakan
                </h4>
                <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1.5 list-disc list-inside">
                    <li>Masukkan informasi tagihan dalam format bebas</li>
                    <li>AI akan otomatis mengenali jumlah tagihan, nominal, tanggal, dan pembayaran</li>
                    <li>Pilih "Isi Form" untuk mengisi form create bill, atau "Buat Langsung" untuk membuat tagihan langsung</li>
                </ul>
            </div>

            <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                    Masukkan Teks Tagihan
                </label>
                <textarea
                    wire:model="inputText"
                    rows="12"
                    class="w-full px-4 py-3 border border-neutral-300 dark:border-neutral-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-neutral-700 dark:text-neutral-100 text-sm min-h-[200px] sm:min-h-[300px] resize-y"
                    placeholder="Contoh format:&#10;&#10;Tagihan 1: Rp 100.000 tanggal 15 Januari 2024&#10;Tagihan 2: Rp 200.000 tanggal 16 Januari 2024 pembayaran 50.000&#10;&#10;Atau format lainnya seperti:&#10;- Tagihan A: 150000&#10;- Bill B: Rp 300.000, tanggal 20 Jan 2024"
                ></textarea>
                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                    💡 Tips: Semakin jelas informasi yang diberikan, semakin akurat hasil ekstraksinya
                </p>
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 border border-red-200 p-2 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-xs font-medium">{{ $errorMessage }}</p>
                    </div>
                </div>
            @endif

            @if($successMessage)
                <div class="rounded-md bg-green-50 border border-green-200 p-2 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-xs font-medium">{{ $successMessage }}</p>
                    </div>
                </div>
            @endif

            <!-- Footer Buttons -->
            <div class="border-t border-neutral-200 dark:border-neutral-700 pt-4 mt-6 flex flex-col sm:flex-row gap-3 justify-end">
                <x-ui.button
                    type="button"
                    variant="outline"
                    wire:click="closeModals"
                    wire:loading.attr="disabled"
                    class="w-full sm:w-auto"
                >
                    Batal
                </x-ui.button>
                <div class="flex gap-3 w-full sm:w-auto">
                    <x-ui.button
                        type="button"
                        variant="outline"
                        wire:click="extractAndPopulateForm"
                        wire:loading.attr="disabled"
                        wire:target="extractAndPopulateForm"
                        class="flex-1 sm:flex-none"
                    >
                        <span wire:loading.remove wire:target="extractAndPopulateForm" class="flex items-center gap-2 justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Isi Form
                        </span>
                        <span wire:loading wire:target="extractAndPopulateForm" class="flex items-center gap-2 justify-center">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Memproses...
                        </span>
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="primary"
                        wire:click="extractAndCreate"
                        wire:loading.attr="disabled"
                        wire:target="extractAndCreate"
                        class="flex-1 sm:flex-none"
                    >
                        <span wire:loading.remove wire:target="extractAndCreate" class="flex items-center gap-2 justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Buat Langsung
                        </span>
                        <span wire:loading wire:target="extractAndCreate" class="flex items-center gap-2 justify-center">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Memproses...
                        </span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.modal>

    <!-- Branch Assignment Modal -->
    <x-ui.modal 
        wire:model="showBranchModal"
        title="Pilih Cabang untuk Tagihan"
        size="4xl"
        :closeable="true"
        :showFooter="false"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    Pilih cabang untuk setiap tagihan. Anda dapat memilih beberapa cabang untuk satu tagihan (akan dibuat duplikat).
                </p>
                <button
                    type="button"
                    wire:click="addManualBill"
                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tambah Tagihan Manual
                </button>
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 border border-red-200 p-3 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm font-medium">{{ $errorMessage }}</p>
                    </div>
                </div>
            @endif

            <!-- Extracted Bills from AI -->
            @foreach($extractedBills as $index => $bill)
                <div class="rounded-xl border-2 p-3 shadow-sm {{ $this->getBackgroundClass($index) }} {{ $this->getBorderClass($index) }}">
                    <!-- Bill Header -->
                    <div class="flex items-center justify-between mb-3 pb-3 border-b {{ $this->getBorderClass($index) }}">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                Tagihan #{{ $index + 1 }}
                            </h3>
                        </div>
                    </div>

                    <!-- Bill Form - Same as Manual -->
                    <div class="space-y-3">
                        <!-- Amounts and Date -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                            <div
                                x-data="{}"
                                @force-currency-update.window="
                                    if ($event.detail && $event.detail.model === 'extractedBills.{{ $index }}.total_amount') {
                                        \$wire.call('updateExtractedCurrencyValue', {{ $index }}, 'total_amount', $event.detail.value);
                                        \$wire.set('extractedBills.{{ $index }}.total_amount', $event.detail.value);
                                    }
                                ">
                                <x-ui.currency-input 
                                    label="Total Tagihan" 
                                    name="extractedBills.{{ $index }}.total_amount" 
                                    :value="$bill['total_amount']"
                                    wireModel="extractedBills.{{ $index }}.total_amount"
                                    placeholder="0"
                                    required
                                />
                            </div>
                            <div
                                x-data="{}"
                                @force-currency-update.window="
                                    if ($event.detail && $event.detail.model === 'extractedBills.{{ $index }}.payment_amount') {
                                        \$wire.call('updateExtractedCurrencyValue', {{ $index }}, 'payment_amount', $event.detail.value);
                                        \$wire.set('extractedBills.{{ $index }}.payment_amount', $event.detail.value);
                                    }
                                ">
                                <x-ui.currency-input 
                                    label="Jumlah Pembayaran" 
                                    name="extractedBills.{{ $index }}.payment_amount" 
                                    :value="$bill['payment_amount']"
                                    wireModel="extractedBills.{{ $index }}.payment_amount"
                                    placeholder="0"
                                    required
                                />
                            </div>
                            <div>
                                <x-ui.input 
                                    label="Tanggal Transaksi" 
                                    name="extractedBills.{{ $index }}.date" 
                                    type="date"
                                    wire:model="extractedBills.{{ $index }}.date"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Images Upload - Compact -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <!-- Payment Proof -->
                            <div class="rounded-lg border-2 {{ $this->getBorderClass($index) }} p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Bukti Pembayaran</h4>
                                    <span class="px-1.5 py-0.5 text-xs bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
                                </div>
                                <div 
                                    x-data="{ 
                                        isDragging: false,
                                        handleDrop(e) {
                                            this.isDragging = false;
                                            if (e.dataTransfer.files.length) {
                                                @this.upload('extractedBills.{{ $index }}.payment_proof_image', e.dataTransfer.files[0]);
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
                                    @if($bill['payment_proof_preview'] ?? null)
                                        <div class="relative w-full h-40 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-2">
                                            <div class="relative w-full h-full overflow-hidden flex items-center justify-center">
                                                <x-ui.image-lightbox :src="$bill['payment_proof_preview']" alt="Pratinjau bukti pembayaran">
                                                    <img src="{{ $bill['payment_proof_preview'] }}" alt="Pratinjau bukti pembayaran" class="w-full h-full object-contain rounded-lg" />
                                                </x-ui.image-lightbox>
                                            </div>
                                            <div wire:loading wire:target="extractedBills.{{ $index }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <label 
                                            for="extracted_payment_proof_{{ $index }}"
                                            class="flex items-center justify-center w-full px-3 py-1.5 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800 text-xs"
                                        >
                                            <svg class="w-4 h-4 mr-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <span class="text-neutral-600 dark:text-neutral-400 font-medium">Upload Baru</span>
                                            <input 
                                                type="file" 
                                                id="extracted_payment_proof_{{ $index }}"
                                                wire:model="extractedBills.{{ $index }}.payment_proof_image"
                                                wire:change="$wire.handlePaymentImageUpdate('extracted', {{ $index }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                        </label>
                                    @else
                                        <label 
                                            for="extracted_payment_proof_{{ $index }}"
                                            class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                                            :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                                        >
                                            <div class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-3">
                                                <svg class="w-8 h-8 mb-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center">
                                                    <span class="font-semibold">Klik untuk upload</span><br>atau drag and drop
                                                </p>
                                            </div>
                                            <input 
                                                type="file" 
                                                id="extracted_payment_proof_{{ $index }}"
                                                wire:model="extractedBills.{{ $index }}.payment_proof_image"
                                                wire:change="$wire.handlePaymentImageUpdate('extracted', {{ $index }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                            <div wire:loading wire:target="extractedBills.{{ $index }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </label>
                                    @endif
                                </div>
                                @if(($bill['extracting_payment'] ?? false))
                                    <div class="mt-2 p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="animate-spin h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="font-medium text-blue-900 dark:text-blue-100 text-xs mb-1">Memproses...</p>
                                                @if($bill['extraction_step_payment'] ?? '')
                                                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ $bill['extraction_step_payment'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Bill Proof -->
                            <div class="rounded-lg border-2 {{ $this->getBorderClass($index) }} p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Bukti Tagihan</h4>
                                    <span class="px-1.5 py-0.5 text-xs bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
                                </div>
                                <div 
                                    x-data="{ 
                                        isDragging: false,
                                        handleDrop(e) {
                                            this.isDragging = false;
                                            if (e.dataTransfer.files.length) {
                                                @this.upload('extractedBills.{{ $index }}.bill_image', e.dataTransfer.files[0]);
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
                                    @if($bill['bill_image_preview'] ?? null)
                                        <div class="relative w-full h-40 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-2">
                                            <div class="relative w-full h-full overflow-hidden flex items-center justify-center">
                                                <x-ui.image-lightbox :src="$bill['bill_image_preview']" alt="Pratinjau bukti tagihan">
                                                    <img src="{{ $bill['bill_image_preview'] }}" alt="Pratinjau bukti tagihan" class="w-full h-full object-contain rounded-lg" />
                                                </x-ui.image-lightbox>
                                            </div>
                                            <div wire:loading wire:target="extractedBills.{{ $index }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <label 
                                            for="extracted_bill_image_{{ $index }}"
                                            class="flex items-center justify-center w-full px-3 py-1.5 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800 text-xs"
                                        >
                                            <svg class="w-4 h-4 mr-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <span class="text-neutral-600 dark:text-neutral-400 font-medium">Upload Baru</span>
                                            <input 
                                                type="file" 
                                                id="extracted_bill_image_{{ $index }}"
                                                wire:model="extractedBills.{{ $index }}.bill_image"
                                                wire:change="$wire.handleBillImageUpdate('extracted', {{ $index }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                        </label>
                                    @else
                                        <label 
                                            for="extracted_bill_image_{{ $index }}"
                                            class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                                            :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                                        >
                                            <div class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-3">
                                                <svg class="w-8 h-8 mb-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                                <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center">
                                                    <span class="font-semibold">Klik untuk upload</span><br>atau drag and drop
                                                </p>
                                            </div>
                                            <input 
                                                type="file" 
                                                id="extracted_bill_image_{{ $index }}"
                                                wire:model="extractedBills.{{ $index }}.bill_image"
                                                wire:change="$wire.handleBillImageUpdate('extracted', {{ $index }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                            <div wire:loading wire:target="extractedBills.{{ $index }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </div>
                                        </label>
                                    @endif
                                </div>
                                @if(($bill['extracting_bill'] ?? false))
                                    <div class="mt-2 p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="animate-spin h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="font-medium text-blue-900 dark:text-blue-100 text-xs mb-1">Memproses...</p>
                                                @if($bill['extraction_step_bill'] ?? '')
                                                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ $bill['extraction_step_bill'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Branch Selection -->
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                                Pilih Cabang:
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-40 overflow-y-auto">
                                @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                                    <label class="flex items-center gap-2 p-1.5 rounded border border-neutral-300 dark:border-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleBranch({{ $index }}, {{ $branch->id }})"
                                            @if(in_array($branch->id, $branchAssignments[$index] ?? [])) checked @endif
                                            class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
                                        >
                                        <span class="text-xs text-neutral-900 dark:text-neutral-100">{{ $branch->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if(empty($branchAssignments[$index]) || count($branchAssignments[$index]) === 0)
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">* Pilih minimal satu cabang</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Manual Bills -->
            @foreach($manualBills as $manualIndex => $bill)
                @php
                    $index = count($extractedBills) + $manualIndex;
                @endphp
                <div class="rounded-xl border-2 p-3 shadow-sm {{ $this->getBackgroundClass($index) }} {{ $this->getBorderClass($index) }}">
                    <!-- Bill Header -->
                    <div class="flex items-center justify-between mb-3 pb-3 border-b {{ $this->getBorderClass($index) }}">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold">
                                {{ $index + 1 }}
                            </span>
                            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                Tagihan Manual #{{ $index + 1 }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            wire:click="removeManualBill({{ $manualIndex }})"
                            class="px-2 py-1 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Hapus
                        </button>
                    </div>

                    <!-- Manual Bill Form -->
                    <div class="space-y-3">
                        <!-- Amounts and Date -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                            <div
                                x-data="{}"
                                @force-currency-update.window="
                                    if ($event.detail && $event.detail.model === 'manualBills.{{ $manualIndex }}.total_amount') {
                                        \$wire.call('updateManualCurrencyValue', {{ $manualIndex }}, 'total_amount', $event.detail.value);
                                        \$wire.set('manualBills.{{ $manualIndex }}.total_amount', $event.detail.value);
                                    }
                                ">
                                <x-ui.currency-input 
                                    label="Total Tagihan" 
                                    name="manualBills.{{ $manualIndex }}.total_amount" 
                                    :value="$bill['total_amount']"
                                    wireModel="manualBills.{{ $manualIndex }}.total_amount"
                                    placeholder="0"
                                    required
                                />
                            </div>
                            <div
                                x-data="{}"
                                @force-currency-update.window="
                                    if ($event.detail && $event.detail.model === 'manualBills.{{ $manualIndex }}.payment_amount') {
                                        \$wire.call('updateManualCurrencyValue', {{ $manualIndex }}, 'payment_amount', $event.detail.value);
                                        \$wire.set('manualBills.{{ $manualIndex }}.payment_amount', $event.detail.value);
                                    }
                                ">
                                <x-ui.currency-input 
                                    label="Jumlah Pembayaran" 
                                    name="manualBills.{{ $manualIndex }}.payment_amount" 
                                    :value="$bill['payment_amount']"
                                    wireModel="manualBills.{{ $manualIndex }}.payment_amount"
                                    placeholder="0"
                                    required
                                />
                            </div>
                            <div>
                                <x-ui.input 
                                    label="Tanggal Transaksi" 
                                    name="manualBills.{{ $manualIndex }}.date" 
                                    type="date"
                                    wire:model="manualBills.{{ $manualIndex }}.date"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Images Upload - Compact -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <!-- Payment Proof -->
                            <div class="rounded-lg border-2 {{ $this->getBorderClass($index) }} p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Bukti Pembayaran</h4>
                                    <span class="px-1.5 py-0.5 text-xs bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
                                </div>

                                <div 
                                    x-data="{ 
                                        isDragging: false,
                                        handleDrop(e) {
                                            this.isDragging = false;
                                            if (e.dataTransfer.files.length) {
                                                @this.upload('manualBills.{{ $manualIndex }}.payment_proof_image', e.dataTransfer.files[0]);
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
                                    @if($bill['payment_proof_preview'])
                                        <div class="relative w-full h-40 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-2">
                                            <div class="relative w-full h-full overflow-hidden flex items-center justify-center">
                                                <x-ui.image-lightbox :src="$bill['payment_proof_preview']" alt="Pratinjau bukti pembayaran">
                                                    <img src="{{ $bill['payment_proof_preview'] }}" alt="Pratinjau bukti pembayaran" class="w-full h-full object-contain rounded-lg" />
                                                </x-ui.image-lightbox>
                                            </div>
                                            <div wire:loading wire:target="manualBills.{{ $manualIndex }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <div class="text-center">
                                                    <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <label 
                                            for="manual_payment_proof_{{ $manualIndex }}"
                                            class="flex items-center justify-center w-full px-3 py-1.5 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800 text-xs"
                                        >
                                            <svg class="w-4 h-4 mr-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <span class="text-neutral-600 dark:text-neutral-400 font-medium">Upload Baru</span>
                                            <input 
                                                type="file" 
                                                id="manual_payment_proof_{{ $manualIndex }}"
                                                wire:model="manualBills.{{ $manualIndex }}.payment_proof_image"
                                                wire:change="$wire.handlePaymentImageUpdate('manual', {{ $manualIndex }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                        </label>
                                    @else
                                        <label 
                                            for="manual_payment_proof_{{ $manualIndex }}"
                                            class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                                            :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                                        >
                                            <div class="relative w-full h-full">
                                                <div class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-3">
                                                    <svg class="w-8 h-8 mb-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                    </svg>
                                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center">
                                                        <span class="font-semibold">Klik untuk upload</span><br>atau drag and drop
                                                    </p>
                                                </div>
                                            </div>
                                            <input 
                                                type="file" 
                                                id="manual_payment_proof_{{ $manualIndex }}"
                                                wire:model="manualBills.{{ $manualIndex }}.payment_proof_image"
                                                wire:change="$wire.handlePaymentImageUpdate('manual', {{ $manualIndex }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                            <div wire:loading wire:target="manualBills.{{ $manualIndex }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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

                                @if($bill['extracting_payment'])
                                    <div class="mt-2 p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="animate-spin h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="font-medium text-blue-900 dark:text-blue-100 text-xs mb-1">Memproses...</p>
                                                @if($bill['extraction_step_payment'])
                                                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ $bill['extraction_step_payment'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Bill Proof -->
                            <div class="rounded-lg border-2 {{ $this->getBorderClass($index) }} p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-neutral-900 dark:text-neutral-100">Bukti Tagihan</h4>
                                    <span class="px-1.5 py-0.5 text-xs bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-400 rounded">Opsional</span>
                                </div>

                                <div 
                                    x-data="{ 
                                        isDragging: false,
                                        handleDrop(e) {
                                            this.isDragging = false;
                                            if (e.dataTransfer.files.length) {
                                                @this.upload('manualBills.{{ $manualIndex }}.bill_image', e.dataTransfer.files[0]);
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
                                    @if($bill['bill_image_preview'])
                                        <div class="relative w-full h-40 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-2">
                                            <div class="relative w-full h-full overflow-hidden flex items-center justify-center">
                                                <x-ui.image-lightbox :src="$bill['bill_image_preview']" alt="Pratinjau bukti tagihan">
                                                    <img src="{{ $bill['bill_image_preview'] }}" alt="Pratinjau bukti tagihan" class="w-full h-full object-contain rounded-lg" />
                                                </x-ui.image-lightbox>
                                            </div>
                                            <div wire:loading wire:target="manualBills.{{ $manualIndex }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
                                                <div class="text-center">
                                                    <svg class="animate-spin h-6 w-6 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <p class="text-xs text-blue-600 font-medium">Mengunggah...</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <label 
                                            for="manual_bill_image_{{ $manualIndex }}"
                                            class="flex items-center justify-center w-full px-3 py-1.5 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800 text-xs"
                                        >
                                            <svg class="w-4 h-4 mr-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <span class="text-neutral-600 dark:text-neutral-400 font-medium">Upload Baru</span>
                                            <input 
                                                type="file" 
                                                id="manual_bill_image_{{ $manualIndex }}"
                                                wire:model="manualBills.{{ $manualIndex }}.bill_image"
                                                wire:change="$wire.handleBillImageUpdate('manual', {{ $manualIndex }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                        </label>
                                    @else
                                        <label 
                                            for="manual_bill_image_{{ $manualIndex }}"
                                            class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors overflow-hidden"
                                            :class="isDragging ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'border-neutral-300 dark:border-neutral-600 hover:border-blue-400 dark:hover:border-blue-500'"
                                        >
                                            <div class="relative w-full h-full">
                                                <div class="absolute inset-0 w-full h-full flex flex-col items-center justify-center py-2 px-3">
                                                    <svg class="w-8 h-8 mb-1.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                    </svg>
                                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 text-center">
                                                        <span class="font-semibold">Klik untuk upload</span><br>atau drag and drop
                                                    </p>
                                                </div>
                                            </div>
                                            <input 
                                                type="file" 
                                                id="manual_bill_image_{{ $manualIndex }}"
                                                wire:model="manualBills.{{ $manualIndex }}.bill_image"
                                                wire:change="$wire.handleBillImageUpdate('manual', {{ $manualIndex }})"
                                                wire:loading.attr="disabled"
                                                accept="image/*"
                                                class="hidden"
                                            />
                                            <div wire:loading wire:target="manualBills.{{ $manualIndex }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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

                                @if($bill['extracting_bill'])
                                    <div class="mt-2 p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                        <div class="flex items-start gap-2">
                                            <svg class="animate-spin h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="font-medium text-blue-900 dark:text-blue-100 text-xs mb-1">Memproses...</p>
                                                @if($bill['extraction_step_bill'])
                                                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ $bill['extraction_step_bill'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Branch Selection -->
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                                Pilih Cabang:
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-40 overflow-y-auto">
                                @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                                    <label class="flex items-center gap-2 p-1.5 rounded border border-neutral-300 dark:border-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleBranch({{ $index }}, {{ $branch->id }})"
                                            @if(in_array($branch->id, $branchAssignments[$index] ?? [])) checked @endif
                                            class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
                                        >
                                        <span class="text-xs text-neutral-900 dark:text-neutral-100">{{ $branch->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if(empty($branchAssignments[$index]) || count($branchAssignments[$index]) === 0)
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">* Pilih minimal satu cabang</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Footer Buttons -->
            <div class="border-t border-neutral-200 dark:border-neutral-700 pt-3 mt-4 flex gap-2 justify-end">
                <x-ui.button
                    type="button"
                    variant="outline"
                    wire:click="closeModals"
                    wire:loading.attr="disabled"
                    class="text-xs px-3 py-1.5"
                >
                    Batal
                </x-ui.button>
                <x-ui.button
                    type="button"
                    variant="primary"
                    wire:click="assignBranches"
                    wire:loading.attr="disabled"
                    wire:target="assignBranches"
                    class="text-xs px-3 py-1.5"
                >
                    <span wire:loading.remove wire:target="assignBranches">Simpan Tagihan</span>
                    <span wire:loading wire:target="assignBranches" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menyimpan...
                    </span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>

@script
<script>
    // Direct update mechanism for currency inputs after AI extraction (AI Helper)
    document.addEventListener('livewire:init', () => {
        const updateCurrencyInput = (index, type, amount) => {
            const fieldName = type === 'total' ? 'total_amount' : 'payment_amount';
            const inputName = `manualBills.${index}.${fieldName}`;
            
            // Try multiple times with delay to ensure element is available
            let attempts = 0;
            const maxAttempts = 10;
            
            const tryUpdate = () => {
                attempts++;
                const inputElement = document.querySelector(`input[name="${inputName}"]`);
                
                if (inputElement) {
                    // Format the amount for display
                    const formatted = formatCurrencyValue(amount);
                    
                    // Update the input value directly
                    inputElement.value = formatted;
                    
                    // Trigger multiple events to ensure Alpine.js detects the change
                    inputElement.dispatchEvent(new Event('input', { bubbles: true }));
                    inputElement.dispatchEvent(new Event('change', { bubbles: true }));
                    inputElement.dispatchEvent(new Event('blur', { bubbles: true }));
                    
                    // Also update via Livewire
                    try {
                        const component = Livewire.find(@js($this->getId()));
                        if (component) {
                            component.set(inputName, amount);
                        }
                    } catch(e) {
                        console.warn('Livewire update failed:', e);
                    }
                    
                    // Force Alpine.js to update by accessing the Alpine data
                    if (inputElement._x_dataStack && inputElement._x_dataStack[0]) {
                        const alpineData = inputElement._x_dataStack[0];
                        if (alpineData.formattedValue !== undefined) {
                            alpineData.formattedValue = formatted;
                        }
                    }
                } else if (attempts < maxAttempts) {
                    setTimeout(tryUpdate, 100);
                }
            };
            
            setTimeout(tryUpdate, 100);
        };
        
        // Listen for amount-extracted event
        Livewire.on('amount-extracted', (eventData) => {
            const data = Array.isArray(eventData) ? eventData[0] : eventData;
            if (data && data.index !== undefined && data.amount !== undefined && data.type) {
                updateCurrencyInput(data.index, data.type, data.amount);
            }
        });
        
        // Also listen for extraction-completed as fallback
        Livewire.on('extraction-completed', (eventData) => {
            const data = Array.isArray(eventData) ? eventData[0] : eventData;
            if (data && data.index !== undefined && data.type) {
                setTimeout(() => {
                    const fieldName = data.type === 'total' ? 'total_amount' : 'payment_amount';
                    const inputName = `manualBills.${data.index}.${fieldName}`;
                    const component = Livewire.find(@js($this->getId()));
                    if (component) {
                        const value = component.get(inputName);
                        if (value && value > 0) {
                            updateCurrencyInput(data.index, data.type, value);
                        }
                    }
                }, 300);
            }
        });
    });
    
    // Format currency value helper function (shared)
    if (typeof formatCurrencyValue === 'undefined') {
        window.formatCurrencyValue = function(value) {
            if (!value || value === 0) return '';
            
            const num = parseFloat(value) || 0;
            const parts = num.toFixed(2).split('.');
            const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const decimalPart = parts[1];
            
            if (decimalPart === '00' || decimalPart === '0') {
                return integerPart;
            }
            return integerPart + ',' + decimalPart;
        };
    }
</script>
@endscript
