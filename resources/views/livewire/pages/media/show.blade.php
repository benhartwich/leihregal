<?php

use App\Enums\MediaStatus;
use App\Models\Loan;
use App\Models\Media;
use App\Models\MediaBookmark;
use App\Models\MediaReview;
use App\Models\Reservation;
use App\Services\LoanService;
use App\Services\PiiFilterService;
use App\Services\SimilarMediaService;
use App\Services\UsageAdvisorService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Media   $media;
    public ?string $toast      = null;
    public bool    $toastOk    = true;
    public bool    $bookmarked = false;

    // Review form
    public bool    $showReviewForm = false;
    public int     $reviewRating   = 0;
    public string  $reviewText     = '';
    public string  $reviewTakeaway = '';

    // Dated reservation form
    public bool   $showDateForm   = false;
    public string $reserveFrom    = '';
    public string $reserveUntil   = '';
    public string $reserveNotes   = '';

    // Einsatz-Assistent (Phase 8)
    public bool    $showAdvisor      = false;
    public string  $advisorSituation = '';
    public ?string $advisorAnswer    = null;
    public ?string $advisorError     = null;
    public bool    $advisorRedacted  = false;
    public array   $advisorPiiTypes  = [];
    public bool    $advisorLoading   = false;

    public function mount(Media $media): void
    {
        $this->media = $media->load('tags', 'creator');

        $this->bookmarked = MediaBookmark::where('user_id', auth()->id())
            ->where('media_id', $media->id)
            ->exists();

        $this->reserveFrom  = today()->toDateString();
        $this->reserveUntil = today()->addDays(14)->toDateString();

        // Pre-fill if user has existing review
        $existing = MediaReview::where('media_id', $media->id)
            ->where('user_id', auth()->id())
            ->first();
        if ($existing) {
            $this->reviewRating   = $existing->rating   ?? 0;
            $this->reviewText     = $existing->review   ?? '';
            $this->reviewTakeaway = $existing->takeaway ?? '';
        }
    }

    public function toggleBookmark(): void
    {
        $userId  = auth()->id();
        $mediaId = $this->media->id;

        if ($this->bookmarked) {
            MediaBookmark::where('user_id', $userId)->where('media_id', $mediaId)->delete();
            $this->bookmarked = false;
        } else {
            MediaBookmark::firstOrCreate(['user_id' => $userId, 'media_id' => $mediaId]);
            $this->bookmarked = true;
        }
    }

    public function saveReview(): void
    {
        $this->validate([
            'reviewRating'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'reviewText'     => ['nullable', 'string', 'max:2000'],
            'reviewTakeaway' => ['nullable', 'string', 'max:2000'],
        ]);

        MediaReview::updateOrCreate(
            ['media_id' => $this->media->id, 'user_id' => auth()->id()],
            [
                'rating'   => $this->reviewRating   ?: null,
                'review'   => $this->reviewText      ?: null,
                'takeaway' => $this->reviewTakeaway  ?: null,
            ]
        );

        $this->showReviewForm = false;
        $this->toast   = 'Deine Bewertung wurde gespeichert.';
        $this->toastOk = true;
    }

    public function deleteReview(): void
    {
        MediaReview::where('media_id', $this->media->id)
            ->where('user_id', auth()->id())
            ->delete();

        $this->reviewRating   = 0;
        $this->reviewText     = '';
        $this->reviewTakeaway = '';
        $this->showReviewForm = false;
        $this->toast   = 'Bewertung gelöscht.';
        $this->toastOk = true;
    }

    public function borrow(): void
    {
        try {
            $loan = app(LoanService::class)->borrow($this->media, auth()->user());
            session()->flash('success', "{$this->media->title} ausgeliehen bis " . $loan->due_at->format('d.m.Y') . '.');
            $this->redirect(route('media.show', $this->media), navigate: true);
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
            $this->media->refresh();
        }
    }

    public function reserve(): void
    {
        try {
            $res = app(LoanService::class)->reserve($this->media, auth()->user());
            $this->toast   = "Reserviert (Position {$res->position} in der Warteschlange).";
            $this->toastOk = true;
            $this->media->refresh();
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
        }
    }

    public function reserveWithDate(): void
    {
        $this->validate([
            'reserveFrom'  => ['required', 'date', 'after_or_equal:today'],
            'reserveUntil' => ['required', 'date', 'after_or_equal:reserveFrom'],
        ]);

        try {
            app(LoanService::class)->reserveWithDates(
                $this->media,
                auth()->user(),
                $this->reserveFrom,
                $this->reserveUntil,
                $this->reserveNotes,
            );
            $this->showDateForm = false;
            $this->reserveNotes = '';
            $this->toast   = 'Urlaubsreservierung gespeichert.';
            $this->toastOk = true;
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
        }
    }

    public function cancelMyReservation(int $reservationId): void
    {
        $reservation = Reservation::find($reservationId);
        if (! $reservation || $reservation->user_id !== auth()->id()) return;

        try {
            app(LoanService::class)->cancelReservation($reservation, auth()->user());
            $this->toast   = 'Reservierung wurde storniert.';
            $this->toastOk = true;
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
        }
    }

    public function addCopy(): void
    {
        if (! auth()->user()->isKurator()) return;

        $copy = $this->media->replicate();
        $copy->internal_code = Media::generateInternalCode();
        $copy->status        = MediaStatus::Verfuegbar;
        $copy->copy_number   = (Media::where('isbn', $this->media->isbn)
            ->whereNotNull('isbn')
            ->where('isbn', '!=', '')
            ->max('copy_number') ?? 1) + 1;
        $copy->save();

        foreach ($this->media->tags as $tag) {
            $copy->tags()->create(['tag' => $tag->tag]);
        }

        $this->redirect(route('media.show', $copy), navigate: true);
    }

    /**
     * Einsatz-Assistent: Wie lässt sich genau dieses Medium in der
     * geschilderten Situation einsetzen?
     */
    public function askAdvisor(): void
    {
        $this->advisorAnswer = null;
        $this->advisorError  = null;

        $situation = trim($this->advisorSituation);

        if (mb_strlen($situation) < 15) {
            $this->advisorError = 'Bitte beschreiben Sie die Situation etwas ausführlicher.';
            return;
        }

        // Eigene Bremse: Die Seite hängt nicht an der throttle:ai-Route,
        // der Aufruf kostet aber genauso Kontingent wie der Assistent.
        $schluessel = 'einsatz-assistent:' . auth()->id();

        if (RateLimiter::tooManyAttempts($schluessel, 10)) {
            $this->advisorError = 'Zu viele Anfragen. Bitte in '
                . RateLimiter::availableIn($schluessel) . ' Sekunden erneut versuchen.';
            return;
        }

        RateLimiter::hit($schluessel, 60);

        // Vor dem Versand durch den PII-Filter – dieselbe Regel wie im
        // Situations-Assistenten (Spec 5).
        $pii = app(PiiFilterService::class)->filter($situation);

        $this->advisorRedacted = $pii['redacted'];
        $this->advisorPiiTypes = $pii['types'];

        $ergebnis = app(UsageAdvisorService::class)->beraten($this->media, $pii['text']);

        $this->advisorAnswer = $ergebnis['text'];
        $this->advisorError  = $ergebnis['fehler'];
    }

    public function with(): array
    {
        $userId = auth()->id();
        return [
            'myActiveLoan' => Loan::where('media_id', $this->media->id)
                ->where('user_id', $userId)
                ->whereNull('returned_at')
                ->first(),
            'myReservation' => Reservation::where('media_id', $this->media->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['wartend', 'bereit'])
                ->first(),
            'queueLength' => Reservation::where('media_id', $this->media->id)
                ->whereIn('status', ['wartend', 'bereit'])
                ->count(),
            'similar' => app(SimilarMediaService::class)->findSimilar($this->media, limit: 4),
            'reviews' => MediaReview::where('media_id', $this->media->id)
                ->with('user')
                ->latest()
                ->get(),
            'myReview' => MediaReview::where('media_id', $this->media->id)
                ->where('user_id', $userId)
                ->first(),
            'hasBorrowedBefore' => Loan::where('media_id', $this->media->id)
                ->where('user_id', $userId)
                ->exists(),
            'myDatedReservation' => Reservation::where('media_id', $this->media->id)
                ->where('user_id', $userId)
                ->whereNotNull('reserved_from')
                ->whereIn('status', ['wartend', 'bereit'])
                ->first(),
            'datedReservations' => Reservation::where('media_id', $this->media->id)
                ->whereNotNull('reserved_from')
                ->whereIn('status', ['wartend', 'bereit'])
                ->with('user')
                ->orderBy('reserved_from')
                ->get(),
        ];
    }
}; ?>

<div class="space-y-4">

    {{-- Back link --}}
    <a href="{{ route('media.index') }}" wire:navigate
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zur Mediathek
    </a>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="md:flex">

            {{-- Cover --}}
            <div class="md:w-48 md:shrink-0 bg-gray-50 flex items-start justify-center p-6">
                @if($media->cover_path)
                    <img src="{{ asset('storage/' . $media->cover_path) }}"
                         alt="{{ $media->title }}"
                         class="w-full max-w-[160px] rounded-xl shadow-xs object-cover">
                @else
                    <div class="w-32 h-44 bg-gray-100 rounded-xl flex items-center justify-center text-5xl text-gray-300">
                        {{ $media->type->icon() }}
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="flex-1 p-6 space-y-4">
                <div class="flex flex-wrap items-start gap-2">
                    <span class="inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $media->type->badgeClass() }}">
                        {{ $media->type->label() }}
                    </span>
                    <span class="inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $media->status->badgeClass() }}">
                        {{ $media->status->label() }}
                    </span>
                    @if($hasBorrowedBefore)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-sm text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            schon ausgeliehen
                        </span>
                    @endif
                </div>

                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $media->title }}</h1>
                    @if($media->author)
                        <p class="text-sm text-gray-600 mt-0.5">{{ $media->author }}</p>
                    @endif
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                    @if($media->publisher)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Verlag</dt>
                            <dd class="text-gray-700">{{ $media->publisher }}</dd>
                        </div>
                    @endif
                    @if($media->year)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Jahr</dt>
                            <dd class="text-gray-700">{{ $media->year }}</dd>
                        </div>
                    @endif
                    @if($media->isbn)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">ISBN</dt>
                            <dd class="text-gray-700 font-mono text-xs">{{ $media->isbn }}</dd>
                        </div>
                    @endif
                    @if($media->age_recommendation)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Altersempfehlung</dt>
                            <dd class="text-gray-700">{{ $media->age_recommendation }}</dd>
                        </div>
                    @endif
                    @if($media->location)
                        <div>
                            <dt class="text-xs text-gray-400 font-medium">Standort</dt>
                            <dd class="text-gray-700">
                                @php $locModel = \App\Models\Location::where('name', $media->location)->first(); @endphp
                                {{ $locModel ? $locModel->fullLabel() : $media->location }}
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-400 font-medium">Medien-Nr.</dt>
                        <dd class="text-gray-700 font-mono text-xs">{{ $media->internal_code }}</dd>
                    </div>
                </dl>

                {{-- Tags --}}
                @if($media->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($media->tags as $tag)
                            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $tag->tag }}</span>
                        @endforeach
                    </div>
                @endif

                {{-- Toast --}}
                @if($toast)
                    <div x-data="{ show: true }" x-show="show"
                         x-init="setTimeout(() => { show = false; $wire.set('toast', null) }, 4000)"
                         class="px-3 py-2 rounded-lg text-sm
                                {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
                        {{ $toast }}
                    </div>
                @endif

                {{-- Ausleihe / Reservierung Actions --}}
                @php
                    $canBorrow  = $media->status->value === 'verfuegbar' && ! $myActiveLoan;
                    $canReserve = in_array($media->status->value, ['ausgeliehen', 'reserviert']) && ! $myActiveLoan && ! $myReservation;
                @endphp

                @if($myActiveLoan)
                    <a href="{{ route('loans.return', $myActiveLoan) }}" wire:navigate
                       class="inline-flex items-center gap-2 bg-gray-800 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-900 transition-colors">
                        Zurückgeben (fällig {{ $myActiveLoan->due_at->format('d.m.Y') }})
                    </a>
                @elseif($myReservation)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex px-3 py-2 rounded-xl text-sm font-medium {{ $myReservation->status->badgeClass() }}">
                            {{ $myReservation->status->label() }}
                        </span>
                        @if($myReservation->status->value === 'wartend')
                            <span class="text-xs text-gray-400">Position {{ $myReservation->position }}</span>
                        @endif
                    </div>
                @elseif($canBorrow)
                    <button wire:click="borrow" dusk="ausleihen"
                            class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ausleihen ({{ app(\App\Services\LoanService::class)->loanDaysFor($media) }} Tage)
                    </button>
                @elseif($canReserve)
                    <button wire:click="reserve" dusk="reservieren"
                            class="inline-flex items-center gap-2 bg-yellow-500 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-yellow-600 transition-colors">
                        Reservieren
                        @if($queueLength > 0)
                            <span class="text-xs opacity-80">({{ $queueLength }} in Warteschlange)</span>
                        @endif
                    </button>
                @endif

                {{-- Bookmark button --}}
                <div class="pt-1">
                    <button wire:click="toggleBookmark"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border transition-colors
                                   {{ $bookmarked
                                       ? 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100'
                                       : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100' }}">
                        @if($bookmarked)
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                            </svg>
                            Vorgemerkt
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                            </svg>
                            Vormerken
                        @endif
                    </button>
                </div>

                {{-- Dated reservation --}}
                @if(!$myDatedReservation && !$myActiveLoan)
                    <div class="pt-1">
                        @if(!$showDateForm)
                            <button wire:click="$set('showDateForm', true)"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Für Urlaub vormerken
                            </button>
                        @else
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
                                <p class="text-sm font-medium text-amber-900">Zeitraum reservieren</p>
                                <div class="flex gap-3 flex-wrap">
                                    <div class="flex-1 min-w-[120px]">
                                        <label class="block text-xs text-gray-500 mb-1">Von</label>
                                        <input type="date" wire:model="reserveFrom"
                                               min="{{ today()->toDateString() }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        @error('reserveFrom') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex-1 min-w-[120px]">
                                        <label class="block text-xs text-gray-500 mb-1">Bis</label>
                                        <input type="date" wire:model="reserveUntil"
                                               min="{{ today()->toDateString() }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        @error('reserveUntil') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Notiz (optional)</label>
                                    <input type="text" wire:model="reserveNotes" placeholder="z.B. Sommercamp Juli"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="reserveWithDate"
                                            class="bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-600">
                                        Reservieren
                                    </button>
                                    <button wire:click="$set('showDateForm', false)"
                                            class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
                                        Abbrechen
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @elseif($myDatedReservation)
                    <div class="pt-1">
                        <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-amber-900">Reserviert für Urlaub</p>
                                <p class="text-xs text-amber-700">{{ $myDatedReservation->reserved_from->format('d.m.Y') }} – {{ $myDatedReservation->reserved_until->format('d.m.Y') }}</p>
                            </div>
                            <button wire:click="cancelMyReservation({{ $myDatedReservation->id }})"
                                    wire:confirm="Urlaubsreservierung stornieren?"
                                    class="text-xs text-red-500 hover:text-red-700 shrink-0">
                                Stornieren
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Edit / Barcode for kurators/admins --}}
                @if(auth()->user()->isKurator())
                    <div class="pt-1 flex flex-wrap items-center gap-4">
                        <a href="{{ route('media.edit', $media) }}" wire:navigate
                           class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Bearbeiten
                        </a>
                        <a href="{{ route('media.barcode', $media) }}"
                           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 4v1m6.364 1.636l-.707.707M20 12h1M17.657 17.657l-.707.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l.707.707"/>
                            </svg>
                            QR-Etikett
                        </a>
                        @if($media->isbn)
                            <button wire:click="addCopy"
                                    wire:confirm="Ein weiteres Exemplar anlegen?"
                                    class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:text-emerald-800 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Weiteres Exemplar
                            </button>
                        @endif
                    </div>

                    {{-- Upcoming dated reservations (kurator view) --}}
                    @if($datedReservations->isNotEmpty())
                        <div class="mt-2 border border-dashed border-amber-300 rounded-xl px-4 py-3 space-y-2">
                            <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Urlaubsreservierungen</p>
                            @foreach($datedReservations as $dr)
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-gray-800">{{ $dr->user->name }}</span>
                                    <span class="text-gray-500 text-xs">{{ $dr->reserved_from->format('d.m.Y') }} – {{ $dr->reserved_until->format('d.m.Y') }}</span>
                                    @if($dr->notes)
                                        <span class="text-gray-400 text-xs italic">· {{ $dr->notes }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- AI fields --}}
        @if($media->summary || $media->target_group || $media->practical_use)
            <div class="border-t border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    KI-Analyse
                </h2>

                @if($media->summary)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Zusammenfassung</p>
                        <p class="text-sm text-gray-700">{{ $media->summary }}</p>
                    </div>
                @endif

                @if($media->target_group)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Zielgruppe</p>
                        <p class="text-sm text-gray-700">{{ $media->target_group }}</p>
                    </div>
                @endif

                @if($media->practical_use)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Praktischer Einsatz</p>
                        <p class="text-sm text-gray-700">{{ $media->practical_use }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Kaufen-Links --}}
    @if($media->isbn || $media->title)
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Kaufen / Nachbestellen</p>
            <div class="flex flex-wrap gap-2">
                @php
                    $searchQuery = urlencode(($media->isbn ?: $media->title . ' ' . ($media->author ?? '')));
                    $isbnQuery   = $media->isbn ? urlencode(preg_replace('/[^0-9]/', '', $media->isbn)) : $searchQuery;
                @endphp
                <a href="https://www.amazon.de/s?k={{ $isbnQuery }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm font-medium hover:bg-amber-100 transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M.045 18.02c.072-.116.187-.124.348-.022 3.636 2.11 7.594 3.166 11.87 3.166 2.852 0 5.668-.533 8.447-1.595l.315-.14c.138-.06.234-.1.293-.13.226-.088.39-.046.525.13.12.174.09.336-.12.48-.256.19-.6.41-1.006.654-1.244.75-2.586 1.344-4.02 1.78C15.6 22.946 14.003 23.16 12.327 23.16c-2.932 0-5.67-.717-8.215-2.148C3.1 20.56 2.017 19.852 1.1 19.046c-.317-.298-.366-.565-.117-.82.25-.254.524-.213.756.053.085.098.186.198.306.292l.13-.044-.13-.047v.04zm23.92-3.5c-.17-.263-.485-.38-.94-.35-1.6.11-3.14.048-4.622-.193-1.48-.24-2.743-.638-3.79-1.195-.316-.17-.616-.18-.9-.027-.283.15-.354.396-.21.74.19.453.527.86 1.01 1.22.483.36 1.065.686 1.745.977.896.385 1.894.665 2.99.84a18.8 18.8 0 003.29.196c.42-.01.74-.08.963-.21.223-.128.345-.295.368-.5.022-.204-.064-.41-.257-.62l.353-.878zm-3.92-8.11c0 1.87-.653 3.418-1.957 4.643-1.305 1.224-3.04 1.837-5.205 1.837-1.38 0-2.637-.33-3.77-.99C7.96 11.24 7.104 10.353 6.52 9.24c-.585-1.113-.878-2.35-.878-3.71 0-1.87.648-3.42 1.944-4.64C8.882.67 10.613.06 12.776.06c1.374 0 2.63.33 3.766.99 1.135.66 2.004 1.55 2.607 2.666.603 1.118.904 2.35.904 3.694z"/>
                    </svg>
                    Amazon
                </a>
                <a href="https://www.thalia.de/suche?sq={{ $searchQuery }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl text-sm font-medium hover:bg-blue-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Thalia
                </a>
            </div>
        </div>
    @endif

    {{-- Einsatz-Assistent --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <button wire:click="$toggle('showAdvisor')" type="button"
                class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <h2 class="text-sm font-semibold text-gray-700">Wie setze ich das ein?</h2>
            </div>
            <svg class="w-4 h-4 text-gray-400 transition-transform {{ $showAdvisor ? 'rotate-180' : '' }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        @if($showAdvisor)
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 pt-4">
                <p class="text-xs text-gray-500">
                    Beschreiben Sie die Situation – Sie erhalten konkrete Vorschläge,
                    wie sich <strong>{{ $media->title }}</strong> darin einsetzen lässt.
                </p>

                <div class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-xs text-amber-800">
                    Bitte keine echten Klientendaten. Erkannte personenbezogene Angaben
                    werden vor dem Versand automatisch entfernt.
                </div>

                <textarea wire:model="advisorSituation" rows="3"
                          placeholder="z. B. Ein Kind zieht sich nach Streit stundenlang zurück und spricht nicht darüber."
                          class="w-full rounded-xl border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500"></textarea>

                <button wire:click="askAdvisor" wire:loading.attr="disabled" type="button"
                        class="bg-purple-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium
                               hover:bg-purple-700 disabled:opacity-50 transition-colors">
                    <span wire:loading.remove wire:target="askAdvisor">Vorschläge erstellen</span>
                    <span wire:loading wire:target="askAdvisor">Denke nach …</span>
                </button>

                @if($advisorError)
                    <div class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
                        {{ $advisorError }}
                    </div>
                @endif

                @if($advisorRedacted && $advisorAnswer)
                    <div class="bg-blue-50 border border-blue-200 rounded-xl px-3 py-2 text-xs text-blue-800">
                        Entfernt vor dem Versand: {{ implode(', ', $advisorPiiTypes) }}
                    </div>
                @endif

                @if($advisorAnswer)
                    <div class="bg-purple-50/60 border border-purple-100 rounded-xl px-4 py-3
                                text-sm text-gray-800 whitespace-pre-line leading-relaxed">{{ $advisorAnswer }}</div>
                    <p class="text-xs text-gray-400">
                        KI-erzeugter Vorschlag – bitte fachlich prüfen.
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- Bewertungen & Rezensionen --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-700">Bewertungen</h2>
                @if($reviews->isNotEmpty())
                    @php
                        $avg = round($reviews->whereNotNull('rating')->avg('rating'), 1);
                        $count = $reviews->whereNotNull('rating')->count();
                    @endphp
                    @if($count > 0)
                        <span class="flex items-center gap-1 text-sm text-amber-500 font-medium">
                            ★ {{ number_format($avg, 1) }}
                            <span class="text-gray-400 font-normal text-xs">({{ $count }})</span>
                        </span>
                    @endif
                @endif
            </div>
            @if($hasBorrowedBefore && !$showReviewForm)
                <button wire:click="$set('showReviewForm', true)"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    {{ $myReview ? 'Bearbeiten' : '+ Bewertung schreiben' }}
                </button>
            @endif
        </div>

        {{-- Review form --}}
        @if($showReviewForm)
            <div class="px-5 py-4 bg-blue-50 border-b border-blue-100 space-y-4">
                {{-- Star rating --}}
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">Bewertung</p>
                    <div class="flex gap-1" x-data="{ hover: 0 }">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    @mouseenter="hover = {{ $i }}"
                                    @mouseleave="hover = 0"
                                    wire:click="$set('reviewRating', {{ $i }})"
                                    class="text-2xl transition-colors"
                                    :class="(hover >= {{ $i }} || (!hover && {{ '$reviewRating' }} >= {{ $i }})) ? 'text-amber-400' : 'text-gray-300'">
                                ★
                            </button>
                        @endfor
                        @if($reviewRating)
                            <button wire:click="$set('reviewRating', 0)" class="ml-2 text-xs text-gray-400 hover:text-gray-600 self-center">
                                zurücksetzen
                            </button>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Rezension</label>
                    <textarea wire:model="reviewText" rows="3" placeholder="Dein Eindruck zum Buch …"
                              class="w-full rounded-xl border-gray-300 text-sm py-2 resize-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Was ist hängen geblieben?</label>
                    <textarea wire:model="reviewTakeaway" rows="3"
                              placeholder="Welche Geschichte, Erkenntnis oder Methode hast du mitgenommen? …"
                              class="w-full rounded-xl border-gray-300 text-sm py-2 resize-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="flex gap-2">
                    <button wire:click="saveReview"
                            class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700">
                        Speichern
                    </button>
                    <button wire:click="$set('showReviewForm', false)"
                            class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
                        Abbrechen
                    </button>
                    @if($myReview)
                        <button wire:click="deleteReview"
                                wire:confirm="Bewertung wirklich löschen?"
                                class="text-sm text-red-500 hover:text-red-700 px-3 py-2 ml-auto">
                            Löschen
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Review list --}}
        @if($reviews->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-400">
                @if($hasBorrowedBefore)
                    Noch keine Bewertungen. Du hast dieses Medium ausgeliehen — schreib die erste!
                @else
                    Noch keine Bewertungen vorhanden.
                @endif
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($reviews as $review)
                    <div class="px-5 py-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-700 shrink-0">
                                {{ mb_substr($review->user->name, 0, 1) }}
                            </span>
                            <span class="text-sm font-medium text-gray-800">{{ $review->user->name }}</span>
                            @if($review->rating)
                                <span class="text-amber-400 text-sm">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            @endif
                            <span class="text-xs text-gray-400 ml-auto">{{ $review->created_at->format('d.m.Y') }}</span>
                        </div>
                        @if($review->review)
                            <p class="text-sm text-gray-700 pl-9">{{ $review->review }}</p>
                        @endif
                        @if($review->takeaway)
                            <div class="pl-9">
                                <p class="text-xs font-medium text-purple-600 mb-0.5">Hängen geblieben:</p>
                                <p class="text-sm text-gray-600 italic">„{{ $review->takeaway }}"</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Ähnliche Medien --}}
    @if($similar->isNotEmpty())
        <div class="space-y-3">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Ähnliche Medien</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($similar as $item)
                    <a href="{{ route('media.show', $item) }}" wire:navigate
                       class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all group">
                        <div class="aspect-3/4 bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if($item->cover_path)
                                <img src="{{ asset('storage/' . $item->cover_path) }}"
                                     loading="lazy" decoding="async"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <span class="text-3xl text-gray-300">{{ $item->type->icon() }}</span>
                            @endif
                        </div>
                        <div class="p-2">
                            <p class="text-xs font-semibold text-gray-800 line-clamp-2 leading-snug">{{ $item->title }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>
