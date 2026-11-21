<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class ResetPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pa:reset-password
        {--email= : Email address of the account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a new password for an account (for example, a locked-out superadmin)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?? text(label: 'Emel akaun', required: true))));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Akaun dengan emel {$email} tidak dijumpai.");

            return self::FAILURE;
        }

        $newPassword = password(label: 'Kata laluan baharu (minimum 12 aksara)', required: true, validate: fn (string $value): ?string => strlen($value) >= 12 ? null : 'Kata laluan mesti sekurang-kurangnya 12 aksara.');
        $confirmation = password(label: 'Sahkan kata laluan baharu', required: true);

        if ($newPassword !== $confirmation) {
            $this->error('Kata laluan tidak sepadan. Tiada perubahan dibuat.');

            return self::FAILURE;
        }

        $user->update(['password' => $newPassword]);

        $this->info("Kata laluan untuk {$user->email} telah ditukar.");

        return self::SUCCESS;
    }
}
