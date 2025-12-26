<?php

use App\Models\Branch;
use App\Models\Bill;
use App\Services\AiExtractionService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithFileUploads;

    public $bills = [];
    public $validation_errors = [];

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

    public function mount()
    {
        // Initialize with one empty bill
        $this->addBill();
        
        // Check if there are bills from AI helper in session
        if (session()->has('ai_extracted_bills')) {
            $extractedBills = session()->get('ai_extracted_bills');
            $this->populateFromAiHelper($extractedBills);
            session()->forget('ai_extracted_bills');
        }
    }
    
    public function updateCurrencyValue($index, $field, $value)
    {
        // Direct method to update currency value
        // In Livewire 3, direct assignment to nested array property should work
        if (isset($this->bills[$index])) {
            $this->bills[$index][$field] = (float) $value;
            // Force Livewire to detect the change
            $this->dispatch('$refresh');
        }
    }

    public function addBill()
    {
        $this->bills[] = [
            'branch_id' => '',
            'new_branch_name' => '',
            'show_new_branch' => false,
            'bill_image' => null,
            'payment_proof_image' => null,
            'total_amount' => '',
            'payment_amount' => '',
            'date' => now()->format('Y-m-d'),
            'bill_image_preview' => null,
            'payment_proof_preview' => null,
            'extracting_bill' => false,
            'extracting_payment' => false,
            'validation_warning' => '',
            'extraction_step_bill' => '',
            'extraction_step_payment' => '',
        ];
        
        $this->dispatch('bill-added');
    }

    public function removeBill($index)
    {
        if (count($this->bills) > 1) {
            unset($this->bills[$index]);
            $this->bills = array_values($this->bills);
            $this->validation_errors = [];
        }
    }

    public function populateFromAiHelper($extractedBills)
    {
        $this->bills = [];
        foreach ($extractedBills as $billData) {
            $this->bills[] = [
                'branch_id' => '',
                'new_branch_name' => '',
                'show_new_branch' => false,
                'bill_image' => null,
                'payment_proof_image' => null,
                'total_amount' => $billData['total_amount'] ?? '',
                'payment_amount' => $billData['payment_amount'] ?? '',
                'date' => $billData['date'] ?? now()->format('Y-m-d'),
                'bill_image_preview' => null,
                'payment_proof_preview' => null,
                'extracting_bill' => false,
                'extracting_payment' => false,
                'validation_warning' => '',
                'extraction_step_bill' => '',
                'extraction_step_payment' => '',
            ];
        }
        
        session()->flash('success', 'Form telah diisi dengan ' . count($extractedBills) . ' tagihan dari AI Helper. Silakan lengkapi informasi cabang dan simpan.');
        $this->dispatch('bills-populated');
    }

    public function updatedBills($value, $path)
    {
        // Handle nested property updates
        // Path format: "0.bill_image" or "bills.0.bill_image" or just the index "0"
        $parts = explode('.', $path);
        
        // Find the index (could be first or second part depending on path format)
        $index = null;
        $field = null;
        
        // Handle different path formats
        if (count($parts) === 1 && is_numeric($parts[0])) {
            // Path is just the index, check all bills for file uploads
            $index = (int) $parts[0];
        } else {
            foreach ($parts as $i => $part) {
                if (is_numeric($part) && $index === null) {
                    $index = (int) $part;
                    // Next part should be the field name
                    if (isset($parts[$i + 1])) {
                        $field = $parts[$i + 1];
                    }
                    break;
                }
            }
        }
        
        if ($index !== null && isset($this->bills[$index])) {
            // If field is not set, check if it's a file upload by checking the value
            if ($field === null) {
                // Check if the value is a file upload object
                if (isset($this->bills[$index]['bill_image']) && 
                    is_object($this->bills[$index]['bill_image']) && 
                    method_exists($this->bills[$index]['bill_image'], 'getRealPath')) {
                    $field = 'bill_image';
                } elseif (isset($this->bills[$index]['payment_proof_image']) && 
                          is_object($this->bills[$index]['payment_proof_image']) && 
                          method_exists($this->bills[$index]['payment_proof_image'], 'getRealPath')) {
                    $field = 'payment_proof_image';
                }
            }
            
            // Handle file uploads
            if ($field === 'bill_image') {
                // Use dispatch to ensure it runs after Livewire finishes updating
                $this->dispatch('bill-image-uploaded', index: $index);
                // Call handler - Livewire should have the file ready by now
                $this->handleBillImageUpdate($index);
            } elseif ($field === 'payment_proof_image') {
                // Use dispatch to ensure it runs after Livewire finishes updating
                $this->dispatch('payment-image-uploaded', index: $index);
                // Call handler - Livewire should have the file ready by now
                $this->handlePaymentImageUpdate($index);
            } elseif ($field === 'total_amount' || $field === 'payment_amount') {
                $this->checkValidation($index);
            }
        }
        
        // Fallback: Check all bills for any new file uploads that might have been missed
        // This ensures we catch file uploads even if updatedBills wasn't called with the right path
        // Only check if the current update didn't trigger a handler (field was null or not a file field)
        if ($field === null || ($field !== 'bill_image' && $field !== 'payment_proof_image')) {
            foreach ($this->bills as $idx => $bill) {
                // Check for bill_image uploads that don't have preview yet and aren't currently extracting
                if (isset($bill['bill_image']) && 
                    is_object($bill['bill_image']) && 
                    method_exists($bill['bill_image'], 'getRealPath') &&
                    empty($bill['bill_image_preview']) &&
                    !($bill['extracting_bill'] ?? false)) {
                    $this->handleBillImageUpdate($idx);
                    break; // Only process one at a time to avoid conflicts
                }
                
                // Check for payment_proof_image uploads that don't have preview yet and aren't currently extracting
                if (isset($bill['payment_proof_image']) && 
                    is_object($bill['payment_proof_image']) && 
                    method_exists($bill['payment_proof_image'], 'getRealPath') &&
                    empty($bill['payment_proof_preview']) &&
                    !($bill['extracting_payment'] ?? false)) {
                    $this->handlePaymentImageUpdate($idx);
                    break; // Only process one at a time to avoid conflicts
                }
            }
        }
    }
    

    public function handleBillImageUpdate($index)
    {
        // Add a small delay to ensure Livewire has finished updating the property
        if (!isset($this->bills[$index]) || !isset($this->bills[$index]['bill_image'])) {
            return;
        }
        
        $billImage = $this->bills[$index]['bill_image'];
        
        // Check if it's a valid file upload object
        if (!$billImage || !is_object($billImage) || !method_exists($billImage, 'getRealPath')) {
            return;
        }
        
        try {
            // Generate preview URL
            $this->bills[$index]['bill_image_preview'] = $billImage->temporaryUrl();
            
            // Start extraction
            $this->extractBillAmount($index);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error handling bill image update: ' . $e->getMessage(), [
                'index' => $index,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function handlePaymentImageUpdate($index)
    {
        // Add a small delay to ensure Livewire has finished updating the property
        if (!isset($this->bills[$index]) || !isset($this->bills[$index]['payment_proof_image'])) {
            return;
        }
        
        $paymentImage = $this->bills[$index]['payment_proof_image'];
        
        // Check if it's a valid file upload object
        if (!$paymentImage || !is_object($paymentImage) || !method_exists($paymentImage, 'getRealPath')) {
            return;
        }
        
        try {
            // Generate preview URL
            $this->bills[$index]['payment_proof_preview'] = $paymentImage->temporaryUrl();
            
            // Start extraction
            $this->extractPaymentAmount($index);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error handling payment image update: ' . $e->getMessage(), [
                'index' => $index,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function extractBillAmount($index)
    {
        if (!isset($this->bills[$index]['bill_image']) || !$this->bills[$index]['bill_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->bills[$index]['total_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->bills[$index]['extracting_bill'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->bills[$index]['total_amount'] = 0;
        }
        
        $this->bills[$index]['extraction_step_bill'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'total', 'index' => $index]);
        
        try {
            $this->bills[$index]['extraction_step_bill'] = 'Membaca file gambar...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_bill']]);
            
            $tempPath = $this->bills[$index]['bill_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->bills[$index]['bill_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->bills[$index]['extraction_step_bill'] = 'Mengekstrak teks dari gambar (OCR)...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_bill']]);
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->bills[$index]['extraction_step_bill'] = 'Menganalisis dengan AI untuk mengekstrak total tagihan...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_bill']]);
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'bill');
            
            if ($amount && $amount > 0) {
                // Ensure amount is a clean numeric value
                $cleanAmount = (float) $amount;
                
                // Update the amount directly - Livewire 3 handles nested property updates automatically
                $this->bills[$index]['total_amount'] = $cleanAmount;
                $this->bills[$index]['extraction_step_bill'] = 'Ekstraksi berhasil diselesaikan!';
                
                // Force Livewire to detect the change by refreshing
                $this->dispatch('$refresh');
                
                // Dispatch extraction completed first
                $this->dispatch('extraction-completed', ['type' => 'total', 'index' => $index]);
                
                // Dispatch amount-extracted event with proper data structure
                $this->dispatch('amount-extracted', [
                    'type' => 'total',
                    'index' => $index,
                    'amount' => $cleanAmount
                ]);
                
                // Direct JavaScript update to ensure currency input gets the value
                $this->dispatch('update-currency-input', [
                    'model' => "bills.{$index}.total_amount",
                    'value' => $cleanAmount
                ]);
                
                // Force update by dispatching a custom event that will be caught by Alpine.js
                $this->dispatch('force-currency-update', [
                    'model' => "bills.{$index}.total_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also dispatch browser event for direct JavaScript execution
                $this->dispatch('currency-value-updated', [
                    'model' => "bills.{$index}.total_amount",
                    'value' => $cleanAmount
                ], to: 'browser');
                
                // Dispatch custom browser event as additional fallback
                $this->js("
                    setTimeout(() => {
                        const event = new CustomEvent('currency-value-updated', {
                            detail: {
                                model: 'bills.{$index}.total_amount',
                                value: {$cleanAmount}
                            }
                        });
                        window.dispatchEvent(event);
                    }, 50);
                ");
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                // Restore previous value if extraction failed and there was a previous value
                if ($hasPreviousValue) {
                    $this->bills[$index]['total_amount'] = $previousAmount;
                }
                $this->bills[$index]['extraction_step_bill'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            // Restore previous value if extraction failed and there was a previous value
            if ($hasPreviousValue) {
                $this->bills[$index]['total_amount'] = $previousAmount;
            }
            \Illuminate\Support\Facades\Log::error('Bill extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $tempPath ?? 'unknown',
                'index' => $index
            ]);
            $this->bills[$index]['extraction_step_bill'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->bills[$index]['extracting_bill'] = false;
            $this->bills[$index]['extraction_step_bill'] = '';
        }
    }

    public function extractPaymentAmount($index)
    {
        if (!isset($this->bills[$index]['payment_proof_image']) || !$this->bills[$index]['payment_proof_image']) {
            return;
        }

        // Save previous value before reset
        $previousAmount = $this->bills[$index]['payment_amount'] ?? 0;
        $hasPreviousValue = $previousAmount > 0;

        $this->bills[$index]['extracting_payment'] = true;
        
        // Only reset to 0 if there's no previous value
        if (!$hasPreviousValue) {
            $this->bills[$index]['payment_amount'] = 0;
        }
        
        $this->bills[$index]['extraction_step_payment'] = 'Membaca file gambar...';
        
        // Dispatch extraction started event
        $this->dispatch('extraction-started', ['type' => 'payment', 'index' => $index]);
        
        try {
            $this->bills[$index]['extraction_step_payment'] = 'Membaca file gambar...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_payment']]);
            
            $tempPath = $this->bills[$index]['payment_proof_image']->getRealPath();
            
            if (!file_exists($tempPath)) {
                $path = $this->bills[$index]['payment_proof_image']->store('temp', 'public');
                $tempPath = storage_path('app/public/' . $path);
            }
            
            if (!file_exists($tempPath)) {
                throw new \Exception('Gagal mengakses file gambar yang diunggah');
            }
            
            $this->bills[$index]['extraction_step_payment'] = 'Mengekstrak teks dari gambar (OCR)...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_payment']]);
            
            $imageContent = file_get_contents($tempPath);
            if (empty($imageContent)) {
                throw new \Exception('File gambar kosong');
            }
            
            $this->bills[$index]['extraction_step_payment'] = 'Menganalisis dengan AI untuk mengekstrak jumlah pembayaran...';
            $this->dispatch('extraction-step-updated', ['step' => $this->bills[$index]['extraction_step_payment']]);
            
            $service = app(AiExtractionService::class);
            $amount = $service->extractAmountFromImageContent($imageContent, 'transfer');
            
            if ($amount && $amount > 0) {
                // Ensure amount is a clean numeric value
                $cleanAmount = (float) $amount;
                
                // Update the amount directly - Livewire 3 handles nested property updates automatically
                $this->bills[$index]['payment_amount'] = $cleanAmount;
                $this->bills[$index]['extraction_step_payment'] = 'Ekstraksi berhasil diselesaikan!';
                
                // Force Livewire to detect the change by refreshing
                $this->dispatch('$refresh');
                
                // Dispatch extraction completed first
                $this->dispatch('extraction-completed', ['type' => 'payment', 'index' => $index]);
                
                // Dispatch amount-extracted event with proper data structure
                $this->dispatch('amount-extracted', [
                    'type' => 'payment',
                    'index' => $index,
                    'amount' => $cleanAmount
                ]);
                
                // Direct JavaScript update to ensure currency input gets the value
                $this->dispatch('update-currency-input', [
                    'model' => "bills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ]);
                
                // Force update by dispatching a custom event that will be caught by Alpine.js
                $this->dispatch('force-currency-update', [
                    'model' => "bills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ]);
                
                // Also dispatch browser event for direct JavaScript execution
                $this->dispatch('currency-value-updated', [
                    'model' => "bills.{$index}.payment_amount",
                    'value' => $cleanAmount
                ], to: 'browser');
                
                // Dispatch custom browser event as additional fallback
                $this->js("
                    setTimeout(() => {
                        const event = new CustomEvent('currency-value-updated', {
                            detail: {
                                model: 'bills.{$index}.payment_amount',
                                value: {$cleanAmount}
                            }
                        });
                        window.dispatchEvent(event);
                    }, 50);
                ");
                
                session()->flash('success', 'Jumlah berhasil diekstrak: Rp ' . \App\Helpers\CurrencyHelper::format($cleanAmount));
            } else {
                // Restore previous value if extraction failed and there was a previous value
                if ($hasPreviousValue) {
                    $this->bills[$index]['payment_amount'] = $previousAmount;
                }
                $this->bills[$index]['extraction_step_payment'] = 'Tidak dapat mengekstrak jumlah. Silakan masukkan secara manual.';
                session()->flash('error', 'Tidak dapat mengekstrak jumlah dari gambar. Silakan masukkan secara manual.');
            }
        } catch (\Exception $e) {
            // Restore previous value if extraction failed and there was a previous value
            if ($hasPreviousValue) {
                $this->bills[$index]['payment_amount'] = $previousAmount;
            }
            \Illuminate\Support\Facades\Log::error('Payment extraction error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $tempPath ?? 'unknown',
                'index' => $index
            ]);
            $this->bills[$index]['extraction_step_payment'] = 'Ekstraksi gagal: ' . $e->getMessage();
            session()->flash('error', 'Gagal mengekstrak jumlah: ' . $e->getMessage());
        } finally {
            $this->bills[$index]['extracting_payment'] = false;
            $this->bills[$index]['extraction_step_payment'] = '';
        }
    }

    public function checkValidation($index)
    {
        if (!isset($this->bills[$index])) {
            return;
        }
        
        $this->bills[$index]['validation_warning'] = '';
        
        $totalAmount = \App\Helpers\CurrencyHelper::sanitize($this->bills[$index]['total_amount']) ?? 0;
        $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($this->bills[$index]['payment_amount']) ?? 0;
        
        if ($totalAmount > 0 && $paymentAmount > 0) {
            if (abs($totalAmount - $paymentAmount) > 0.01) {
                $this->bills[$index]['validation_warning'] = 'Total pembayaran tidak sama dengan total tagihan. Pastikan data sudah benar.';
            }
        }
    }

    public function save()
    {
        $this->validation_errors = [];
        $allValid = true;

        // Validate all bills
        foreach ($this->bills as $index => $bill) {
            $errors = [];
            
            // Validate branch
            if (!$bill['show_new_branch'] && empty($bill['branch_id'])) {
                $errors['branch_id'] = 'Pilih cabang atau buat cabang baru';
                $allValid = false;
            }
            
            if ($bill['show_new_branch'] && empty($bill['new_branch_name'])) {
                $errors['new_branch_name'] = 'Nama cabang baru harus diisi';
                $allValid = false;
            }
            
            // Validate payment amount
            $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($bill['payment_amount']) ?? 0;
            if ($paymentAmount <= 0) {
                $errors['payment_amount'] = 'Jumlah pembayaran wajib diisi dan harus lebih dari 0';
                $allValid = false;
            }
            
            // Validate total amount (required)
            $totalAmount = \App\Helpers\CurrencyHelper::sanitize($bill['total_amount']) ?? 0;
            if ($totalAmount <= 0) {
                $errors['total_amount'] = 'Total tagihan wajib diisi dan harus lebih dari 0';
                $allValid = false;
            }
            
            // Validate date
            if (empty($bill['date'])) {
                $errors['date'] = 'Tanggal wajib diisi';
                $allValid = false;
            }
            
            // Validate images if uploaded
            if (isset($bill['bill_image']) && $bill['bill_image']) {
                $ext = strtolower($bill['bill_image']->getClientOriginalExtension());
                if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif'])) {
                    $errors['bill_image'] = 'File harus berupa gambar (JPEG, JPG, PNG, GIF)';
                    $allValid = false;
                }
                if ($bill['bill_image']->getSize() > 5120 * 1024) {
                    $errors['bill_image'] = 'Ukuran gambar maksimal 5MB';
                    $allValid = false;
                }
            }
            
            if (isset($bill['payment_proof_image']) && $bill['payment_proof_image']) {
                $ext = strtolower($bill['payment_proof_image']->getClientOriginalExtension());
                if (!in_array($ext, ['jpeg', 'jpg', 'png', 'gif'])) {
                    $errors['payment_proof_image'] = 'File harus berupa gambar (JPEG, JPG, PNG, GIF)';
                    $allValid = false;
                }
                if ($bill['payment_proof_image']->getSize() > 5120 * 1024) {
                    $errors['payment_proof_image'] = 'Ukuran gambar maksimal 5MB';
                    $allValid = false;
                }
            }
            
            if (!empty($errors)) {
                $this->validation_errors[$index] = $errors;
            }
        }

        if (!$allValid) {
            session()->flash('error', 'Terdapat kesalahan pada beberapa tagihan. Silakan periksa dan perbaiki.');
            return;
        }

        // All valid, create bills in transaction
        try {
            DB::beginTransaction();
            
            $createdCount = 0;
            
            foreach ($this->bills as $index => $bill) {
                // Create branch if new
                $branchId = $bill['branch_id'];
                if ($bill['show_new_branch'] && $bill['new_branch_name']) {
                    $existingBranch = Branch::where('name', $bill['new_branch_name'])->first();
                    if ($existingBranch) {
                        $branchId = $existingBranch->id;
                    } else {
                        $branch = Branch::create(['name' => $bill['new_branch_name']]);
                        $branchId = $branch->id;
                    }
                }
                
                if (empty($branchId)) {
                    throw new \Exception("Cabang harus dipilih atau dibuat untuk tagihan #" . ($index + 1));
                }
                
                // Store images
                $billImagePath = null;
                if (isset($bill['bill_image']) && $bill['bill_image']) {
                    $billImagePath = $bill['bill_image']->store('bills', 'public');
                    if (!$billImagePath) {
                        throw new \Exception('Gagal menyimpan gambar bukti tagihan untuk tagihan #' . ($index + 1));
                    }
                }
                
                $paymentProofPath = null;
                if (isset($bill['payment_proof_image']) && $bill['payment_proof_image']) {
                    $paymentProofPath = $bill['payment_proof_image']->store('payments', 'public');
                    if (!$paymentProofPath) {
                        throw new \Exception('Gagal menyimpan gambar bukti pembayaran untuk tagihan #' . ($index + 1));
                    }
                }
                
                // Sanitize amounts
                $totalAmount = \App\Helpers\CurrencyHelper::sanitize($bill['total_amount']) ?? 0;
                $paymentAmount = \App\Helpers\CurrencyHelper::sanitize($bill['payment_amount']);
                
                if ($totalAmount <= 0) {
                    throw new \Exception('Total tagihan wajib diisi dan harus lebih dari 0 untuk tagihan #' . ($index + 1));
                }
                
                if ($paymentAmount <= 0) {
                    throw new \Exception('Jumlah pembayaran harus lebih dari 0 untuk tagihan #' . ($index + 1));
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
                
                // Create bill
                $createdBill = Bill::create([
                    'branch_id' => $branchId,
                    'user_id' => auth()->id(),
                    'bill_image_path' => $billImagePath ? 'public/' . $billImagePath : null,
                    'total_amount' => $totalAmount,
                    'payment_proof_image_path' => $paymentProofPath ? 'public/' . $paymentProofPath : null,
                    'payment_amount' => $paymentAmount,
                    'status' => $status,
                    'date' => $bill['date'],
                ]);
                
                if (!$createdBill) {
                    throw new \Exception('Gagal menyimpan bill ke database untuk tagihan #' . ($index + 1));
                }
                
                $createdCount++;
            }
            
            DB::commit();
            
            $message = $createdCount === 1 
                ? 'Bill berhasil dibuat!' 
                : "Berhasil membuat {$createdCount} tagihan!";
            
            session()->flash('success', $message);
            
            return $this->redirect(route('bills.index'), navigate: true);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error saving bills: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'bills_count' => count($this->bills)
            ]);
            session()->flash('error', 'Gagal menyimpan tagihan: ' . $e->getMessage());
        }
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Buat Bill'];
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" 
     x-data="{ 
         scrollToNewBill() {
             setTimeout(() => {
                 const lastBill = document.querySelector('[data-bill-index]');
                 if (lastBill) {
                     lastBill.scrollIntoView({ behavior: 'smooth', block: 'start' });
                 }
             }, 100);
         }
     }"
     @bill-added.window="scrollToNewBill()"
     @bills-populated.window="window.scrollTo({ top: 0, behavior: 'smooth' })"
>
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 mb-2">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">Buat Bill Baru</h1>
                <p class="text-sm sm:text-base text-neutral-600 dark:text-neutral-400">Unggah bukti pembayaran dan opsional bukti tagihan untuk melacak tagihan Anda</p>
            </div>
            <livewire:bills.ai-helper />
        </div>
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

    @if (!empty($validation_errors))
        <div class="mb-4 sm:mb-6 rounded-lg bg-red-50 border border-red-200 p-3 sm:p-4 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200">
            <div class="flex items-start gap-2">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <p class="font-medium mb-2">Terdapat kesalahan pada beberapa tagihan:</p>
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach($validation_errors as $index => $errors)
                            <li>Tagihan #{{ $index + 1 }}: {{ implode(', ', $errors) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6 sm:space-y-8">
        @foreach($bills as $index => $bill)
            <div 
                data-bill-index="{{ $index }}"
                class="rounded-xl border-2 p-4 sm:p-6 shadow-sm {{ $this->getBackgroundClass($index) }} {{ $this->getBorderClass($index) }} {{ isset($validation_errors[$index]) ? 'border-red-500 dark:border-red-500' : '' }}"
            >
                <!-- Bill Header -->
                <div class="flex items-center justify-between mb-4 pb-4 border-b {{ $this->getBorderClass($index) }}">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-bold">
                            {{ $index + 1 }}
                        </span>
                        <h2 class="text-lg sm:text-xl font-bold text-neutral-900 dark:text-neutral-100">
                            Tagihan #{{ $index + 1 }}
                        </h2>
                    </div>
                    @if(count($bills) > 1)
                        <button
                            type="button"
                            wire:click="removeBill({{ $index }})"
                            class="px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex items-center gap-2"
                            onclick="return confirm('Yakin ingin menghapus tagihan #{{ $index + 1 }}?')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Hapus
                        </button>
                    @endif
                </div>

                <!-- Branch Selection and Date -->
                <div class="mb-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                        <!-- Branch Selection / New Branch Input -->
                        <div>
                            @if(!$bill['show_new_branch'])
                                <x-ui.select-searchable 
                                    label="Pilih Cabang" 
                                    name="bills.{{ $index }}.branch_id" 
                                    wire:model="bills.{{ $index }}.branch_id"
                                    required
                                    placeholder="Pilih cabang..."
                                >
                                    <option value="">Pilih cabang...</option>
                                    @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </x-ui.select-searchable>
                                @if(isset($validation_errors[$index]['branch_id']))
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $validation_errors[$index]['branch_id'] }}</p>
                                @endif
                            @else
                                <x-ui.input 
                                    label="Nama Cabang Baru" 
                                    name="bills.{{ $index }}.new_branch_name" 
                                    wire:model="bills.{{ $index }}.new_branch_name"
                                    placeholder="Masukkan nama cabang"
                                    required
                                />
                                @if(isset($validation_errors[$index]['new_branch_name']))
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $validation_errors[$index]['new_branch_name'] }}</p>
                                @endif
                            @endif
                        </div>

                        <!-- Date -->
                        <div>
                            <x-ui.input 
                                label="Tanggal Transaksi" 
                                name="bills.{{ $index }}.date" 
                                type="date"
                                wire:model="bills.{{ $index }}.date"
                                required
                            />
                            @if(isset($validation_errors[$index]['date']))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $validation_errors[$index]['date'] }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input 
                            type="checkbox" 
                            id="new_branch_{{ $index }}"
                            wire:model.live="bills.{{ $index }}.show_new_branch"
                            class="w-4 h-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label for="new_branch_{{ $index }}" class="text-sm font-medium text-neutral-700 dark:text-neutral-300 cursor-pointer">
                            Tambah cabang baru
                        </label>
                    </div>
                </div>

                <!-- Images Upload Section -->
                <div class="grid gap-6 lg:grid-cols-2 mb-6">
                    <!-- Payment Proof -->
                    <div class="rounded-xl border-2 {{ $this->getBorderClass($index) }} p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">Bukti Pembayaran</h3>
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
                                        @this.upload('bills.{{ $index }}.payment_proof_image', e.dataTransfer.files[0], (uploadedFilename) => {
                                            // After upload completes, trigger extraction
                                            @this.call('handlePaymentImageUpdate', {{ $index }});
                                        });
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
                                <div class="relative w-full h-64 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-3">
                                    <div 
                                        x-data="{ show: true }"
                                        x-show="show"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        class="relative w-full h-full overflow-hidden flex items-center justify-center"
                                    >
                                        <x-ui.image-lightbox :src="$bill['payment_proof_preview']" alt="Pratinjau bukti pembayaran">
                                            <img src="{{ $bill['payment_proof_preview'] }}" alt="Pratinjau bukti pembayaran" class="w-full h-full object-contain rounded-lg" />
                                        </x-ui.image-lightbox>
                                        <div class="absolute top-2 right-2 flex gap-2">
                                            <div class="bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                Gambar terunggah
                                            </div>
                                        </div>
                                    </div>
                                    <div wire:loading wire:target="bills.{{ $index }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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
                                    for="payment_proof_image_{{ $index }}"
                                    class="flex items-center justify-center w-full px-4 py-2 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800"
                                >
                                    <svg class="w-5 h-5 mr-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400 font-medium">Upload Gambar Baru</span>
                                    <input 
                                        type="file" 
                                        id="payment_proof_image_{{ $index }}"
                                        wire:model.live="bills.{{ $index }}.payment_proof_image"
                                        wire:loading.attr="disabled"
                                        accept="image/*"
                                        class="hidden"
                                    />
                                </label>
                            @else
                                <label 
                                    for="payment_proof_image_{{ $index }}"
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
                                        id="payment_proof_image_{{ $index }}"
                                        wire:model.live="bills.{{ $index }}.payment_proof_image"
                                        wire:loading.attr="disabled"
                                        accept="image/*"
                                        class="hidden"
                                    />
                                    <div wire:loading wire:target="bills.{{ $index }}.payment_proof_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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
                        
                        @if($bill['payment_proof_preview'] && !$bill['extracting_payment'] && ($bill['payment_amount'] == 0 || empty($bill['payment_amount'])))
                            <div class="mt-4 p-3 sm:p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                                <div class="flex items-start gap-2 text-yellow-800 dark:text-yellow-200">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">Jumlah belum diekstrak</p>
                                        <p class="text-xs">Silakan masukkan jumlah transfer secara manual di bawah, atau pastikan gambar jelas dan mengandung informasi jumlah transfer.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Payment Amount Field - Always Visible -->
                        <div class="mt-4">
                            <x-ui.currency-input 
                                label="Jumlah Pembayaran" 
                                name="bills.{{ $index }}.payment_amount" 
                                :value="$bill['payment_amount']"
                                wireModel="bills.{{ $index }}.payment_amount"
                                placeholder="0"
                                required
                            />
                            @if(isset($validation_errors[$index]['payment_amount']))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $validation_errors[$index]['payment_amount'] }}</p>
                            @endif
                            @if($bill['payment_amount'] > 0 && $bill['payment_proof_preview'])
                                <div class="mt-2 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Jumlah yang diekstrak:</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                        Rp {{ \App\Helpers\CurrencyHelper::format($bill['payment_amount']) }}
                                    </p>
                                </div>
                            @endif
                            @if($bill['payment_amount'] > 0 && $bill['payment_proof_preview'] && !$bill['extracting_payment'])
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
                            
                            @if($bill['extracting_payment'])
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
                                                @if($bill['extraction_step_payment'])
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                                        <span wire:key="step-payment-{{ $index }}-{{ $bill['extraction_step_payment'] }}">{{ $bill['extraction_step_payment'] }}</span>
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
                        </div>
                    </div>

                    <!-- Bill Proof -->
                    <div class="rounded-xl border-2 {{ $this->getBorderClass($index) }} p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">Bukti Tagihan</h3>
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
                                        @this.upload('bills.{{ $index }}.bill_image', e.dataTransfer.files[0], (uploadedFilename) => {
                                            // After upload completes, trigger extraction
                                            @this.call('handleBillImageUpdate', {{ $index }});
                                        });
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
                                <div class="relative w-full h-64 border-2 border-blue-300 bg-blue-50 dark:bg-blue-900/10 dark:border-blue-700 rounded-lg overflow-hidden mb-3">
                                    <div 
                                        x-data="{ show: true }"
                                        x-show="show"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        class="relative w-full h-full overflow-hidden flex items-center justify-center"
                                    >
                                        <x-ui.image-lightbox :src="$bill['bill_image_preview']" alt="Pratinjau bukti tagihan">
                                            <img src="{{ $bill['bill_image_preview'] }}" alt="Pratinjau bukti tagihan" class="w-full h-full object-contain rounded-lg" />
                                        </x-ui.image-lightbox>
                                        <div class="absolute top-2 right-2 flex gap-2">
                                            <div class="bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                Gambar terunggah
                                            </div>
                                        </div>
                                    </div>
                                    <div wire:loading wire:target="bills.{{ $index }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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
                                    for="bill_image_{{ $index }}"
                                    class="flex items-center justify-center w-full px-4 py-2 border-2 border-dashed border-neutral-300 dark:border-neutral-600 rounded-lg cursor-pointer transition-colors hover:border-blue-400 dark:hover:border-blue-500 bg-white dark:bg-neutral-800"
                                >
                                    <svg class="w-5 h-5 mr-2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400 font-medium">Upload Gambar Baru</span>
                                    <input 
                                        type="file" 
                                        id="bill_image_{{ $index }}"
                                        wire:model.live="bills.{{ $index }}.bill_image"
                                        wire:loading.attr="disabled"
                                        accept="image/*"
                                        class="hidden"
                                    />
                                </label>
                            @else
                                <label 
                                    for="bill_image_{{ $index }}"
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
                                        id="bill_image_{{ $index }}"
                                        wire:model.live="bills.{{ $index }}.bill_image"
                                        wire:loading.attr="disabled"
                                        accept="image/*"
                                        class="hidden"
                                    />
                                    <div wire:loading wire:target="bills.{{ $index }}.bill_image" class="absolute inset-0 bg-blue-500/10 rounded-lg flex items-center justify-center">
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
                        
                        @if($bill['bill_image_preview'] && !$bill['extracting_bill'] && ($bill['total_amount'] == 0 || empty($bill['total_amount'])))
                            <div class="mt-4 p-3 sm:p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                                <div class="flex items-start gap-2 text-yellow-800 dark:text-yellow-200">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="font-medium text-sm mb-1">Jumlah belum diekstrak</p>
                                        <p class="text-xs">Silakan masukkan total tagihan secara manual di bawah, atau pastikan gambar jelas dan mengandung informasi total tagihan.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Total Tagihan Field - Always Visible -->
                        <div class="mt-4"
                             x-data="{}"
                             @force-currency-update.window="
                                if ($event.detail && $event.detail.model === 'bills.{{ $index }}.total_amount') {
                                    \$wire.call('updateCurrencyValue', {{ $index }}, 'total_amount', $event.detail.value);
                                    \$wire.set('bills.{{ $index }}.total_amount', $event.detail.value);
                                }
                             ">
                            <x-ui.currency-input 
                                label="Total Tagihan" 
                                name="bills.{{ $index }}.total_amount" 
                                :value="$bill['total_amount']"
                                wireModel="bills.{{ $index }}.total_amount"
                                placeholder="0"
                                required
                            />
                            @if(isset($validation_errors[$index]['total_amount']))
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $validation_errors[$index]['total_amount'] }}</p>
                            @endif
                            @if($bill['total_amount'] > 0 && $bill['bill_image_preview'])
                                <div class="mt-2 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Jumlah yang diekstrak:</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                        Rp {{ \App\Helpers\CurrencyHelper::format($bill['total_amount']) }}
                                    </p>
                                </div>
                            @endif
                            @if($bill['total_amount'] > 0 && $bill['bill_image_preview'] && !$bill['extracting_bill'])
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
                            
                            @if($bill['extracting_bill'])
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
                                                @if($bill['extraction_step_bill'])
                                                    <div class="flex items-center gap-2">
                                                        <span class="inline-block w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                                                        <span wire:key="step-bill-{{ $index }}-{{ $bill['extraction_step_bill'] }}">{{ $bill['extraction_step_bill'] }}</span>
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
                        </div>
                    </div>
                </div>

                <!-- Validation Warning -->
                @if($bill['validation_warning'])
                    <div class="rounded-lg bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800 p-3 sm:p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ $bill['validation_warning'] }}</p>
                                <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-300">Anda masih dapat melanjutkan, tetapi pastikan jumlahnya benar.</p>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        @endforeach

        <!-- Add Bill Button -->
        <div class="flex justify-center pt-4">
            <button
                type="button"
                wire:click="addBill"
                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Tagihan Lagi
            </button>
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
                wire:target="save"
            >
                <span wire:loading.remove wire:target="save">
                    Simpan {{ count($bills) > 1 ? count($bills) . ' Tagihan' : 'Bill' }}
                </span>
                <span wire:loading wire:target="save" class="flex items-center gap-2 justify-center">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Membuat...
                </span>
            </x-ui.button>
        </div>
    </form>
</div>

@script
<script>
(function() {
    'use strict';
    
    // Format currency value helper function
    function formatCurrencyValue(value) {
        if (!value || value === 0) return '';
        const num = parseFloat(value) || 0;
        const parts = num.toFixed(2).split('.');
        const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const decimalPart = parts[1];
        if (decimalPart === '00' || decimalPart === '0') {
            return integerPart;
        }
        return integerPart + ',' + decimalPart;
    }
    
    // Aggressive update function using vanilla JS
    function forceUpdateInput(inputElement, formattedValue, numericValue) {
        if (!inputElement) return false;
        
        let updated = false;
        
        // Method 1: Direct DOM manipulation
        inputElement.value = formattedValue;
        updated = true;
        
        // Method 2: Update Alpine.js x-model binding
        if (inputElement._x_model) {
            try {
                inputElement._x_model.set(formattedValue);
                updated = true;
            } catch(e) {
                console.warn('Alpine model update failed:', e);
            }
        }
        
        // Method 3: Find parent Alpine component and update formattedValue
        let alpineComponent = inputElement.closest('[x-data]');
        if (alpineComponent) {
            // Try multiple ways to access Alpine data
            if (alpineComponent._x_dataStack && alpineComponent._x_dataStack[0]) {
                const alpineData = alpineComponent._x_dataStack[0];
                if (alpineData.formattedValue !== undefined) {
                    alpineData.formattedValue = formattedValue;
                    updated = true;
                }
            }
            
            // Try Alpine.$data if available
            if (window.Alpine && alpineComponent._x_dataStack) {
                try {
                    const data = Alpine.$data(alpineComponent);
                    if (data && data.formattedValue !== undefined) {
                        data.formattedValue = formattedValue;
                        updated = true;
                    }
                } catch(e) {
                    // Ignore
                }
            }
        }
        
        // Method 4: Update input element's Alpine data stack directly
        if (inputElement._x_dataStack && inputElement._x_dataStack[0]) {
            const alpineData = inputElement._x_dataStack[0];
            if (alpineData.formattedValue !== undefined) {
                alpineData.formattedValue = formattedValue;
                updated = true;
            }
        }
        
        // Method 5: Trigger events to make Alpine.js react
        const inputEvent = new Event('input', { bubbles: true, cancelable: true });
        const changeEvent = new Event('change', { bubbles: true, cancelable: true });
        inputElement.dispatchEvent(inputEvent);
        inputElement.dispatchEvent(changeEvent);
        
        // Method 6: Use Object.defineProperty to trigger reactivity (if needed)
        try {
            Object.defineProperty(inputElement, 'value', {
                value: formattedValue,
                writable: true,
                configurable: true
            });
            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
        } catch(e) {
            // Ignore
        }
        
        return updated;
    }
    
    // Main update function - very aggressive
    function updateCurrencyInputVanilla(index, type, amount) {
        const fieldName = type === 'total' ? 'total_amount' : 'payment_amount';
        const inputName = `bills.${index}.${fieldName}`;
        const formatted = formatCurrencyValue(amount);
        
        // Try to find input immediately
        let inputElement = document.querySelector(`input[name="${inputName}"]`);
        
        // If not found, try multiple times
        let attempts = 0;
        const maxAttempts = 30; // More attempts
        
        const tryUpdate = () => {
            attempts++;
            
            if (!inputElement) {
                inputElement = document.querySelector(`input[name="${inputName}"]`);
            }
            
            if (inputElement) {
                // Force update with all methods
                forceUpdateInput(inputElement, formatted, amount);
                
                // Also update Livewire value
                try {
                    if (typeof Livewire !== 'undefined') {
                        const componentId = @js($this->getId());
                        const component = Livewire.find(componentId);
                        if (component) {
                            component.set(inputName, amount);
                        }
                    }
                } catch(e) {
                    console.warn('Livewire update failed:', e);
                }
                
                return true;
            } else if (attempts < maxAttempts) {
                // Retry with exponential backoff
                setTimeout(tryUpdate, Math.min(50 * attempts, 500));
            }
            
            return false;
        };
        
        // Start immediately
        if (!tryUpdate()) {
            // Also try after a short delay
            setTimeout(tryUpdate, 10);
            setTimeout(tryUpdate, 50);
            setTimeout(tryUpdate, 100);
            setTimeout(tryUpdate, 200);
            setTimeout(tryUpdate, 500);
        }
        
        // Multiple aggressive updates
        for (let i = 0; i < 10; i++) {
            setTimeout(() => {
                const el = document.querySelector(`input[name="${inputName}"]`);
                if (el) {
                    forceUpdateInput(el, formatted, amount);
                }
            }, i * 100);
        }
    }
    
    // Global event listeners - works even before Livewire is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for Livewire events
        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:init', () => {
                // Listen for amount-extracted event
                Livewire.on('amount-extracted', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (data && data.index !== undefined && data.amount !== undefined && data.type) {
                        updateCurrencyInputVanilla(data.index, data.type, data.amount);
                    }
                });
                
                // Listen for extraction-completed
                Livewire.on('extraction-completed', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (data && data.index !== undefined && data.type) {
                        setTimeout(() => {
                            try {
                                const fieldName = data.type === 'total' ? 'total_amount' : 'payment_amount';
                                const inputName = `bills.${data.index}.${fieldName}`;
                                const componentId = @js($this->getId());
                                const component = Livewire.find(componentId);
                                if (component) {
                                    const value = component.get(inputName);
                                    if (value && value > 0) {
                                        updateCurrencyInputVanilla(data.index, data.type, value);
                                    }
                                }
                            } catch(e) {
                                console.warn('Error getting value:', e);
                            }
                        }, 100);
                    }
                });
                
                // Listen for update-currency-input
                Livewire.on('update-currency-input', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (data && data.model && data.value !== undefined) {
                        const parts = data.model.split('.');
                        if (parts.length === 3 && parts[0] === 'bills') {
                            const index = parseInt(parts[1]);
                            const type = parts[2] === 'total_amount' ? 'total' : 'payment';
                            updateCurrencyInputVanilla(index, type, data.value);
                        }
                    }
                });
                
                // Listen for force-currency-update
                Livewire.on('force-currency-update', (eventData) => {
                    const data = Array.isArray(eventData) ? eventData[0] : eventData;
                    if (data && data.model && data.value !== undefined) {
                        const parts = data.model.split('.');
                        if (parts.length === 3 && parts[0] === 'bills') {
                            const index = parseInt(parts[1]);
                            const type = parts[2] === 'total_amount' ? 'total' : 'payment';
                            updateCurrencyInputVanilla(index, type, data.value);
                        }
                    }
                });
            });
        }
        
        // Also listen for browser events (fallback)
        window.addEventListener('currency-value-updated', function(e) {
            const data = e.detail || e;
            if (data && data.model && data.value !== undefined) {
                const parts = data.model.split('.');
                if (parts.length === 3 && parts[0] === 'bills') {
                    const index = parseInt(parts[1]);
                    const type = parts[2] === 'total_amount' ? 'total' : 'payment';
                    updateCurrencyInputVanilla(index, type, data.value);
                }
            }
        });
        
        // Aggressive polling - check every 100ms for changes
        const pollInterval = setInterval(() => {
            try {
                if (typeof Livewire !== 'undefined') {
                    const componentId = @js($this->getId());
                    const component = Livewire.find(componentId);
                    if (component) {
                        // Check all bills for updated values
                        const bills = component.get('bills');
                        if (Array.isArray(bills)) {
                            bills.forEach((bill, index) => {
                                // Check total_amount
                                if (bill.total_amount && bill.total_amount > 0) {
                                    const inputName = `bills.${index}.total_amount`;
                                    const inputElement = document.querySelector(`input[name="${inputName}"]`);
                                    if (inputElement && inputElement.value !== formatCurrencyValue(bill.total_amount)) {
                                        forceUpdateInput(inputElement, formatCurrencyValue(bill.total_amount), bill.total_amount);
                                    }
                                }
                                
                                // Check payment_amount
                                if (bill.payment_amount && bill.payment_amount > 0) {
                                    const inputName = `bills.${index}.payment_amount`;
                                    const inputElement = document.querySelector(`input[name="${inputName}"]`);
                                    if (inputElement && inputElement.value !== formatCurrencyValue(bill.payment_amount)) {
                                        forceUpdateInput(inputElement, formatCurrencyValue(bill.payment_amount), bill.payment_amount);
                                    }
                                }
                            });
                        }
                    }
                }
            } catch(e) {
                // Ignore errors
            }
        }, 100);
        
        // Stop polling after 30 seconds (extraction should be done by then)
        setTimeout(() => {
            clearInterval(pollInterval);
        }, 30000);
    });
})();
</script>
@endscript
