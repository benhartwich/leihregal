<?php

use App\Livewire\Actions\Logout;
use App\Enums\UserRole;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-14">

            {{-- Logo --}}
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 shrink-0">
                <x-brand-logo />
            </a>

            {{-- Desktop nav (only on lg+) --}}
            <div class="hidden md:flex md:items-center md:gap-1">
                <a href="{{ route('dashboard') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Übersicht
                </a>
                <a href="{{ route('media.index') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('media.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Medien
                </a>
                <a href="{{ route('loans.index') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('loans.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Ausleihen
                </a>
                <a href="{{ route('chat') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('chat') ? 'bg-purple-50 text-purple-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    KI-Assistent
                </a>
                <a href="{{ route('wishes.index') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('wishes.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Wünsche
                </a>
                <a href="{{ route('bookmarks') }}" wire:navigate
                   class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs('bookmarks') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Merkliste
                </a>
            </div>

            {{-- Right side: notifications + user menu + hamburger --}}
            <div class="flex items-center gap-2">

                @php $user = auth()->user(); @endphp

                {{-- Benachrichtigungen --}}
                @if($user)
                    <a href="{{ route('notifications') }}" wire:navigate
                       title="Benachrichtigungen"
                       class="relative p-2 rounded-lg transition-colors
                              {{ request()->routeIs('notifications') ? 'text-blue-700 bg-blue-50' : 'text-gray-500 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @php $ungelesen = $user->unreadNotifications()->count(); @endphp
                        @if($ungelesen > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full
                                         bg-red-600 text-white text-[10px] font-bold
                                         flex items-center justify-center">
                                {{ $ungelesen > 99 ? '99+' : $ungelesen }}
                            </span>
                        @endif
                    </a>
                @endif

                {{-- User dropdown --}}
                <div class="relative" x-data="{ menuOpen: false }" @click.outside="menuOpen = false">
                    <button @click="menuOpen = !menuOpen"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <span class="hidden sm:inline max-w-[100px] truncate">{{ $user?->name }}</span>
                        <span class="sm:hidden w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">
                            {{ mb_substr($user?->name ?? 'U', 0, 1) }}
                        </span>
                        <svg class="hidden sm:block w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="menuOpen" x-cloak x-transition
                         class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">

                        {{-- Role badge inside dropdown --}}
                        @if($user)
                            <div class="px-4 py-2 border-b border-gray-50">
                                <p class="text-xs text-gray-400">Angemeldet als</p>
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $user->name }}</p>
                                <span class="inline-flex mt-0.5 items-center px-1.5 py-0.5 rounded-sm text-xs font-medium {{ $user->role->badgeClass() }}">
                                    {{ $user->role->label() }}
                                </span>
                            </div>
                        @endif

                        {{-- Role-specific links --}}
                        @if(auth()->user()?->isKurator())
                            <a href="{{ route('curation.index') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('curation.index') ? 'text-emerald-700 bg-emerald-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Kuration
                            </a>
                            <a href="{{ route('curation.stats') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('curation.stats') ? 'text-emerald-700 bg-emerald-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Statistiken
                            </a>
                        @endif
                        @if(auth()->user()?->isAdmin())
                            <a href="{{ route('admin.users') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('admin.users*') ? 'text-purple-700 bg-purple-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                                Administration
                            </a>
                            <a href="{{ route('admin.settings') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('admin.settings') ? 'text-purple-700 bg-purple-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                Einstellungen
                            </a>
                            <a href="{{ route('admin.locations') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('admin.locations') ? 'text-purple-700 bg-purple-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Standorte
                            </a>
                            <a href="{{ route('admin.loans') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('admin.loans') ? 'text-purple-700 bg-purple-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Ausleihen
                            </a>
                            <a href="{{ route('admin.audit') }}" wire:navigate
                               class="flex items-center gap-2 px-4 py-2 text-sm {{ request()->routeIs('admin.audit') ? 'text-purple-700 bg-purple-50' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Protokoll
                            </a>
                        @endif

                        @if(auth()->user()?->isKurator() || auth()->user()?->isAdmin())
                            <hr class="my-1 border-gray-100">
                        @endif

                        <a href="{{ route('profile') }}" wire:navigate
                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Mein Profil
                        </a>
                        <hr class="my-1 border-gray-100">
                        <button wire:click="logout"
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            Abmelden
                        </button>
                    </div>
                </div>

                {{-- Mobile hamburger (hidden on lg+) --}}
                <button @click="open = !open"
                        class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path :class="{'hidden': open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': !open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile/tablet menu (below lg) --}}
    <div :class="{'block': open, 'hidden': !open}" class="hidden md:hidden border-t border-gray-100">
        <div class="px-4 py-3 space-y-1">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Übersicht
            </a>
            <a href="{{ route('media.index') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('media.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Medien
            </a>
            <a href="{{ route('loans.index') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('loans.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Ausleihen
            </a>
            <a href="{{ route('chat') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('chat') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                KI-Assistent
            </a>
            <a href="{{ route('wishes.index') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('wishes.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Wünsche
            </a>
            <a href="{{ route('bookmarks') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('bookmarks') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Merkliste
            </a>
            @if(auth()->user()?->isKurator())
                <a href="{{ route('curation.index') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('curation.index') ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Kuration
                </a>
                <a href="{{ route('curation.stats') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('curation.stats') ? 'bg-emerald-50 text-emerald-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Statistiken
                </a>
            @endif
            @if(auth()->user()?->isAdmin())
                <a href="{{ route('admin.users') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('admin.users*') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Administration
                </a>
                <a href="{{ route('admin.settings') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('admin.settings') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Einstellungen
                </a>
                <a href="{{ route('admin.locations') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('admin.locations') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Standorte
                </a>
                <a href="{{ route('admin.loans') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('admin.loans') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Ausleihen
                </a>
                <a href="{{ route('admin.audit') }}" wire:navigate
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium
                          {{ request()->routeIs('admin.audit') ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50' }}">
                    Protokoll
                </a>
            @endif

            <a href="{{ route('notifications') }}" wire:navigate
               class="block px-3 py-2.5 rounded-lg text-sm font-medium
                      {{ request()->routeIs('notifications') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }}">
                Benachrichtigungen
                @php $ungelesenMobil = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp
                @if($ungelesenMobil > 0)
                    <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1
                                 rounded-full bg-red-600 text-white text-[10px] font-bold">
                        {{ $ungelesenMobil > 99 ? '99+' : $ungelesenMobil }}
                    </span>
                @endif
            </a>
        </div>
    </div>
</nav>
