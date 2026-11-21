<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pa:create-user
        {--name= : Full name of the account holder}
        {--email= : Login email address}
        {--account-type= : superadmin (developer), admin, or staff}
        {--role= : Access role for staff accounts: committee or viewer}
        {--password= : Password (omit to be prompted; prompting keeps it out of shell history)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a system account (superadmin, admin, or staff)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? text(label: 'Nama penuh', required: true));
        $email = strtolower((string) ($this->option('email') ?? text(label: 'Emel log masuk', required: true)));
        $accountType = (string) ($this->option('account-type') ?? select(
            label: 'Jenis akaun',
            options: ['superadmin' => 'Superadmin (pembangun)', 'admin' => 'Admin', 'staff' => 'Staf'],
            default: AccountType::Staff->value,
        ));
        $role = $accountType === AccountType::Staff->value
            ? (string) ($this->option('role') ?? select(
                label: 'Peranan akses',
                options: [UserRole::Committee->value => 'Jawatankuasa', UserRole::Viewer->value => 'Pemerhati'],
                default: UserRole::Committee->value,
            ))
            : UserRole::Committee->value;
        $password = (string) ($this->option('password') ?? password(label: 'Kata laluan (minimum 12 aksara)', required: true));

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'account_type' => $accountType,
            'role' => $role,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'account_type' => ['required', 'in:'.implode(',', array_column(AccountType::cases(), 'value'))],
            'role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'password' => ['required', 'string', 'min:12'],
        ], [
            'password.min' => 'Kata laluan mesti sekurang-kurangnya 12 aksara.',
            'email.unique' => 'Emel ini sudah didaftarkan.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'account_type' => AccountType::from($accountType),
            'role' => UserRole::from($role),
            'can_invite' => false,
            'is_active' => true,
            'account_position_id' => null,
        ]);

        $this->info("Akaun {$user->email} telah dicipta sebagai {$user->account_type->label()}.");

        return self::SUCCESS;
    }
}
