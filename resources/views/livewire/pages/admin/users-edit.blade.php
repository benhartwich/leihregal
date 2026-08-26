<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public User $user;

    public string $name     = '';
    public string $email    = '';
    public string $role     = '';
    public bool   $active   = true;
    public string $password = '';

    public function mount(User $user): void
    {
        $this->user   = $user;
        $this->name   = $user->name;
        $this->email  = $user->email;
        $this->role   = $user->role->value;
        $this->active = $user->active;
    }

    public function save(): void
    {
        $rules = [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
            'role'   => ['required', 'in:betreuer,kurator,admin'],
            'active' => ['boolean'],
        ];

        // Password is optional on edit; only validate if provided
        if ($this->password !== '') {
            $rules['password'] = ['string', 'min:8'];
        }

        $validated = $this->validate($rules, [
            'name.required'  => 'Bitte einen Namen eingeben.',
            'email.required' => 'Bitte eine E-Mail-Adresse eingeben.',
            'email.unique'   => 'Diese E-Mail-Adresse ist bereits vergeben.',
            'password.min'   => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        // Prevent removing own admin role or deactivating own account
        if ($this->user->id === auth()->id()) {
            $validated['role']   = 'admin';
            $validated['active'] = true;
        }

        $updateData = [
            'name'   => $validated['name'],
            'email'  => $validated['email'],
            'role'   => UserRole::from($validated['role']),
            'active' => $validated['active'],
        ];

        if ($this->password !== '') {
            $updateData['password'] = $this->password;
        }

        $this->user->update($updateData);

        session()->flash('success', "Konto von {$this->user->name} wurde gespeichert.");
        $this->redirect(route('admin.users'), navigate: true);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-semibold text-gray-900">Person bearbeiten</h1>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <form wire:submit="save" class="space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Vollständiger Name <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="name" type="text" required
                           class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        E-Mail-Adresse <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="email" type="email" required
                           class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rolle</label>
                    <select wire:model="role"
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}
                            class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3 disabled:bg-gray-50">
                        @foreach(\App\Enums\UserRole::cases() as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                    @if($user->id === auth()->id())
                        <p class="mt-1 text-xs text-gray-400">Die eigene Rolle kann nicht geändert werden.</p>
                    @endif
                </div>

                @if($user->id !== auth()->id())
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input wire:model="active" type="checkbox" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer
                                        peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Konto aktiv</span>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Neues Passwort
                        <span class="text-gray-400 font-normal">(leer lassen = unverändert)</span>
                    </label>
                    <input wire:model="password" type="password" autocomplete="new-password"
                           placeholder="Mindestens 8 Zeichen"
                           class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 transition-colors w-full sm:w-auto">
                        Speichern
                    </button>
                    <a href="{{ route('admin.users') }}" wire:navigate
                       class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                </div>

            </form>
        </div>

</div>
