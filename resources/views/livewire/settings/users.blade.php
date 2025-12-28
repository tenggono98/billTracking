<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component {
    public $users = [];
    public $editingUser = null;
    public $name = '';
    public $email = '';
    public $phone_number = '';
    public $pin = '';
    public $password = '';
    public $showForm = false;

    public function mount()
    {
        $this->loadUsers();
    }

    public function loadUsers()
    {
        $this->users = User::all();
    }

    public function create()
    {
        $this->reset(['editingUser', 'name', 'email', 'phone_number', 'pin', 'password', 'showForm']);
        $this->showForm = true;
    }

    public function edit($userId)
    {
        $user = User::find($userId);
        if ($user) {
            $this->editingUser = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone_number = $user->phone_number ?? '';
            $this->pin = '';
            $this->password = '';
            $this->showForm = true;
        }
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email' . ($this->editingUser ? ',' . $this->editingUser->id : ''),
            'phone_number' => 'nullable|string|max:12|unique:users,phone_number' . ($this->editingUser ? ',' . $this->editingUser->id : ''),
        ];

        if (!$this->editingUser) {
            $rules['password'] = 'required|string|min:8';
            $rules['pin'] = 'required|string|min:4|max:6';
        } else {
            if ($this->password) {
                $rules['password'] = 'required|string|min:8';
            }
            if ($this->pin) {
                $rules['pin'] = 'required|string|min:4|max:6';
            }
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->phone_number) {
            $data['phone_number'] = $this->phone_number;
        }

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->pin) {
            // PIN will be automatically hashed by the model's cast 'hashed'
            $data['pin'] = $this->pin;
        }

        if ($this->editingUser) {
            $this->editingUser->update($data);
            session()->flash('success', 'Pengguna berhasil diperbarui!');
        } else {
            User::create($data);
            session()->flash('success', 'Pengguna berhasil dibuat!');
        }

        $this->reset(['editingUser', 'name', 'email', 'phone_number', 'pin', 'password', 'showForm']);
        $this->loadUsers();
    }

    public function delete($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) {
            $user->delete();
            $this->loadUsers();
            session()->flash('success', 'Pengguna berhasil dihapus!');
        } else {
            session()->flash('error', 'Tidak dapat menghapus akun Anda sendiri!');
        }
    }

    public function cancel()
    {
        $this->reset(['editingUser', 'name', 'email', 'phone_number', 'pin', 'password', 'showForm']);
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Manajemen Pengguna'];
    }
}; ?>

<div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">Manajemen Pengguna</h1>
            <x-ui.button variant="primary" wire:click="create">
                Tambah Pengguna Baru
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
            <x-ui.card title="{{ $editingUser ? 'Edit Pengguna' : 'Buat Pengguna' }}">
                <form wire:submit="save" class="flex flex-col gap-4">
                    <x-ui.input 
                        label="Nama" 
                        name="name" 
                        wire:model="name"
                        required
                    />
                    <x-ui.input 
                        label="Email" 
                        name="email" 
                        type="email"
                        wire:model="email"
                        required
                    />
                    <x-ui.input 
                        label="Nomor HP" 
                        name="phone_number" 
                        type="text"
                        wire:model="phone_number"
                        placeholder="***REMOVED***"
                        maxlength="12"
                    />
                    <x-ui.input 
                        label="PIN {{ $editingUser ? '(kosongkan untuk mempertahankan yang sekarang)' : '' }}" 
                        name="pin" 
                        type="password"
                        wire:model="pin"
                        placeholder="Masukkan PIN (4-6 digit)"
                        maxlength="6"
                        :required="!$editingUser"
                    />
                    <x-ui.input 
                        label="Kata Sandi {{ $editingUser ? '(kosongkan untuk mempertahankan yang sekarang)' : '' }}" 
                        name="password" 
                        type="password"
                        wire:model="password"
                        :required="!$editingUser"
                    />
                    <div class="flex justify-end gap-4">
                        <x-ui.button type="button" variant="outline" wire:click="cancel">
                            Batal
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary">
                            {{ $editingUser ? 'Perbarui' : 'Buat' }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card title="Pengguna">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 dark:border-neutral-700">
                            <th class="px-4 py-2 text-left text-sm font-semibold">Nama</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Email</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Nomor HP</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Jumlah Bill</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr class="border-b border-neutral-200 dark:border-neutral-700">
                                <td class="px-4 py-2 text-sm">{{ $user->name }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->email }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->phone_number ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm">{{ $user->bills()->count() }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <div class="flex gap-2">
                                        <x-ui.button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm"
                                            wire:click="edit({{ $user->id }})"
                                        >
                                            Edit
                                        </x-ui.button>
                                        @if($user->id !== auth()->id())
                                            <x-ui.button 
                                                type="button" 
                                                variant="danger" 
                                                size="sm"
                                                wire:click="delete({{ $user->id }})"
                                                wire:confirm="Apakah Anda yakin ingin menghapus pengguna ini?"
                                            >
                                                Hapus
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-500">
                                    Tidak ada pengguna ditemukan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
</div>

