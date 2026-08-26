<?php

use App\Models\Media;
use App\Services\ChatService;
use App\Services\PiiFilterService;
use App\Services\SimilarMediaService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $situation   = '';
    public bool   $loading     = false;
    public ?string $response   = null;
    public array  $recommended = [];   // Media objects to display
    public bool   $piiRedacted = false;
    public array  $piiTypes    = [];
    public ?string $error      = null;

    // Simple client-side session history (stored as component state, not persisted to DB)
    public array $history = [];

    public function ask(): void
    {
        $this->validate([
            'situation' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'situation.required' => 'Bitte beschreiben Sie die Situation.',
            'situation.min'      => 'Bitte geben Sie mindestens 20 Zeichen ein.',
            'situation.max'      => 'Maximal 2000 Zeichen erlaubt.',
        ]);

        $this->loading     = true;
        $this->response    = null;
        $this->recommended = [];
        $this->error       = null;

        // 1. PII filter
        $pii = app(PiiFilterService::class)->filter($this->situation);
        $this->piiRedacted = $pii['redacted'];
        $this->piiTypes    = $pii['types'];
        $cleanSituation    = $pii['text'];

        // 2. Semantic search for relevant media context
        $inventory = app(SimilarMediaService::class)->semanticSearch($cleanSituation, limit: 12);

        // 3. Ask Claude
        $result = app(ChatService::class)->ask($cleanSituation, $inventory);

        $this->response = $result['text'];
        $this->loading  = false;

        // 4. Load recommended media models for display
        if (! empty($result['media_ids'])) {
            $this->recommended = Media::whereIn('id', $result['media_ids'])
                ->with('tags')
                ->get()
                ->toArray();
        }

        // 5. Add to local history (kept in component state)
        array_unshift($this->history, [
            'situation' => mb_substr($this->situation, 0, 80) . (mb_strlen($this->situation) > 80 ? '…' : ''),
            'response'  => $this->response,
            'time'      => now()->format('H:i'),
        ]);
        $this->history = array_slice($this->history, 0, 5);

        $this->situation = '';
    }

    public function clearHistory(): void
    {
        $this->history     = [];
        $this->response    = null;
        $this->recommended = [];
        $this->piiRedacted = false;
        $this->piiTypes    = [];
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">KI-Situations-Assistent</h1>
        @if(!empty($history))
            <button wire:click="clearHistory" class="text-xs text-gray-400 hover:text-gray-600">
                Verlauf löschen
            </button>
        @endif
    </div>

    {{-- Privacy notice --}}
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex gap-3">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div class="text-sm text-amber-800">
            <strong>Datenschutzhinweis:</strong> Bitte keine echten Namen, Geburtsdaten oder andere personenbezogene Daten eingeben.
            Erkannte Daten werden automatisch entfernt. Die Eingabe wird nicht gespeichert.
        </div>
    </div>

    {{-- Input form --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Situation beschreiben
            </label>
            <textarea wire:model="situation"
                      rows="4"
                      placeholder="z. B.: Ein Kind in unserer Gruppe hat Schwierigkeiten, seine Gefühle auszudrücken und reagiert oft mit Rückzug. Wir suchen ein Material, das spielerisch dabei hilft, Emotionen zu benennen und zu verarbeiten."
                      class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"
                      maxlength="2000"
                      :disabled="$wire.loading"></textarea>
            @error('situation')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-400 text-right" x-data x-text="'{{ strlen($situation) }}/2000'"></p>
        </div>

        <button wire:click="ask"
                wire:loading.attr="disabled"
                x-bind:disabled="$wire.loading || ($wire.situation || '').trim().length < 20"
                class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
            <span wire:loading.remove wire:target="ask">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                Medien empfehlen lassen
            </span>
            <span wire:loading wire:target="ask" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                KI analysiert Situation …
            </span>
        </button>
    </div>

    {{-- PII warning --}}
    @if($piiRedacted)
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800">
            Folgende Daten wurden automatisch entfernt:
            <strong>{{ implode(', ', $piiTypes) }}</strong>.
        </div>
    @endif

    {{-- Response --}}
    @if($response)
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="bg-purple-50 border-b border-purple-100 px-5 py-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span class="text-sm font-medium text-purple-800">Empfehlung des Assistenten</span>
            </div>
            <div class="p-5 prose prose-sm prose-gray max-w-none">{!! Str::markdown($response) !!}</div>

            {{-- Recommended media cards --}}
            @if(!empty($recommended))
                <div class="border-t border-gray-100 p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Empfohlene Medien</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($recommended as $item)
                            <a href="{{ route('media.show', $item['id']) }}" wire:navigate
                               class="bg-gray-50 rounded-xl border border-gray-200 p-3 hover:border-blue-200 hover:bg-blue-50 transition-all">
                                <p class="text-xs font-semibold text-gray-800 leading-snug line-clamp-2">{{ $item['title'] }}</p>
                                @if(!empty($item['author']))
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $item['author'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- History --}}
    @if(!empty($history) && count($history) > 1)
        <div class="space-y-2">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Frühere Anfragen dieser Sitzung</p>
            @foreach(array_slice($history, 1) as $h)
                <div class="bg-white rounded-xl border border-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-400 mb-1">{{ $h['time'] }}</p>
                    <p class="text-sm text-gray-600 italic truncate">{{ $h['situation'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

</div>
