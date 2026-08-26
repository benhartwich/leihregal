<?php

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Loan;
use App\Models\Media;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'typ')]
    public string $filterType = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedFilterType(): void  { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function with(): array
    {
        $query = Media::query()
            ->when($this->search, function ($q) {
                $term = trim($this->search);
                if (strlen($term) >= 3) {
                    // FULLTEXT boolean search for 3+ chars
                    $q->whereRaw(
                        'MATCH(title, author, summary) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    );
                } else {
                    $q->where(function ($q) use ($term) {
                        $q->where('title', 'like', "%{$term}%")
                          ->orWhere('author', 'like', "%{$term}%");
                    });
                }
            })
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderBy('title')
            ->paginate(24);

        $borrowedIds = Loan::where('user_id', auth()->id())
            ->whereIn('media_id', collect($query->items())->pluck('id'))
            ->pluck('media_id')
            ->unique()
            ->all();

        return [
            'items'       => $query,
            'types'       => MediaType::cases(),
            'statuses'    => MediaStatus::cases(),
            'borrowedIds' => array_flip($borrowedIds),
        ];
    }
}; ?>

<div class="space-y-4">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Mediathek</h1>
        @if(auth()->user()->isKurator())
            <a href="{{ route('media.create') }}" wire:navigate
               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Medium hinzufügen
            </a>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row">
        {{-- Search --}}
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search"
                   type="search" placeholder="Titel, Autor, Beschreibung …"
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Type filter --}}
        <select wire:model.live="filterType"
                class="rounded-xl border-gray-300 text-sm py-2.5 pr-8 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Alle Typen</option>
            @foreach($types as $t)
                <option value="{{ $t->value }}">{{ $t->label() }}</option>
            @endforeach
        </select>

        {{-- Status filter --}}
        <select wire:model.live="filterStatus"
                class="rounded-xl border-gray-300 text-sm py-2.5 pr-8 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Alle Status</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
    </div>

    {{-- Grid --}}
    @if($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400 text-sm">
            @if($search || $filterType || $filterStatus)
                Keine Medien gefunden. Bitte Filter anpassen.
            @else
                Noch keine Medien vorhanden.
                @if(auth()->user()->isKurator())
                    <a href="{{ route('media.create') }}" wire:navigate class="text-blue-600 hover:underline ml-1">Erstes Medium hinzufügen</a>
                @endif
            @endif
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            @foreach($items as $item)
                <a href="{{ route('media.show', $item) }}" wire:navigate
                   class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md hover:border-blue-200 transition-all group">
                    {{-- Cover --}}
                    <div class="aspect-3/4 bg-gray-100 relative overflow-hidden">
                        @if($item->cover_path)
                            <img src="{{ asset('storage/' . $item->cover_path) }}"
                                 alt="{{ $item->title }}"
                                 loading="lazy" decoding="async"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">
                                {{ $item->type->icon() }}
                            </div>
                        @endif
                        {{-- Status badge --}}
                        @if(! $item->status->isAvailable())
                            <div class="absolute top-2 right-2">
                                <span class="inline-flex px-1.5 py-0.5 rounded-sm text-xs font-medium {{ $item->status->badgeClass() }}">
                                    {{ $item->status->label() }}
                                </span>
                            </div>
                        @endif
                        @if(isset($borrowedIds[$item->id]))
                            <div class="absolute top-2 left-2">
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-sm text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200" title="Du hast dieses Medium schon ausgeliehen">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    gelesen
                                </span>
                            </div>
                        @endif
                    </div>
                    {{-- Info --}}
                    <div class="p-3">
                        <p class="text-xs font-semibold text-gray-900 leading-snug line-clamp-2">{{ $item->title }}</p>
                        @if($item->author)
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item->author }}</p>
                        @endif
                        <div class="mt-2">
                            <span class="inline-flex px-1.5 py-0.5 rounded-sm text-xs font-medium {{ $item->type->badgeClass() }}">
                                {{ $item->type->label() }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($items->hasPages())
            <div class="flex justify-center pt-2">
                {{ $items->links() }}
            </div>
        @endif
    @endif

    <p class="text-xs text-gray-400 text-center">{{ $items->total() }} Medium/Medien gesamt</p>

</div>
