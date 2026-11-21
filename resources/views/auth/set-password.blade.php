@extends('layouts.public')

@section('title', __('app.members.account.set_title'))

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">{{ __('app.members.account.set_title') }}</h1>
                <p class="text-body-secondary small mb-4">{{ __('app.members.account.set_intro') }}</p>

                <form method="POST" action="{{ route('password.setup.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="mb-3">
                        <label for="email" class="form-label">{{ __('app.profile.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" required readonly>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('app.profile.new_password') }}</label>
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               autocomplete="new-password" minlength="12" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">{{ __('app.profile.confirm_password') }}</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control"
                               autocomplete="new-password" minlength="12" required>
                    </div>

                    @error('token')
                        <div class="alert alert-danger small">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="btn btn-primary w-100">{{ __('app.members.account.set_save') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
