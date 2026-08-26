<?php

use App\Models\Loan;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\LoanService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $toast   = null;
    public bool    $toastOk = true;

    public function cancelReservation(int $reservationId): void
    {
        try {
            $reservation = Reservation::findOrFail($reservationId);
            app(LoanService::class)->cancelReservation($reservation, auth()->user());
            $this->toast   = 'Reservierung wurde storniert.';
            $this->toastOk = true;
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
        }
    }

    public function extendLoan(int $loanId): void
    {
        try {
            $loan = Loan::findOrFail($loanId);
            app(LoanService::class)->extendLoan($loan, auth()->user());
            $this->toast   = 'Ausleihe wurde verlängert.';
            $this->toastOk = true;
        } catch (\RuntimeException $e) {
            $this->toast   = $e->getMessage();
            $this->toastOk = false;
        }
    }

    public function with(): array
    {
        $userId = auth()->id();

        return [
            'maxExtensions' => (int) Setting::get('max_extensions', 1),

            'activeLoans' => Loan::with('media')
                ->where('user_id', $userId)
                ->whereNull('returned_at')
                ->orderBy('due_at')
                ->get(),

            'activeReservations' => Reservation::with('media')
                ->where('user_id', $userId)
                ->whereIn('status', ['wartend', 'bereit'])
                ->orderBy('status', 'desc')  // 'wartend' > 'bereit' alphabetically, so use desc to put 'bereit' first
                ->orderBy('updated_at')
                ->get(),

            'recentLoans' => Loan::with('media')
                ->where('user_id', $userId)
                ->whereNotNull('returned_at')
                ->orderByDesc('returned_at')
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Meine Ausleihen</h1>
        <a href="{{ route('loans.scan') }}" wire:navigate
           class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v1m6.364 1.636l-.707.707M20 12h1M17.657 17.657l-.707.707M12 20v1M6.343 17.657l-.707.707M4 12H3M6.343 6.343l.707.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            </svg>
            QR-Code scannen
        </a>
    </div>

    {{-- Toast --}}
    @if($toast)
        <div x-data="{ show: true }" x-show="show"
             x-init="setTimeout(() => { show = false; $wire.set('toast', null) }, 3500)"
             class="px-4 py-3 rounded-lg text-sm flex items-center gap-3
                    {{ $toastOk ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' }}">
            {{ $toast }}
        </div>
    @endif

    {{-- ── Aktive Ausleihen ──────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Aktuell ausgeliehen</h2>

        @if($activeLoans->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center text-sm text-gray-400">
                Keine aktiven Ausleihen.
            </div>
        @else
            <div class="space-y-2">
                @foreach($activeLoans as $loan)
                    @php $overdue = $loan->isOverdue(); @endphp
                    <div class="bg-white rounded-2xl border {{ $overdue ? 'border-red-200' : 'border-gray-200' }} p-4">
                        <div class="flex items-start gap-3">
                            {{-- Cover thumbnail --}}
                            <div class="w-10 h-14 rounded-lg bg-gray-100 shrink-0 overflow-hidden flex items-center justify-center">
                                @if($loan->media->cover_path)
                                    <img src="{{ asset('storage/' . $loan->media->cover_path) }}" loading="lazy" decoding="async" class="w-full h-full object-cover">
                                @else
                                    <span class="text-xl">{{ $loan->media->type->icon() }}</span>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <a href="{{ route('media.show', $loan->media) }}" wire:navigate
                                   class="font-medium text-gray-900 wrap-break-word hover:text-blue-700">{{ $loan->media->title }}</a>
                                @if($loan->media->author)
                                    <p class="text-xs text-gray-500 truncate">{{ $loan->media->author }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-1">
                                    Fällig: {{ $loan->due_at->format('d.m.Y') }}
                                    @if($overdue)
                                        <span class="text-red-600 font-medium block sm:inline sm:ml-1">– {{ $loan->daysOverdue() }} Tag(e) überfällig!</span>
                                    @elseif($loan->due_at->diffInDays(now()) <= 2)
                                        <span class="text-amber-600 font-medium block sm:inline sm:ml-1">– bald fällig</span>
                                    @endif
                                </p>
                                @if($maxExtensions > 0)
                                    @php $remaining = $maxExtensions - $loan->extension_count; @endphp
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        noch {{ $remaining }} Verlängerung(en) möglich
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2 justify-end">
                            @if($maxExtensions > 0 && $loan->extension_count < $maxExtensions)
                                <button wire:click="extendLoan({{ $loan->id }})"
                                        wire:confirm="Ausleihe wirklich verlängern?"
                                        class="bg-blue-50 text-blue-700 text-sm font-medium px-3 py-2 rounded-xl hover:bg-blue-100 transition-colors border border-blue-200">
                                    Verlängern
                                </button>
                            @endif
                            <a href="{{ route('loans.return', $loan) }}" wire:navigate
                               class="bg-gray-800 text-white text-sm font-medium px-4 py-2 rounded-xl hover:bg-gray-900 transition-colors">
                                Zurückgeben
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Reservierungen ────────────────────────────────────────────────── --}}
    @if($activeReservations->isNotEmpty())
    <section>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Reservierungen</h2>
        <div class="space-y-2">
            @foreach($activeReservations as $res)
                <div class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-4">
                    <div class="w-10 h-14 rounded-lg bg-gray-100 shrink-0 overflow-hidden flex items-center justify-center">
                        @if($res->media->cover_path)
                            <img src="{{ asset('storage/' . $res->media->cover_path) }}" loading="lazy" decoding="async" class="w-full h-full object-cover">
                        @else
                            <span class="text-xl">{{ $res->media->type->icon() }}</span>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ $res->media->title }}</p>
                        <span class="inline-flex mt-1 px-2 py-0.5 rounded-sm text-xs font-medium {{ $res->status->badgeClass() }}">
                            {{ $res->status->label() }}
                        </span>
                        @if($res->status->value === 'wartend')
                            <span class="text-xs text-gray-400 ml-2">Position {{ $res->position }}</span>
                        @endif
                    </div>

                    <button wire:click="cancelReservation({{ $res->id }})"
                            wire:confirm="Reservierung wirklich stornieren?"
                            class="shrink-0 text-sm font-medium text-red-600 hover:text-red-800">
                        Stornieren
                    </button>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── Rückgaben ─────────────────────────────────────────────────────── --}}
    @if($recentLoans->isNotEmpty())
    <section>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Zuletzt zurückgegeben</h2>
        <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-50">
            @foreach($recentLoans as $loan)
                <div class="px-4 py-3 flex items-center gap-3">
                    <span class="text-lg">{{ $loan->media->type->icon() }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 truncate">{{ $loan->media->title }}</p>
                        <p class="text-xs text-gray-400">Zurückgegeben {{ $loan->returned_at->format('d.m.Y') }}</p>
                    </div>
                    @if($loan->rating !== null)
                        <span class="text-lg">{{ $loan->rating ? '👍' : '👎' }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
