@extends('layouts.admin')

@section('title', __('app.billing.my_title'))

@section('content')
@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach ($errors->all() as $message)
            <div>{{ $message }}</div>
        @endforeach
    </div>
@endif

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
                <div class="card-header fw-semibold">{{ __('app.billing.member.choose_title') }}</div>
                <div class="card-body">
                    @if (count($choices) === 0)
                        <p class="text-body-secondary mb-0">{{ __('app.billing.member.no_choices') }}</p>
                    @else
                        <form method="POST" action="{{ route('member.invoices.choose') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-12 col-md-9">
                                <label class="form-label" for="choice">{{ __('app.billing.member.choose_label') }}</label>
                                <select id="choice" name="choice" class="form-select" required>
                                    @foreach ($choices as $choice)
                                        <optgroup label="{{ $choice['name'] }}">
                                            @foreach ($choice['options'] as $option)
                                                <option value="{{ $choice['id'] }}|{{ $option['value'] }}" @selected(old('choice') === $choice['id'].'|'.$option['value'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-3 d-grid">
                                <button type="submit" class="btn btn-primary">{{ __('app.billing.member.choose_button') }}</button>
                            </div>
                        </form>
                    @endif
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
                                    @if ($invoice['pending_report'])
                                        <div class="small text-info mt-1">{{ __('app.billing.member.waiting_confirmation') }}</div>
                                    @endif
                                </div>
                            </div>

                            @if ($invoice['can_report'] && ! $invoice['pending_report'] && count($methods) > 0)
                                <details class="mt-2">
                                    <summary class="small text-primary" style="cursor: pointer">{{ __('app.billing.member.report_button') }}</summary>
                                    <form method="POST" action="{{ route('member.invoices.report', $invoice['id']) }}" class="row g-2 align-items-end mt-1">
                                        @csrf
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="amount-{{ $invoice['id'] }}">{{ __('app.billing.member.report_amount') }}</label>
                                            <input id="amount-{{ $invoice['id'] }}" name="amount" type="text" inputmode="decimal" class="form-control form-control-sm" value="{{ number_format($invoice['balance_cents'] / 100, 2, '.', '') }}" required>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="method-{{ $invoice['id'] }}">{{ __('app.billing.field_method') }}</label>
                                            <select id="method-{{ $invoice['id'] }}" name="method" class="form-select form-select-sm" required>
                                                @foreach ($methods as $method)
                                                    <option value="{{ $method }}">{{ __('app.billing.method_'.$method) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="paid-{{ $invoice['id'] }}">{{ __('app.billing.field_paid_on') }}</label>
                                            <input id="paid-{{ $invoice['id'] }}" name="paid_on" type="date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="ref-{{ $invoice['id'] }}">{{ __('app.billing.field_reference') }}</label>
                                            <input id="ref-{{ $invoice['id'] }}" name="reference" type="text" maxlength="120" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12 col-md-2 d-grid">
                                            <button type="submit" class="btn btn-sm btn-success">{{ __('app.billing.member.report_submit') }}</button>
                                        </div>
                                    </form>
                                </details>
                            @endif

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
