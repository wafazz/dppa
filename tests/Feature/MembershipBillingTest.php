<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\BusinessCategory;
use App\Models\FeeType;
use App\Models\Member;
use App\Models\MembershipInvoice;
use App\Models\Payment;
use App\Models\PaymentClaim;
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
        return $fee->priceFor(FeeType::PERIOD_LIFETIME) !== null && $fee->yearly_cents === null ? null : (string) $year;
    }

    private function periodTypeFor(FeeType $fee): string
    {
        return $fee->yearly_cents !== null ? FeeType::PERIOD_YEARLY : (string) ($fee->offeredPeriods()[0] ?? FeeType::PERIOD_LIFETIME);
    }

    private function generate(FeeType $fee, int $year = 2027): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => $this->periodTypeFor($fee), 'period' => $this->periodFor($fee, $year)])
            ->assertOk();
    }

    private function invoiceFor(Member $member, FeeType $fee, int $year = 2027): MembershipInvoice
    {
        $key = match ($this->periodTypeFor($fee)) {
            FeeType::PERIOD_LIFETIME => 'lifetime',
            FeeType::PERIOD_MONTHLY => $year.'-01',
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
        $payload = ['code' => 'tahunan', 'name_bm' => 'Yuran tahunan', 'yearly_amount' => '50', 'due_days' => 30];

        $this->actingAs($this->committee())->postJson(route('admin.api.fee-types.store'), $payload)->assertForbidden();
        $this->actingAs($this->committee())->getJson(route('admin.api.fee-types.index'))->assertOk();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.fee-types.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.prices.yearly.label', 'RM 50.00');

        $this->assertDatabaseHas('fee_types', ['code' => 'tahunan', 'yearly_cents' => 5000]);
    }

    public function test_fee_codes_are_unique_and_the_amount_must_be_a_positive_figure(): void
    {
        FeeType::factory()->create(['code' => 'tahunan']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.fee-types.store'), ['code' => 'tahunan', 'name_bm' => 'A', 'yearly_amount' => '50', 'due_days' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($admin)
            ->postJson(route('admin.api.fee-types.store'), ['code' => 'baru', 'name_bm' => 'B', 'yearly_amount' => '0', 'due_days' => 30])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('yearly_amount');
    }

    public function test_generating_invoices_is_repeatable_and_numbers_them_once(): void
    {
        $fee = FeeType::factory()->create(['yearly_cents' => 5000, 'due_days' => 14]);
        $first = $this->approvedMember('satu@example.com');
        $second = $this->approvedMember('dua@example.com');
        Member::factory()->create(['email' => 'belum@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'yearly', 'period' => '2027'])
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'yearly', 'period' => '2027'])
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
        $member = $this->approvedMember('harga@example.com');
        $this->generate($fee);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('admin.api.fee-types.update', $fee), [
                'code' => $fee->code, 'name_bm' => $fee->name_bm, 'yearly_amount' => '80', 'due_days' => 30,
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000, 'due_days' => 0]);
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
        $fee = FeeType::factory()->create(['yearly_cents' => 5000]);
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
        $fee = FeeType::factory()->monthly()->create(['yearly_cents' => 1000]);
        $member = $this->approvedMember('bulanan@example.com');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'monthly', 'period' => '2027'])
            ->assertOk()
            ->assertJsonPath('data.created', 12);

        $this->assertSame(12, MembershipInvoice::query()->where('member_id', $member->id)->count());
        $this->assertNotNull(MembershipInvoice::query()->where('period_key', '2027-03')->first());

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'monthly', 'period' => '2028-06'])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertSame(13, MembershipInvoice::query()->where('member_id', $member->id)->count());
    }

    public function test_a_lifetime_fee_is_charged_once_per_member_without_a_period(): void
    {
        $fee = FeeType::factory()->lifetime()->create(['yearly_cents' => 20000]);
        $member = $this->approvedMember('seumur@example.com');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'lifetime'])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $fee->id, 'period_type' => 'lifetime'])
            ->assertOk()
            ->assertJsonPath('data.created', 0);

        $this->assertSame('lifetime', MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail()->period_key);
    }

    public function test_a_period_that_does_not_fit_the_fee_is_refused(): void
    {
        $yearly = FeeType::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $yearly->id, 'period_type' => 'yearly', 'period' => '2027-03'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');

        $this->actingAs($admin)
            ->postJson(route('admin.api.invoices.generate'), ['fee_type_id' => $yearly->id, 'period_type' => 'yearly', 'period' => '2027-13'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_a_fee_offers_the_periods_whose_prices_are_filled_in(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['code' => 'pelbagai', 'name_bm' => 'Yuran keahlian', 'due_days' => 30];

        $this->actingAs($admin)->postJson(route('admin.api.fee-types.store'), array_merge($base, ['monthly_amount' => '12.50', 'yearly_amount' => '100', 'lifetime_amount' => '900']))
            ->assertCreated()
            ->assertJsonPath('data.periods', ['monthly', 'yearly', 'lifetime'])
            ->assertJsonPath('data.prices.monthly.label', 'RM 12.50');

        $this->actingAs($admin)->postJson(route('admin.api.fee-types.store'), array_merge($base, ['code' => 'sahaja', 'monthly_amount' => '5']))
            ->assertCreated()
            ->assertJsonPath('data.periods', ['monthly']);

        $this->actingAs($admin)->postJson(route('admin.api.fee-types.store'), array_merge($base, ['code' => 'kosong']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('yearly_amount');
    }

    public function test_a_member_chooses_a_period_and_pays_that_periods_price(): void
    {
        $fee = FeeType::factory()->allPeriods()->create(['code' => 'pilih']);
        $member = $this->approvedMember('pilih@example.com');
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();

        $this->actingAs($account)
            ->post(route('member.invoices.choose'), ['choice' => $fee->id.'|yearly|'.now()->year])
            ->assertRedirect()
            ->assertSessionHas('status');

        $invoice = MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame(10000, $invoice->amount_cents);
        $this->assertSame((string) now()->year, $invoice->period_key);

        $this->actingAs($account)
            ->post(route('member.invoices.choose'), ['choice' => $fee->id.'|lifetime|lifetime'])
            ->assertRedirect();

        $this->assertSame(200000, MembershipInvoice::query()->where('period_key', 'lifetime')->firstOrFail()->amount_cents);
        $this->assertSame(2, MembershipInvoice::query()->where('member_id', $member->id)->count());
    }

    public function test_a_member_cannot_choose_a_period_the_fee_does_not_offer_or_a_past_month(): void
    {
        $fee = FeeType::factory()->monthly()->create(['code' => 'bulanan']);
        $member = $this->approvedMember('tiada@example.com');
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();

        $this->actingAs($account)
            ->post(route('member.invoices.choose'), ['choice' => $fee->id.'|yearly|'.now()->year])
            ->assertSessionHasErrors('period');

        $past = now()->subMonths(2)->format('Y-m');
        $this->actingAs($account)
            ->post(route('member.invoices.choose'), ['choice' => $fee->id.'|monthly|'.$past])
            ->assertSessionHasErrors('period');

        $this->assertSame(0, MembershipInvoice::query()->count());
    }

    public function test_a_members_reported_payment_waits_for_confirmation_then_becomes_a_payment(): void
    {
        $this->connectResend();
        Http::fake([ResendMailer::ENDPOINT => Http::response(['id' => 'msg_claim'])]);
        $fee = FeeType::factory()->allPeriods()->create(['code' => 'lapor']);
        $member = $this->approvedMember('lapor@example.com');
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();
        $this->actingAs($account)->post(route('member.invoices.choose'), ['choice' => $fee->id.'|yearly|'.now()->year]);
        $invoice = MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail();

        $this->actingAs($account)
            ->post(route('member.invoices.report', $invoice), ['amount' => '100.00', 'method' => 'bank_transfer', 'reference' => 'TXN-88', 'paid_on' => today()->toDateString()])
            ->assertRedirect()
            ->assertSessionHas('status');

        $claim = PaymentClaim::query()->firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->assertSame(MembershipInvoice::STATUS_UNPAID, $invoice->refresh()->status);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.api.payment-claims.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'TXN-88');

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.payment-claims.confirm', $claim))
            ->assertOk();

        $this->assertSame(MembershipInvoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertSame('confirmed', $claim->refresh()->status);
        $this->assertNotNull($claim->payment_id);
    }

    public function test_a_reported_payment_can_be_rejected_and_is_not_recorded(): void
    {
        $fee = FeeType::factory()->allPeriods()->create(['code' => 'tolak']);
        $member = $this->approvedMember('tolak@example.com');
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();
        $this->actingAs($account)->post(route('member.invoices.choose'), ['choice' => $fee->id.'|yearly|'.now()->year]);
        $invoice = MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail();
        $this->actingAs($account)->post(route('member.invoices.report', $invoice), ['amount' => '100', 'method' => 'cash', 'paid_on' => today()->toDateString()]);
        $claim = PaymentClaim::query()->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.api.payment-claims.reject', $claim), ['reason' => 'Tiada dalam akaun bank'])
            ->assertOk();

        $this->assertSame('rejected', $claim->refresh()->status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(MembershipInvoice::STATUS_UNPAID, $invoice->refresh()->status);
    }

    public function test_a_member_cannot_report_a_payment_on_someone_elses_invoice(): void
    {
        $fee = FeeType::factory()->allPeriods()->create(['code' => 'orang']);
        $owner = $this->approvedMember('pemilik@example.com');
        $intruder = $this->approvedMember('penceroboh@example.com');
        $this->generate($fee);
        $invoice = MembershipInvoice::query()->where('member_id', $owner->id)->firstOrFail();
        $account = User::query()->where('member_id', $intruder->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();

        $this->actingAs($account)
            ->post(route('member.invoices.report', $invoice), ['amount' => '10', 'method' => 'cash', 'paid_on' => today()->toDateString()])
            ->assertNotFound();

        $this->assertSame(0, PaymentClaim::query()->count());
    }

    public function test_only_committee_accounts_can_confirm_reported_payments(): void
    {
        $fee = FeeType::factory()->allPeriods()->create(['code' => 'akses']);
        $member = $this->approvedMember('akses@example.com');
        $account = User::query()->where('member_id', $member->id)->firstOrFail();
        $account->forceFill(['password' => 'kata-laluan-ahli-123'])->save();
        $this->actingAs($account)->post(route('member.invoices.choose'), ['choice' => $fee->id.'|yearly|'.now()->year]);
        $invoice = MembershipInvoice::query()->where('member_id', $member->id)->firstOrFail();
        $this->actingAs($account)->post(route('member.invoices.report', $invoice), ['amount' => '100', 'method' => 'cash', 'paid_on' => today()->toDateString()]);
        $claim = PaymentClaim::query()->firstOrFail();

        $this->actingAs($account)->postJson(route('admin.api.payment-claims.confirm', $claim))->assertForbidden();
        $this->actingAs($this->committee())->postJson(route('admin.api.payment-claims.confirm', $claim))->assertOk();
    }
}
