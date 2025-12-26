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
    public $bills = [];
    public $summary = [
        'total_bills' => 0,
        'total_payments' => 0,
        'outstanding' => 0,
        'count' => 0,
    ];

    public function mount()
    {
        $this->date_from = now()->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
        $this->loadData();
    }

    public function updatedDateFrom()
    {
        $this->loadData();
    }

    public function updatedDateTo()
    {
        $this->loadData();
    }

    public function updatedBranchId()
    {
        $this->loadData();
    }

    public function updatedStatus()
    {
        $this->loadData();
    }

    public function loadData()
    {
        // Base query disamakan dengan master bills menggunakan scope filtered
        $baseQuery = Bill::filtered([
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'branch_id' => $this->branch_id ?: null,
            'status' => $this->status ?: null,
        ]);

        // Recent transactions list (subset dari base query)
        $this->bills = (clone $baseQuery)
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        // Calculate summary dari query yang sama
        $allBills = (clone $baseQuery)->get();
        $this->summary = [
            'total_bills' => $allBills->sum('total_amount'),
            'total_payments' => $allBills->sum('payment_amount'),
            'outstanding' => $allBills->sum('total_amount') - $allBills->sum('payment_amount'),
            'count' => $allBills->count(),
        ];
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
        return ['title' => 'Dashboard'];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">Dashboard</h1>
    </div>

        <!-- Filters -->
        <x-ui.card>
            <div class="grid gap-4 md:grid-cols-5">
                <x-ui.input 
                    label="Date From" 
                    name="date_from" 
                    type="date"
                    wire:model.live="date_from"
                />
                <x-ui.input 
                    label="Date To" 
                    name="date_to" 
                    type="date"
                    wire:model.live="date_to"
                />
                <x-ui.select-searchable 
                    label="Branch" 
                    name="branch_id" 
                    wire:model.live="branch_id"
                    placeholder="All Branches"
                >
                    <option value="">All Branches</option>
                    @foreach(\App\Models\Branch::orderBy('name', 'asc')->get() as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </x-ui.select-searchable>
                <x-ui.select 
                    label="Status" 
                    name="status" 
                    wire:model.live="status"
                >
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </x-ui.select>
                <div class="flex items-end">
                    <x-ui.button 
                        type="button" 
                        variant="primary"
                        wire:click="exportPdf"
                        wire:loading.attr="disabled"
                        wire:target="exportPdf"
                        class="w-full"
                    >
                        <span wire:loading.remove wire:target="exportPdf">Export PDF</span>
                        <span wire:loading wire:target="exportPdf" class="flex items-center gap-2 justify-center">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Exporting...
                        </span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <!-- Summary Cards -->
        <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.card>
                <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Total Bills</div>
                <div class="mt-2 text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                    Rp {{ number_format($summary['total_bills'], 0, ',', '.') }}
                </div>
            </x-ui.card>
            <x-ui.card>
                <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Total Payments</div>
                <div class="mt-2 text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                    Rp {{ number_format($summary['total_payments'], 0, ',', '.') }}
                </div>
            </x-ui.card>
            <x-ui.card>
                <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Outstanding</div>
                <div class="mt-2 text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                    Rp {{ number_format($summary['outstanding'], 0, ',', '.') }}
                </div>
            </x-ui.card>
            <x-ui.card>
                <div class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">Transaction Count</div>
                <div class="mt-2 text-xl sm:text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ $summary['count'] }}
                </div>
            </x-ui.card>
        </div>

        <!-- Recent Transactions -->
        <x-ui.card title="Recent Transactions">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 dark:border-neutral-700">
                            <th class="px-4 py-2 text-left text-sm font-semibold">Date</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Branch</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Total</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Payment</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bills as $bill)
                            <tr class="border-b border-neutral-200 dark:border-neutral-700">
                                <td class="px-4 py-2 text-sm">{{ $bill->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-sm">{{ $bill->branch->name }}</td>
                                <td class="px-4 py-2 text-sm">Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-sm">Rp {{ number_format($bill->payment_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="rounded px-2 py-1 text-xs font-semibold 
                                        @if($bill->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($bill->status === 'partial') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @endif">
                                        {{ strtoupper($bill->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-500">
                                    No transactions found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
</div>

