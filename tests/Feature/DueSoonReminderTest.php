<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Notifications\LoanDueSoonNotification;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Erinnerung vor Ablauf der Leihfrist (Spec 4.6: 3 Tage, 1 Tag, am Fälligkeitstag).
 */
class DueSoonReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function ausleiheMitFrist(int $tageBisFaellig): array
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);

        $loan->update(['due_at' => now()->addDays($tageBisFaellig)->setTime(12, 0)]);

        return [$loan->fresh(), $user];
    }

    public function test_erinnert_drei_tage_vorher(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(3);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertSentTo($user, LoanDueSoonNotification::class);
        $this->assertSame(3, $loan->fresh()->due_soon_stage);
    }

    public function test_erinnert_einen_tag_vorher(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(1);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertSentTo($user, LoanDueSoonNotification::class);
        $this->assertSame(1, $loan->fresh()->due_soon_stage);
    }

    public function test_erinnert_am_faelligkeitstag(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(0);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertSentTo($user, LoanDueSoonNotification::class);
        $this->assertSame(0, $loan->fresh()->due_soon_stage);
    }

    public function test_erinnert_nicht_zu_frueh(): void
    {
        [, $user] = $this->ausleiheMitFrist(5);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertNotSentTo($user, LoanDueSoonNotification::class);
    }

    public function test_erinnert_nicht_bei_zwei_tagen_erneut_auf_derselben_stufe(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(3);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        // Zweiter Lauf am Folgetag: noch 2 Tage übrig, das fällt weiterhin
        // unter die 3-Tage-Stufe – es darf keine zweite Mail geben.
        $loan->update(['due_at' => now()->addDays(2)->setTime(12, 0)]);
        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertSentToTimes($user, LoanDueSoonNotification::class, 1);
    }

    public function test_jede_stufe_erinnert_genau_einmal(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(3);

        $this->artisan('loans:remind-due-soon');           // Stufe 3
        $loan->update(['due_at' => now()->addDay()->setTime(12, 0)]);
        $this->artisan('loans:remind-due-soon');           // Stufe 1
        $loan->update(['due_at' => now()->setTime(12, 0)]);
        $this->artisan('loans:remind-due-soon');           // Stufe 0

        Notification::assertSentToTimes($user, LoanDueSoonNotification::class, 3);
        $this->assertSame(0, $loan->fresh()->due_soon_stage);
    }

    public function test_zurueckgegebene_ausleihe_erzeugt_keine_erinnerung(): void
    {
        [$loan, $user] = $this->ausleiheMitFrist(1);
        app(LoanService::class)->returnMedia($loan);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        Notification::assertNotSentTo($user, LoanDueSoonNotification::class);
    }

    public function test_ueberfaellige_ausleihe_faellt_in_die_andere_erinnerung(): void
    {
        [, $user] = $this->ausleiheMitFrist(-2);

        $this->artisan('loans:remind-due-soon')->assertSuccessful();

        // Überfälliges wird von loans:remind-overdue bedient, nicht hier.
        Notification::assertNotSentTo($user, LoanDueSoonNotification::class);
    }

    public function test_betreff_nennt_die_verbleibende_zeit(): void
    {
        [$loan] = $this->ausleiheMitFrist(0);

        $mailable = (new LoanDueSoonNotification($loan, 0))->toMail($loan->user);
        $this->assertStringStartsWith('Heute fällig:', $mailable->envelope()->subject);

        $mailable = (new LoanDueSoonNotification($loan, 1))->toMail($loan->user);
        $this->assertStringStartsWith('Morgen fällig:', $mailable->envelope()->subject);

        $mailable = (new LoanDueSoonNotification($loan, 3))->toMail($loan->user);
        $this->assertStringStartsWith('Rückgabe in 3 Tagen:', $mailable->envelope()->subject);
    }
}
