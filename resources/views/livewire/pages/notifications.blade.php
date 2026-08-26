<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $nurUngelesene = false;

    public function updatedNurUngelesene(): void
    {
        $this->resetPage();
    }

    public function alsGelesenMarkieren(string $id): void
    {
        Auth::user()->notifications()->where('id', $id)->update(['read_at' => now()]);
    }

    public function alleAlsGelesenMarkieren(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function loeschen(string $id): void
    {
        Auth::user()->notifications()->where('id', $id)->delete();
    }

    public function with(): array
    {
        $abfrage = Auth::user()->notifications();

        if ($this->nurUngelesene) {
            $abfrage->whereNull('read_at');
        }

        return [
            'benachrichtigungen' => $abfrage->latest()->paginate(20),
            'ungelesen'          => Auth::user()->unreadNotifications()->count(),
        ];
    }
}; ?>

<div class="space-y-4">

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900">Benachrichtigungen</h1>

        @if($ungelesen > 0)
            <button wire:click="alleAlsGelesenMarkieren" type="button"
                    class="text-sm text-blue-700 hover:underline whitespace-nowrap">
                Alle als gelesen markieren
            </button>
        @endif
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" wire:model.live="nurUngelesene"
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        {{-- Vor @if muss ein Leerzeichen stehen: Klebt die Direktive an einem
             Wortzeichen, lässt Blade sie als Text stehen, kompiliert aber das
             zugehörige @endif – das ergibt einen Syntaxfehler. --}}
        Nur ungelesene @if($ungelesen > 0) ({{ $ungelesen }}) @endif
    </label>

    <div class="space-y-2">
        @forelse($benachrichtigungen as $eintrag)
            @php
                $daten    = $eintrag->data;
                $gelesen  = $eintrag->read_at !== null;
                $farben   = match($daten['symbol'] ?? '') {
                    'warnung'    => 'bg-amber-100 text-amber-700',
                    'bereit'     => 'bg-green-100 text-green-700',
                    'neu'        => 'bg-blue-100 text-blue-700',
                    'wunsch'     => 'bg-purple-100 text-purple-700',
                    default      => 'bg-gray-100 text-gray-600',
                };
            @endphp

            <div class="flex gap-3 p-4 rounded-lg border
                        {{ $gelesen ? 'bg-white border-gray-200' : 'bg-blue-50/50 border-blue-200' }}">

                <div class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center {{ $farben }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium text-gray-900 text-sm">
                            {{ $daten['titel'] ?? 'Hinweis' }}
                            @unless($gelesen)
                                <span class="ml-1 inline-block w-2 h-2 rounded-full bg-blue-600 align-middle"></span>
                            @endunless
                        </p>
                        <span class="text-xs text-gray-400 whitespace-nowrap shrink-0">
                            {{ $eintrag->created_at->diffForHumans() }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-600 mt-0.5">{{ $daten['text'] ?? '' }}</p>

                    <div class="flex flex-wrap items-center gap-3 mt-2 text-xs">
                        @if(! empty($daten['url']))
                            <a href="{{ $daten['url'] }}" wire:navigate
                               class="text-blue-700 hover:underline font-medium">Ansehen</a>
                        @endif

                        @unless($gelesen)
                            <button wire:click="alsGelesenMarkieren('{{ $eintrag->id }}')" type="button"
                                    class="text-gray-500 hover:text-gray-800">Als gelesen markieren</button>
                        @endunless

                        <button wire:click="loeschen('{{ $eintrag->id }}')" type="button"
                                class="text-gray-400 hover:text-red-600">Entfernen</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-sm text-gray-500">
                @if($nurUngelesene)
                    Keine ungelesenen Benachrichtigungen.
                @else
                    Noch keine Benachrichtigungen.
                @endif
            </div>
        @endforelse
    </div>

    <div>{{ $benachrichtigungen->links() }}</div>

    <p class="text-xs text-gray-400">
        Benachrichtigungen erhalten Sie zusätzlich per E-Mail. Welche Themen Sie
        abonnieren, legen Sie unter
        <a href="{{ route('profile') }}" wire:navigate class="underline">Mein Profil</a> fest.
    </p>
</div>
