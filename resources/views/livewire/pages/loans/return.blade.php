<?php

use App\Enums\MediaStatus;
use App\Models\DamageReport;
use App\Models\Loan;
use App\Models\MediaReview;
use App\Services\LoanService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Loan $loan;

    public ?int    $rating      = null;
    public string  $comment     = '';
    public string  $reviewText  = '';
    public bool    $reportDamage      = false;
    public string  $damageDescription = '';

    public function mount(Loan $loan): void
    {
        // Only the borrower or kurators/admins may access
        if ($loan->user_id !== auth()->id() && ! auth()->user()->isKurator()) {
            abort(403);
        }

        if ($loan->returned_at) {
            session()->flash('success', 'Dieses Medium wurde bereits zurückgegeben.');
            $this->redirect(route('loans.index'), navigate: true);
            return;
        }

        $this->loan = $loan->load('media');

        // Pre-fill if user already has a review for this medium
        $existingReview = MediaReview::where('media_id', $loan->media_id)
            ->where('user_id', auth()->id())
            ->first();
        if ($existingReview) {
            $this->reviewText = $existingReview->review ?? '';
        }
    }

    public function returnNow(): void
    {
        if ($this->reportDamage) {
            $this->validate([
                'damageDescription' => ['required', 'string', 'min:5', 'max:2000'],
            ], [
                'damageDescription.required' => 'Bitte eine Schadensbeschreibung eingeben.',
                'damageDescription.min'      => 'Die Beschreibung muss mindestens 5 Zeichen lang sein.',
            ]);
        }

        try {
            $loanId  = $this->loan->id;
            $mediaId = $this->loan->media_id;

            app(LoanService::class)->returnMedia(
                $this->loan,
                $this->rating,
                $this->comment ?: null,
            );

            if (trim($this->reviewText) !== '') {
                MediaReview::updateOrCreate(
                    ['media_id' => $mediaId, 'user_id' => auth()->id()],
                    [
                        'rating' => $this->rating === 1 ? 5 : ($this->rating === 0 ? 2 : null),
                        'review' => trim($this->reviewText),
                    ],
                );
            }

            if ($this->reportDamage && trim($this->damageDescription)) {
                DamageReport::create([
                    'media_id'    => $mediaId,
                    'user_id'     => auth()->id(),
                    'loan_id'     => $loanId,
                    'description' => trim($this->damageDescription),
                    'status'      => 'offen',
                ]);
                // Set media to in_aufbereitung
                \App\Models\Media::where('id', $mediaId)
                    ->update(['status' => MediaStatus::InAufbereitung]);
            }

            session()->flash('success', "{$this->loan->media->title} wurde zurückgegeben."
                . ($this->reportDamage ? ' Schadensmeldung wurde erstellt.' : ''));
            $this->redirect(route('loans.index'), navigate: true);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('loans.index'), navigate: true);
        }
    }

    public function skip(): void
    {
        $this->returnNow();
    }
}; ?>

<div class="space-y-5">

    <a href="{{ route('loans.index') }}" wire:navigate
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück
    </a>

    <h1 class="text-xl font-semibold text-gray-900">Medium zurückgeben</h1>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">

        {{-- Medium info --}}
        <div class="flex items-center gap-4">
            <div class="w-14 h-20 rounded-xl bg-gray-100 shrink-0 overflow-hidden flex items-center justify-center">
                @if($loan->media->cover_path)
                    <img src="{{ asset('storage/' . $loan->media->cover_path) }}" class="w-full h-full object-cover">
                @else
                    <span class="text-3xl">{{ $loan->media->type->icon() }}</span>
                @endif
            </div>
            <div>
                <p class="font-semibold text-gray-900">{{ $loan->media->title }}</p>
                @if($loan->media->author)
                    <p class="text-sm text-gray-500">{{ $loan->media->author }}</p>
                @endif
                <p class="text-sm text-gray-500 mt-1">
                    Ausgeliehen am {{ $loan->borrowed_at->format('d.m.Y') }} ·
                    @if($loan->isOverdue())
                        <span class="text-red-600 font-medium">{{ $loan->daysOverdue() }} Tag(e) überfällig</span>
                    @else
                        Fällig {{ $loan->due_at->format('d.m.Y') }}
                    @endif
                </p>
            </div>
        </div>

        <hr class="border-gray-100">

        {{-- Rating --}}
        <div>
            <p class="text-sm font-medium text-gray-700 mb-3">Wie war das Medium? <span class="text-gray-400 font-normal">(optional)</span></p>
            <div class="flex gap-4">
                <button type="button"
                        wire:click="$set('rating', 1)"
                        class="flex-1 flex flex-col items-center gap-2 p-4 rounded-2xl border-2 transition-all
                               {{ $rating === 1 ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300' }}">
                    <span class="text-3xl">👍</span>
                    <span class="text-sm font-medium text-gray-700">Empfehlenswert</span>
                </button>
                <button type="button"
                        wire:click="$set('rating', 0)"
                        class="flex-1 flex flex-col items-center gap-2 p-4 rounded-2xl border-2 transition-all
                               {{ $rating === 0 ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-gray-300' }}">
                    <span class="text-3xl">👎</span>
                    <span class="text-sm font-medium text-gray-700">Weniger geeignet</span>
                </button>
            </div>
        </div>

        @if($rating !== null)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kurze Anmerkung <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea wire:model="comment" rows="2"
                          placeholder="Was hat Ihnen {{ $rating ? 'gefallen' : 'nicht gefallen' }}?"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Rezension <span class="text-gray-400 font-normal">(optional, sichtbar für andere)</span>
                </label>
                <textarea wire:model="reviewText" rows="3"
                          placeholder="Dein Eindruck zum Buch – wird auf der Detailseite angezeigt …"
                          class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
            </div>
        @endif

        <hr class="border-gray-100">

        {{-- Damage report --}}
        <div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model.live="reportDamage" type="checkbox"
                       class="rounded-sm border-gray-300 text-red-600 focus:ring-red-500">
                <span class="text-sm font-medium text-gray-700">Schaden melden</span>
            </label>
            <p class="mt-1 text-xs text-gray-400 ml-7">Wenn das Medium beschädigt ist, bitte Schaden melden.</p>

            @if($reportDamage)
                <div class="mt-3 ml-7">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Schadensbeschreibung <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model="damageDescription" rows="3"
                              placeholder="Was ist beschädigt? z. B. Seiten eingerissen, Einband beschädigt …"
                              class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-red-500 focus:border-red-500 resize-none
                                     {{ $errors->has('damageDescription') ? 'border-red-300' : '' }}"></textarea>
                    @error('damageDescription')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-amber-600">
                        Das Medium wird nach der Rückgabe als "In Aufbereitung" markiert.
                    </p>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2">
            <button wire:click="returnNow"
                    class="flex-1 sm:flex-none bg-gray-800 text-white px-6 py-3 rounded-xl font-medium hover:bg-gray-900 transition-colors text-center">
                Zurückgeben{{ $rating !== null ? ' & Bewerten' : '' }}{{ $reportDamage ? ' & Schaden melden' : '' }}
            </button>
            @if($rating !== null)
                <button wire:click="skip" class="text-sm text-gray-500 hover:text-gray-700">
                    Ohne Bewertung
                </button>
            @endif
        </div>
    </div>
</div>
