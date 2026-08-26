<?php

use App\Enums\MediaStatus;
use App\Models\AcquisitionSuggestion;
use App\Models\DamageReport;
use App\Models\Media;
use App\Models\Wish;
use App\Services\CurationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool    $analyzing       = false;
    public bool    $checkingOutdated = false;
    public ?string $gapError        = null;
    public array   $gapSuggestions  = [];

    // Veraltungs-Check state
    public ?int    $outdatedMediaId  = null;
    public ?array  $outdatedResult   = null;
    public ?string $outdatedError    = null;

    public function analyzeGaps(): void
    {
        $this->analyzing     = true;
        $this->gapError      = null;
        $this->gapSuggestions = [];

        $result = app(CurationService::class)->analyzeGaps();

        $this->analyzing = false;

        if (isset($result['error'])) {
            $this->gapError = $result['error'];
        } else {
            $this->gapSuggestions = $result['suggestions'] ?? [];
        }
    }

    public function saveGapSuggestion(int $index): void
    {
        $s = $this->gapSuggestions[$index] ?? null;
        if (! $s) return;

        AcquisitionSuggestion::create([
            'source'         => 'ki',
            'title'          => $s['title'],
            'author'         => $s['author'] ?? null,
            'publisher'      => $s['publisher'] ?? null,
            'isbn'           => $s['isbn'] ?? null,
            'price_estimate' => $s['price_estimate'] ?? null,
            'reason'         => $s['reason'],
            'shop_urls'      => $s['shop_urls'] ?? null,
            'status'         => 'offen',
        ]);

        unset($this->gapSuggestions[$index]);
        $this->gapSuggestions = array_values($this->gapSuggestions);
    }

    public function checkOutdated(int $mediaId): void
    {
        $this->outdatedMediaId = $mediaId;
        $this->outdatedResult  = null;
        $this->outdatedError   = null;
        $this->checkingOutdated = true;

        $media  = Media::findOrFail($mediaId);
        $result = app(CurationService::class)->checkOutdated($media);

        $this->checkingOutdated = false;

        if (isset($result['error'])) {
            $this->outdatedError = $result['error'];
        } else {
            $this->outdatedResult = $result;
        }
    }

    public function resolveReport(int $reportId, bool $setAvailable = false): void
    {
        $report = DamageReport::with('media')->findOrFail($reportId);
        $report->update(['status' => 'behoben']);

        if ($setAvailable && $report->media) {
            $report->media->update(['status' => MediaStatus::Verfuegbar]);
        }
    }

    public function with(): array
    {
        return [
            'pendingWishes'      => Wish::where('status', 'eingereicht')->count(),
            'openAcquisitions'   => AcquisitionSuggestion::where('status', 'offen')->count(),
            'openDamageReports'  => DamageReport::with(['media', 'user'])
                ->where('status', 'offen')
                ->latest()
                ->get(),
            'mediaForCheck'      => Media::whereNotIn('status', ['verloren', 'ausgemustert'])
                ->whereNotNull('year')
                ->where('year', '<', now()->year - 8)
                ->orderBy('year')
                ->limit(10)
                ->get(['id', 'title', 'author', 'year', 'type']),
        ];
    }
}; ?>

<div class="space-y-6">

    <h1 class="text-xl font-semibold text-gray-900">Kurations-Dashboard</h1>

    {{-- Quick links --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('curation.wishes') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-2xl font-bold text-blue-600">{{ $pendingWishes }}</div>
            <div class="text-xs text-gray-500 mt-1">Offene Wünsche</div>
        </a>
        <a href="{{ route('curation.acquisitions') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-2xl font-bold text-blue-600">{{ $openAcquisitions }}</div>
            <div class="text-xs text-gray-500 mt-1">Anschaffungsvorschläge</div>
        </a>
        <a href="{{ route('curation.whitelist') }}" wire:navigate
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-blue-200 hover:shadow-xs transition-all">
            <div class="text-xs font-medium text-gray-700 mt-2">Whitelist</div>
            <div class="text-xs text-gray-400">Verlage & Autoren</div>
        </a>
        <a href="{{ route('curation.inventory.pdf') }}"
           class="bg-white rounded-2xl border border-gray-200 p-4 text-center hover:border-gray-300 hover:shadow-xs transition-all">
            <div class="text-xs font-medium text-gray-700 mt-2">Bestandsliste PDF</div>
            <div class="text-xs text-gray-400">Druckversion</div>
        </a>
    </div>

    {{-- ── Bestandslücken-Analyse ────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-800">Bestandslücken-Analyse</h2>
                <p class="text-xs text-gray-400 mt-0.5">KI analysiert Tag-Verteilung und Whitelist → konkrete Anschaffungsempfehlungen</p>
            </div>
            <button wire:click="analyzeGaps" wire:loading.attr="disabled"
                    class="px-4 py-2.5 bg-purple-600 text-white text-sm font-medium rounded-xl hover:bg-purple-700 disabled:opacity-50 transition-colors">
                <span wire:loading.remove wire:target="analyzeGaps">Analysieren</span>
                <span wire:loading wire:target="analyzeGaps">Analysiert …</span>
            </button>
        </div>

        <div wire:loading wire:target="analyzeGaps"
             class="px-5 py-6 text-sm text-purple-600 flex items-center gap-3">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            KI analysiert Bestand und Whitelist …
        </div>

        @if($gapError)
            <div class="px-5 py-4 text-sm text-red-700 bg-red-50">{{ $gapError }}</div>
        @endif

        @if(!empty($gapSuggestions))
            <div class="divide-y divide-gray-50">
                @foreach($gapSuggestions as $i => $s)
                    <div class="px-5 py-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900">{{ $s['title'] }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $s['author'] ?? '' }}
                                    @if(!empty($s['publisher'])) · {{ $s['publisher'] }} @endif
                                    @if(!empty($s['price_estimate'])) · ca. {{ number_format($s['price_estimate'], 2, ',', '.') }} € @endif
                                </p>
                                <p class="text-sm text-gray-600 mt-1 italic">{{ $s['reason'] }}</p>
                                @if(!empty($s['shop_urls']))
                                    <div class="mt-2 flex gap-3">
                                        @foreach($s['shop_urls'] as $shop => $url)
                                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                               class="text-xs text-blue-600 hover:underline">{{ ucfirst($shop) }} →</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <button wire:click="saveGapSuggestion({{ $i }})"
                                    class="shrink-0 text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg hover:bg-blue-100 font-medium">
                                Zur Liste
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif(!$analyzing && empty($gapError))
            <div class="px-5 py-6 text-sm text-gray-400 text-center">
                Noch keine Analyse gestartet.
            </div>
        @endif
    </div>

    {{-- ── Schadensmeldungen ────────────────────────────────────────────── --}}
    @if($openDamageReports->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-800">Schadensmeldungen</h2>
                <p class="text-xs text-gray-400 mt-0.5">Offene Meldungen von zurückgegebenen Medien</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                {{ $openDamageReports->count() }} offen
            </span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($openDamageReports as $report)
                <div class="px-5 py-4">
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">
                                {{ $report->media?->title ?? '–' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Gemeldet von {{ $report->user?->name ?? 'Unbekannt' }}
                                &middot; {{ $report->created_at->format('d.m.Y') }}
                                @if($report->media)
                                    &middot; Status: {{ $report->media->status->label() }}
                                @endif
                            </p>
                            <p class="text-sm text-gray-700 mt-2">{{ $report->description }}</p>
                        </div>
                        <div class="shrink-0 flex flex-col gap-2">
                            <button wire:click="resolveReport({{ $report->id }}, true)"
                                    wire:confirm="Schaden beheben und Medium als 'Verfügbar' markieren?"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 font-medium transition-colors">
                                Behoben & verfügbar
                            </button>
                            <button wire:click="resolveReport({{ $report->id }}, false)"
                                    wire:confirm="Schaden als behoben markieren (Status bleibt unverändert)?"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 font-medium transition-colors">
                                Nur behoben
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Veraltungs-Check ─────────────────────────────────────────────── --}}
    @if($mediaForCheck->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Veraltungs-Check</h2>
            <p class="text-xs text-gray-400 mt-0.5">Medien älter als 8 Jahre – KI beurteilt ob noch zeitgemäß</p>
        </div>

        @if($outdatedResult)
            <div class="px-5 py-4 {{ $outdatedResult['outdated'] ? 'bg-red-50 border-b border-red-100' : 'bg-green-50 border-b border-green-100' }}">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">{{ $outdatedResult['outdated'] ? '⚠️' : '✅' }}</span>
                    <div>
                        <p class="text-sm font-medium {{ $outdatedResult['outdated'] ? 'text-red-800' : 'text-green-800' }}">
                            {{ $outdatedResult['outdated'] ? 'Möglicherweise veraltet' : 'Noch zeitgemäß' }}
                            <span class="text-xs font-normal">(Konfidenz: {{ $outdatedResult['confidence'] }})</span>
                        </p>
                        <p class="text-sm mt-1 {{ $outdatedResult['outdated'] ? 'text-red-700' : 'text-green-700' }}">
                            {{ $outdatedResult['reason'] }}
                        </p>
                        @if(!empty($outdatedResult['alternative']))
                            <p class="text-sm text-gray-600 mt-1">
                                <strong>Alternative:</strong> {{ $outdatedResult['alternative'] }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($outdatedError)
            <div class="px-5 py-3 text-sm text-red-700 bg-red-50 border-b border-red-100">{{ $outdatedError }}</div>
        @endif

        <div class="divide-y divide-gray-50">
            @foreach($mediaForCheck as $m)
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $m->title }}</p>
                        <p class="text-xs text-gray-400">{{ $m->author ?? '' }} · {{ $m->year }}</p>
                    </div>
                    <button wire:click="checkOutdated({{ $m->id }})"
                            wire:loading.attr="disabled"
                            class="shrink-0 text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="checkOutdated({{ $m->id }})">Prüfen</span>
                        <span wire:loading wire:target="checkOutdated({{ $m->id }})">…</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
