<?php

use App\Models\Loan;
use App\Models\Media;
use App\Models\Reservation;
use App\Models\Wish;
use App\Services\RecommendationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $user = auth()->user();
        return [
            'activeLoans'    => Loan::where('user_id', $user->id)->whereNull('returned_at')->with('media')->get(),
            'readyReservations' => Reservation::where('user_id', $user->id)->where('status', 'bereit')->with('media')->get(),
            'mediaCount'     => Media::whereNotIn('status', ['verloren', 'ausgemustert'])->count(),
            'myWishCount'    => Wish::where('user_id', $user->id)->where('status', 'eingereicht')->count(),
            'empfehlungen'   => app(RecommendationService::class)->fuerNutzer($user, 4),
            'hatHistorie'    => Loan::where('user_id', $user->id)->whereNotNull('returned_at')->exists(),
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Welcome --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
            <span class="text-blue-700 font-bold text-lg">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        </div>
        <div>
            <h1 class="font-semibold text-gray-900">Willkommen, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500">{{ auth()->user()->role->label() }} · {{ now()->format('d. F Y') }}</p>
        </div>
    </div>

    {{-- Alerts: reservation ready or overdue loans --}}
    @foreach($readyReservations as $res)
        <div class="bg-green-50 border border-green-200 rounded-2xl px-5 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1 text-sm">
                <span class="font-semibold text-green-800">Abholbereit:</span>
                <span class="text-green-700"> {{ $res->media->title }}</span>
            </div>
            <a href="{{ route('loans.index') }}" wire:navigate
               class="text-xs text-green-700 font-medium hover:underline">Ansehen →</a>
        </div>
    @endforeach

    @foreach($activeLoans->filter(fn($l) => $l->isOverdue()) as $loan)
        <div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div class="flex-1 text-sm">
                <span class="font-semibold text-red-800">Rückgabe überfällig:</span>
                <span class="text-red-700"> {{ $loan->media->title }}</span>
                <span class="text-red-400"> (seit {{ $loan->daysOverdue() }} Tagen)</span>
            </div>
            <a href="{{ route('loans.return', $loan) }}" wire:navigate
               class="text-xs text-red-700 font-medium hover:underline">Zurückgeben →</a>
        </div>
    @endforeach

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('media.index') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-2xl font-bold text-blue-600">{{ $mediaCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Medien</div>
        </a>
        <a href="{{ route('loans.index') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-2xl font-bold {{ $activeLoans->count() > 0 ? 'text-blue-600' : 'text-gray-400' }}">
                {{ $activeLoans->count() }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Aktive Ausleihen</div>
        </a>
        <a href="{{ route('wishes.index') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-2xl font-bold {{ $myWishCount > 0 ? 'text-yellow-500' : 'text-gray-400' }}">
                {{ $myWishCount }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Offene Wünsche</div>
        </a>
        <a href="{{ route('loans.scan') }}" wire:navigate
           class="bg-blue-600 rounded-2xl p-4 text-center hover:bg-blue-700 transition-all">
            <div class="flex justify-center mb-1">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
            </div>
            <div class="text-xs text-white font-medium">Barcode scannen</div>
        </a>
    </div>

    {{-- Active loans list --}}
    @if($activeLoans->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Meine Ausleihen</h2>
                <a href="{{ route('loans.index') }}" wire:navigate class="text-xs text-blue-600 hover:underline">Alle anzeigen</a>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($activeLoans as $loan)
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $loan->media->title }}</p>
                            <p class="text-xs {{ $loan->isOverdue() ? 'text-red-500' : 'text-gray-400' }}">
                                Fällig: {{ $loan->due_at->format('d.m.Y') }}
                                @if($loan->isOverdue()) · {{ $loan->daysOverdue() }} Tage überfällig @endif
                            </p>
                        </div>
                        <a href="{{ route('loans.return', $loan) }}" wire:navigate
                           class="text-xs text-gray-500 border border-gray-200 px-2.5 py-1 rounded-lg hover:bg-gray-50">
                            Zurückgeben
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('chat') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3 hover:border-purple-200 hover:shadow-xs transition-all">
            <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700">KI-Assistent</span>
        </a>
        <a href="{{ route('wishes.create') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3 hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Medium wünschen</span>
        </a>
    </div>

    {{-- Persönliche Empfehlungen --}}
    @if($empfehlungen->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-baseline justify-between gap-3 mb-1">
                <h2 class="font-semibold text-gray-900">
                    {{ $hatHistorie ? 'Für Sie ausgewählt' : 'Häufig genutzt' }}
                </h2>
                <a href="{{ route('media.index') }}" wire:navigate
                   class="text-xs text-blue-700 hover:underline shrink-0">Alle Medien</a>
            </div>
            <p class="text-xs text-gray-400 mb-4">
                {{ $hatHistorie
                    ? 'Auf Grundlage Ihrer bisherigen Ausleihen und Bewertungen.'
                    : 'Sobald Sie etwas ausgeliehen haben, werden die Vorschläge persönlicher.' }}
            </p>

            <div class="space-y-2">
                @foreach($empfehlungen as $medium)
                    <a href="{{ route('media.show', $medium) }}" wire:navigate
                       class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50/30 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $medium->title }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $medium->type->label() }}@if($medium->author) · {{ $medium->author }} @endif
                            </p>
                        </div>
                        <span class="text-xs shrink-0 px-2 py-0.5 rounded-full {{ $medium->status->badgeClass() ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $medium->status->label() }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kurator shortcut --}}
    @if(auth()->user()->isKurator())
        <div class="bg-emerald-50 rounded-2xl border border-emerald-200 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-900">Kurations-Dashboard</p>
                <p class="text-xs text-emerald-600 mt-0.5">Wünsche, Anschaffungen, KI-Analyse</p>
            </div>
            <a href="{{ route('curation.index') }}" wire:navigate
               class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-emerald-700 transition-colors">
                Öffnen
            </a>
        </div>
    @endif

</div>
