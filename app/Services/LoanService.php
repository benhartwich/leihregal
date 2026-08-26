<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\ReservationStatus;
use App\Models\Loan;
use App\Models\Media;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DateReservationAvailableNotification;
use App\Notifications\ReservationQueueNotification;
use App\Notifications\ReservationReadyNotification;
use Illuminate\Support\Facades\DB;

class LoanService
{
    const LOAN_DAYS = 14; // fallback only

    public function loanDaysFor(Media $media): int
    {
        if ($media->loan_days) {
            return (int) $media->loan_days;
        }
        return (int) Setting::get('loan_default_days', self::LOAN_DAYS);
    }

    // ── Borrow ────────────────────────────────────────────────────────────────

    /**
     * Lend a medium to a user.
     * Throws \RuntimeException if not possible.
     */
    public function borrow(Media $media, User $user): Loan
    {
        return DB::transaction(function () use ($media, $user) {
            // Re-fetch with lock
            $media = Media::lockForUpdate()->findOrFail($media->id);

            if ($media->status !== MediaStatus::Verfuegbar) {
                throw new \RuntimeException(
                    match ($media->status) {
                        MediaStatus::Ausgeliehen    => 'Dieses Medium ist bereits ausgeliehen.',
                        MediaStatus::Reserviert     => 'Dieses Medium ist reserviert.',
                        MediaStatus::InAufbereitung => 'Dieses Medium befindet sich in Aufbereitung.',
                        MediaStatus::Verloren       => 'Dieses Medium wurde als verloren markiert.',
                        MediaStatus::Ausgemustert   => 'Dieses Medium wurde ausgemustert.',
                        default                     => 'Dieses Medium ist aktuell nicht verfügbar.',
                    }
                );
            }

            // Check user doesn't already have this medium
            if (Loan::where('media_id', $media->id)->where('user_id', $user->id)->whereNull('returned_at')->exists()) {
                throw new \RuntimeException('Sie haben dieses Medium bereits ausgeliehen.');
            }

            $loan = Loan::create([
                'media_id'    => $media->id,
                'user_id'     => $user->id,
                'borrowed_at' => now(),
                'due_at'      => now()->addDays($this->loanDaysFor($media)),
            ]);

            $media->update(['status' => MediaStatus::Ausgeliehen]);

            return $loan;
        });
    }

    // ── Extend ───────────────────────────────────────────────────────────────

    /**
     * Extend a loan's due date by one period.
     * Throws \RuntimeException if not allowed.
     */
    public function extendLoan(Loan $loan, User $user): Loan
    {
        $maxExtensions = (int) Setting::get('max_extensions', 1);
        if ($loan->extension_count >= $maxExtensions) {
            throw new \RuntimeException("Maximale Anzahl Verlängerungen ($maxExtensions) erreicht.");
        }
        // Can't extend if someone is waiting in queue
        $hasReservation = Reservation::where('media_id', $loan->media_id)
            ->whereIn('status', ['wartend', 'bereit'])
            ->whereNull('reserved_from') // exclude dated reservations
            ->exists();
        if ($hasReservation) {
            throw new \RuntimeException('Verlängerung nicht möglich – jemand wartet auf dieses Medium.');
        }
        $days = $this->loanDaysFor($loan->media);
        $loan->update([
            'due_at'          => $loan->due_at->addDays($days),
            'extension_count' => $loan->extension_count + 1,
        ]);
        return $loan->fresh();
    }

    // ── Return ────────────────────────────────────────────────────────────────

    /**
     * Return a medium. Optionally accepts a rating (0/1) and comment.
     * Activates the next reservation if one exists.
     */
    public function returnMedia(Loan $loan, ?int $rating = null, ?string $comment = null): void
    {
        DB::transaction(function () use ($loan, $rating, $comment) {
            $loan->update([
                'returned_at'    => now(),
                'rating'         => $rating,
                'rating_comment' => $comment,
            ]);

            // Promote next reservation in queue, or set media back to available
            $nextReservation = Reservation::where('media_id', $loan->media_id)
                ->where('status', ReservationStatus::Wartend)
                ->orderBy('position')
                ->first();

            if ($nextReservation) {
                $nextReservation->update([
                    'status'       => ReservationStatus::Bereit,
                    'notified_at'  => now(),
                ]);
                $loan->media->update(['status' => MediaStatus::Reserviert]);

                // Notify first person: ready for pickup
                $nextReservation->load(['user', 'media']);
                if ($nextReservation->user) {
                    try {
                        $nextReservation->user->notify(
                            new ReservationReadyNotification($nextReservation)
                        );
                    } catch (\Throwable) {}
                }

                // Notify everyone else in queue: position update
                $queueRest = Reservation::where('media_id', $loan->media_id)
                    ->where('status', ReservationStatus::Wartend)
                    ->whereNull('reserved_from')
                    ->orderBy('position')
                    ->with(['user', 'media'])
                    ->get();

                foreach ($queueRest as $queued) {
                    if ($queued->user) {
                        try {
                            $queued->user->notify(
                                new ReservationQueueNotification($queued, $queued->position)
                            );
                        } catch (\Throwable) {}
                    }
                }
            } else {
                $loan->media->update(['status' => MediaStatus::Verfuegbar]);

                // Notify users with upcoming dated reservations (start within 7 days)
                $upcomingDated = Reservation::where('media_id', $loan->media_id)
                    ->whereNotNull('reserved_from')
                    ->whereBetween('reserved_from', [today(), today()->addDays(7)])
                    ->where('status', ReservationStatus::Wartend)
                    ->with(['user', 'media'])
                    ->get();

                foreach ($upcomingDated as $dated) {
                    if ($dated->user) {
                        try {
                            $dated->user->notify(
                                new DateReservationAvailableNotification($dated)
                            );
                        } catch (\Throwable) {}
                    }
                }
            }
        });
    }

    // ── Reserve ───────────────────────────────────────────────────────────────

    /**
     * Place a reservation for a user.
     * Throws \RuntimeException if not allowed.
     */
    public function reserve(Media $media, User $user): Reservation
    {
        return DB::transaction(function () use ($media, $user) {
            $media = Media::lockForUpdate()->findOrFail($media->id);

            if ($media->status === MediaStatus::Verfuegbar) {
                throw new \RuntimeException('Dieses Medium ist verfügbar – bitte direkt ausleihen.');
            }

            if (in_array($media->status, [MediaStatus::Verloren, MediaStatus::Ausgemustert])) {
                throw new \RuntimeException('Eine Reservierung ist für dieses Medium nicht möglich.');
            }

            // Already reserved or active loan by same user?
            $alreadyActive = Reservation::where('media_id', $media->id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['wartend', 'bereit'])
                ->exists();

            if ($alreadyActive) {
                throw new \RuntimeException('Sie haben dieses Medium bereits reserviert.');
            }

            $alreadyBorrowed = Loan::where('media_id', $media->id)
                ->where('user_id', $user->id)
                ->whereNull('returned_at')
                ->exists();

            if ($alreadyBorrowed) {
                throw new \RuntimeException('Sie haben dieses Medium bereits ausgeliehen.');
            }

            // Determine queue position
            $position = (Reservation::where('media_id', $media->id)
                ->whereIn('status', ['wartend', 'bereit'])
                ->max('position') ?? 0) + 1;

            return Reservation::create([
                'media_id' => $media->id,
                'user_id'  => $user->id,
                'position' => $position,
                'status'   => ReservationStatus::Wartend,
            ]);
        });
    }

    // ── Dated (vacation) reservation ─────────────────────────────────────────

    public function reserveWithDates(Media $media, User $user, string $from, string $until, string $notes = ''): Reservation
    {
        if (in_array($media->status->value, ['verloren', 'ausgemustert'])) {
            throw new \RuntimeException('Eine Reservierung ist für dieses Medium nicht möglich.');
        }

        $fromDate  = \Carbon\Carbon::parse($from)->startOfDay();
        $untilDate = \Carbon\Carbon::parse($until)->startOfDay();

        if ($fromDate->lt(today())) {
            throw new \RuntimeException('Das Startdatum muss heute oder in der Zukunft liegen.');
        }
        if ($untilDate->lt($fromDate)) {
            throw new \RuntimeException('Das Enddatum muss nach dem Startdatum liegen.');
        }

        $existing = Reservation::where('media_id', $media->id)
            ->where('user_id', $user->id)
            ->whereNotNull('reserved_from')
            ->whereIn('status', ['wartend', 'bereit'])
            ->exists();

        if ($existing) {
            throw new \RuntimeException('Du hast für dieses Medium bereits eine Urlaubsreservierung.');
        }

        // Check for overlapping dated reservations by other users
        $conflict = Reservation::where('media_id', $media->id)
            ->whereNotNull('reserved_from')
            ->whereIn('status', ['wartend', 'bereit'])
            ->where('reserved_from', '<=', $untilDate)
            ->where('reserved_until', '>=', $fromDate)
            ->exists();

        if ($conflict) {
            throw new \RuntimeException('Für diesen Zeitraum liegt bereits eine andere Reservierung vor.');
        }

        return Reservation::create([
            'media_id'       => $media->id,
            'user_id'        => $user->id,
            'status'         => ReservationStatus::Wartend,
            'reserved_from'  => $fromDate,
            'reserved_until' => $untilDate,
            'notes'          => $notes ?: null,
        ]);
    }

    // ── Cancel Reservation ────────────────────────────────────────────────────

    public function cancelReservation(Reservation $reservation, User $requestingUser): void
    {
        if ($reservation->user_id !== $requestingUser->id && ! $requestingUser->isKurator()) {
            throw new \RuntimeException('Keine Berechtigung zum Stornieren dieser Reservierung.');
        }

        DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => ReservationStatus::Storniert]);
            $this->resequenceQueue($reservation->media_id);

            // If the 'bereit' slot was cancelled, check if medium should revert to 'ausgeliehen' or 'verfuegbar'
            $media = Media::lockForUpdate()->findOrFail($reservation->media_id);
            if ($media->status === MediaStatus::Reserviert) {
                $nextWaiting = Reservation::where('media_id', $media->id)
                    ->where('status', ReservationStatus::Wartend)
                    ->orderBy('position')
                    ->first();

                if ($nextWaiting) {
                    $nextWaiting->update(['status' => ReservationStatus::Bereit, 'notified_at' => now()]);
                } elseif (! Loan::where('media_id', $media->id)->whereNull('returned_at')->exists()) {
                    $media->update(['status' => MediaStatus::Verfuegbar]);
                }
            }
        });
    }

    // ── Pick up reserved medium ───────────────────────────────────────────────

    public function pickupReservation(Reservation $reservation): Loan
    {
        return DB::transaction(function () use ($reservation) {
            if ($reservation->status !== ReservationStatus::Bereit) {
                throw new \RuntimeException('Diese Reservierung ist noch nicht zur Abholung bereit.');
            }

            $reservation->update(['status' => ReservationStatus::Abgeholt]);

            $loan = Loan::create([
                'media_id'    => $reservation->media_id,
                'user_id'     => $reservation->user_id,
                'borrowed_at' => now(),
                'due_at'      => now()->addDays($this->loanDaysFor($reservation->media)),
            ]);

            $reservation->media->update(['status' => MediaStatus::Ausgeliehen]);

            return $loan;
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resequenceQueue(int $mediaId): void
    {
        $reservations = Reservation::where('media_id', $mediaId)
            ->whereIn('status', ['wartend', 'bereit'])
            ->orderBy('position')
            ->get();

        foreach ($reservations as $i => $r) {
            $r->update(['position' => $i + 1]);
        }
    }

    /**
     * Find an active loan by a media's internal_code barcode.
     */
    public function findActiveLoanByCode(string $code): ?Loan
    {
        $media = Media::where('internal_code', $code)->first();
        if (! $media) return null;

        return Loan::where('media_id', $media->id)
            ->whereNull('returned_at')
            ->with('media', 'user')
            ->first();
    }

    /**
     * Find a media by internal_code.
     */
    public function findMediaByCode(string $code): ?Media
    {
        $upper = strtoupper(trim($code));

        // Try exact match first (LIB codes contain dashes — don't strip them)
        $byCode = Media::where('internal_code', $upper)->first();
        if ($byCode) return $byCode;

        // Strip spaces/dashes for ISBN matching
        $normalized = preg_replace('/[\s\-]/', '', $upper);

        // Also try internal_code without dashes in case scanner omits them
        $byCodeStripped = Media::whereRaw("REPLACE(internal_code, '-', '') = ?", [$normalized])->first();
        if ($byCodeStripped) return $byCodeStripped;

        // ISBN match: prefer available copy, fall back to first
        return Media::whereRaw("REPLACE(REPLACE(isbn, '-', ''), ' ', '') = ?", [$normalized])
            ->orderByRaw("CASE status WHEN 'verfuegbar' THEN 0 ELSE 1 END")
            ->first();
    }
}
