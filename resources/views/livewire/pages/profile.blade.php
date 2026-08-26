<?php

use App\Models\MediaTag;
use App\Models\TagSubscription;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name     = '';
    public string $email    = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public array  $subscriptions = [];

    public function mount(): void
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->subscriptions = TagSubscription::where('user_id', Auth::id())
            ->pluck('tag')
            ->toArray();
    }

    public function toggleSubscription(string $tag): void
    {
        $userId = Auth::id();
        $existing = TagSubscription::where('user_id', $userId)->where('tag', $tag)->first();
        if ($existing) {
            $existing->delete();
            $this->subscriptions = array_values(array_filter($this->subscriptions, fn ($t) => $t !== $tag));
        } else {
            TagSubscription::create(['user_id' => $userId, 'tag' => $tag]);
            $this->subscriptions[] = $tag;
        }
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
        ], [
            'name.required'  => 'Bitte einen Namen eingeben.',
            'email.required' => 'Bitte eine E-Mail-Adresse eingeben.',
            'email.unique'   => 'Diese E-Mail-Adresse ist bereits vergeben.',
        ]);

        Auth::user()->fill($validated)->save();

        session()->flash('success', 'Profil wurde gespeichert.');
        $this->redirect(route('profile'), navigate: true);
    }

    public function updatePassword(): void
    {
        // Ohne Bremse liesse sich das aktuelle Passwort ueber dieses Formular
        // erraten - die Pruefung unten ist sonst selbst ein Orakel.
        $schluessel = 'passwortwechsel:' . Auth::id();

        if (RateLimiter::tooManyAttempts($schluessel, 5)) {
            throw ValidationException::withMessages([
                'current_password' => 'Zu viele Versuche. Bitte in '
                    . RateLimiter::availableIn($schluessel) . ' Sekunden erneut versuchen.',
            ]);
        }

        try {
            $validated = $this->validate([
                // 'current_password' prueft gegen das Passwort des angemeldeten
                // Kontos. Ohne diese Regel genuegte eine uebernommene Sitzung,
                // um das Konto dauerhaft zu kapern: Der rechtmaessige Inhaber
                // waere ausgesperrt, der Zugriff bliebe bestehen.
                'current_password' => ['required', 'current_password'],
                'password'         => ['required', 'min:8', 'confirmed', 'different:current_password'],
            ], [
                'current_password.required'         => 'Bitte das aktuelle Passwort eingeben.',
                'current_password.current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
                'password.required'                 => 'Bitte ein neues Passwort eingeben.',
                'password.min'                      => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
                'password.confirmed'                => 'Die Passwörter stimmen nicht überein.',
                'password.different'                => 'Das neue Passwort muss sich vom aktuellen unterscheiden.',
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($schluessel, 60);
            throw $e;
        }

        RateLimiter::clear($schluessel);

        Auth::user()->update(['password' => $validated['password']]);

        // Andere Sitzungen desselben Kontos entwerten. Wurde das Passwort
        // gewechselt, weil eine Sitzung fremd uebernommen wurde, waere sie
        // sonst weiterhin gueltig.
        //
        // Mit dem NEUEN Passwort aufrufen: Die Methode prueft gegen den
        // aktuell gespeicherten Hash - der ist nach dem update() oben bereits
        // der neue - und schreibt ihn in die eigene Sitzung. Alle uebrigen
        // Sitzungen tragen noch den alten Hash und werden von der
        // AuthenticateSession-Middleware beim naechsten Zugriff abgemeldet.
        Auth::logoutOtherDevices($validated['password']);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('success', 'Passwort wurde geändert. Andere Geräte wurden abgemeldet.');
        $this->redirect(route('profile'), navigate: true);
    }

    public function with(): array
    {
        return [
            'allTags' => MediaTag::distinct()->orderBy('tag')->pluck('tag'),
        ];
    }
}; ?>

<div class="space-y-6">

    <h1 class="text-xl font-semibold text-gray-900">Mein Profil</h1>

    {{-- Profile data --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-medium text-gray-700 mb-4">Persönliche Daten</h2>

        <form wire:submit="updateProfile" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input wire:model="name" type="text" required
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail-Adresse</label>
                <input wire:model="email" type="email" required
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 transition-colors">
                    Speichern
                </button>
                <span class="text-xs text-gray-400">Rolle: {{ auth()->user()->role->label() }}</span>
            </div>
        </form>
    </div>

    {{-- Password change --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-medium text-gray-700 mb-4">Passwort ändern</h2>

        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Aktuelles Passwort</label>
                <input wire:model="current_password" type="password" autocomplete="current-password"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort</label>
                <input wire:model="password" type="password" autocomplete="new-password"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passwort bestätigen</label>
                <input wire:model="password_confirmation" type="password" autocomplete="new-password"
                       class="w-full rounded-xl border-gray-300 shadow-xs focus:ring-blue-500 focus:border-blue-500 text-base py-3">
                @error('password_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="bg-gray-800 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-gray-900 transition-colors">
                Passwort ändern
            </button>

            <p class="text-xs text-gray-400">
                Nach der Änderung werden Sie auf allen anderen Geräten abgemeldet.
            </p>
        </form>
    </div>

    {{-- Push-Benachrichtigungen (Phase 8) --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6"
         x-data="{
             zustand: 'pruefe',
             meldung: '',
             fehler: false,
             async pruefen() {
                 if (!window.appPush || !window.appPush.verfuegbar()) {
                     this.zustand = 'nicht-verfuegbar';
                     return;
                 }
                 if (window.appPush.status() === 'denied') {
                     this.zustand = 'blockiert';
                     return;
                 }
                 this.zustand = (await window.appPush.istAngemeldet()) ? 'an' : 'aus';
             },
             async anmelden() {
                 this.meldung = ''; this.fehler = false;
                 try {
                     await window.appPush.anmelden(@js(config('webpush.public_key')));
                     this.zustand = 'an';
                     this.meldung = 'Dieses Gerät erhält jetzt Push-Benachrichtigungen.';
                 } catch (e) {
                     this.fehler = true;
                     this.meldung = e.message;
                     if (window.appPush.status() === 'denied') this.zustand = 'blockiert';
                 }
             },
             async abmelden() {
                 this.meldung = ''; this.fehler = false;
                 try {
                     await window.appPush.abmelden();
                     this.zustand = 'aus';
                     this.meldung = 'Dieses Gerät erhält keine Push-Benachrichtigungen mehr.';
                 } catch (e) {
                     this.fehler = true;
                     this.meldung = e.message;
                 }
             }
         }"
         x-init="pruefen()">

        <h2 class="font-medium text-gray-700 mb-1">Push auf dieses Gerät</h2>
        <p class="text-sm text-gray-400 mb-4">
            Hinweise erscheinen sofort auf dem Sperrbildschirm, auch wenn {{ config('app.name') }}
            gerade nicht geöffnet ist. Die Einstellung gilt nur für dieses Gerät.
        </p>

        <template x-if="zustand === 'pruefe'">
            <p class="text-sm text-gray-400">Wird geprüft …</p>
        </template>

        <template x-if="zustand === 'nicht-verfuegbar'">
            <p class="text-sm text-gray-500">
                Dieser Browser unterstützt keine Push-Benachrichtigungen.
                Auf dem iPhone funktioniert es erst, wenn {{ config('app.name') }} über
                „Zum Home-Bildschirm" hinzugefügt wurde.
            </p>
        </template>

        <template x-if="zustand === 'blockiert'">
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                Benachrichtigungen sind für diese Seite im Browser blockiert.
                Bitte in den Website-Einstellungen des Browsers erlauben.
            </p>
        </template>

        <template x-if="zustand === 'aus'">
            <button @click="anmelden()" type="button"
                    class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-medium hover:bg-blue-700 transition-colors">
                Auf diesem Gerät aktivieren
            </button>
        </template>

        <template x-if="zustand === 'an'">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex items-center gap-1.5 text-sm text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
                    <span class="w-2 h-2 rounded-full bg-green-600"></span> Aktiv
                </span>
                <button @click="abmelden()" type="button"
                        class="text-sm text-gray-600 hover:text-gray-900 underline">
                    Auf diesem Gerät deaktivieren
                </button>
            </div>
        </template>

        <p x-show="meldung" x-cloak class="mt-3 text-sm"
           :class="fehler ? 'text-red-600' : 'text-green-700'" x-text="meldung"></p>
    </div>

    {{-- Tag subscriptions --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-medium text-gray-700 mb-1">Benachrichtigungen</h2>
        <p class="text-sm text-gray-400 mb-4">
            Erhalten Sie eine E-Mail, wenn ein neues Medium mit einem der folgenden Schlagwörter hinzugefügt wird.
        </p>

        @if($allTags->isEmpty())
            <p class="text-sm text-gray-400">Noch keine Schlagwörter in der Bibliothek vorhanden.</p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach($allTags as $tag)
                    @php $subscribed = in_array($tag, $subscriptions); @endphp
                    <button wire:click="toggleSubscription('{{ $tag }}')"
                            class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors
                                   {{ $subscribed
                                       ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                                       : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-700' }}">
                        {{ $tag }}
                        @if($subscribed)
                            <span class="ml-1 opacity-75">&#10003;</span>
                        @endif
                    </button>
                @endforeach
            </div>
            @if(!empty($subscriptions))
                <p class="mt-3 text-xs text-gray-400">
                    {{ count($subscriptions) }} Schlagwort(e) abonniert. Klicken Sie auf ein Schlagwort um das Abonnement umzuschalten.
                </p>
            @endif
        @endif
    </div>

</div>
