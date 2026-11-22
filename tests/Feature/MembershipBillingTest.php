<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\BusinessCategory;
use App\Models\FeeType;
use App\Models\Member;
use App\Models\MembershipInvoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\IntegrationSettings;
use App\Services\MemberStatusService;
use App\Services\Money;
use App\Services\PaymentSettings;
use App\Services\ResendMailer;
use App\Services\ScheduledNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MembershipBillingTest extends TestCase
{
    use RefreshDatabase;

    private function approvedMember(string $email, ?BusinessCategory $category = null): Member
    {
        $member = Member::factory()->create([
            'email' => $email,
            'applicant_name' => 'Ali bin Abu',
            'business_category_id' => $category?->id ?? BusinessCategory::factory()->create()->id,
        ]);

        app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, User::factory()->superadmin()->create(), null);

        return $member->refresh();
    }

    private function committee(): User
    {
        return User::factory()->viewer()->create(['role' => UserRole::Committee]);
    }

    private function connectResend(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->put('resend.api_key', 're_live_key', null);
        $settings->put('resend.from_email', 'info@example.com', null);
        $settings->markVerified('resend', null);
    }

    /**
     * The period a generate request sends for this fee: a year, or nothing for a lifetime fee.
     */
    private function periodFor(FeeType $fee, int $year = 2027): ?string
    {
        return $fee->frequency === FeeType::FREQUENCY_LIFETIME ? null : (string) $year;
    }

    private function generate(FeeType $fee, int $year = 2027): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period' => $this->periodFor($fee, $year)])
            ->assertOk();
    }

    private function invoiceFor(Member $member, FeeType $fee, int $year = 2027): MembershipInvoice
    {
        $key = match ($fee->frequency) {
            FeeType::FREQUENCY_LIFETIME => 'lifetime',
            FeeType::FREQUENCY_MONTHLY => $year.'-01',
            default => (string) $year,
        };

        return MembershipInvoice::query()
            ->where('member_id', $member->id)
            ->where('fee_type_id', $fee->id)
            ->where('period_key', $key)
            ->firstOrFail();
    }

    public function test_money_is_read_from_typed_ringgit_and_shown_with_two_decimals(): void
    {
        $this->assertSame(5000, Money::toCents('50'));
        $this->assertSame(5050, Money::toCents('50.50'));
        $this->assertSame(123450, Money::toCents('1,234.50'));
        $this->assertNull(Money::toCents('0'));
        $this->assertNull(Money::toCents('12.345'));
        $this->assertNull(Money::toCents('abc'));
        $this->assertSame('RM 1,234.50', Money::format(123450));
    }

    public function test_only_administrators_manage_fee_types_and_the_committee_can_read_them(): void
    {
        $payload = ['code' => 'tahunan', 'name_bm' => 'Yuran tahunan', 'amount' => '50', 'frequency' => 'yearly', 'due_days' => 30];

        $this->actingAs($this->committee())->postJson(route('admin.api.fee-types.store'), $payload)->assertForbidden();
        $this->actingAs($this->committee())->getJson(route('admin.api.fee-types.index'))->assertOk();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.fee-types.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.amount_label', 'RM 50.00');

        $this->assertDatabaseHas('fee_types', ['code' => 'tahunan', 'amount_cents' => 5000]);
    }

    public function test_fee_codes_are_unique_and_the_amount_must_be_a_positive_figure(): void
    {
        FeeType::factory()->create(['code' => 'tahunan']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.fee-types.store'), ['code' => 'tahunan', 'name_bm' => 'A', 'amount' => '50', 'frequency' => 'yearly', 'due_days' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($admin)
            ->postJson(route('admin.api.fee-types.store'), ['code' => 'baru', 'name_bm' => 'B', 'amount' => '0', 'frequency' => 'yearly', 'due_days' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_generating_invoices_is_repeatable_and_numbers_them_once(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000, 'due_days' => 14]);
        $first = $this->approvedMember('satu@example.com');
        $second = $this->approvedMember('dua@example.com');
        Member::factory()->create(['email' => 'belum@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period' => '2027'])
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period' => '2027'])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 2);

        $this->assertSame(2, MembershipInvoice::query()->count());

        $invoice = $this->invoiceFor($first, $fee);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', (string) $invoice->number);
        $this->assertSame(5000, $invoice->amount_cents);
        $this->assertSame(now()->addDays(14)->toDateString(), $invoice->due_on->toDateString());
        $this->assertNotNull($second->id);
    }

    public function test_changing_a_fee_does_not_change_invoices_already_issued(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('harga@example.com');
        $this->generate($fee);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.fee-types.update', $fee), [
                'code' => $fee->code, 'name_bm' => $fee->name_bm, 'amount' => '80', 'frequency' => 'yearly', 'due_days' => 30,
            ])
            ->assertOk();

        $this->assertSame(5000, $this->invoiceFor($member, $fee)->amount_cents);
    }

    public function test_a_fee_for_one_business_category_only_reaches_that_category(): void
    {
        $category = BusinessCategory::factory()->create();
        $fee = FeeType::factory()->create(['business_category_id' => $category->id]);
        $inside = $this->approvedMember('dalam@example.com', $category);
        $this->approvedMember('luar@example.com');

        $this->generate($fee);

        $this->assertSame(1, MembershipInvoice::query()->count());
        $this->assertNotNull($this->invoiceFor($inside, $fee));
    }

    public function test_partial_payments_move_the_invoice_to_partly_paid_then_paid(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_receipt'])]);
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('bayar@example.com');
        $this->generate($fee);
        $invoice = $this->invoiceFor($member, $fee);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '20', 'method' => 'cash', 'paid_on' => today()->toDateString()])
            ->assertCreated();
        $this->assertSame(MembershipInvoice::STATUS_PARTIAL, $invoice->refresh()->status);
        $this->assertSame(3000, $invoice->balanceCents());

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '30', 'method' => 'bank_transfer', 'reference' => 'TXN-1', 'paid_on' => today()->toDateString()])
            ->assertCreated();
        $this->assertSame(MembershipInvoice::STATUS_PAID, $invoice->refresh()->status);

        $payment = Payment::query()->where('membership_invoice_id', $invoice->id)->first();
        $this->assertMatchesRegularExpression('/^RCP-\d{4}-\d{6}$/', (string) $payment->receipt_number);

        Http::assertSent(fn (HttpRequest $request): bool => $request['to'] === [$member->email]
            && str_contains((string) $request['html'], (string) $payment->receipt_number));
    }

    public function test_a_payment_larger_than_the_balance_or_by_a_switched_off_method_is_refused(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('lebih@example.com');
        $this->generate($fee);
        $invoice = $this->invoiceFor($member, $fee);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '51', 'method' => 'cash', 'paid_on' => today()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $settings = app(PaymentSettings::class);
        $values = $settings->all();
        $values['enabled_methods'] = ['cash'];
        $settings->save($values, null);

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '10', 'method' => 'cheque', 'paid_on' => today()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('method');
    }

    public function test_a_voided_payment_puts_the_balance_back_and_is_kept_on_record(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('silap@example.com');
        $this->generate($fee);
        $invoice = $this->invoiceFor($member, $fee);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '50', 'method' => 'cash', 'paid_on' => today()->toDateString()])
            ->assertCreated();
        $payment = Payment::query()->firstOrFail();
        $this->assertSame(MembershipInvoice::STATUS_PAID, $invoice->refresh()->status);

        $this->actingAs($this->committee())
            ->postJson(route('admin.api.payments.void', $payment), ['reason' => 'Salah masuk'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.payments.void', $payment), ['reason' => 'Salah masuk'])
            ->assertOk();

        $this->assertSame(MembershipInvoice::STATUS_UNPAID, $invoice->refresh()->status);
        $this->assertSame(5000, $invoice->balanceCents());
        $this->assertNotNull($payment->refresh()->voided_at);
        $this->assertSame('Salah masuk', $payment->void_reason);
    }

    public function test_waiving_clears_the_balance_and_needs_management_and_a_reason(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('ampun@example.com');
        $this->generate($fee);
        $invoice = $this->invoiceFor($member, $fee);

        $this->actingAs($this->committee())
            ->postJson(route('admin.api.invoices.waive', $invoice), ['reason' => 'Ahli veteran'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.waive', $invoice), ['reason' => ''])
            ->assertUnprocessable();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.waive', $invoice), ['reason' => 'Ahli veteran'])
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(MembershipInvoice::STATUS_WAIVED, $invoice->status);
        $this->assertSame(0, $invoice->balanceCents());
    }

    public function test_the_summary_counts_what_is_owed_overdue_and_received_this_month(): void
    {
        $fee = FeeType::factory()->create(['amount_cents' => 5000, 'due_days' => 0]);
        $member = $this->approvedMember('ringkasan@example.com');
        $this->generate($fee);
        $invoice = $this->invoiceFor($member, $fee);
        $invoice->forceFill(['due_on' => today()->subDay()])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.payments.store', $invoice), ['amount' => '10', 'method' => 'cash', 'paid_on' => today()->toDateString()])
            ->assertCreated();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.api.invoices.summary'))
            ->assertOk()
            ->assertJsonPath('data.outstanding_cents', 4000)
            ->assertJsonPath('data.overdue_invoices', 1)
            ->assertJsonPath('data.collected_month_cents', 1000);
    }

    public function test_an_overdue_reminder_is_sent_once(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_overdue'])]);
        $fee = FeeType::factory()->create(['amount_cents' => 5000]);
        $member = $this->approvedMember('tertunggak@example.com');
        $this->generate($fee);
        $this->invoiceFor($member, $fee)->forceFill(['due_on' => today()->subDays(3)])->save();

        $notices = app(ScheduledNotices::class);
        $this->assertSame(1, $notices->sendDue()['payment_reminders']);
        $this->assertSame(0, $notices->sendDue()['payment_reminders']);

        $reminders = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains((string) $pair[0]['subject'], 'Reminder'));

        $this->assertCount(1, $reminders);
        $this->assertSame([$member->email], $reminders->first()[0]['to']);
    }

    public function test_approval_issues_this_years_fees_only_when_the_setting_is_on(): void
    {
        FeeType::factory()->create(['code' => 'tahunan']);
        FeeType::factory()->lifetime()->create(['code' => 'masuk']);
        $member = Member::factory()->create(['email' => 'auto@example.com']);

        $settings = app(PaymentSettings::class);
        $values = $settings->all();
        $values['auto_invoice_on_approval'] = false;
        $settings->save($values, null);

        app(MemberStatusService::class)->changeStatus($member, MemberStatus::Approved, User::factory()->superadmin()->create(), null);
        $this->assertSame(0, MembershipInvoice::query()->count());

        $values['auto_invoice_on_approval'] = true;
        $settings->save($values, null);

        $second = Member::factory()->create(['email' => 'baru@example.com']);
        app(MemberStatusService::class)->changeStatus($second, MemberStatus::Approved, User::factory()->superadmin()->create(), null);

        $this->assertSame(2, MembershipInvoice::query()->where('member_id', $second->id)->count());
    }

    public function test_a_member_sees_only_their_own_invoices_and_the_bank_details(): void
    {
        $fee = FeeType::factory()->create(['name_bm' => 'Yuran tahunan']);
        $mine = $this->approvedMember('saya@example.com');
        $theirs = $this->approvedMember('orang@example.com');
        $this->generate($fee);

        $settings = app(PaymentSettings::class);
        $values = $settings->all();
        $values['bank_name'] = 'Bank Rakyat';
        $values['account_number'] = '1234567890';
        $settings->save($values, null);

        $account = User::query()->where('member_id', $mine->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();
        $other = User::query()->where('member_id', $theirs->id)->firstOrFail();

        $page = $this->actingAs($account)->get(route('member.invoices.index'))->assertOk();
        $page->assertSee($this->invoiceFor($mine, $fee)->number)
            ->assertSee('Bank Rakyat')
            ->assertDontSee($this->invoiceFor($theirs, $fee)->number);

        $this->assertNotNull($other);
    }

    public function test_only_administrators_change_payment_settings_and_disabled_methods_are_saved(): void
    {
        $payload = [
            'bank_name' => 'Bank Rakyat',
            'account_name' => 'Dewan Perniagaan Puncak Alam',
            'account_number' => '1234567890',
            'enabled_methods' => ['cash', 'bank_transfer'],
            'instructions_bm' => 'Bayar melalui pindahan bank.',
            'instructions_en' => 'Pay by bank transfer.',
            'receipt_footer' => 'Terima kasih',
            'invoice_prefix' => 'inv',
            'receipt_prefix' => 'rcp',
            'currency_symbol' => 'RM',
            'auto_invoice_on_approval' => true,
            'send_invoice_emails' => false,
            'reminder_after_days' => 7,
        ];

        $this->actingAs($this->committee())->putJson(route('admin.api.payment-settings.update'), $payload)->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.payment-settings.update'), $payload)
            ->assertOk()
            ->assertJsonPath('data.invoice_prefix', 'INV');

        $this->assertSame(['cash', 'bank_transfer'], app(PaymentSettings::class)->enabledMethods());
        $this->assertSame(7, app(PaymentSettings::class)->all()['reminder_after_days']);
    }

    public function test_a_monthly_fee_given_a_year_issues_twelve_months_and_a_single_month_issues_one(): void
    {
        $fee = FeeType::factory()->monthly()->create(['amount_cents' => 1000]);
        $member = $this->approvedMember('bulanan@example.com');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period' => '2027'])
            ->assertOk()
            ->assertJsonPath('data.created', 12);

        $this->assertSame(12, MembershipInvoice::query()->where('member_id', $member->id)->count());
        $this->assertNotNull(MembershipInvoice::query()->where('period_key', '2027-03')->first());

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period' => '2028-06'])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertSame(13, MembershipInvoice::query()->where('member_id', $member->id)->count());
    }

    public function test_a_lifetime_fee_is_charged_once_per_member_without_a_period(): void
    {
        $fee = FeeType::factory()->lifetime()->create(['amount_cents' => 20000]);
        $member = $this->approvedMember('seumur@example.com');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id])
            ->assertOk()
            ->assertJsonPath('data.created', 0);

        $this->assertSame('lifetime', MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail()->period_key);
    }

    public function test_a_period_that_does_not_fit_the_fee_is_refused(): void
    {
        $yearly = FeeType::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $yearly->id, 'period' => '2027-03'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $yearly->id, 'period' => '2027-13'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_admins_choose_yearly_monthly_or_lifetime_and_no_other_period(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['code' => 'pelbagai', 'name_bm' => 'Yuran', 'amount' => '12.50', 'due_days' => 30];

        $this->actingAs($admin)->postJson(route('admin.api.fee-types.store'), $base + ['frequency' => 'monthly'])
            ->assertCreated()
            ->assertJsonPath('data.frequency', 'monthly')
            ->assertJsonPath('data.amount_label', 'RM 12.50');

        $this->actingAs($admin)->postJson(route('admin.api.fee-types.store'), $base + ['code' => 'lain', 'frequency' => 'weekly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('frequency');
    }
}
