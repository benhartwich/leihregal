<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll der Kurations- und Admin-Aktionen (Spec 5).
 *
 * Einträge sind unveränderlich: Sie werden ausschließlich angelegt, nie
 * bearbeitet oder gelöscht. Deshalb kein `updated_at`.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'entity',
        'entity_id',
        'entity_label',
        'diff',
    ];

    protected function casts(): array
    {
        return [
            'diff'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Deutsche Beschriftung der Aktion für die Anzeige.
     */
    public function actionLabel(): string
    {
        return match ($this->action) {
            'erstellt'     => 'angelegt',
            'geaendert'    => 'geändert',
            'geloescht'    => 'gelöscht',
            'aktiviert'    => 'aktiviert',
            'deaktiviert'  => 'deaktiviert',
            default        => $this->action,
        };
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'erstellt'    => 'bg-green-100 text-green-800',
            'geloescht'   => 'bg-red-100 text-red-800',
            'deaktiviert' => 'bg-amber-100 text-amber-800',
            'aktiviert'   => 'bg-green-100 text-green-800',
            default       => 'bg-blue-100 text-blue-800',
        };
    }

    /**
     * Anzeigename der betroffenen Entität, z. B. „Medium", „Nutzer".
     */
    public function entityLabel(): string
    {
        return match ($this->entity) {
            'Media'                 => 'Medium',
            'User'                  => 'Nutzer',
            'WhitelistEntry'        => 'Whitelist-Eintrag',
            'AcquisitionSuggestion' => 'Anschaffung',
            'Wish'                  => 'Wunsch',
            'Location'              => 'Standort',
            'Setting'               => 'Einstellung',
            default                 => $this->entity,
        };
    }
}
