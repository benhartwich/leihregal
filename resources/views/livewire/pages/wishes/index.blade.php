<?php

use App\Models\Wish;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public ?string $toast   = null;
    public bool    $toastOk = true;

    public function deleteWish(int $id): void
    {
        $wish = Wish::where('id', $id)->where('user_id', auth()->id())->first();
        if (! $wish) {
            $this->toast   = 'Wunsch nicht gefunden.';
            $this->toastOk = false;
            return;
        }
        $wish->delete();
        $this->toast   = 'Wunsch wurde gelöscht.';
        $this->toastOk = true;
    }

    public function with(): array
    {
        return [
            'myWishes' => Wish::where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->paginate(15),
        ];
    }
}; ?>

<div class="space-y-4">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Meine Medienwünsche</h1>
        <a href="{{ route('wishes.create') }}" wire:navigate
           class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Wunsch einreichen
        </a>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show"
             x-init="setTimeout(() => { show = false; $wire.set('toast', null) }, 3500)"
             class="px-4 py-3 rounded-lg text-sm
                    {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            {{ $toast }}
        </div>
    @endif

    @if($myWishes->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-400">
            Sie haben noch keine Wünsche eingereicht.
            <a href="{{ route('wishes.create') }}" wire:navigate class="text-blue-600 hover:underline ml-1">Jetzt einreichen</a>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-50">
            @foreach($myWishes as $wish)
                <div class="px-5 py-4 flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900">
                            {{ $wish->title ?? ('Thema: ' . mb_substr($wish->topic_freetext ?? '', 0, 60) . '…') }}
                        </p>
                        @if($wish->isbn)
                            <p class="text-xs text-gray-400 font-mono mt-0.5">ISBN: {{ $wish->isbn }}</p>
                        @endif
                        @if($wish->topic_freetext && $wish->title)
                            <p class="text-sm text-gray-500 mt-1">{{ mb_substr($wish->topic_freetext, 0, 120) }}</p>
                        @endif
                        @if($wish->curator_note)
                            <p class="text-sm text-blue-700 bg-blue-50 rounded-lg px-3 py-2 mt-2">
                                <span class="font-medium">Anmerkung:</span> {{ $wish->curator_note }}
                            </p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1">{{ $wish->created_at->format('d.m.Y') }}</p>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-2" wire:key="wish-actions-{{ $wish->id }}">
                        <span class="inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $wish->status->badgeClass() }}">
                            {{ $wish->status->label() }}
                        </span>
                        <button type="button"
                                wire:click="deleteWish({{ $wish->id }})"
                                wire:confirm="Wunsch wirklich löschen?"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                            Löschen
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @if($myWishes->hasPages())
            <div class="flex justify-center">{{ $myWishes->links() }}</div>
        @endif
    @endif
</div>
