<?php

use App\Models\WhitelistEntry;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $type  = 'verlag';
    public string $name  = '';
    public string $notes = '';

    public ?string $toast    = null;
    public bool    $toastOk  = true;

    public function add(): void
    {
        $this->validate([
            'type'  => ['required', 'in:verlag,autor'],
            'name'  => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = WhitelistEntry::where('type', $this->type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->name)])
            ->exists();

        if ($exists) {
            $this->toast   = 'Dieser Eintrag ist bereits in der Whitelist.';
            $this->toastOk = false;
            return;
        }

        WhitelistEntry::create([
            'type'     => $this->type,
            'name'     => trim($this->name),
            'notes'    => $this->notes ?: null,
            'added_by' => auth()->id(),
        ]);

        $this->name    = '';
        $this->notes   = '';
        $this->toast   = 'Eintrag wurde hinzugefügt.';
        $this->toastOk = true;
    }

    public function remove(int $id): void
    {
        WhitelistEntry::findOrFail($id)->delete();
        $this->toast   = 'Eintrag entfernt.';
        $this->toastOk = true;
    }

    public function with(): array
    {
        return [
            'verlage' => WhitelistEntry::where('type', 'verlag')->orderBy('name')->get(),
            'autoren' => WhitelistEntry::where('type', 'autor')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('curation.index') }}" wire:navigate
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Whitelist – Verlage & Autoren</h1>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="px-4 py-3 rounded-xl text-sm font-medium {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            {{ $toast }}
        </div>
    @endif

    {{-- Add form --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
        <h2 class="font-medium text-gray-800">Neuen Eintrag hinzufügen</h2>

        <form wire:submit="add" class="space-y-3">
            <div class="flex gap-3">
                <div class="w-36 shrink-0">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Typ</label>
                    <select wire:model="type"
                            class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="verlag">Verlag</option>
                        <option value="autor">Autor:in</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                    <input wire:model="name" type="text"
                           placeholder="z. B. Don Bosco Medien"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    Notiz <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input wire:model="notes" type="text"
                       placeholder="z. B. bewährte Bücher für sozialpädagogischen Bereich"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                Hinzufügen
            </button>
        </form>
    </div>

    {{-- Verlage --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">Verlage <span class="text-sm font-normal text-gray-400">({{ $verlage->count() }})</span></h2>
        </div>
        @if($verlage->isEmpty())
            <div class="px-5 py-6 text-sm text-gray-400 text-center">Noch keine Verlage eingetragen.</div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($verlage as $e)
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $e->name }}</p>
                            @if($e->notes)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $e->notes }}</p>
                            @endif
                        </div>
                        <button wire:click="remove({{ $e->id }})"
                                wire:confirm="Eintrag wirklich entfernen?"
                                class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded-sm hover:bg-red-50 transition-colors">
                            Entfernen
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Autoren --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Autoren & Autorinnen <span class="text-sm font-normal text-gray-400">({{ $autoren->count() }})</span></h2>
        </div>
        @if($autoren->isEmpty())
            <div class="px-5 py-6 text-sm text-gray-400 text-center">Noch keine Autoren eingetragen.</div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($autoren as $e)
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $e->name }}</p>
                            @if($e->notes)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $e->notes }}</p>
                            @endif
                        </div>
                        <button wire:click="remove({{ $e->id }})"
                                wire:confirm="Eintrag wirklich entfernen?"
                                class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded-sm hover:bg-red-50 transition-colors">
                            Entfernen
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
