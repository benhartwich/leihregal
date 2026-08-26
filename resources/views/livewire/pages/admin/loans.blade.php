<?php

use App\Enums\MediaStatus;
use App\Enums\ReservationStatus;
use App\Models\Loan;
use App\Models\Media;
use App\Models\Reservation;
use App\Services\LoanService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    public string  $filterUser  = '';
    public string  $filterMedia = '';
    public ?string $toast       = null;
    public bool    $toastError  = false;

    public function getLoansProperty()
    {
        return Loan::with(['user', 'media'])
            ->whereNull('returned_at')
            ->when($this->filterUser,  fn($q) => $q->whereHas('user',  fn($q) => $q->where('name', 'like', "%{$this->filterUser}%")))
            ->when($this->filterMedia, fn($q) => $q->whereHas('media', fn($q) => $q->where('title', 'like', "%{$this->filterMedia}%")))
            ->orderBy('due_at')
            ->get();
    }

    public function getReservationsProperty()
    {
        return Reservation::with(['user', 'media'])
            ->whereIn('status', [ReservationStatus::Wartend, ReservationStatus::Bereit])
            ->when($this->filterUser,  fn($q) => $q->whereHas('user',  fn($q) => $q->where('name', 'like', "%{$this->filterUser}%")))
            ->when($this->filterMedia, fn($q) => $q->whereHas('media', fn($q) => $q->where('title', 'like', "%{$this->filterMedia}%")))
            ->orderBy('media_id')->orderBy('position')
            ->get();
    }

    public function returnLoan(int $loanId): void
    {
        $loan = Loan::with('media')->find($loanId);
        if (! $loan || $loan->returned_at) return;

        app(LoanService::class)->returnMedia($loan);
        $this->toast = "{$loan->media->title} zurückgegeben.";
        $this->toastError = false;
    }

    public function cancelReservation(int $reservationId): void
    {
        $reservation = Reservation::with('media')->find($reservationId);
        if (! $reservation) return;

        try {
            app(LoanService::class)->cancelReservation($reservation, auth()->user());
            $this->toast = "Reservierung für \"{$reservation->media->title}\" storniert.";
            $this->toastError = false;
        } catch (\RuntimeException $e) {
            $this->toast = $e->getMessage();
            $this->toastError = true;
        }
    }

    public function resetAll(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('loans')->truncate();
        DB::table('reservations')->truncate();
        DB::table('damage_reports')->truncate();
        DB::table('wishes')->truncate();
        DB::table('acquisition_suggestions')->truncate();
        DB::table('media_reviews')->truncate();
        DB::table('media_bookmarks')->truncate();
        DB::table('media')
            ->whereIn('status', ['ausgeliehen', 'reserviert', 'in_aufbereitung'])
            ->update(['status' => 'verfuegbar']);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->toast = 'Alle Ausleihdaten wurden zurückgesetzt.';
        $this->toastError = false;
    }
}; ?>

<div class="space-y-5" x-data="{ confirmReset: false }">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.users') }}" wire:navigate class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Ausleihen & Reservierungen</h1>
    </div>

    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="{{ $toastError ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' }} border px-4 py-3 rounded-xl text-sm font-medium">
            {{ $toast }}
        </div>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input wire:model.live.debounce.300ms="filterUser" type="text" placeholder="Nach Nutzer filtern …"
                   class="rounded-xl border-gray-300 text-sm py-2 focus:ring-blue-500 focus:border-blue-500">
            <input wire:model.live.debounce.300ms="filterMedia" type="text" placeholder="Nach Buchtitel filtern …"
                   class="rounded-xl border-gray-300 text-sm py-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
    </div>

    {{-- Active loans --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Aktive Ausleihen
                <span class="ml-2 text-xs font-normal text-gray-400">({{ $this->loans->count() }})</span>
            </h2>
        </div>

        @if($this->loans->isEmpty())
            <p class="p-5 text-sm text-gray-400">Keine aktiven Ausleihen.</p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($this->loans as $loan)
                    @php $overdue = $loan->due_at->isPast(); @endphp
                    <div class="flex items-center gap-4 px-5 py-3.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $loan->media->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $loan->user->name }}
                                &middot;
                                <span class="{{ $overdue ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                    fällig {{ $loan->due_at->format('d.m.Y') }}
                                    @if($overdue) (überfällig) @endif
                                </span>
                            </p>
                        </div>
                        <button wire:click="returnLoan({{ $loan->id }})"
                                wire:confirm="Ausleihe von „{{ $loan->media->title }}" für {{ $loan->user->name }} zurückbuchen?"
                                class="shrink-0 text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                            Zurückgeben
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Active reservations --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Aktive Reservierungen
                <span class="ml-2 text-xs font-normal text-gray-400">({{ $this->reservations->count() }})</span>
            </h2>
        </div>

        @if($this->reservations->isEmpty())
            <p class="p-5 text-sm text-gray-400">Keine aktiven Reservierungen.</p>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($this->reservations as $res)
                    <div class="flex items-center gap-4 px-5 py-3.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $res->media->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $res->user->name }}
                                &middot;
                                @if($res->reserved_from)
                                    Urlaubsvormerkung {{ $res->reserved_from->format('d.m.') }}–{{ $res->reserved_until->format('d.m.Y') }}
                                @else
                                    Platz {{ $res->position }}
                                @endif
                                &middot;
                                <span class="{{ $res->status === \App\Enums\ReservationStatus::Bereit ? 'text-green-600 font-medium' : 'text-gray-500' }}">
                                    {{ $res->status->label() }}
                                </span>
                            </p>
                        </div>
                        <button wire:click="cancelReservation({{ $res->id }})"
                                wire:confirm="Reservierung von „{{ $res->media->title }}" für {{ $res->user->name }} stornieren?"
                                class="shrink-0 text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-colors">
                            Stornieren
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Danger zone --}}
    <div class="bg-white rounded-2xl border border-red-200 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-red-700">Gefahrenzone</h2>
        <p class="text-xs text-gray-500">
            Setzt <strong>alle</strong> Ausleihen, Reservierungen, Schadensmeldungen, Wünsche und Bewertungen zurück.
            Alle Medien werden auf „verfügbar" gesetzt. Diese Aktion kann nicht rückgängig gemacht werden.
        </p>

        <div x-show="!confirmReset">
            <button @click="confirmReset = true"
                    class="px-4 py-2 rounded-xl text-sm font-medium bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-colors">
                Alle Ausleihdaten zurücksetzen …
            </button>
        </div>

        <div x-show="confirmReset" class="space-y-3">
            <p class="text-sm font-medium text-red-700">Wirklich alle Daten löschen? Das kann nicht rückgängig gemacht werden.</p>
            <div class="flex gap-3">
                <button wire:click="resetAll" @click="confirmReset = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition-colors">
                    Ja, alles zurücksetzen
                </button>
                <button @click="confirmReset = false"
                        class="px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>

</div>
