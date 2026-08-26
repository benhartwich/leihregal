<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isKurator(), 403);
    }

    public function with(): array
    {
        $topMedia = DB::table('loans')
            ->join('media', 'loans.media_id', '=', 'media.id')
            ->select('media.id', 'media.title', 'media.author', DB::raw('COUNT(loans.id) as loan_count'))
            ->groupBy('media.id', 'media.title', 'media.author')
            ->orderByDesc('loan_count')
            ->limit(10)
            ->get();

        $neverBorrowed = DB::table('media')
            ->leftJoin('loans', 'media.id', '=', 'loans.media_id')
            ->whereNull('loans.id')
            ->whereNotIn('media.status', ['verloren', 'ausgemustert'])
            ->select('media.id', 'media.title', 'media.author')
            ->orderBy('media.title')
            ->limit(10)
            ->get();

        $avgDuration = DB::table('loans')
            ->selectRaw('ROUND(AVG(DATEDIFF(COALESCE(returned_at, NOW()), borrowed_at)), 1) as avg_days')
            ->value('avg_days');

        $activeLoansCount = DB::table('loans')->whereNull('returned_at')->count();

        $totalLoansCount = DB::table('loans')->count();

        $overdueCount = DB::table('loans')
            ->whereNull('returned_at')
            ->where('due_at', '<', now())
            ->count();

        $topUsers = DB::table('loans')
            ->join('users', 'loans.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', DB::raw('COUNT(loans.id) as loan_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('loan_count')
            ->limit(5)
            ->get();

        $mediaCount = DB::table('media')
            ->whereNotIn('status', ['verloren', 'ausgemustert'])
            ->count();

        $reviewStats = DB::table('media_reviews')
            ->selectRaw('COUNT(*) as total, ROUND(AVG(rating), 1) as avg_rating')
            ->first();

        return [
            'topMedia'         => $topMedia,
            'neverBorrowed'    => $neverBorrowed,
            'avgDuration'      => $avgDuration,
            'activeLoansCount' => $activeLoansCount,
            'totalLoansCount'  => $totalLoansCount,
            'overdueCount'     => $overdueCount,
            'topUsers'         => $topUsers,
            'mediaCount'       => $mediaCount,
            'reviewCount'      => $reviewStats->total ?? 0,
            'avgRating'        => $reviewStats->avg_rating ?? null,
        ];
    }
}; ?>

<div class="space-y-6">

    {{-- Back link --}}
    <a href="{{ route('curation.index') }}" wire:navigate
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Kuration
    </a>

    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold text-gray-900">Statistiken</h1>
    </div>

    {{-- KPI Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Medien gesamt</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($mediaCount) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Aktive Ausleihen</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($activeLoansCount) }}</p>
            <p class="text-xs text-gray-400 mt-1">von {{ number_format($totalLoansCount) }} gesamt</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Überfällig</p>
            <p class="text-3xl font-bold mt-1 {{ $overdueCount > 0 ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($overdueCount) }}
            </p>
            @if($overdueCount > 0)
                <p class="text-xs text-red-400 mt-1">nicht zurückgegeben</p>
            @endif
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">Ø Ausleihdauer</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">
                {{ $avgDuration !== null ? number_format($avgDuration, 1) : '–' }}
            </p>
            @if($avgDuration !== null)
                <p class="text-xs text-gray-400 mt-1">Tage</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Meistgeliehene Medien --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Meistgeliehene Medien</h2>
                <p class="text-xs text-gray-400 mt-0.5">Top 10 nach Anzahl der Ausleihen</p>
            </div>
            @if($topMedia->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-400">Noch keine Ausleihdaten vorhanden.</div>
            @else
                @php $maxLoans = $topMedia->first()->loan_count; @endphp
                <div class="divide-y divide-gray-50">
                    @foreach($topMedia as $i => $item)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="text-xs font-bold text-gray-300 w-5 text-right shrink-0">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $item->title }}</p>
                                @if($item->author)
                                    <p class="text-xs text-gray-400 truncate">{{ $item->author }}</p>
                                @endif
                                <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-400 rounded-full transition-all"
                                         style="width: {{ $maxLoans > 0 ? round($item->loan_count / $maxLoans * 100) : 0 }}%">
                                    </div>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-blue-600 shrink-0">{{ $item->loan_count }}×</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-4">

            {{-- Aktivste Nutzer --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Aktivste Nutzer</h2>
                </div>
                @if($topUsers->isEmpty())
                    <div class="px-5 py-6 text-center text-sm text-gray-400">Keine Daten.</div>
                @else
                    <div class="divide-y divide-gray-50">
                        @foreach($topUsers as $i => $user)
                            <div class="px-5 py-2.5 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center text-xs font-bold text-blue-700 shrink-0">
                                        {{ mb_substr($user->name, 0, 1) }}
                                    </span>
                                    <span class="text-sm text-gray-700 truncate">{{ $user->name }}</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 shrink-0">{{ $user->loan_count }}×</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Bewertungen --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Bewertungen</h2>
                <div class="flex items-end gap-4">
                    <div>
                        <p class="text-3xl font-bold text-gray-900">{{ number_format($reviewCount) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Bewertungen gesamt</p>
                    </div>
                    @if($avgRating !== null)
                        <div class="pb-0.5">
                            <div class="flex items-center gap-1">
                                @for($s = 1; $s <= 5; $s++)
                                    <span class="text-lg {{ $s <= round($avgRating) ? 'text-amber-400' : 'text-gray-200' }}">★</span>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">Ø {{ number_format($avgRating, 1) }} Sterne</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Nie ausgeliehen --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Nie ausgeliehen</h2>
                <p class="text-xs text-gray-400 mt-0.5">Verfügbare Medien ohne jede Ausleihe (max. 10)</p>
            </div>
        </div>
        @if($neverBorrowed->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-400">Alle Medien wurden mindestens einmal ausgeliehen.</div>
        @else
            <div class="px-5 py-3">
                <div class="flex flex-wrap gap-2">
                    @foreach($neverBorrowed as $item)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs bg-gray-100 text-gray-600">
                            {{ $item->title }}
                            @if($item->author)
                                <span class="ml-1 text-gray-400">· {{ $item->author }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

</div>
