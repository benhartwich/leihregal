<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Setting;
use App\Notifications\LoanOverdueNotification;
use Illuminate\Console\Command;

class SendOverdueReminders extends Command
{
    protected $signature   = 'loans:remind-overdue';
    protected $description = 'Send reminder emails for overdue loans (respects loan_reminder_days interval)';

    public function handle(): void
    {
        $intervalDays = (int) Setting::get('loan_reminder_days', 2);

        $query = Loan::with(['media', 'user'])
            ->whereNull('returned_at')
            ->where('due_at', '<', now())
            ->where(function ($q) use ($intervalDays) {
                $q->whereNull('last_reminded_at')
                  ->orWhere('last_reminded_at', '<', now()->subDays($intervalDays));
            });

        $loans = $query->get();

        $sent = 0;
        foreach ($loans as $loan) {
            if (! $loan->user) continue;

            try {
                $loan->user->notify(new LoanOverdueNotification($loan));
                $loan->update(['last_reminded_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Benachrichtigung an {$loan->user->email} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->info("Overdue reminders sent: {$sent}");
    }
}
