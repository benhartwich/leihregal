<?php

use App\Enums\WishStatus;
use App\Notifications\WishStatusChangedNotification;
use App\Models\AcquisitionSuggestion;
use App\Models\Wish;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string  $filterStatus = '';
    public ?int    $editingId    = null;
    public string  $curatorNote  = '';
    public ?string $toast        = null;
    public bool    $toastOk      = true;

    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function startEdit(int $id): void
    {
        $wish = Wish::findOrFail($id);
        $this->editingId   = $id;
        $this->curatorNote = $wish->curator_note ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingId   = null;
        $this->curatorNote = '';
    }

    public function setStatus(int $id, string $status): void
    {
        $wish = Wish::with('user')->findOrFail($id);
        $wish->update(['status' => $status]);

        if ($wish->user && in_array($status, ['angenommen', 'abgelehnt', 'beobachten'])) {
            try {
                $wish->user->notify(new WishStatusChangedNotification($wish->fresh('user')));
            } catch (\Throwable) {}
        }

        $this->toast   = 'Status aktualisiert.';
        $this->toastOk = true;
    }

    public function saveNote(): void
    {
        $this->validate(['curatorNote' => ['nullable', 'string', 'max:500']]);

        Wish::findOrFail($this->editingId)->update([
            'curator_note' => $this->curatorNote ?: null,
        ]);

        $this->toast     = 'Anmerkung gespeichert.';
        $this->toastOk   = true;
        $this->editingId = null;
    }

    public function toAcquisition(int $id): void
    {
        $wish = Wish::findOrFail($id);

        if (AcquisitionSuggestion::where('wish_id', $id)->exists()) {
            $this->toast   = 'Dieser Wunsch ist bereits in der Anschaffungsliste.';
            $this->toastOk = false;
            return;
        }

        AcquisitionSuggestion::create([
            'source'  => 'wunsch',
            'title'   => $wish->title ?? ('Thema: ' . mb_substr($wish->topic_freetext ?? '', 0, 200)),
            'isbn'    => $wish->isbn,
            'reason'  => $wish->topic_freetext ?? 'Nutzer-Wunsch',
            'status'  => 'offen',
            'wish_id' => $wish->id,
        ]);

        $wish->update(['status' => 'angenommen']);

        $this->toast   = 'Zur Anschaffungsliste hinzugefügt.';
        $this->toastOk = true;
    }

    public function with(): array
    {
        $query = Wish::with('user')->orderByDesc('created_at');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return ['wishes' => $query->paginate(20)];
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('curation.index') }}" wire:navigate
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Medienwünsche verwalten</h1>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="px-4 py-3 rounded-xl text-sm font-medium {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-amber-50 border border-amber-200 text-amber-800' }}">
            {{ $toast }}
        </div>
    @endif

    {{-- Filter --}}
    <div class="flex gap-2 flex-wrap">
        @foreach(['' => 'Alle', 'eingereicht' => 'Eingereicht', 'angenommen' => 'Angenommen', 'beobachten' => 'Beobachten', 'abgelehnt' => 'Abgelehnt'] as $val => $label)
            <button wire:click="$set('filterStatus', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                           {{ $filterStatus === $val ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Wish list --}}
    @if($wishes->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-400">
            Keine Wünsche gefunden.
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-50">
            @foreach($wishes as $wish)
                <div class="px-5 py-4 space-y-3">
                    {{-- Header row --}}
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900">
                                {{ $wish->title ?? ('Thema: ' . mb_substr($wish->topic_freetext ?? '', 0, 80) . '…') }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                von {{ $wish->user?->name ?? '–' }} · {{ $wish->created_at->format('d.m.Y') }}
                                @if($wish->isbn) · <span class="font-mono">ISBN {{ $wish->isbn }}</span> @endif
                            </p>
                            @if($wish->topic_freetext && $wish->title)
                                <p class="text-sm text-gray-500 mt-1">{{ mb_substr($wish->topic_freetext, 0, 200) }}</p>
                            @elseif($wish->topic_freetext && !$wish->title)
                                <p class="text-sm text-gray-600 mt-1">{{ mb_substr($wish->topic_freetext, 0, 200) }}</p>
                            @endif
                            @if($wish->curator_note)
                                <p class="text-sm text-blue-700 bg-blue-50 rounded-lg px-3 py-1.5 mt-1.5">
                                    <span class="font-medium">Anmerkung:</span> {{ $wish->curator_note }}
                                </p>
                            @endif
                        </div>
                        <span class="shrink-0 inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $wish->status->badgeClass() }}">
                            {{ $wish->status->label() }}
                        </span>
                    </div>

                    {{-- Note editor --}}
                    @if($editingId === $wish->id)
                        <div class="space-y-2" wire:key="note-{{ $wish->id }}">
                            <textarea wire:model="curatorNote" rows="2"
                                      placeholder="Interne Anmerkung (sichtbar für Nutzer:in) …"
                                      class="w-full rounded-xl border-gray-300 text-sm py-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                            <div class="flex gap-2">
                                <button wire:click="saveNote"
                                        class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">Speichern</button>
                                <button wire:click="cancelEdit"
                                        class="text-xs text-gray-500 hover:text-gray-700 px-3 py-1.5">Abbrechen</button>
                            </div>
                        </div>
                    @endif

                    {{-- Action row --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Status buttons --}}
                        @if($wish->status->value !== 'angenommen')
                            <button wire:click="setStatus({{ $wish->id }}, 'angenommen')"
                                    class="text-xs bg-green-50 text-green-700 px-3 py-1.5 rounded-lg hover:bg-green-100 font-medium">
                                Annehmen
                            </button>
                        @endif
                        @if($wish->status->value !== 'beobachten')
                            <button wire:click="setStatus({{ $wish->id }}, 'beobachten')"
                                    class="text-xs bg-yellow-50 text-yellow-700 px-3 py-1.5 rounded-lg hover:bg-yellow-100 font-medium">
                                Beobachten
                            </button>
                        @endif
                        @if($wish->status->value !== 'abgelehnt')
                            <button wire:click="setStatus({{ $wish->id }}, 'abgelehnt')"
                                    class="text-xs bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 font-medium">
                                Ablehnen
                            </button>
                        @endif

                        <button wire:click="startEdit({{ $wish->id }})"
                                class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 font-medium">
                            Anmerkung
                        </button>

                        @unless(AcquisitionSuggestion::where('wish_id', $wish->id)->exists())
                            <button wire:click="toAcquisition({{ $wish->id }})"
                                    class="text-xs bg-purple-50 text-purple-700 px-3 py-1.5 rounded-lg hover:bg-purple-100 font-medium ml-auto">
                                + Anschaffungsliste
                            </button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>

        @if($wishes->hasPages())
            <div class="flex justify-center">{{ $wishes->links() }}</div>
        @endif
    @endif

</div>
