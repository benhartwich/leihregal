<?php

use App\Enums\AcquisitionStatus;
use App\Models\AcquisitionSuggestion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string  $filterStatus = '';
    public ?string $toast        = null;
    public bool    $toastOk      = true;

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function setStatus(int $id, string $status): void
    {
        AcquisitionSuggestion::findOrFail($id)->update(['status' => $status]);
        $this->toast   = 'Status aktualisiert.';
        $this->toastOk = true;
    }

    public function delete(int $id): void
    {
        AcquisitionSuggestion::findOrFail($id)->delete();
        $this->toast   = 'Eintrag gelöscht.';
        $this->toastOk = true;
    }

    public function with(): array
    {
        $query = AcquisitionSuggestion::with('wish.user')->orderByDesc('created_at');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return ['items' => $query->paginate(20)];
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('curation.index') }}" wire:navigate
               class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-semibold text-gray-900">Anschaffungsvorschläge</h1>
        </div>

        {{-- Export links --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('curation.acquisitions.csv') }}"
               class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                CSV
            </a>
            <a href="{{ route('curation.acquisitions.pdf') }}"
               class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                PDF
            </a>
        </div>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="px-4 py-3 rounded-xl text-sm font-medium {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            {{ $toast }}
        </div>
    @endif

    {{-- Filter --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['' => 'Alle', 'offen' => 'Offen', 'bestellt' => 'Bestellt', 'eingetroffen' => 'Eingetroffen', 'verworfen' => 'Verworfen'] as $val => $label)
            <button wire:click="$set('filterStatus', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                           {{ $filterStatus === $val ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- List --}}
    @if($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-400">
            Keine Einträge gefunden.
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-50">
            @foreach($items as $item)
                <div class="px-5 py-4 space-y-2">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                @if($item->author) {{ $item->author }} · @endif
                                @if($item->publisher) {{ $item->publisher }} · @endif
                                @if($item->isbn) <span class="font-mono">{{ $item->isbn }}</span> · @endif
                                <span class="capitalize">{{ $item->source === 'ki' ? 'KI-Analyse' : 'Nutzer-Wunsch' }}</span>
                                @if($item->wish?->user) · {{ $item->wish->user->name }} @endif
                            </p>
                            @if($item->price_estimate)
                                <p class="text-xs text-gray-500 mt-0.5">ca. {{ number_format($item->price_estimate, 2, ',', '.') }} €</p>
                            @endif
                            <p class="text-sm text-gray-500 mt-1 italic">{{ $item->reason }}</p>

                            @if(!empty($item->shop_urls))
                                <div class="mt-1.5 flex gap-3">
                                    @foreach($item->shop_urls as $shop => $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                           class="text-xs text-blue-600 hover:underline">{{ ucfirst($shop) }} →</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="shrink-0 inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $item->status->badgeClass() }}">
                            {{ $item->status->label() }}
                        </span>
                    </div>

                    {{-- Status actions --}}
                    <div class="flex items-center gap-2 flex-wrap pt-1">
                        @if($item->status->value === 'offen')
                            <button wire:click="setStatus({{ $item->id }}, 'bestellt')"
                                    class="text-xs bg-yellow-50 text-yellow-700 px-3 py-1.5 rounded-lg hover:bg-yellow-100 font-medium">
                                Als bestellt markieren
                            </button>
                            <button wire:click="setStatus({{ $item->id }}, 'verworfen')"
                                    class="text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg hover:bg-gray-200 font-medium">
                                Verwerfen
                            </button>
                        @elseif($item->status->value === 'bestellt')
                            <button wire:click="setStatus({{ $item->id }}, 'eingetroffen')"
                                    class="text-xs bg-green-50 text-green-700 px-3 py-1.5 rounded-lg hover:bg-green-100 font-medium">
                                Eingetroffen
                            </button>
                            <button wire:click="setStatus({{ $item->id }}, 'offen')"
                                    class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 font-medium">
                                Zurück auf offen
                            </button>
                        @elseif($item->status->value === 'verworfen')
                            <button wire:click="setStatus({{ $item->id }}, 'offen')"
                                    class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 font-medium">
                                Wieder öffnen
                            </button>
                        @endif

                        @if(in_array($item->status->value, ['eingetroffen', 'verworfen']))
                            <button wire:click="delete({{ $item->id }})"
                                    wire:confirm="Eintrag wirklich löschen?"
                                    class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded-sm hover:bg-red-50 transition-colors ml-auto">
                                Löschen
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($items->hasPages())
            <div class="flex justify-center">{{ $items->links() }}</div>
        @endif
    @endif

</div>
