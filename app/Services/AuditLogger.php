<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Schreibt Einträge ins Protokoll der Kurations- und Admin-Aktionen (Spec 5).
 *
 * Einzige Stelle, an der Protokolleinträge entstehen – damit die Auflösung des
 * handelnden Nutzers und die Schwärzung sensibler Werte nicht an mehreren
 * Stellen gepflegt werden müssen.
 */
class AuditLogger
{
    /**
     * Feldnamen, deren Werte niemals im Protokoll landen dürfen.
     * Geprüft wird per Teilstring, damit auch `password_confirmation` greift.
     */
    private const GEHEIME_FELDER = [
        'password',
        'remember_token',
        'api_key',
        'secret',
        'token',
    ];

    public static function schreibe(
        string $action,
        string $entity,
        int|string|null $entityId = null,
        ?string $entityLabel = null,
        array $diff = [],
    ): ?AuditLog {
        $nutzer   = Auth::user();
        $nutzerId = $nutzer?->id;

        // Löscht ein Konto sich selbst, ist die users-Zeile bereits weg, wenn
        // das `deleted`-Event hier ankommt – ein Verweis darauf verletzt den
        // Fremdschlüssel und liesse das Löschen scheitern. Der Name bleibt
        // ohnehin im Klartext erhalten, der Eintrag also lesbar.
        if ($nutzerId !== null
            && $action === 'geloescht'
            && $entity === 'User'
            && (string) $entityId === (string) $nutzerId) {
            $nutzerId = null;
        }

        return AuditLog::create([
            'user_id'      => $nutzerId,
            // Bei Konsolen- und Scheduler-Aktionen gibt es keinen angemeldeten
            // Nutzer – das wird als solches festgehalten statt leer zu bleiben.
            'user_name'    => $nutzer?->name ?? 'System',
            'user_role'    => $nutzer instanceof User ? $nutzer->role?->value : null,
            'action'       => $action,
            'entity'       => $entity,
            'entity_id'    => $entityId !== null ? (string) $entityId : null,
            'entity_label' => $entityLabel,
            'diff'         => $diff ?: null,
        ]);
    }

    /**
     * Ersetzt Werte zu sensiblen Feldern durch einen Platzhalter.
     * Der Umstand *dass* etwas geändert wurde bleibt sichtbar, der Wert nicht.
     */
    public static function istGeheim(string $feld): bool
    {
        $feld = strtolower($feld);

        foreach (self::GEHEIME_FELDER as $muster) {
            if (str_contains($feld, $muster)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bringt einen Attributwert in eine protokollierbare Form.
     */
    public static function wert(string $feld, mixed $wert): mixed
    {
        if (self::istGeheim($feld)) {
            return '(geändert, nicht protokolliert)';
        }

        if ($wert instanceof \BackedEnum) {
            return $wert->value;
        }

        if ($wert instanceof \DateTimeInterface) {
            return $wert->format('Y-m-d H:i:s');
        }

        if (is_object($wert)) {
            return (string) $wert;
        }

        // Lange Texte (KI-Zusammenfassungen, Praxiseinsatz) würden das
        // Protokoll unlesbar machen und aufblähen.
        if (is_string($wert) && mb_strlen($wert) > 300) {
            return mb_substr($wert, 0, 300) . ' … (gekürzt)';
        }

        return $wert;
    }
}
