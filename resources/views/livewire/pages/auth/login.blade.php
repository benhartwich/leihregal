<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                E-Mail-Adresse
            </label>
            <input wire:model="form.email" id="email" type="email" name="email"
                   required autofocus autocomplete="username"
                   placeholder="ihre@email.at"
                   class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                Passwort
            </label>
            <input wire:model="form.password" id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center gap-2 cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox"
                       class="rounded-sm border-gray-300 text-blue-600 shadow-xs focus:ring-blue-500">
                <span class="text-sm text-gray-600">Angemeldet bleiben</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate
                   class="text-sm text-blue-600 hover:text-blue-800">
                    Passwort vergessen?
                </a>
            @endif
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-3 px-4 rounded-xl font-semibold text-base hover:bg-blue-700 transition-colors">
            Anmelden
        </button>

    </form>
</div>
