<?php

use App\Models\MediaBookmark;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function removeBookmark(int $mediaId): void
    {
        MediaBookmark::where('user_id', auth()->id())
            ->where('media_id', $mediaId)
            ->delete();
    }

    public function with(): array
    {
        return [
            'bookmarks' => MediaBookmark::where('user_id', auth()->id())
                ->with('media.tags')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div class="space-y-6">

    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Meine Merkliste</h1>
            @if($bookmarks->isNotEmpty())
                <p class="text-xs text-gray-400">{{ $bookmarks->count() }} {{ $bookmarks->count() === 1 ? 'Medium' : 'Medien' }} vorgemerkt</p>
            @endif
        </div>
    </div>

    @if($bookmarks->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-500">Noch keine Medien vorgemerkt.</p>
            <p class="text-sm text-gray-400 mt-1">Auf der Medienseite kannst du Medien mit dem Vormerken-Button auf die Merkliste setzen.</p>
            <a href="{{ route('media.index') }}" wire:navigate
               class="inline-flex items-center gap-2 mt-4 bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                Zur Mediathek
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            @foreach($bookmarks as $bookmark)
                @php $media = $bookmark->media; @endphp
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col hover:shadow-md hover:border-blue-200 transition-all group">

                    {{-- Cover --}}
                    <a href="{{ route('media.show', $media) }}" wire:navigate class="block">
                        <div class="aspect-3/4 bg-gray-100 flex items-center justify-center overflow-hidden">
                            @if($media->cover_path)
                                <img src="{{ asset('storage/' . $media->cover_path) }}"
                                     alt="{{ $media->title }}"
                                     loading="lazy" decoding="async"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <span class="text-4xl text-gray-300">{{ $media->type->icon() }}</span>
                            @endif
                        </div>
                    </a>

                    {{-- Info --}}
                    <div class="p-3 flex flex-col flex-1 gap-2">
                        <div class="flex-1">
                            <a href="{{ route('media.show', $media) }}" wire:navigate>
                                <p class="text-sm font-semibold text-gray-800 line-clamp-2 leading-snug group-hover:text-blue-700 transition-colors">{{ $media->title }}</p>
                            </a>
                            @if($media->author)
                                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $media->author }}</p>
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-1">
                            <span class="inline-flex px-2 py-0.5 rounded-sm text-xs font-medium {{ $media->status->badgeClass() }}">
                                {{ $media->status->label() }}
                            </span>
                            <button wire:click="removeBookmark({{ $media->id }})"
                                    wire:confirm="Vorgemerkt entfernen?"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium shrink-0 transition-colors">
                                Entfernen
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
