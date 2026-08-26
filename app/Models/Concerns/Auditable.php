<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Protokolliert Anlegen, Ändern und Löschen eines Models automatisch.
 *
 * Bewusst über Model-Events statt über Aufrufe an den einzelnen Stellen:
 * Die Schreibzugriffe verteilen sich über Volt-Komponenten, Controller,
 * Services und Konsolenbefehle. Jeder neue Schreibpfad wäre sonst eine
 * potenziell vergessene Protokollzeile.
 *
 * Models können anpassen:
 *   protected array $auditIgnoriert = ['spalte'];
 *   public function auditBezeichnung(): string
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLogger::schreibe(
                action:      'erstellt',
                entity:      class_basename($model),
                entityId:    $model->getKey(),
                entityLabel: $model->auditBezeichnung(),
                diff:        $model->auditNeuwerte(),
            );
        });

        static::updated(function ($model) {
            $diff = $model->auditAenderungen();

            // Wurde nichts Protokollwürdiges verändert (z. B. nur ein
            // Zeitstempel), entsteht auch kein Eintrag.
            if ($diff === []) {
                return;
            }

            AuditLogger::schreibe(
                action:      'geaendert',
                entity:      class_basename($model),
                entityId:    $model->getKey(),
                entityLabel: $model->auditBezeichnung(),
                diff:        $diff,
            );
        });

        static::deleted(function ($model) {
            AuditLogger::schreibe(
                action:      'geloescht',
                entity:      class_basename($model),
                entityId:    $model->getKey(),
                entityLabel: $model->auditBezeichnung(),
                diff:        $model->auditNeuwerte(),
            );
        });
    }

    /**
     * Spalten, die nie protokolliert werden.
     */
    protected function auditIgnorierteFelder(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'remember_token'],
            property_exists($this, 'auditIgnoriert') ? $this->auditIgnoriert : [],
        );
    }

    /**
     * Geänderte Felder als ['feld' => ['alt' => …, 'neu' => …]].
     */
    public function auditAenderungen(): array
    {
        $ignoriert = $this->auditIgnorierteFelder();
        $diff      = [];

        foreach ($this->getChanges() as $feld => $neu) {
            if (in_array($feld, $ignoriert, true)) {
                continue;
            }

            $diff[$feld] = [
                'alt' => AuditLogger::wert($feld, $this->getOriginal($feld)),
                'neu' => AuditLogger::wert($feld, $neu),
            ];
        }

        return $diff;
    }

    /**
     * Alle protokollierbaren Attribute – für Anlegen und Löschen.
     */
    public function auditNeuwerte(): array
    {
        $ignoriert = $this->auditIgnorierteFelder();
        $werte     = [];

        foreach ($this->getAttributes() as $feld => $wert) {
            if (in_array($feld, $ignoriert, true)) {
                continue;
            }

            $werte[$feld] = AuditLogger::wert($feld, $wert);
        }

        return $werte;
    }

    /**
     * Lesbare Bezeichnung des Datensatzes. Models überschreiben das.
     */
    public function auditBezeichnung(): string
    {
        foreach (['title', 'name', 'key'] as $feld) {
            if (! empty($this->{$feld})) {
                return (string) $this->{$feld};
            }
        }

        return '#' . $this->getKey();
    }
}
