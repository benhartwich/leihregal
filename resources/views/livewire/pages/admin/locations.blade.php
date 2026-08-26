<?php

use App\Models\Location;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string  $newName      = '';
    public string  $newPlace     = '';
    public int     $newSortOrder = 0;
    public ?string $toast        = null;
    public bool    $toastOk      = true;

    // Editing state: locationId => ['name' => ..., 'place' => ..., 'sort_order' => ...]
    public array $editing = [];

    public function mount(): void
    {
        foreach (Location::ordered()->get() as $loc) {
            $this->editing[$loc->id] = [
                'name'       => $loc->name,
                'place'      => $loc->place ?? '',
                'sort_order' => $loc->sort_order,
            ];
        }
    }

    public function addLocation(): void
    {
        $this->validate([
            'newName'      => ['required', 'string', 'max:100', 'unique:locations,name'],
            'newPlace'     => ['nullable', 'string', 'max:100'],
            'newSortOrder' => ['integer', 'min:0', 'max:9999'],
        ], [
            'newName.required' => 'Bitte einen Regal-Namen eingeben.',
            'newName.unique'   => 'Dieser Standort existiert bereits.',
        ]);

        $loc = Location::create([
            'name'       => trim($this->newName),
            'place'      => trim($this->newPlace) ?: null,
            'sort_order' => $this->newSortOrder,
        ]);

        $this->editing[$loc->id] = [
            'name'       => $loc->name,
            'place'      => $loc->place ?? '',
            'sort_order' => $loc->sort_order,
        ];
        $this->newName      = '';
        $this->newPlace     = '';
        $this->newSortOrder = 0;
        $this->toast        = 'Standort wurde hinzugefügt.';
        $this->toastOk      = true;
    }

    public function updateLocation(int $id): void
    {
        $this->validate([
            "editing.{$id}.name"       => ['required', 'string', 'max:100'],
            "editing.{$id}.place"      => ['nullable', 'string', 'max:100'],
            "editing.{$id}.sort_order" => ['integer', 'min:0', 'max:9999'],
        ], [
            "editing.{$id}.name.required" => 'Regal-Name darf nicht leer sein.',
        ]);

        $loc = Location::findOrFail($id);

        $exists = Location::where('name', trim($this->editing[$id]['name']))
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            $this->toast   = 'Dieser Regal-Name ist bereits vergeben.';
            $this->toastOk = false;
            return;
        }

        $oldName = $loc->name;
        $newName = trim($this->editing[$id]['name']);
        $loc->update([
            'name'       => $newName,
            'place'      => trim($this->editing[$id]['place'] ?? '') ?: null,
            'sort_order' => (int) $this->editing[$id]['sort_order'],
        ]);

        // If name changed, update Media records that reference the old name
        if ($oldName !== $newName) {
            \App\Models\Media::where('location', $oldName)->update(['location' => $newName]);
        }

        $this->toast   = 'Standort wurde gespeichert.';
        $this->toastOk = true;
    }

    public function deleteLocation(int $id): void
    {
        $loc = Location::findOrFail($id);

        $inUse = \App\Models\Media::where('location', $loc->name)->exists();
        if ($inUse) {
            $this->toast   = 'Standort kann nicht gelöscht werden – es sind noch Medien zugeordnet.';
            $this->toastOk = false;
            return;
        }

        unset($this->editing[$id]);
        $loc->delete();
        $this->toast   = 'Standort wurde gelöscht.';
        $this->toastOk = true;
    }

    public function with(): array
    {
        return [
            'locations' => Location::ordered()->get(),
        ];
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings') }}" wire:navigate class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Standorte</h1>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show"
             x-init="setTimeout(() => { show = false; $wire.set('toast', null) }, 3500)"
             class="px-4 py-3 rounded-lg text-sm flex items-center gap-3
                    {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            {{ $toast }}
        </div>
    @endif

    {{-- Existing locations --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Vorhandene Standorte</h2>
        </div>

        @if($locations->isEmpty())
            <div class="px-5 py-6 text-sm text-gray-400 text-center">Noch keine Standorte angelegt.</div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($locations as $loc)
                    <div class="px-5 py-3 flex flex-wrap items-center gap-2">
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-4 gap-2 min-w-0">
                            <input wire:model="editing.{{ $loc->id }}.place"
                                   type="text"
                                   class="rounded-lg border-gray-300 text-sm py-1.5 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ort (z.B. Hauptraum)">
                            <input wire:model="editing.{{ $loc->id }}.name"
                                   type="text"
                                   class="sm:col-span-2 rounded-lg border-gray-300 text-sm py-1.5 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Regal (z.B. A3)">
                            <input wire:model="editing.{{ $loc->id }}.sort_order"
                                   type="number" min="0" max="9999"
                                   class="rounded-lg border-gray-300 text-sm py-1.5 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Reihenfolge">
                        </div>
                        <button wire:click="updateLocation({{ $loc->id }})"
                                class="shrink-0 px-3 py-1.5 rounded-lg text-sm font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                            Speichern
                        </button>
                        <button wire:click="deleteLocation({{ $loc->id }})"
                                wire:confirm="Standort '{{ $loc->fullLabel() }}' wirklich löschen?"
                                class="shrink-0 px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                            Löschen
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add new --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Neuer Standort</h2>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ort</label>
                <input wire:model="newPlace" type="text" placeholder="z. B. Hauptraum"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                @error('newPlace') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Regal <span class="text-red-500">*</span></label>
                <input wire:model="newName" type="text" placeholder="z. B. A3"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                @error('newName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reihenfolge</label>
                <input wire:model="newSortOrder" type="number" min="0" max="9999" placeholder="0"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <button wire:click="addLocation"
                class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 transition-colors text-sm">
            Standort hinzufügen
        </button>
    </div>

</div>
