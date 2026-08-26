<?php

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $entity = '';
    public string $action = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEntity(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function filterZuruecksetzen(): void
    {
        $this->reset(['search', 'entity', 'action']);
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'eintraege' => AuditLog::query()
                ->when($this->search, fn ($q) => $q->where(function ($q) {
                    $q->where('user_name', 'like', "%{$this->search}%")
                      ->orWhere('entity_label', 'like', "%{$this->search}%");
                }))
                ->when($this->entity, fn ($q) => $q->where('entity', $this->entity))
                ->when($this->action, fn ($q) => $q->where('action', $this->action))
                ->latest('created_at')
                ->latest('id')
                ->paginate(30),

            // Nur tatsächlich vorkommende Werte anbieten – leere Filter sind
            // irreführend.
            'entitaeten' => AuditLog::query()->distinct()->orderBy('entity')->pluck('entity'),
            'aktionen'   => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ];
    }
}; ?>

<div class="space-y-4">

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900">Protokoll</h1>
        <span class="text-sm text-gray-500">{{ $eintraege->total() }} Einträge</span>
    </div>

    <p class="text-sm text-gray-600">
        Aufzeichnung aller Kurations- und Admin-Aktionen. Einträge werden nicht
        verändert oder gelöscht.
    </p>

    {{-- Filter --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search"
                   type="search" placeholder="Nutzer oder Datensatz suchen …"
                   class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm
                          focus:ring-purple-500 focus:border-purple-500">
        </div>

        <select wire:model.live="entity"
                class="py-2 pl-3 pr-8 border border-gray-300 rounded-lg text-sm
                       focus:ring-purple-500 focus:border-purple-500">
            <option value="">Alle Bereiche</option>
            @foreach($entitaeten as $e)
                <option value="{{ $e }}">{{ (new \App\Models\AuditLog(['entity' => $e]))->entityLabel() }}</option>
            @endforeach
        </select>

        <select wire:model.live="action"
                class="py-2 pl-3 pr-8 border border-gray-300 rounded-lg text-sm
                       focus:ring-purple-500 focus:border-purple-500">
            <option value="">Alle Aktionen</option>
            @foreach($aktionen as $a)
                <option value="{{ $a }}">{{ (new \App\Models\AuditLog(['action' => $a]))->actionLabel() }}</option>
            @endforeach
        </select>

        @if($search || $entity || $action)
            <button wire:click="filterZuruecksetzen" type="button"
                    class="py-2 px-3 text-sm text-gray-600 hover:text-gray-900 whitespace-nowrap">
                Filter zurücksetzen
            </button>
        @endif
    </div>

    {{-- Liste --}}
    <div class="space-y-2">
        @forelse($eintraege as $eintrag)
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $eintrag->actionBadgeClass() }}">
                        {{ $eintrag->actionLabel() }}
                    </span>
                    <span class="text-gray-500">{{ $eintrag->entityLabel() }}</span>
                    <span class="font-medium text-gray-900">{{ $eintrag->entity_label ?? '#' . $eintrag->entity_id }}</span>
                </div>

                <div class="mt-1 text-xs text-gray-500">
                    {{ $eintrag->user_name }}@if($eintrag->user_role) ({{ $eintrag->user_role }})@endif
                    · {{ $eintrag->created_at->format('d.m.Y H:i') }}
                </div>

                @if($eintrag->diff)
                    <details class="mt-2">
                        <summary class="text-xs text-purple-700 cursor-pointer hover:underline">
                            {{ count($eintrag->diff) }} {{ count($eintrag->diff) === 1 ? 'Feld' : 'Felder' }} anzeigen
                        </summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-xs">
                                <tbody>
                                @foreach($eintrag->diff as $feld => $wert)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-1 pr-3 font-medium text-gray-700 align-top whitespace-nowrap">{{ $feld }}</td>
                                        @if(is_array($wert) && array_key_exists('alt', $wert))
                                            <td class="py-1 pr-3 text-red-700 align-top break-all">{{ $wert['alt'] ?? '—' }}</td>
                                            <td class="py-1 text-green-700 align-top break-all">{{ $wert['neu'] ?? '—' }}</td>
                                        @else
                                            <td class="py-1 text-gray-600 align-top break-all" colspan="2">{{ is_scalar($wert) ? $wert : json_encode($wert, JSON_UNESCAPED_UNICODE) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-sm text-gray-500">
                @if($search || $entity || $action)
                    Keine Einträge für diese Filter.
                @else
                    Noch keine Einträge vorhanden.
                @endif
            </div>
        @endforelse
    </div>

    <div>{{ $eintraege->links() }}</div>
</div>
