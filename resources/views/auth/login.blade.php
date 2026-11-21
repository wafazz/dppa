@extends('layouts.public')

@section('title', 'Log Masuk Jawatankuasa')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Log Masuk Jawatankuasa</h1>
                <p class="text-body-secondary small mb-4">Akses terhad untuk ahli jawatankuasa yang diberi kuasa.</p>

                <form method="POST" action="{{ route('login') }}" novalidate>
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

                    <div class="mb-3">
                        <label for="password" class="form-label">Kata laluan</label>
                        <input id="password" type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               autocomplete="current-password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-end">
                            <a href="{{ route('password.request') }}">Lupa kata laluan?</a>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">Ingat saya di peranti ini</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Log masuk</button>
                </form>
            </div>
        </div>
        
    </div>
</div>
@endsection
