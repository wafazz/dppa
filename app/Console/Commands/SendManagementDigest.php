<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotices;
use Illuminate\Console\Command;

class SendManagementDigest extends Command
{
    protected $signature = 'pa:send-management-digest';

    protected $description = 'Send the weekly digest of applications waiting for review to the administrators.';

    public function handle(ScheduledNotices $notices): int
    {
        $sent = $notices->managementDigest();

        $this->info("Digest sent to {$sent} account(s).");

        return self::SUCCESS;
    }
}
