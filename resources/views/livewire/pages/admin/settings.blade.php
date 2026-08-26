<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string  $loan_default_days  = '14';
    public string  $loan_reminder_days = '2';
    public string  $max_extensions     = '1';
    public string  $isbndb_api_key     = '';
    public ?string $toast              = null;
    public ?bool   $isbndbValid        = null;

    public function mount(): void
    {
        $this->loan_default_days  = Setting::get('loan_default_days', '14');
        $this->loan_reminder_days = Setting::get('loan_reminder_days', '2');
        $this->max_extensions     = Setting::get('max_extensions', '1');
        $this->isbndb_api_key     = Setting::get('isbndb_api_key', '');
    }

    public function save(): void
    {
        $this->validate([
            'loan_default_days'  => ['required', 'integer', 'min:1', 'max:365'],
            'loan_reminder_days' => ['required', 'integer', 'min:0', 'max:30'],
            'max_extensions'     => ['required', 'integer', 'min:0', 'max:5'],
            'isbndb_api_key'     => ['nullable', 'string', 'max:200'],
        ], [
            'loan_default_days.required'  => 'Bitte einen Wert eingeben.',
            'loan_default_days.min'       => 'Mindestens 1 Tag.',
            'loan_reminder_days.required' => 'Bitte einen Wert eingeben.',
            'max_extensions.required'     => 'Bitte einen Wert eingeben.',
        ]);

        Setting::set('loan_default_days',  $this->loan_default_days);
        Setting::set('loan_reminder_days', $this->loan_reminder_days);
        Setting::set('max_extensions',     $this->max_extensions);
        Setting::set('isbndb_api_key',     trim($this->isbndb_api_key));

        $this->toast = 'Einstellungen gespeichert.';
    }

    public function testIsbndb(): void
    {
        $key = trim($this->isbndb_api_key);
        if (! $key) {
            $this->isbndbValid = false;
            return;
        }
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders(['Authorization' => $key])
                ->get('https://api2.isbndb.com/book/9780062316097');
            $this->isbndbValid = $resp->successful() && ! empty($resp->json('book.title'));
        } catch (\Throwable) {
            $this->isbndbValid = false;
        }
    }
}; ?>

<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.users') }}" wire:navigate class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900">Einstellungen</h1>
    </div>

    @if($toast)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm font-medium">
            {{ $toast }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5">

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Ausleihzeiten</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Standard-Ausleihzeit (Tage)
                    </label>
                    <input wire:model="loan_default_days" type="number" min="1" max="365"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">Gilt für alle Medien ohne individuelle Ausleihzeit.</p>
                    @error('loan_default_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Erinnerung vor Fälligkeit (Tage)
                    </label>
                    <input wire:model="loan_reminder_days" type="number" min="0" max="30"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">0 = keine Erinnerungs-E-Mail versenden.</p>
                    @error('loan_reminder_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Max. Verlängerungen pro Ausleihe
                    </label>
                    <input wire:model="max_extensions" type="number" min="0" max="5"
                           class="w-full rounded-xl border-gray-300 text-sm py-2.5 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">0 = keine Verlängerungen erlaubt.</p>
                    @error('max_extensions') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ISBNdb --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">ISBNdb (optional)</h2>
                <p class="mt-1 text-xs text-gray-400">
                    Kostenpflichtige ISBN-Datenbank (~10 €/Monat) als letzter Fallback für Bücher kleiner Verlage.
                    API-Key unter <a href="https://isbndb.com" target="_blank" class="text-blue-500 hover:underline">isbndb.com</a> beantragen.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">API-Key</label>
                <div class="flex gap-2">
                    <input wire:model="isbndb_api_key" type="password"
                           placeholder="sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                           autocomplete="off"
                           class="flex-1 rounded-xl border-gray-300 text-sm py-2.5 font-mono focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" wire:click="testIsbndb"
                            class="px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-600 whitespace-nowrap">
                        Testen
                    </button>
                </div>

                @if($isbndbValid === true)
                    <p class="mt-1.5 text-xs text-green-600 font-medium">✓ API-Key gültig – ISBNdb ist aktiv</p>
                @elseif($isbndbValid === false)
                    <p class="mt-1.5 text-xs text-red-600">✗ Ungültiger Key oder Verbindungsfehler</p>
                @else
                    <p class="mt-1.5 text-xs text-gray-400">Leer lassen um ISBNdb zu deaktivieren.</p>
                @endif
            </div>
        </div>

        <button type="submit"
                class="bg-blue-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-blue-700 transition-colors">
            Speichern
        </button>

    </form>

</div>
