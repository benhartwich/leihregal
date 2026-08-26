<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Versendet Benachrichtigungen als Web-Push (Phase 8).
 *
 * Eine Notification wird darüber zugestellt, wenn sie `toWebPush()`
 * bereitstellt und der Kanal `webpush` in `via()` steht.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $abos = PushSubscription::where('user_id', $notifiable->getKey())->get();

        if ($abos->isEmpty()) {
            return;
        }

        $push = $this->client();

        if ($push === null) {
            return;
        }

        $nutzlast = json_encode($notification->toWebPush($notifiable), JSON_UNESCAPED_UNICODE);

        foreach ($abos as $abo) {
            try {
                $push->queueNotification(
                    Subscription::create([
                        'endpoint'        => $abo->endpoint,
                        'publicKey'       => $abo->public_key,
                        'authToken'       => $abo->auth_token,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    $nutzlast
                );
            } catch (Throwable $e) {
                Log::warning('Push-Abo konnte nicht eingereiht werden', [
                    'abo_id' => $abo->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        try {
            foreach ($push->flush() as $bericht) {
                $endpunkt = $bericht->getRequest()->getUri()->__toString();
                $abo      = $abos->firstWhere('endpoint_hash', PushSubscription::hashFor($endpunkt));

                if ($bericht->isSuccess()) {
                    $abo?->update(['last_used_at' => now()]);
                    continue;
                }

                // 404/410 heisst: Der Browser hat das Abo verworfen (App
                // deinstalliert, Berechtigung entzogen). Solche Abos müssen
                // weg, sonst sammeln sich Karteileichen an.
                if ($bericht->isSubscriptionExpired()) {
                    $abo?->delete();
                    continue;
                }

                Log::warning('Push-Zustellung fehlgeschlagen', [
                    'grund' => $bericht->getReason(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Push-Versand fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    private function client(): ?WebPush
    {
        $oeffentlich = config('webpush.public_key');
        $privat      = config('webpush.private_key');

        if (! $oeffentlich || ! $privat) {
            Log::warning('Web-Push ohne VAPID-Schlüssel – Versand übersprungen.');
            return null;
        }

        try {
            return new WebPush([
                'VAPID' => [
                    'subject'    => config('webpush.subject'),
                    'publicKey'  => $oeffentlich,
                    'privateKey' => $privat,
                ],
            ], ['TTL' => config('webpush.ttl', 86400)]);
        } catch (Throwable $e) {
            Log::warning('Web-Push-Client konnte nicht erstellt werden', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
