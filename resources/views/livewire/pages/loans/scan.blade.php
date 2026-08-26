<?php

use App\Enums\ReservationStatus;
use App\Models\Loan;
use App\Models\Media;
use App\Models\Reservation;
use App\Services\LoanService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string  $manualCode = '';
    public ?string $message    = null;
    public bool    $success    = false;
    public ?array  $mediaInfo  = null;
    public string  $pendingAction = '';   // 'borrow' | 'pickup' | 'reserve' | ''
    public ?int    $pendingId     = null; // media or reservation id

    public function processCode(string $code): void
    {
        $this->reset(['message', 'mediaInfo', 'pendingAction', 'pendingId']);
        $this->success = false;

        // Extract LIB-code from a URL if QR contains a full URL
        if (preg_match('/(LIB-[A-Z0-9]+)/i', $code, $m)) {
            $code = $m[1];
        }
        $code = trim(strtoupper($code));
        if (! $code) return;

        $service = app(LoanService::class);
        $media   = $service->findMediaByCode($code);

        if (! $media) {
            $this->message = "Code \"$code\" wurde nicht gefunden.";
            $this->success = false;
            return;
        }

        $this->mediaInfo = [
            'title'  => $media->title,
            'author' => $media->author,
            'cover'  => $media->cover_path,
            'icon'   => $media->type->icon(),
            'status' => $media->status->label(),
            'code'   => $media->internal_code,
        ];

        // Already loaned by me → straight to return page (this IS the explicit confirm step)
        $activeLoan = Loan::where('media_id', $media->id)
            ->where('user_id', auth()->id())
            ->whereNull('returned_at')
            ->first();

        if ($activeLoan) {
            $this->message = "Rückgabe: " . $media->title;
            $this->success = true;
            $this->redirect(route('loans.return', $activeLoan), navigate: true);
            return;
        }

        // User has a "Bereit" reservation → offer pickup
        $readyReservation = Reservation::where('media_id', $media->id)
            ->where('user_id', auth()->id())
            ->where('status', ReservationStatus::Bereit)
            ->first();

        if ($readyReservation) {
            $this->pendingAction = 'pickup';
            $this->pendingId     = $readyReservation->id;
            $this->message       = "{$media->title} – möchtest du es jetzt abholen?";
            return;
        }

        if ($media->status->value === 'verfuegbar') {
            $loanDays = $service->loanDaysFor($media);
            $this->pendingAction = 'borrow';
            $this->pendingId     = $media->id;
            $this->message       = "{$media->title} – ausleihen für {$loanDays} Tage?";
            return;
        }

        if ($media->status->value === 'ausgeliehen') {
            $this->pendingAction = 'reserve';
            $this->pendingId     = $media->id;
            $this->message       = "{$media->title} ist ausgeliehen. Reservieren?";
            return;
        }

        $this->message = "{$media->title} ist aktuell nicht verfügbar ({$media->status->label()}).";
        $this->success = false;
    }

    public function confirmAction(): void
    {
        $service = app(LoanService::class);

        try {
            if ($this->pendingAction === 'borrow') {
                $media = Media::findOrFail($this->pendingId);
                $loan  = $service->borrow($media, auth()->user());
                $this->message = "{$media->title} ausgeliehen bis " . $loan->due_at->format('d.m.Y') . '.';
                $this->success = true;
            } elseif ($this->pendingAction === 'pickup') {
                $reservation = Reservation::findOrFail($this->pendingId);
                $loan = $service->pickupReservation($reservation);
                $this->message = "{$reservation->media->title} abgeholt – zurück bis " . $loan->due_at->format('d.m.Y') . '.';
                $this->success = true;
            } elseif ($this->pendingAction === 'reserve') {
                $media = Media::findOrFail($this->pendingId);
                $res   = $service->reserve($media, auth()->user());
                $this->message = "{$media->title} reserviert (Position {$res->position}).";
                $this->success = true;
            }
        } catch (\RuntimeException $e) {
            $this->message = $e->getMessage();
            $this->success = false;
        }

        $this->pendingAction = '';
        $this->pendingId     = null;
        $this->mediaInfo     = null;
        $this->manualCode    = '';
    }

    public function cancelAction(): void
    {
        $this->reset(['pendingAction', 'pendingId', 'mediaInfo', 'message', 'manualCode']);
        $this->success = false;
    }

    public function submitManual(): void
    {
        $this->processCode($this->manualCode);
    }
}; ?>

<div class="space-y-5">

    <h1 class="text-xl font-semibold text-gray-900">QR-Code scannen</h1>

    {{-- Bestätigungsschritt --}}
    @if($pendingAction && $mediaInfo)
        <div class="bg-white rounded-2xl border-2 border-blue-300 p-5 space-y-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-22 rounded-lg bg-gray-100 shrink-0 overflow-hidden flex items-center justify-center">
                    @if($mediaInfo['cover'])
                        <img src="{{ asset('storage/' . $mediaInfo['cover']) }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-3xl">{{ $mediaInfo['icon'] }}</span>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 wrap-break-word">{{ $mediaInfo['title'] }}</p>
                    @if($mediaInfo['author'])
                        <p class="text-sm text-gray-500">{{ $mediaInfo['author'] }}</p>
                    @endif
                    <p class="text-xs text-gray-400 font-mono mt-1">{{ $mediaInfo['code'] }}</p>
                </div>
            </div>
            <p class="text-sm text-gray-700">{{ $message }}</p>
            <div class="flex flex-wrap gap-2">
                <button wire:click="confirmAction"
                        class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-700">
                    @if($pendingAction === 'borrow') Jetzt ausleihen
                    @elseif($pendingAction === 'pickup') Jetzt abholen
                    @elseif($pendingAction === 'reserve') Reservieren
                    @endif
                </button>
                <button wire:click="cancelAction"
                        class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2.5">
                    Abbrechen
                </button>
            </div>
        </div>
    @elseif($message)
        <div class="px-4 py-4 rounded-2xl text-sm font-medium
                    {{ $success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-amber-50 border border-amber-200 text-amber-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Camera scanner --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden"
         x-data="qrScanner($wire)">

        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700">Kamera-Scan</p>
            <button @click="toggleCamera()" x-text="cameraActive ? 'Kamera stoppen' : 'Kamera starten'"
                    class="text-sm text-blue-600 hover:text-blue-800 font-medium"></button>
        </div>

        <div x-show="cameraActive" class="relative bg-black">
            <video id="scan-video" class="w-full max-h-72 object-cover" autoplay muted playsinline></video>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-48 h-48 border-2 border-blue-400 rounded-2xl opacity-70 shadow-lg"></div>
            </div>
            <div x-show="lastDetected" class="absolute bottom-2 left-0 right-0 text-center">
                <span class="bg-black/60 text-white text-xs px-2 py-1 rounded-sm font-mono"
                      x-text="lastDetected"></span>
            </div>
        </div>

        <div x-show="!cameraActive" class="p-8 text-center text-gray-400 text-sm">
            Kamera antippen um QR-Code zu scannen
        </div>
    </div>

    {{-- Manual entry --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
        <p class="text-sm font-medium text-gray-700">Manuelle Eingabe</p>
        <div class="flex gap-2">
            <input wire:model="manualCode"
                   type="text"
                   placeholder="LIB-Code eingeben"
                   class="flex-1 rounded-xl border-gray-300 text-sm py-2.5 font-mono focus:ring-blue-500 focus:border-blue-500 uppercase"
                   x-on:keydown.enter="$wire.submitManual()">
            <button wire:click="submitManual"
                    class="px-4 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition-colors">
                OK
            </button>
        </div>
    </div>

    <div class="text-center">
        <a href="{{ route('loans.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
            Zurück zu meinen Ausleihen
        </a>
    </div>

</div>

<script>
window.qrScanner = function($wire) {
    return {
        cameraActive: false,
        controls: null,
        lastDetected: '',

        async toggleCamera() {
            this.cameraActive ? this.stopCamera() : await this.startCamera();
        },

        async startCamera() {
            if (! window.BrowserMultiFormatReader) {
                alert('Scanner nicht verfügbar. Bitte Seite neu laden.');
                return;
            }
            try {
                // POSSIBLE_FORMATS = 2, TRY_HARDER = 3
                // CODE_128 = 4 (aktuelle Etiketten, Spec 4.1),
                // QR_CODE = 11 (früher gedruckte Etiketten weiterhin lesbar).
                const hints = new Map([[2, [4, 11]], [3, true]]);
                const reader = new window.BrowserMultiFormatReader(hints);
                const video  = document.getElementById('scan-video');
                this.cameraActive = true;
                this.lastDetected = '';

                this.controls = await reader.decodeFromConstraints(
                    { video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } },
                    video,
                    (result) => {
                        if (result) {
                            const raw = result.getText().trim();
                            this.lastDetected = raw;
                            this.stopCamera();
                            $wire.processCode(raw);
                        }
                    }
                );
            } catch (e) {
                alert('Kamera konnte nicht gestartet werden: ' + e.message);
                this.cameraActive = false;
            }
        },

        stopCamera() {
            if (this.controls) {
                try { this.controls.stop(); } catch (_) {}
                this.controls = null;
            }
            this.cameraActive = false;
        },

        destroy() { this.stopCamera(); }
    };
};
</script>
