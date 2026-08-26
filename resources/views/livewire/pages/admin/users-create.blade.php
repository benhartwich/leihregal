<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name     = '';
    public string $email    = '';
    public string $password = '';
    public string $role     = 'betreuer';

    public function save(): void
    {
        $validated = $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'in:betreuer,kurator,admin'],
        ], [
            'name.required'     => 'Bitte einen Namen eingeben.',
            'email.required'    => 'Bitte eine E-Mail-Adresse eingeben.',
            'email.unique'      => 'Diese E-Mail-Adresse ist bereits vergeben.',
            'password.required' => 'Bitte ein Passwort eingeben.',
            'password.min'      => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
            'role'     => UserRole::from($validated['role']),
            'active'   => true,
        ]);

        session()->flash('success', "Konto für {$this->name} wurde angelegt.");
        $this->redirect(route('admin.users'), navigate: true);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-semibold text-gray-900">Neue Person anlegen</h1>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">

        <form wire:submit="save" class="space-y-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Vollständiger Name <span class="text-red-500">*</span>
                </label>
                <input wire:model="name" type="text" required autofocus
                       placeholder="z. B. Maria Muster"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    E-Mail-Adresse <span class="text-red-500">*</span>
                </label>
                <input wire:model="email" type="email" required
                       placeholder="maria.muster@beispiel.at"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Passwort <span class="text-red-500">*</span>
                </label>
                <input wire:model="password" type="password" required autocomplete="new-password"
                       placeholder="Mindestens 8 Zeichen"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">
                    Die Person sollte das Passwort nach dem ersten Login unter „Mein Profil" ändern.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rolle</label>
                <select wire:model="role"
                        class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                    @foreach(\App\Enums\UserRole::cases() as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Betreuer:in – Suchen & Ausleihen |
                    Kurator:in – + Medien verwalten |
                    Administrator:in – alle Rechte
                </p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 transition-colors w-full sm:w-auto">
                    Konto anlegen
                </button>
                <a href="{{ route('admin.users') }}" wire:navigate
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>

        </form>
    </div>
</div>
