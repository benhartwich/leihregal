<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Notifications\NewMediaNotification;
use App\Notifications\WishFulfilledNotification;
use App\Models\Media;
use App\Models\MediaEmbedding;
use App\Models\MediaTag;
use App\Models\TagSubscription;
use App\Models\Wish;
use App\Services\CoverImageService;
use App\Services\GoogleBooksService;
use App\Services\MediaAiService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // ── ISBN lookup state ──────────────────────────────────────────────────
    public string $isbnInput   = '';
    public bool   $isbnLoading = false;
    public ?string $isbnError  = null;

    // ── Form fields ────────────────────────────────────────────────────────
    public string  $type      = 'buch';
    public string  $title     = '';
    public string  $author    = '';
    public string  $publisher = '';
    public string  $year      = '';
    public string  $isbn      = '';
    public string  $language  = 'de';
    public string  $location  = '';
    public string  $tagsInput = '';  // comma-separated

    // AI-generated (editable)
    public string $summary          = '';
    public string $target_group     = '';
    public string $age_recommendation = '';
    public string $practical_use    = '';

    public bool   $aiLoading = false;
    public ?string $aiError  = null;

    // Cover
    public $cover = null;
    public ?string $coverUrlPreview = null;  // from Google Books

    public function mount(): void
    {
        if ($isbn = request('isbn')) {
            $this->isbnInput = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
            if ($this->isbnInput) {
                $this->lookupIsbn();
            }
        }
    }

    public function lookupIsbn(): void
    {
        $this->isbnError  = null;
        $isbn = trim($this->isbnInput);

        if (! $isbn) return;

        $this->isbnLoading = true;

        $service = app(GoogleBooksService::class);
        $data    = $service->lookupIsbn($isbn);

        $this->isbnLoading = false;

        if (! $data) {
            $this->isbnError = 'ISBN nicht gefunden. Bitte Titel und Autor manuell eingeben.';
            $this->isbn = $isbn;
            return;
        }

        // Cover found but no metadata (very small publisher)
        if (! $data['title']) {
            $this->isbnError = 'Kein Eintrag in Bibliotheksdatenbanken – bitte Titel und Autor manuell eingeben.';
            $this->isbn      = $isbn;
            $this->coverUrlPreview = $data['cover_url'] ?? null;
            return;
        }

        // Pre-fill form fields
        $this->isbn      = $isbn;
        $this->title     = $data['title']     ?? $this->title;
        $this->author    = $data['author']    ?? $this->author;
        $this->publisher = $data['publisher'] ?? $this->publisher;
        $this->year      = $data['year']      ? (string) $data['year'] : $this->year;
        $this->language  = $data['language']  ?? $this->language;
        $this->coverUrlPreview = $data['cover_url'] ?? null;

        // Auto-trigger AI enrichment only when no summary exists yet
        if ($this->title && ! $this->summary) {
            $this->dispatch('isbn-loaded', description: $data['description'] ?? '');
        }
    }

    public function enrichWithAi(string $description = ''): void
    {
        $this->aiError   = null;
        $this->aiLoading = true;

        $aiService = app(MediaAiService::class);
        $result    = $aiService->enrichMedia([
            'type'        => $this->type,
            'title'       => $this->title,
            'author'      => $this->author,
            'description' => $description,
        ]);

        $this->aiLoading = false;

        if (! $result) {
            $this->aiError = 'KI-Analyse nicht verfügbar (API-Key fehlt oder Fehler). Felder können manuell ausgefüllt werden.';
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
        // Determine copy_number if ISBN already exists
        $isbnNormalized = preg_replace('/[^0-9X]/', '', strtoupper($this->isbn));
        $copyNumber = 1;
        if ($isbnNormalized) {
            $maxCopy = Media::whereRaw("REPLACE(REPLACE(isbn,'-',''),' ','') = ?", [$isbnNormalized])
                ->max('copy_number');
            if ($maxCopy) {
                $copyNumber = $maxCopy + 1;
            }
        }

        $this->validate([
            'type'    => ['required', 'in:' . implode(',', array_column(MediaType::cases(), 'value'))],
            'title'   => ['required', 'string', 'max:255'],
            'author'  => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'year'    => ['nullable', 'integer', 'min:1800', 'max:' . (date('Y') + 1)],
            'isbn'    => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:5'],
            'location' => ['nullable', 'string', 'max:100'],
            'cover'   => ['nullable', 'image', 'max:4096'],
        ], [
            'title.required' => 'Bitte einen Titel eingeben.',
            'year.min'       => 'Ungültiges Jahr.',
            'cover.image'    => 'Nur Bilddateien erlaubt.',
            'cover.max'      => 'Bild darf max. 4 MB groß sein.',
        ]);

        // Store cover (resized via CoverImageService)
        $coverPath = null;
        $coverSvc  = app(CoverImageService::class);
        if ($this->cover) {
            $coverPath = $coverSvc->storeFromUpload($this->cover);
        } elseif ($this->coverUrlPreview) {
            $coverPath = $coverSvc->storeFromUrl($this->coverUrlPreview);
        }

        // Parse tags
        $tags = array_filter(
            array_map('trim', explode(',', $this->tagsInput))
        );

        // Create media record
        $media = Media::create([
            'type'              => $this->type,
            'title'             => $this->title,
            'author'            => $this->author ?: null,
            'publisher'         => $this->publisher ?: null,
            'year'              => $this->year ? (int) $this->year : null,
            'isbn'              => $this->isbn ?: null,
            'language'          => $this->language ?: 'de',
            'status'            => MediaStatus::Verfuegbar,
            'internal_code'     => Media::generateInternalCode(),
            'copy_number'       => $copyNumber,
            'summary'           => $this->summary ?: null,
            'target_group'      => $this->target_group ?: null,
            'age_recommendation' => $this->age_recommendation ?: null,
            'practical_use'     => $this->practical_use ?: null,
            'cover_path'        => $coverPath,
            'location'          => $this->location ?: null,
            'created_by'        => auth()->id(),
        ]);

        // Insert tags
        foreach ($tags as $tag) {
            MediaTag::create(['media_id' => $media->id, 'tag' => mb_substr($tag, 0, 80)]);
        }

        // Notify users subscribed to any of the media's tags
        if (! empty($tags)) {
            $subscribedUsers = TagSubscription::with('user')
                ->whereIn('tag', $tags)
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->reject(fn ($u) => $u->id === auth()->id()); // don't notify creator

            foreach ($subscribedUsers as $subscriber) {
                if ($subscriber) {
                    try {
                        $subscriber->notify(new NewMediaNotification($media, $subscriber));
                    } catch (\Throwable) {}
                }
            }
        }

        // Notify wishers whose wish matches this new medium (by ISBN, or fuzzy by title)
        $matchingWishes = collect();
        if ($media->isbn) {
            $matchingWishes = Wish::with('user')
                ->where('isbn', $media->isbn)
                ->whereIn('status', ['eingereicht', 'angenommen', 'beobachten'])
                ->get();
        }
        if ($matchingWishes->isEmpty() && $media->title) {
            $matchingWishes = Wish::with('user')
                ->where('title', 'like', '%' . $media->title . '%')
                ->whereIn('status', ['eingereicht', 'angenommen', 'beobachten'])
                ->get();
        }
        foreach ($matchingWishes as $wish) {
            if ($wish->user) {
                try {
                    $wish->user->notify(new WishFulfilledNotification($wish, $media));
                } catch (\Throwable) {}
            }
            $wish->update(['status' => 'angenommen']);
        }

        // Generate & store embedding (best-effort, non-blocking)
        $aiService = app(MediaAiService::class);
        $text      = $aiService->buildEmbeddingText([
            'title'        => $this->title,
            'author'       => $this->author,
            'summary'      => $this->summary,
            'target_group' => $this->target_group,
            'practical_use' => $this->practical_use,
            'tags'         => $tags,
        ]);
        try {
            $embedding = $aiService->generateEmbedding($text);
            if ($embedding) {
                \Illuminate\Support\Facades\DB::statement(
                    'INSERT INTO media_embeddings (media_id, embedding, updated_at) VALUES (?, UNHEX(?), NOW())
                     ON DUPLICATE KEY UPDATE embedding = VALUES(embedding), updated_at = NOW()',
                    [$media->id, bin2hex($embedding)]
                );
            }
        } catch (\Throwable) {}

        session()->flash('success', "{$media->title} wurde hinzugefügt.");
        $this->redirect(route('media.show', $media), navigate: true);
    }
}; ?>

<div class="space-y-5"
     x-data="{}"
     @isbn-loaded.window="(e) => $wire.enrichWithAi(e.detail.description || '')">

    <h1 class="text-xl font-semibold text-gray-900">Medium hinzufügen</h1>

    {{-- ── ISBN Quick-Fill ────────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 space-y-3"
         x-data="isbnScanner($wire)">

        <p class="text-sm font-medium text-blue-800">ISBN-Schnellerfassung</p>

        {{-- Input row --}}
        <div class="flex gap-2">
            <div class="relative flex-1">
                <input wire:model="isbnInput" type="text" inputmode="numeric"
                       placeholder="ISBN-10 oder ISBN-13"
                       class="w-full rounded-xl border-blue-200 text-sm py-2.5 pr-10 focus:ring-blue-500 focus:border-blue-500"
                       x-on:keydown.enter="$wire.lookupIsbn()">
                {{-- Camera button inlined --}}
                <button type="button" @click="toggleCamera()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-lg transition-colors"
                        :class="scanning ? 'text-red-500' : 'text-blue-400 hover:text-blue-600'"
                        title="Barcode scannen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 9V6a1 1 0 011-1h3M3 15v3a1 1 0 001 1h3m11-4v3a1 1 0 01-1 1h-3m4-13h-3a1 1 0 00-1 1v3M7 7h10M7 12h10M7 17h10"/>
                    </svg>
                </button>
            </div>

            <button wire:click="lookupIsbn" wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors whitespace-nowrap shrink-0">
                <span wire:loading.remove wire:target="lookupIsbn">Suchen</span>
                <span wire:loading wire:target="lookupIsbn">Lädt …</span>
            </button>
        </div>

        {{-- Camera preview --}}
        <div x-show="scanning" class="relative bg-black rounded-xl overflow-hidden">
            <video id="isbn-scan-video" class="w-full max-h-48 object-cover" autoplay muted playsinline></video>

            {{-- Viewfinder: corner brackets + animated scan line --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="relative w-56 h-20">
                    {{-- Corner brackets --}}
                    <span class="absolute top-0 left-0 w-5 h-5 border-t-2 border-l-2 border-blue-400 rounded-tl"></span>
                    <span class="absolute top-0 right-0 w-5 h-5 border-t-2 border-r-2 border-blue-400 rounded-tr"></span>
                    <span class="absolute bottom-0 left-0 w-5 h-5 border-b-2 border-l-2 border-blue-400 rounded-bl"></span>
                    <span class="absolute bottom-0 right-0 w-5 h-5 border-b-2 border-r-2 border-blue-400 rounded-br"></span>
                    {{-- Animated scan line --}}
                    <div class="absolute left-1 right-1 h-0.5 bg-blue-400 opacity-80 rounded-sm animate-scan-line"></div>
                </div>
            </div>

            <p class="absolute bottom-2 left-0 right-0 text-center text-white text-xs opacity-75">
                Barcode-Etikett in den Rahmen halten
            </p>
        </div>
        <style>
            @keyframes scan-line {
                0%   { top: 4px; }
                50%  { top: calc(100% - 6px); }
                100% { top: 4px; }
            }
            .animate-scan-line { animation: scan-line 2s ease-in-out infinite; position: absolute; }
        </style>

        {{-- Cover preview after lookup --}}
        @if($coverUrlPreview && !$cover)
            <div class="flex items-center gap-4 bg-white rounded-xl p-3 border border-blue-100">
                <img src="{{ $coverUrlPreview }}" alt="Cover"
                     class="h-20 w-14 object-cover rounded-lg shadow-xs shrink-0">
                <div class="text-sm text-blue-700">
                    <p class="font-medium">Cover gefunden</p>
                    <p class="text-xs text-blue-500 mt-0.5">Wird beim Speichern automatisch übernommen.</p>
                </div>
            </div>
        @endif

        @if($isbnError)
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">{{ $isbnError }}</p>
        @endif
        <p class="text-xs text-blue-600">ISBN eintippen oder Kamera-Icon zum Scannen nutzen — Daten werden automatisch ausgefüllt.</p>
    </div>

    <form wire:submit="save" class="space-y-5">

        {{-- ── Basic fields ────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Basisinformationen</h2>

            {{-- Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Medientyp <span class="text-red-500">*</span></label>
                <select wire:model="type" class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    @foreach(\App\Enums\MediaType::cases() as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Title --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Titel <span class="text-red-500">*</span></label>
                <input wire:model="title" type="text" placeholder="Titel des Mediums"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Author --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Autor / Autorin</label>
                <input wire:model="author" type="text" placeholder="z. B. Max Mustermann"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Publisher --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verlag</label>
                    <input wire:model="publisher" type="text"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Year --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jahr</label>
                    <input wire:model="year" type="number" min="1800" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    @error('year') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- ISBN --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ISBN</label>
                    <input wire:model="isbn" type="text" placeholder="978-3-…"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 font-mono">
                    @error('isbn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Location --}}
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
            </div>

            {{-- Tags --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Schlagwörter</label>
                <input wire:model="tagsInput" type="text" placeholder="Kommagetrennt: Trauma, Resilienz, Bindung …"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-gray-400">Kommagetrennte Schlagwörter</p>
            </div>
        </div>

        {{-- ── Cover ──────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-3">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Cover</h2>

            @if($coverUrlPreview && ! $cover)
                <div class="flex items-center gap-4">
                    <img src="{{ $coverUrlPreview }}" alt="Cover Vorschau" class="h-32 rounded-lg shadow-xs object-cover">
                    <p class="text-xs text-gray-500">Cover von Google Books – wird beim Speichern übernommen.</p>
                </div>
            @endif

            @if($cover)
                <div class="flex items-center gap-4">
                    <img src="{{ $cover->temporaryUrl() }}" alt="Vorschau" class="h-32 rounded-lg shadow-xs object-cover">
                    <button type="button" wire:click="$set('cover', null)" class="text-xs text-red-600 hover:text-red-800">Entfernen</button>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Bild hochladen
                    <span class="text-gray-400 font-normal">(JPG/PNG, max. 4 MB)</span>
                </label>
                <input wire:model="cover" type="file" accept="image/*"
                       class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('cover') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- ── KI-Analyse ──────────────────────────────────────────────────── --}}
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

            <div wire:loading wire:target="enrichWithAi"
                 class="text-sm text-purple-600 bg-purple-50 rounded-lg px-3 py-2 flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                KI analysiert das Medium …
            </div>

            {{-- Summary --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zusammenfassung</label>
                <textarea wire:model="summary" rows="3" placeholder="Kurze Beschreibung für Betreuer:innen …"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>

            {{-- Target group --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zielgruppe</label>
                <input wire:model="target_group" type="text" placeholder="z. B. Jugendliche ab 12 Jahren"
                       class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Age recommendation --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Altersempfehlung</label>
                    <input wire:model="age_recommendation" type="text" placeholder="z. B. 8–12 Jahre"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            {{-- Practical use --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Praktischer Einsatz</label>
                <textarea wire:model="practical_use" rows="3" placeholder="Wie kann dieses Medium in der sozialpädagogischen Arbeit eingesetzt werden?"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors w-full sm:w-auto">
                <span wire:loading.remove wire:target="save">Medium speichern</span>
                <span wire:loading wire:target="save">Speichert …</span>
            </button>
            <a href="{{ route('media.index') }}" wire:navigate
               class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
        </div>

    </form>
</div>

<script>
function isbn10to13(isbn10) {
    const body = '978' + isbn10.substring(0, 9);
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(body[i]) * (i % 2 === 0 ? 1 : 3);
    }
    const check = (10 - (sum % 10)) % 10;
    return body + check;
}

window.isbnScanner = function($wire) {
    return {
        scanning: false,
        controls: null,

        async toggleCamera() {
            this.scanning ? this.stopCamera() : await this.startCamera();
        },

        async startCamera() {
            if (! window.BrowserMultiFormatReader) {
                alert('Scanner nicht verfügbar. Bitte Seite neu laden.');
                return;
            }
            try {
                const hints = new Map();
                hints.set(2, [7, 6]); // POSSIBLE_FORMATS = 2, EAN_13 = 7, EAN_8 = 6
                hints.set(3, true);   // TRY_HARDER = 3

                const reader = new window.BrowserMultiFormatReader(hints);
                const video  = document.getElementById('isbn-scan-video');
                this.scanning = true;

                this.controls = await reader.decodeFromConstraints(
                    { video: { facingMode: 'environment', width: { ideal: 1280 } } },
                    video,
                    (result, err) => {
                        if (result) {
                            let code = result.getText().replace(/\D/g, '');
                            const fmt = result.getBarcodeFormat();
                            const EAN_13 = 7, EAN_8 = 6;

                            // Convert ISBN-10 to ISBN-13
                            if (code.length === 10) {
                                code = isbn10to13(code);
                            }

                            if ((fmt === EAN_13 || fmt === EAN_8) && /^97[89]/.test(code)) {
                                this.stopCamera();
                                $wire.set('isbnInput', code).then(() => $wire.lookupIsbn());
                            }
                        }
                    }
                );
            } catch (e) {
                alert('Kamera konnte nicht gestartet werden: ' + e.message);
                this.scanning = false;
            }
        },

        stopCamera() {
            if (this.controls) {
                try { this.controls.stop(); } catch (_) {}
                this.controls = null;
            }
            this.scanning = false;
        },

        destroy() { this.stopCamera(); }
    };
};
</script>
