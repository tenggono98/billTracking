<?php

use App\Models\Branch;
use Livewire\Volt\Component;

new class extends Component {
    public $branches = [];
    public $editingBranch = null;
    public $branchName = '';
    public $showForm = false;

    public function mount()
    {
        $this->loadBranches();
    }

    public function loadBranches()
    {
        $this->branches = Branch::all();
    }

    public function create()
    {
        $this->reset(['editingBranch', 'branchName', 'showForm']);
        $this->showForm = true;
    }

    public function edit($branchId)
    {
        $branch = Branch::find($branchId);
        if ($branch) {
            $this->editingBranch = $branch;
            $this->branchName = $branch->name;
            $this->showForm = true;
        }
    }

    public function save()
    {
        $this->validate([
            'branchName' => 'required|string|max:255|unique:branches,name' . ($this->editingBranch ? ',' . $this->editingBranch->id : ''),
        ]);

        if ($this->editingBranch) {
            $this->editingBranch->update(['name' => $this->branchName]);
            session()->flash('success', 'Cabang berhasil diperbarui!');
        } else {
            Branch::create(['name' => $this->branchName]);
            session()->flash('success', 'Cabang berhasil dibuat!');
        }

        $this->reset(['editingBranch', 'branchName', 'showForm']);
        $this->loadBranches();
    }

    public function delete($branchId)
    {
        $branch = Branch::find($branchId);
        if ($branch && $branch->bills()->count() === 0) {
            $branch->delete();
            $this->loadBranches();
            session()->flash('success', 'Cabang berhasil dihapus!');
        } else {
            session()->flash('error', 'Tidak dapat menghapus cabang yang memiliki bill!');
        }
    }

    public function cancel()
    {
        $this->reset(['editingBranch', 'branchName', 'showForm']);
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Manajemen Cabang'];
    }
}; ?>

<div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">Manajemen Cabang</h1>
            <x-ui.button variant="primary" wire:click="create">
                Tambah Cabang Baru
            </x-ui.button>
        </div>

        @if (session('success'))
            <div class="rounded-md bg-green-50 p-4 text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        @if($showForm)
            <x-ui.card title="{{ $editingBranch ? 'Edit Cabang' : 'Buat Cabang' }}">
                <form wire:submit="save" class="flex flex-col gap-4">
                    <x-ui.input 
                        label="Nama Cabang" 
                        name="branchName" 
                        wire:model="branchName"
                        required
                    />
                    <div class="flex justify-end gap-4">
                        <x-ui.button type="button" variant="outline" wire:click="cancel">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary">
                            {{ $editingBranch ? 'Perbarui' : 'Buat' }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card title="Cabang">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 dark:border-neutral-700">
                            <th class="px-4 py-2 text-left text-sm font-semibold">Nama</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Jumlah Bill</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr class="border-b border-neutral-200 dark:border-neutral-700">
                                <td class="px-4 py-2 text-sm">{{ $branch->name }}</td>
                                <td class="px-4 py-2 text-sm">{{ $branch->bills()->count() }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <div class="flex gap-2">
                                        <x-ui.button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm"
                                            wire:click="edit({{ $branch->id }})"
                                        >
                                            Edit
                                        </x-ui.button>
                                        <x-ui.button 
                                            type="button" 
                                            variant="danger" 
                                            size="sm"
                                            wire:click="delete({{ $branch->id }})"
                                            wire:confirm="Apakah Anda yakin ingin menghapus cabang ini?"
                                        >
                                            Hapus
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-neutral-500">
                                    Tidak ada cabang ditemukan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
</div>

