<?php

use App\Models\Bill;
use App\Models\Branch;
use App\Services\PdfExportService;
use Livewire\Volt\Component;

new class extends Component {
    public $date_from;
    public $date_to;
    public $branch_id = '';
    public $status = '';
    public $has_bill_image = '';
    public $has_payment_image = '';
    public $bills = [];
    public $selectedBill = null;
    public $currentSortField = 'date';
    public $currentSortDirection = 'desc';
    public $sortStack = []; // Array untuk multiple sorting: [['field' => 'date', 'direction' => 'desc'], ...]

    public function mount()
    {
        // Set Carbon locale to Indonesian
        \Carbon\Carbon::setLocale('id');
        
        // Default to last 30 days to show more bills, but if no bills found, expand to last year
        $this->date_from = now()->subDays(30)->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
        $this->loadBills();
        
        // If no bills found with default range, expand to last year
        if ($this->bills->isEmpty()) {
            $this->date_from = now()->subYear()->format('Y-m-d');
            $this->date_to = now()->format('Y-m-d');
            $this->loadBills();
        }
    }

    public function updatedDateFrom()
    {
        $this->loadBills();
    }

    public function updatedDateTo()
    {
        $this->loadBills();
    }

    public function updatedBranchId()
    {
        $this->loadBills();
    }

    public function updatedStatus()
    {
        $this->loadBills();
    }

    public function updatedHasBillImage()
    {
        $this->loadBills();
    }

    public function updatedHasPaymentImage()
    {
        $this->loadBills();
    }

    public function setDateRange($days)
    {
        if ($days === 'month') {
            $this->date_from = now()->startOfMonth()->format('Y-m-d');
            $this->date_to = now()->format('Y-m-d');
        } elseif ($days === 'year') {
            $this->date_from = now()->startOfYear()->format('Y-m-d');
            $this->date_to = now()->format('Y-m-d');
        } else {
            $this->date_from = now()->subDays($days)->format('Y-m-d');
            $this->date_to = now()->format('Y-m-d');
        }
        $this->loadBills();
    }

    public function loadBills()
    {
        $query = Bill::filtered([
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'branch_id' => $this->branch_id ?: null,
            'status' => $this->status ?: null,
            'has_bill_image' => $this->has_bill_image ?: null,
            'has_payment_image' => $this->has_payment_image ?: null,
        ]);

        // Apply sorting - jika ada sortStack, gunakan itu, jika tidak gunakan sortBy default
        if (!empty($this->sortStack)) {
            $hasBranchSort = false;
            foreach ($this->sortStack as $sort) {
                if ($sort['field'] === 'branch') {
                    $hasBranchSort = true;
                    break;
                }
            }
            
            // Join branches jika ada branch sort
            if ($hasBranchSort) {
                $query->join('branches', 'bills.branch_id', '=', 'branches.id')
                      ->select('bills.*');
            }
            
            foreach ($this->sortStack as $sort) {
                $field = $sort['field'];
                $direction = $sort['direction'];
                
                // Handle special fields
                if ($field === 'branch') {
                    $query->orderBy('branches.name', $direction);
                } else {
                    $query->orderBy('bills.' . $field, $direction);
                }
            }
        } else {
            // Default sort
            $query->orderBy('bills.' . $this->currentSortField, $this->currentSortDirection);
        }

        $this->bills = $query->get();
    }

    public function sortBy($field)
    {
        // Cek apakah field sudah ada di stack
        $existingIndex = null;
        foreach ($this->sortStack as $index => $sort) {
            if ($sort['field'] === $field) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            // Field sudah ada, toggle direction atau remove
            $currentDirection = $this->sortStack[$existingIndex]['direction'];
            
            if ($currentDirection === 'asc') {
                // Change to desc
                $this->sortStack[$existingIndex]['direction'] = 'desc';
            } else {
                // Remove from stack
                unset($this->sortStack[$existingIndex]);
                $this->sortStack = array_values($this->sortStack); // Re-index
            }
        } else {
            // Add new sort to stack
            $this->sortStack[] = [
                'field' => $field,
                'direction' => 'asc'
            ];
        }

        // Update single sort for backward compatibility
        $this->currentSortField = $field;
        if (isset($this->sortStack[0])) {
            $this->currentSortDirection = $this->sortStack[0]['direction'];
        }

        $this->loadBills();
    }

    public function getSortIcon($field)
    {
        foreach ($this->sortStack as $sort) {
            if ($sort['field'] === $field) {
                if ($sort['direction'] === 'asc') {
                    return '↑';
                } else {
                    return '↓';
                }
            }
        }
        return '';
    }

    public function getSortNumber($field)
    {
        foreach ($this->sortStack as $index => $sort) {
            if ($sort['field'] === $field) {
                return count($this->sortStack) > 1 ? ($index + 1) : '';
            }
        }
        return '';
    }

    public function viewBill($billId)
    {
        $this->selectedBill = Bill::with(['branch', 'user'])->find($billId);
        
        // Ensure the bill exists
        if (!$this->selectedBill) {
            session()->flash('error', 'Bill tidak ditemukan');
            return;
        }
    }

    public function deleteBill($billId)
    {
        $bill = Bill::find($billId);
        if ($bill) {
            // Delete images
            if ($bill->bill_image_path) {
                \Illuminate\Support\Facades\Storage::delete($bill->bill_image_path);
            }
            if ($bill->payment_proof_image_path) {
                \Illuminate\Support\Facades\Storage::delete($bill->payment_proof_image_path);
            }
            $bill->delete();
            $this->loadBills();
            session()->flash('success', 'Bill berhasil dihapus!');
        }
    }

    public function exportPdf()
    {
        $service = app(PdfExportService::class);
        $pdf = $service->exportBranchBills(
            $this->branch_id ?: null,
            [
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'status' => $this->status ?: null,
            ]
        );

        $branchName = $this->branch_id 
            ? Branch::find($this->branch_id)->name 
            : 'all-branches';
        $filename = 'bills-' . $branchName . '-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Tagihan'];
    }
}; ?>

<div class="flex flex-col gap-4 sm:gap-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <h1 class="text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">Tagihan</h1>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <livewire:bills.ai-helper />
                <a href="{{ route('bills.create') }}" wire:navigate class="w-full sm:w-auto">
                    <x-ui.button variant="primary" class="w-full sm:w-auto">
                        Buat Bill Baru
                    </x-ui.button>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-md bg-green-50 p-3 sm:p-4 text-green-800 dark:bg-green-900 dark:text-green-200">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-xs sm:text-sm font-medium">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Filters -->
        <x-ui.card>
            <div 
                x-data="{ 
                    showFilters: false,
                    toggleFilters() {
                        this.showFilters = !this.showFilters;
                    }
                }"
                class="space-y-4"
            >
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <h2 class="text-base sm:text-lg font-semibold text-neutral-900 dark:text-neutral-100">Filter Tagihan</h2>
                        <button 
                            type="button"
                            @click="toggleFilters()"
                            class="sm:hidden flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                        >
                            <span x-show="!showFilters">Buka Filter</span>
                            <span x-show="showFilters">Tutup Filter</span>
                            <svg x-show="!showFilters" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                            <svg x-show="showFilters" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <x-ui.button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            wire:click="setDateRange(7)"
                            class="flex-1 sm:flex-none text-xs"
                        >
                            7 Hari
                        </x-ui.button>
                        <x-ui.button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            wire:click="setDateRange(30)"
                            class="flex-1 sm:flex-none text-xs"
                        >
                            30 Hari
                        </x-ui.button>
                        <x-ui.button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            wire:click="setDateRange('month')"
                            class="flex-1 sm:flex-none text-xs"
                        >
                            Bulan Ini
                        </x-ui.button>
                        <x-ui.button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            wire:click="setDateRange('year')"
                            class="flex-1 sm:flex-none text-xs"
                        >
                            Tahun Ini
                        </x-ui.button>
                    </div>
                </div>
                <div 
                    x-show="showFilters || window.innerWidth >= 640"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="space-y-4"
                >
                    <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5">
                        <x-ui.input 
                            label="Dari Tanggal" 
                            name="date_from" 
                            type="date"
                            wire:model.live="date_from"
                        />
                        <x-ui.input 
                            label="Sampai Tanggal" 
                            name="date_to" 
                            type="date"
                            wire:model.live="date_to"
                        />
                        <x-ui.select-searchable 
                            label="Cabang" 
                            name="branch_id" 
                            wire:model.live="branch_id"
                            placeholder="Semua Cabang"
                        >
                            <option value="">Semua Cabang</option>
                            @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </x-ui.select-searchable>
                        <x-ui.select 
                            label="Status" 
                            name="status" 
                            wire:model.live="status"
                        >
                            <option value="">Semua Status</option>
                            <option value="pending">Menunggu</option>
                            <option value="partial">Sebagian</option>
                            <option value="paid">Lunas</option>
                        </x-ui.select>
                        <div class="flex items-end">
                            <x-ui.button 
                                type="button" 
                                variant="primary"
                                wire:click="exportPdf"
                                class="w-full"
                            >
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Ekspor PDF
                            </x-ui.button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 border-t border-neutral-200 dark:border-neutral-700 pt-4">
                        <x-ui.select 
                            label="Bukti Tagihan" 
                            name="has_bill_image" 
                            wire:model.live="has_bill_image"
                        >
                            <option value="">Semua</option>
                            <option value="yes">Sudah Upload</option>
                            <option value="no">Belum Upload</option>
                        </x-ui.select>
                        <x-ui.select 
                            label="Bukti Pembayaran" 
                            name="has_payment_image" 
                            wire:model.live="has_payment_image"
                        >
                            <option value="">Semua</option>
                            <option value="yes">Sudah Upload</option>
                            <option value="no">Belum Upload</option>
                        </x-ui.select>
                    </div>
                </div>
            </div>
        </x-ui.card>
        
        @if(count($bills) > 0)
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm text-blue-800 dark:text-blue-200">
                        Menampilkan <strong>{{ count($bills) }}</strong> bill dari tanggal <strong>{{ \Carbon\Carbon::parse($date_from)->locale('id')->translatedFormat('l, d-m-Y') }}</strong> hingga <strong>{{ \Carbon\Carbon::parse($date_to)->locale('id')->translatedFormat('l, d-m-Y') }}</strong>
                    </p>
                    @if(!empty($sortStack))
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-blue-700 dark:text-blue-300 font-medium">Urutkan:</span>
                            @foreach($sortStack as $index => $sort)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/40 rounded text-xs text-blue-800 dark:text-blue-200">
                                    {{ $sort['field'] === 'date' ? 'Tanggal' : ($sort['field'] === 'branch' ? 'Cabang' : ($sort['field'] === 'total_amount' ? 'Tagihan' : ($sort['field'] === 'payment_amount' ? 'Pembayaran' : 'Status'))) }}
                                    {{ $sort['direction'] === 'asc' ? '↑' : '↓' }}
                                    @if(count($sortStack) > 1)
                                        <span class="text-[10px]">{{ $index + 1 }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Bills - Mobile Card View -->
        <div class="block sm:hidden space-y-3">
            @forelse($bills as $bill)
                <x-ui.card class="p-4">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Tanggal</p>
                                <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $bill->date->locale('id')->translatedFormat('l, d-m-Y') }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold 
                                    @if($bill->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @elseif($bill->status === 'partial') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                    @endif">
                                    {{ strtoupper($bill->status) }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <!-- Indikator Gambar Tagihan -->
                                    <div class="flex items-center gap-1" title="Bukti Tagihan: {{ $bill->bill_image_path ? 'Sudah Upload' : 'Belum Upload' }}">
                                        @if($bill->bill_image_path)
                                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                            <span class="text-[10px] text-green-700 dark:text-green-400 font-medium">Tagihan</span>
                                        @else
                                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                            <span class="text-[10px] text-red-700 dark:text-red-400 font-medium">Tagihan</span>
                                        @endif
                                    </div>
                                    <!-- Indikator Gambar Pembayaran -->
                                    <div class="flex items-center gap-1" title="Bukti Pembayaran: {{ $bill->payment_proof_image_path ? 'Sudah Upload' : 'Belum Upload' }}">
                                        @if($bill->payment_proof_image_path)
                                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                            <span class="text-[10px] text-green-700 dark:text-green-400 font-medium">Bayar</span>
                                        @else
                                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                            <span class="text-[10px] text-red-700 dark:text-red-400 font-medium">Bayar</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                            <div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Cabang</p>
                                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $bill->branch->name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Tagihan</p>
                                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Pembayaran</p>
                                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Rp {{ number_format($bill->payment_amount, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Sisa</p>
                                <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Rp {{ number_format($bill->outstanding_amount, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-2 border-t border-neutral-200 dark:border-neutral-700">
                            <x-ui.button 
                                type="button" 
                                variant="outline" 
                                size="sm"
                                wire:click="viewBill({{ $bill->id }})"
                                wire:loading.attr="disabled"
                                wire:target="viewBill({{ $bill->id }})"
                                class="flex-1"
                            >
                                <span wire:loading.remove wire:target="viewBill({{ $bill->id }})">Lihat</span>
                                <span wire:loading wire:target="viewBill({{ $bill->id }})" class="flex items-center justify-center gap-1">
                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </x-ui.button>
                            <a href="{{ route('bills.edit', $bill->id) }}" wire:navigate class="flex-1">
                                <x-ui.button type="button" variant="outline" size="sm" class="w-full">
                                    Edit
                                </x-ui.button>
                            </a>
                            <x-ui.button 
                                type="button" 
                                variant="danger" 
                                size="sm"
                                wire:click="deleteBill({{ $bill->id }})"
                                wire:confirm="Apakah Anda yakin ingin menghapus bill ini?"
                                wire:loading.attr="disabled"
                                wire:target="deleteBill({{ $bill->id }})"
                                class="flex-1"
                            >
                                <span wire:loading.remove wire:target="deleteBill({{ $bill->id }})">Hapus</span>
                                <span wire:loading wire:target="deleteBill({{ $bill->id }})" class="flex items-center justify-center gap-1">
                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card class="p-8 text-center">
                    <div class="flex flex-col items-center gap-2">
                        <svg class="w-12 h-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-sm font-medium text-neutral-500">Tidak ada bill ditemukan</p>
                        <p class="text-xs text-neutral-400">Coba sesuaikan rentang tanggal atau filter di atas</p>
                        <a href="{{ route('bills.create') }}" wire:navigate class="mt-2">
                            <x-ui.button variant="primary" size="sm">
                                Buat Bill Baru
                            </x-ui.button>
                        </a>
                    </div>
                </x-ui.card>
            @endforelse
        </div>

        <!-- Bills Table - Desktop View -->
        <x-ui.card class="hidden sm:block">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold
                                    @if($this->getSortIcon('date')) 
                                        bg-blue-100 dark:bg-blue-900/40 border-l-3 border-blue-600 dark:border-blue-400 shadow-sm
                                    @endif
                                ">
                                    <button 
                                        type="button"
                                        wire:click="sortBy('date')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left flex items-center gap-2 group transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-0 py-0 bg-transparent border-0 cursor-pointer
                                            @if($this->getSortIcon('date')) 
                                                text-blue-800 dark:text-blue-200 font-semibold
                                            @else 
                                                text-neutral-700 dark:text-neutral-300 hover:text-blue-700 dark:hover:text-blue-300 font-semibold
                                            @endif
                                        "
                                        title="Klik untuk mengurutkan @if($this->getSortIcon('date')) ({{ $this->getSortIcon('date') === '↑' ? 'Naik' : 'Turun' }}) @endif"
                                    >
                                        <span>Tanggal</span>
                                        @if($this->getSortIcon('date'))
                                            <div class="flex items-center gap-1.5">
                                                <div class="flex items-center justify-center w-5 h-5 rounded bg-blue-600 dark:bg-blue-500">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($this->getSortIcon('date') === '↑')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                        @endif
                                                    </svg>
                                                </div>
                                                @if($this->getSortNumber('date') && count($sortStack) > 1)
                                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 rounded-full border border-blue-300 dark:border-blue-600">
                                                        {{ $this->getSortNumber('date') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                                <span class="text-[10px] text-neutral-500 dark:text-neutral-400">Sort</span>
                                            </div>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold
                                    @if($this->getSortIcon('branch')) 
                                        bg-blue-100 dark:bg-blue-900/40 border-l-3 border-blue-600 dark:border-blue-400 shadow-sm
                                    @endif
                                ">
                                    <button 
                                        type="button"
                                        wire:click="sortBy('branch')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left flex items-center gap-2 group transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-0 py-0 bg-transparent border-0 cursor-pointer
                                            @if($this->getSortIcon('branch')) 
                                                text-blue-800 dark:text-blue-200 font-semibold
                                            @else 
                                                text-neutral-700 dark:text-neutral-300 hover:text-blue-700 dark:hover:text-blue-300 font-semibold
                                            @endif
                                        "
                                        title="Klik untuk mengurutkan @if($this->getSortIcon('branch')) ({{ $this->getSortIcon('branch') === '↑' ? 'Naik' : 'Turun' }}) @endif"
                                    >
                                        <span>Cabang</span>
                                        @if($this->getSortIcon('branch'))
                                            <div class="flex items-center gap-1.5">
                                                <div class="flex items-center justify-center w-5 h-5 rounded bg-blue-600 dark:bg-blue-500">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($this->getSortIcon('branch') === '↑')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                        @endif
                                                    </svg>
                                                </div>
                                                @if($this->getSortNumber('branch') && count($sortStack) > 1)
                                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 rounded-full border border-blue-300 dark:border-blue-600">
                                                        {{ $this->getSortNumber('branch') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                                <span class="text-[10px] text-neutral-500 dark:text-neutral-400">Sort</span>
                                            </div>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold
                                    @if($this->getSortIcon('total_amount')) 
                                        bg-blue-100 dark:bg-blue-900/40 border-l-3 border-blue-600 dark:border-blue-400 shadow-sm
                                    @endif
                                ">
                                    <button 
                                        type="button"
                                        wire:click="sortBy('total_amount')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left flex items-center gap-2 group transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-0 py-0 bg-transparent border-0 cursor-pointer
                                            @if($this->getSortIcon('total_amount')) 
                                                text-blue-800 dark:text-blue-200 font-semibold
                                            @else 
                                                text-neutral-700 dark:text-neutral-300 hover:text-blue-700 dark:hover:text-blue-300 font-semibold
                                            @endif
                                        "
                                        title="Klik untuk mengurutkan @if($this->getSortIcon('total_amount')) ({{ $this->getSortIcon('total_amount') === '↑' ? 'Naik' : 'Turun' }}) @endif"
                                    >
                                        <span>Tagihan</span>
                                        @if($this->getSortIcon('total_amount'))
                                            <div class="flex items-center gap-1.5">
                                                <div class="flex items-center justify-center w-5 h-5 rounded bg-blue-600 dark:bg-blue-500">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($this->getSortIcon('total_amount') === '↑')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                        @endif
                                                    </svg>
                                                </div>
                                                @if($this->getSortNumber('total_amount') && count($sortStack) > 1)
                                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 rounded-full border border-blue-300 dark:border-blue-600">
                                                        {{ $this->getSortNumber('total_amount') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                                <span class="text-[10px] text-neutral-500 dark:text-neutral-400">Sort</span>
                                            </div>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold
                                    @if($this->getSortIcon('payment_amount')) 
                                        bg-blue-100 dark:bg-blue-900/40 border-l-3 border-blue-600 dark:border-blue-400 shadow-sm
                                    @endif
                                ">
                                    <button 
                                        type="button"
                                        wire:click="sortBy('payment_amount')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left flex items-center gap-2 group transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-0 py-0 bg-transparent border-0 cursor-pointer
                                            @if($this->getSortIcon('payment_amount')) 
                                                text-blue-800 dark:text-blue-200 font-semibold
                                            @else 
                                                text-neutral-700 dark:text-neutral-300 hover:text-blue-700 dark:hover:text-blue-300 font-semibold
                                            @endif
                                        "
                                        title="Klik untuk mengurutkan @if($this->getSortIcon('payment_amount')) ({{ $this->getSortIcon('payment_amount') === '↑' ? 'Naik' : 'Turun' }}) @endif"
                                    >
                                        <span>Pembayaran</span>
                                        @if($this->getSortIcon('payment_amount'))
                                            <div class="flex items-center gap-1.5">
                                                <div class="flex items-center justify-center w-5 h-5 rounded bg-blue-600 dark:bg-blue-500">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($this->getSortIcon('payment_amount') === '↑')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                        @endif
                                                    </svg>
                                                </div>
                                                @if($this->getSortNumber('payment_amount') && count($sortStack) > 1)
                                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 rounded-full border border-blue-300 dark:border-blue-600">
                                                        {{ $this->getSortNumber('payment_amount') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                                <span class="text-[10px] text-neutral-500 dark:text-neutral-400">Sort</span>
                                            </div>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold
                                    @if($this->getSortIcon('status')) 
                                        bg-blue-100 dark:bg-blue-900/40 border-l-3 border-blue-600 dark:border-blue-400 shadow-sm
                                    @endif
                                ">
                                    <button 
                                        type="button"
                                        wire:click="sortBy('status')"
                                        wire:loading.attr="disabled"
                                        class="w-full text-left flex items-center gap-2 group transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-0 py-0 bg-transparent border-0 cursor-pointer
                                            @if($this->getSortIcon('status')) 
                                                text-blue-800 dark:text-blue-200 font-semibold
                                            @else 
                                                text-neutral-700 dark:text-neutral-300 hover:text-blue-700 dark:hover:text-blue-300 font-semibold
                                            @endif
                                        "
                                        title="Klik untuk mengurutkan @if($this->getSortIcon('status')) ({{ $this->getSortIcon('status') === '↑' ? 'Naik' : 'Turun' }}) @endif"
                                    >
                                        <span>Status</span>
                                        @if($this->getSortIcon('status'))
                                            <div class="flex items-center gap-1.5">
                                                <div class="flex items-center justify-center w-5 h-5 rounded bg-blue-600 dark:bg-blue-500">
                                                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($this->getSortIcon('status') === '↑')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                        @endif
                                                    </svg>
                                                </div>
                                                @if($this->getSortNumber('status') && count($sortStack) > 1)
                                                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-200 dark:bg-blue-800 rounded-full border border-blue-300 dark:border-blue-600">
                                                        {{ $this->getSortNumber('status') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                                </svg>
                                                <span class="text-[10px] text-neutral-500 dark:text-neutral-400">Sort</span>
                                            </div>
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                    <div class="flex flex-col gap-1">
                                        <span>Gambar</span>
                                        <span class="text-[10px] font-normal text-neutral-500 dark:text-neutral-400">Tagihan | Bayar</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-700 dark:text-neutral-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700 bg-white dark:bg-neutral-800">
                            @forelse($bills as $bill)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">
                                        {{ $bill->date->locale('id')->translatedFormat('l, d-m-Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">{{ $bill->branch->name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-neutral-900 dark:text-neutral-100">Rp {{ number_format($bill->payment_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold 
                                            @if($bill->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($bill->status === 'partial') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                            @endif">
                                            {{ strtoupper($bill->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <!-- Indikator Gambar Tagihan -->
                                            <div class="flex items-center gap-1.5" title="Bukti Tagihan: {{ $bill->bill_image_path ? 'Sudah Upload' : 'Belum Upload' }}">
                                                @if($bill->bill_image_path)
                                                    <div class="w-3 h-3 rounded-full bg-green-500 border border-green-600 dark:border-green-400"></div>
                                                    <span class="text-xs text-green-700 dark:text-green-400 font-medium">Tagihan</span>
                                                @else
                                                    <div class="w-3 h-3 rounded-full bg-red-500 border border-red-600 dark:border-red-400"></div>
                                                    <span class="text-xs text-red-700 dark:text-red-400 font-medium">Tagihan</span>
                                                @endif
                                            </div>
                                            <!-- Indikator Gambar Pembayaran -->
                                            <div class="flex items-center gap-1.5" title="Bukti Pembayaran: {{ $bill->payment_proof_image_path ? 'Sudah Upload' : 'Belum Upload' }}">
                                                @if($bill->payment_proof_image_path)
                                                    <div class="w-3 h-3 rounded-full bg-green-500 border border-green-600 dark:border-green-400"></div>
                                                    <span class="text-xs text-green-700 dark:text-green-400 font-medium">Bayar</span>
                                                @else
                                                    <div class="w-3 h-3 rounded-full bg-red-500 border border-red-600 dark:border-red-400"></div>
                                                    <span class="text-xs text-red-700 dark:text-red-400 font-medium">Bayar</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex gap-2">
                                            <x-ui.button 
                                                type="button" 
                                                variant="outline" 
                                                size="sm"
                                                wire:click="viewBill({{ $bill->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="viewBill({{ $bill->id }})"
                                            >
                                                <span wire:loading.remove wire:target="viewBill({{ $bill->id }})">Lihat</span>
                                                <span wire:loading wire:target="viewBill({{ $bill->id }})" class="flex items-center gap-1">
                                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                            </x-ui.button>
                                            <a href="{{ route('bills.edit', $bill->id) }}" wire:navigate>
                                                <x-ui.button type="button" variant="outline" size="sm">
                                                    Edit
                                                </x-ui.button>
                                            </a>
                                            <x-ui.button 
                                                type="button" 
                                                variant="danger" 
                                                size="sm"
                                                wire:click="deleteBill({{ $bill->id }})"
                                                wire:confirm="Apakah Anda yakin ingin menghapus bill ini?"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteBill({{ $bill->id }})"
                                            >
                                                <span wire:loading.remove wire:target="deleteBill({{ $bill->id }})">Hapus</span>
                                                <span wire:loading wire:target="deleteBill({{ $bill->id }})" class="flex items-center gap-1">
                                                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-12 h-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-sm font-medium text-neutral-500">Tidak ada bill ditemukan</p>
                                            <p class="text-xs text-neutral-400">Coba sesuaikan rentang tanggal atau filter di atas</p>
                                            <a href="{{ route('bills.create') }}" wire:navigate class="mt-2">
                                                <x-ui.button variant="primary" size="sm">
                                                    Buat Bill Baru
                                                </x-ui.button>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-ui.card>

        <!-- View Modal -->
        @if($selectedBill)
            <div 
                wire:key="modal-{{ $selectedBill->id }}"
                wire:ignore.self
                x-data="{
                    show: true,
                    init() {
                        // Force show modal
                        this.show = true;
                    },
                    close() {
                        this.show = false;
                        setTimeout(() => {
                            @this.set('selectedBill', null);
                        }, 200);
                    }
                }"
                x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-2 sm:p-4"
                @keydown.escape.window="if (show) close()"
                @click.self="close()"
            >
                <x-ui.modal 
                    :show="true" 
                    size="xl"
                    :title="'Detail Bill - ' . $selectedBill->date->locale('id')->translatedFormat('l, d-m-Y')"
                    :closeable="true"
                    :noWrapper="true"
                >
                <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2">
                    <div>
                        <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Cabang</div>
                        <div class="text-base sm:text-lg font-semibold">{{ $selectedBill->branch->name }}</div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Status</div>
                        <div class="text-base sm:text-lg font-semibold">{{ strtoupper($selectedBill->status) }}</div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Total Tagihan</div>
                        <div class="text-base sm:text-lg font-semibold">Rp {{ number_format($selectedBill->total_amount, 0, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Jumlah Pembayaran</div>
                        <div class="text-base sm:text-lg font-semibold">Rp {{ number_format($selectedBill->payment_amount, 0, ',', '.') }}</div>
                    </div>
                </div>
                
                <!-- Side by Side Images - Smaller on mobile -->
                <div class="mt-4 sm:mt-6 grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2">
                    @if($selectedBill->bill_image_path)
                        <div>
                            <div class="mb-2 text-xs sm:text-sm font-semibold">Bukti Tagihan</div>
                            <x-ui.image-lightbox :src="Storage::url($selectedBill->bill_image_path)" alt="Bukti Tagihan">
                                <img 
                                    src="{{ Storage::url($selectedBill->bill_image_path) }}" 
                                    alt="Bukti Tagihan" 
                                    class="w-full max-h-48 sm:max-h-64 object-contain rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer hover:opacity-90 transition-opacity"
                                />
                            </x-ui.image-lightbox>
                        </div>
                    @endif
                    @if($selectedBill->payment_proof_image_path)
                        <div>
                            <div class="mb-2 text-xs sm:text-sm font-semibold">Bukti Pembayaran</div>
                            <x-ui.image-lightbox :src="Storage::url($selectedBill->payment_proof_image_path)" alt="Bukti Pembayaran">
                                <img 
                                    src="{{ Storage::url($selectedBill->payment_proof_image_path) }}" 
                                    alt="Bukti Pembayaran" 
                                    class="w-full max-h-48 sm:max-h-64 object-contain rounded-md border border-neutral-300 dark:border-neutral-600 cursor-pointer hover:opacity-90 transition-opacity"
                                />
                            </x-ui.image-lightbox>
                        </div>
                    @endif
                </div>
            </x-ui.modal>
        </div>
        @endif
</div>

