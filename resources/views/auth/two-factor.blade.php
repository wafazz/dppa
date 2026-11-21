@extends('layouts.public')

@section('title', __('app.two_factor.title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">{{ __('app.two_factor.title') }}</h1>
                <p class="text-body-secondary small mb-4">{{ __('app.two_factor.intro') }}</p>

                <form method="POST" action="{{ route('login.two-factor.method') }}" novalidate class="mb-4">
                    @csrf
                    <fieldset>
                        <legend class="form-label fw-semibold">{{ __('app.two_factor.choose_method') }}</legend>
                        @foreach ($methods as $method)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="method" id="method-{{ $method }}" value="{{ $method }}"
                                       {{ $selected === $method ? 'checked' : '' }} required>
                                <label class="form-check-label" for="method-{{ $method }}">{{ __('app.two_factor.method_'.$method) }}</label>
                            </div>
                        @endforeach
                        @error('method')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </fieldset>
                    <button type="submit" class="btn btn-outline-primary w-100 mt-3">
                        {{ $selected === 'email' && $emailCodeSent ? __('app.two_factor.resend_code') : __('app.two_factor.continue') }}
                    </button>
                </form>

                @if ($selected)
                    @if ($selected === 'email')
                        <p class="small text-body-secondary">{{ $emailCodeSent ? __('app.two_factor.email_sent') : __('app.two_factor.email_not_sent') }}</p>
                    @else
                        <p class="small text-body-secondary">{{ __('app.two_factor.app_hint') }}</p>
                    @endif

                    <form method="POST" action="{{ route('login.two-factor.verify') }}" novalidate>
                        @csrf
                        <div class="mb-4">
                            <div id="code-label" class="form-label text-center w-100">{{ __('app.two_factor.code_label') }}</div>
                            <div class="pin-field" role="group" tabindex="0" aria-labelledby="code-label" data-pin-pad data-length="6" data-auto-open
                                 data-title="{{ __('app.two_factor.pad_title') }}" data-cancel="{{ __('app.two_factor.pad_cancel') }}">
                                @for ($box = 0; $box < 6; $box++)
                                    <span class="pin-box" data-pin-box aria-hidden="true"></span>
                                @endfor
                                <input type="hidden" name="code" value="" data-pin-value>
                            </div>
                            @error('code')
                                <div class="invalid-feedback d-block text-center">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('app.two_factor.verify') }}</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('login.two-factor.cancel') }}" class="mt-3 text-center">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm">{{ __('app.two_factor.back') }}</button>
                </form>

                @error('email')
                    <div class="alert alert-danger mt-3 mb-0 small">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
@endsection
