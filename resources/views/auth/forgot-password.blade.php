@extends('layouts.public')

@section('title', 'Lupa Kata Laluan')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Lupa kata laluan</h1>
                <p class="text-body-secondary small mb-4">
                    Masukkan e-mel akaun anda. Jika akaun itu wujud, pautan untuk memilih kata laluan baharu akan dihantar.
                </p>

                @if (session('status'))
                    <div class="alert alert-info small" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Emel</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               autocomplete="username" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Hantar pautan</button>
                </form>

                <p class="small text-center mt-3 mb-0"><a href="{{ route('login') }}">Kembali ke log masuk</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
