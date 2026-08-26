<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An- und Abmeldung von Geräten für Web-Push (Phase 8).
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $daten = $request->validate([
            'endpoint'  => ['required', 'string', 'max:2000', 'url'],
            'publicKey' => ['required', 'string', 'max:255'],
            'authToken' => ['required', 'string', 'max:255'],
        ]);

        $abo = PushSubscription::updateOrCreate(
            // Der Hash ist der Schlüssel: Meldet sich dasselbe Gerät erneut
            // an, soll kein zweiter Eintrag entstehen.
            ['endpoint_hash' => PushSubscription::hashFor($daten['endpoint'])],
            [
                'user_id'      => $request->user()->id,
                'endpoint'     => $daten['endpoint'],
                'public_key'   => $daten['publicKey'],
                'auth_token'   => $daten['authToken'],
                'geraet'       => mb_substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => null,
            ]
        );

        return response()->json(['status' => 'ok', 'id' => $abo->id]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $daten = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        // Nur eigene Abos: Ein fremder Endpunkt darf sich nicht abmelden lassen.
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashFor($daten['endpoint']))
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
