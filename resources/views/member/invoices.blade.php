@extends('layouts.admin')

@section('title', __('app.billing.my_title'))

@section('content')
@if ($billing === null)
    <div class="alert alert-info">{{ __('app.billing.member.no_invoices') }}</div>
@else
    @php($bank = $settings['bank_name'] !== '' || $settings['account_number'] !== '')
    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">{{ __('app.billing.member.owed') }}</div>
                    <div class="fs-3 fw-semibold">{{ \App\Services\Money::format($billing['owed_cents'], $symbol) }}</div>
                    @if ($billing['owed_cents'] === 0)
                        <div class="small text-success mt-1">{{ __('app.billing.member.none_owed') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('app.billing.member.how_to_pay') }}</div>
                <div class="card-body small">
                    @if ($bank)
                        <dl class="row mb-2">
                            @if ($settings['bank_name'] !== '')<dt class="col-4">{{ __('app.billing.member.bank') }}</dt><dd class="col-8">{{ $settings['bank_name'] }}</dd>@endif
                            @if ($settings['account_name'] !== '')<dt class="col-4">{{ __('app.billing.member.account_name') }}</dt><dd class="col-8">{{ $settings['account_name'] }}</dd>@endif
                            @if ($settings['account_number'] !== '')<dt class="col-4">{{ __('app.billing.member.account_number') }}</dt><dd class="col-8">{{ $settings['account_number'] }}</dd>@endif
                        </dl>
                    @else
                        <p class="text-body-secondary">{{ __('app.billing.member.no_bank') }}</p>
                    @endif
                    @php($instructions = app()->getLocale() === 'ms' ? $settings['instructions_bm'] : $settings['instructions_en'])
                    @if ($instructions !== '')
                        <p class="mb-2">{!! nl2br(e($instructions)) !!}</p>
                    @endif
                    <p class="text-body-secondary mb-0">{{ __('app.billing.member.reference_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold">{{ __('app.billing.member.invoices') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($billing['invoices'] as $invoice)
                        <div class="list-group-item">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $invoice['fee'] }} @if ($invoice['period']) <span class="text-body-secondary">({{ $invoice['period'] }})</span> @endif</div>
                                    <div class="small text-body-secondary">{{ $invoice['number'] }} · {{ __('app.billing.member.due', ['date' => $invoice['due_on']]) }}</div>
                                </div>
                                <div class="text-end">
                                    <div>{{ __('app.billing.member.amount') }}: <strong>{{ \App\Services\Money::format($invoice['amount_cents'], $symbol) }}</strong></div>
                                    <div class="small">{{ __('app.billing.member.paid') }}: {{ \App\Services\Money::format($invoice['paid_cents'], $symbol) }} · {{ __('app.billing.member.balance') }}: {{ \App\Services\Money::format($invoice['balance_cents'], $symbol) }}</div>
                                    @if ($invoice['status'] === 'waived')
                                        <span class="badge text-bg-secondary">{{ __('app.billing.status_waived') }}</span>
                                    @elseif ($invoice['status'] === 'paid')
                                        <span class="badge text-bg-success">{{ __('app.billing.status_paid') }}</span>
                                    @elseif ($invoice['overdue'])
                                        <span class="badge text-bg-danger">{{ __('app.billing.member.overdue') }}</span>
                                    @elseif ($invoice['status'] === 'partial')
                                        <span class="badge text-bg-info">{{ __('app.billing.status_partial') }}</span>
                                    @else
                                        <span class="badge text-bg-warning">{{ __('app.billing.status_unpaid') }}</span>
                                    @endif
                                </div>
                            </div>
                            @if (count($invoice['payments']) > 0)
                                <ul class="small mt-2 mb-0 list-unstyled">
                                    @foreach ($invoice['payments'] as $payment)
                                        <li>{{ __('app.billing.member.receipt', ['receipt' => $payment['receipt']]) }} · {{ \App\Services\Money::format($payment['amount_cents'], $symbol) }} · {{ __('app.billing.member.paid_on', ['date' => $payment['paid_on']]) }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('app.billing.member.no_invoices') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
