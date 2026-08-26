<?php

use App\Models\Media;
use App\Models\Wish;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $title          = '';
    public string $isbn           = '';
    public string $topic_freetext = '';

    /** @var array<int, array{id:int, title:string, author:?string, isbn:?string}> */
    public array  $suggestions = [];

    public function updatedTitle(): void
    {
        $term = trim($this->title);
        $this->suggestions = [];

        if (mb_strlen($term) < 3) return;

        // Search by title or author (existing media)
        $hits = Media::query()
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('author', 'like', "%{$term}%");
            })
            ->orderBy('title')
            ->limit(6)
            ->get(['id', 'title', 'author', 'isbn']);

        $this->suggestions = $hits->map(fn ($m) => [
            'id'     => $m->id,
            'title'  => $m->title,
            'author' => $m->author,
            'isbn'   => $m->isbn,
        ])->all();
    }

    public function clearSuggestions(): void
    {
        $this->suggestions = [];
    }

    public function save(): void
    {
        $this->validate([
            'title'          => ['required_without:topic_freetext', 'nullable', 'string', 'max:255'],
            'isbn'           => ['nullable', 'string', 'max:20'],
            'topic_freetext' => ['required_without:title', 'nullable', 'string', 'max:1000'],
        ], [
            'title.required_without'          => 'Bitte einen Titel oder ein Thema angeben.',
            'topic_freetext.required_without' => 'Bitte einen Titel oder ein Thema angeben.',
        ]);

        Wish::create([
            'user_id'        => auth()->id(),
            'title'          => $this->title ?: null,
            'isbn'           => $this->isbn ?: null,
            'topic_freetext' => $this->topic_freetext ?: null,
        ]);

        session()->flash('success', 'Ihr Medienwunsch wurde eingereicht.');
        $this->redirect(route('wishes.index'), navigate: true);
    }
}; ?>

<div class="space-y-5">

    <a href="{{ route('wishes.index') }}" wire:navigate
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück
    </a>

    <h1 class="text-xl font-semibold text-gray-900">Medium wünschen</h1>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">

        <p class="text-sm text-gray-500">
            Kennen Sie ein Medium, das in unserer Bibliothek fehlt? Reichen Sie hier einen Wunsch ein –
            die Kuratoren prüfen ihn und entscheiden über eine Anschaffung.
        </p>

        <form wire:submit="save" class="space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Titel des Mediums
                </label>
                <input wire:model.live.debounce.400ms="title" dusk="wunsch-titel"
                       type="text"
                       placeholder="z. B. Mein Körper gehört mir!"
                       autocomplete="off"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                @if(!empty($suggestions))
                    <div class="mt-2 bg-white border border-amber-200 rounded-xl shadow-xs overflow-hidden divide-y divide-gray-50">
                        <div class="px-3 py-2 bg-amber-50 text-amber-800 text-xs flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Diese Medien sind bereits im Bestand:
                            </span>
                            <button type="button" wire:click="clearSuggestions"
                                    class="text-amber-700 hover:text-amber-900 font-medium">
                                Schließen ✕
                            </button>
                        </div>
                        @foreach($suggestions as $s)
                            <a href="{{ route('media.show', $s['id']) }}" wire:navigate
                               class="block px-3 py-2 hover:bg-blue-50">
                                <p class="text-sm font-medium text-gray-900">{{ $s['title'] }}</p>
                                @if($s['author'])
                                    <p class="text-xs text-gray-500">{{ $s['author'] }}</p>
                                @endif
                                @if($s['isbn'])
                                    <p class="text-xs text-gray-400 font-mono">ISBN {{ $s['isbn'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ISBN <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input wire:model="isbn" type="text" placeholder="978-3-…"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 font-mono">
            </div>

            <div class="relative">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-xs text-gray-400 uppercase">
                    <span class="bg-white px-3">oder Thema beschreiben</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Themen-Beschreibung
                </label>
                <textarea wire:model="topic_freetext" rows="3"
                          placeholder="z. B.: Ein Buch zum Thema Pubertät für Jugendliche ab 12, das spielerisch Körperveränderungen erklärt."
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                @error('topic_freetext') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" dusk="wunsch-absenden"
                        class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 transition-colors">
                    Wunsch einreichen
                </button>
                <a href="{{ route('wishes.index') }}" wire:navigate
                   class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
