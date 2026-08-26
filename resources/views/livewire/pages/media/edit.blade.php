<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\MediaEmbedding;
use App\Models\MediaTag;
use App\Services\CoverImageService;
use App\Services\GoogleBooksService;
use App\Services\MediaAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Media $media;

    public string $type      = 'buch';
    public string $title     = '';
    public string $author    = '';
    public string $publisher = '';
    public string $year      = '';
    public string $isbn      = '';
    public string $language  = 'de';
    public string $status    = 'verfuegbar';
    public string $location  = '';
    public string $tagsInput = '';
    public string $loanDays  = '';

    public bool    $coverLoading    = false;
    public ?string $coverUrlPreview = null;

    public string $summary           = '';
    public string $target_group      = '';
    public string $age_recommendation = '';
    public string $practical_use     = '';

    public $cover = null;

    public bool   $aiLoading = false;
    public ?string $aiError  = null;

    public function mount(Media $media): void
    {
        $this->media             = $media->load('tags');
        $this->type              = $media->type->value;
        $this->title             = $media->title;
        $this->author            = $media->author ?? '';
        $this->publisher         = $media->publisher ?? '';
        $this->year              = $media->year ? (string) $media->year : '';
        $this->isbn              = $media->isbn ?? '';
        $this->language          = $media->language ?? 'de';
        $this->status            = $media->status->value;
        $this->location          = $media->location ?? '';
        $this->summary           = $media->summary ?? '';
        $this->target_group      = $media->target_group ?? '';
        $this->age_recommendation = $media->age_recommendation ?? '';
        $this->practical_use     = $media->practical_use ?? '';
        $this->tagsInput         = implode(', ', $media->tagList());
        $this->loanDays          = $media->loan_days ? (string) $media->loan_days : '';
    }

    public function fetchCover(): void
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($this->isbn));
        if (! $isbn) return;

        $this->coverLoading = true;
        $service = app(GoogleBooksService::class);
        $data    = $service->lookupIsbn($isbn);
        $coverUrl = $data['cover_url'] ?? $service->directCoverUrl($isbn);
        $this->coverLoading = false;

        if (! $coverUrl) {
            session()->flash('error', 'Kein Cover für diese ISBN gefunden.');
            return;
        }
        $data = ['cover_url' => $coverUrl];

        try {
            $filename = app(CoverImageService::class)
                ->storeFromUrl($data['cover_url'], $this->media->cover_path);
            if (! $filename) throw new \RuntimeException();

            $this->media->update(['cover_path' => $filename]);
            $this->media->refresh();
            session()->flash('success', 'Cover wurde geladen.');
        } catch (\Throwable) {
            session()->flash('error', 'Cover konnte nicht heruntergeladen werden.');
        }
    }

    public function enrichWithAi(): void
    {
        $this->aiError   = null;
        $this->aiLoading = true;

        $aiService = app(MediaAiService::class);
        $result    = $aiService->enrichMedia([
            'type'   => $this->type,
            'title'  => $this->title,
            'author' => $this->author,
        ]);

        $this->aiLoading = false;

        if (! $result) {
            $this->aiError = 'KI-Analyse nicht verfügbar (API-Key fehlt oder Fehler).';
            return;
        }

        $this->summary           = $result['summary']           ?? $this->summary;
        $this->target_group      = $result['target_group']      ?? $this->target_group;
        $this->age_recommendation = $result['age_recommendation'] ?? $this->age_recommendation;
        $this->practical_use     = $result['practical_use']     ?? $this->practical_use;

        if (! empty($result['tags'])) {
            $this->tagsInput = implode(', ', $result['tags']);
        }
    }

    public function save(): void
    {
        $this->validate([
            'type'    => ['required', 'in:' . implode(',', array_column(MediaType::cases(), 'value'))],
            'title'   => ['required', 'string', 'max:255'],
            'author'  => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year'    => ['nullable', 'integer', 'min:1800', 'max:' . (date('Y') + 1)],
            'isbn'    => ['nullable', 'string', 'max:20', 'unique:media,isbn,' . $this->media->id],
            'language' => ['nullable', 'string', 'max:5'],
            'status'  => ['required', 'in:' . implode(',', array_column(MediaStatus::cases(), 'value'))],
            'location' => ['nullable', 'string', 'max:100'],
            'loanDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'cover'   => ['nullable', 'image', 'max:4096'],
        ], [
            'title.required' => 'Bitte einen Titel eingeben.',
            'isbn.unique'    => 'Diese ISBN ist bereits bei einem anderen Medium vorhanden.',
            'year.min'       => 'Ungültiges Jahr.',
        ]);

        $coverPath = $this->media->cover_path;
        if ($this->cover) {
            $coverPath = app(CoverImageService::class)
                ->storeFromUpload($this->cover, $coverPath) ?? $coverPath;
        }

        $this->media->update([
            'type'              => $this->type,
            'title'             => $this->title,
            'author'            => $this->author ?: null,
            'publisher'         => $this->publisher ?: null,
            'year'              => $this->year ? (int) $this->year : null,
            'isbn'              => $this->isbn ?: null,
            'language'          => $this->language ?: 'de',
            'status'            => $this->status,
            'location'          => $this->location ?: null,
            'loan_days'         => $this->loanDays ? (int) $this->loanDays : null,
            'summary'           => $this->summary ?: null,
            'target_group'      => $this->target_group ?: null,
            'age_recommendation' => $this->age_recommendation ?: null,
            'practical_use'     => $this->practical_use ?: null,
            'cover_path'        => $coverPath,
        ]);

        // Sync tags
        $this->media->tags()->delete();
        $tags = array_filter(array_map('trim', explode(',', $this->tagsInput)));
        foreach ($tags as $tag) {
            MediaTag::create(['media_id' => $this->media->id, 'tag' => mb_substr($tag, 0, 80)]);
        }

        session()->flash('success', "{$this->media->title} wurde gespeichert.");
        $this->redirect(route('media.show', $this->media), navigate: true);
    }

    public function retire(): void
    {
        $this->media->update(['status' => MediaStatus::Ausgemustert]);
        session()->flash('success', "{$this->media->title} wurde ausgemustert.");
        $this->redirect(route('media.index'), navigate: true);
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('media.show', $media) }}" wire:navigate
           class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Medium bearbeiten</h1>
    </div>

    <form wire:submit="save" class="space-y-5">

        {{-- Basic fields --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Basisinformationen</h2>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medientyp</label>
                    <select wire:model="type" class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                        @foreach(\App\Enums\MediaType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model="status" class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                        @foreach(\App\Enums\MediaStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                <input wire:model="title" type="text"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Autor / Autorin</label>
                <input wire:model="author" type="text"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verlag</label>
                    <input wire:model="publisher" type="text"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jahr</label>
                    <input wire:model="year" type="number" min="1800" max="{{ date('Y') + 1 }}"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ISBN</label>
                    <input wire:model="isbn" type="text"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 font-mono">
                    @error('isbn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Standort</label>
                    <select wire:model="location"
                            class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">– kein Standort –</option>
                        @foreach(\App\Models\Location::ordered()->get() as $loc)
                            <option value="{{ $loc->name }}">{{ $loc->fullLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Ausleihzeit (Tage)
                        <span class="text-gray-400 font-normal">– leer = globale Einstellung</span>
                    </label>
                    <input wire:model="loanDays" type="number" min="1" max="365" placeholder="z. B. 7"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    @error('loanDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Schlagwörter</label>
                <input wire:model="tagsInput" type="text"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-gray-400">Kommagetrennt</p>
            </div>
        </div>

        {{-- Cover --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-3">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Cover</h2>

            @if($cover)
                <div class="flex items-center gap-4">
                    <img src="{{ $cover->temporaryUrl() }}" alt="Vorschau" class="h-32 rounded-lg shadow-xs object-cover">
                    <button type="button" wire:click="$set('cover', null)" class="text-xs text-red-600 hover:text-red-800">Entfernen</button>
                </div>
            @elseif($media->cover_path)
                <img src="{{ asset('storage/' . $media->cover_path) }}" alt="Aktuelles Cover" class="h-32 rounded-lg shadow-xs object-cover">
            @else
                <p class="text-sm text-gray-400">Kein Cover vorhanden.</p>
            @endif

            {{-- Fetch from ISBN --}}
            @if($isbn)
                <button type="button" wire:click="fetchCover" wire:loading.attr="disabled"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium disabled:opacity-50">
                    <span wire:loading.remove wire:target="fetchCover">Cover von ISBN laden</span>
                    <span wire:loading wire:target="fetchCover">Lädt Cover …</span>
                </button>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Eigenes Cover hochladen
                    <span class="text-gray-400 font-normal">(JPG/PNG, max. 4 MB)</span>
                </label>
                <input wire:model="cover" type="file" accept="image/*"
                       class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('cover') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- KI fields --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    KI-Analyse
                </h2>
                <button type="button" wire:click="enrichWithAi" wire:loading.attr="disabled"
                        class="text-xs text-purple-600 hover:text-purple-800 disabled:opacity-50 font-medium">
                    <span wire:loading.remove wire:target="enrichWithAi">Neu generieren</span>
                    <span wire:loading wire:target="enrichWithAi">Analysiert …</span>
                </button>
            </div>

            @if($aiError)
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">{{ $aiError }}</p>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zusammenfassung</label>
                <textarea wire:model="summary" rows="3"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zielgruppe</label>
                <input wire:model="target_group" type="text"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Altersempfehlung</label>
                <input wire:model="age_recommendation" type="text"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Praktischer Einsatz</label>
                <textarea wire:model="practical_use" rows="3"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
                <span wire:loading.remove wire:target="save">Änderungen speichern</span>
                <span wire:loading wire:target="save">Speichert …</span>
            </button>
            <a href="{{ route('media.show', $media) }}" wire:navigate
               class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>

            @if($media->status !== \App\Enums\MediaStatus::Ausgemustert)
                <button type="button"
                        wire:click="retire"
                        wire:confirm="Medium wirklich ausmustern? Dies kann in den Einstellungen rückgängig gemacht werden."
                        class="ml-auto text-sm text-red-600 hover:text-red-800 font-medium">
                    Ausmustern
                </button>
            @endif
        </div>

    </form>

    {{-- Internal code info --}}
    <p class="text-xs text-gray-400 text-center">Interne Nummer: <span class="font-mono">{{ $media->internal_code }}</span></p>

</div>
