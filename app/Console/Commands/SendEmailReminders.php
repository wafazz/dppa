<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotices;
use Illuminate\Console\Command;

class SendEmailReminders extends Command
{
    protected $signature = 'pa:send-email-reminders';

    protected $description = 'Send the due reminders: verification, term endings and vote notices.';

    public function handle(ScheduledNotices $notices): int
    {
        $counts = $notices->sendDue();

        if (isset($counts['skipped'])) {
            $this->warn('Resend is not connected. Nothing was sent.');

            return self::SUCCESS;
        }

        foreach ($counts as $type => $count) {
            $this->line("{$type}: {$count}");
        }

        return self::SUCCESS;
    }
}
