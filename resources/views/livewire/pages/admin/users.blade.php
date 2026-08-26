<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string  $search  = '';
    public ?string $toast   = null;   // inline feedback message
    public bool    $toastOk = true;   // true = success, false = error

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === auth()->id()) {
            $this->toast   = 'Sie können Ihr eigenes Konto nicht deaktivieren.';
            $this->toastOk = false;
            return;
        }

        $user->update(['active' => ! $user->active]);

        $status      = $user->active ? 'aktiviert' : 'deaktiviert';
        $this->toast   = "Konto von {$user->name} wurde {$status}.";
        $this->toastOk = true;
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->when($this->search, fn($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                }))
                ->orderBy('name')
                ->paginate(20),
        ];
    }
}; ?>

<div class="space-y-4">

        <h1 class="text-xl font-semibold text-gray-900">Nutzerverwaltung</h1>

        {{-- Inline toast for toggleActive feedback --}}
        @if($toast)
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => { show = false; $wire.set('toast', null) }, 3000)"
                 class="px-4 py-3 rounded-lg flex items-center gap-3 text-sm
                        {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
                {{ $toast }}
            </div>
        @endif

        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search" placeholder="Name oder E-Mail suchen …"
                       class="pl-9 pr-4 py-2.5 rounded-xl border-gray-300 text-sm w-full sm:w-64 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <a href="{{ route('admin.users.create') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neue Person anlegen
            </a>
        </div>

        {{-- Users table / list --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">E-Mail</th>
                            <th class="px-4 py-3">Rolle</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50 {{ !$user->active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(Sie)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium {{ $user->role->badgeClass() }}">
                                        {{ $user->role->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($user->active)
                                        <span class="inline-flex items-center gap-1 text-xs text-green-700">
                                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Aktiv
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>Deaktiviert
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user) }}" wire:navigate
                                           class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            Bearbeiten
                                        </a>
                                        @if($user->id !== auth()->id())
                                            <button wire:click="toggleActive({{ $user->id }})"
                                                    wire:confirm="{{ $user->active ? 'Konto wirklich deaktivieren?' : 'Konto wieder aktivieren?' }}"
                                                    class="text-xs font-medium {{ $user->active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                                {{ $user->active ? 'Deaktivieren' : 'Aktivieren' }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Keine Personen gefunden.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile list --}}
            <div class="sm:hidden divide-y divide-gray-100">
                @forelse($users as $user)
                    <div class="p-4 {{ !$user->active ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(Sie)</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500 truncate">{{ $user->email }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium {{ $user->role->badgeClass() }}">
                                        {{ $user->role->label() }}
                                    </span>
                                    @if(!$user->active)
                                        <span class="text-xs text-gray-500">Deaktiviert</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate
                                   class="text-blue-600 text-sm font-medium">Bearbeiten</a>
                                @if($user->id !== auth()->id())
                                    <button wire:click="toggleActive({{ $user->id }})"
                                            wire:confirm="{{ $user->active ? 'Konto wirklich deaktivieren?' : 'Konto wieder aktivieren?' }}"
                                            class="text-sm font-medium {{ $user->active ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $user->active ? 'Deaktivieren' : 'Aktivieren' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-gray-400">Keine Personen gefunden.</div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $users->links() }}
                </div>
            @endif
        </div>

        <p class="text-xs text-gray-400 text-center">{{ $users->total() }} Person(en) gesamt</p>
</div>
