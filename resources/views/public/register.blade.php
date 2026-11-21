@extends('layouts.public')

@section('title', 'Permohonan Keahlian')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Permohonan Keahlian</h1>
                <p class="text-body-secondary mb-4">
                    Isi borang ini untuk memohon keahlian Dewan Perniagaan Puncak Alam.
                    Medan bertanda <span class="text-danger">*</span> wajib diisi.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        Sila semak maklumat yang ditandakan dalam borang di bawah.
                    </div>
                @endif

                <form method="POST" action="{{ route('registration.store', ['token' => $token]) }}" novalidate>
                    @csrf

                    {{-- Honeypot: hidden from people, filled in only by automated form fillers. --}}
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="website">Laman web (jangan isi)</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <fieldset class="mb-4">
                        <legend class="h5 border-bottom pb-2 mb-3">1. Maklumat Pemohon</legend>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="applicant_name" class="form-label">Nama pemohon <span class="text-danger">*</span></label>
                                <input id="applicant_name" type="text" name="applicant_name" value="{{ old('applicant_name') }}" maxlength="150"
                                       class="form-control @error('applicant_name') is-invalid @enderror" required>
                                @error('applicant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="phone" class="form-label">No. telefon <span class="text-danger">*</span></label>
                                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" placeholder="012-3456789"
                                       class="form-control @error('phone') is-invalid @enderror" required>
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Emel <span class="text-danger">*</span></label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="190"
                                       class="form-control @error('email') is-invalid @enderror" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h5 border-bottom pb-2 mb-3">2. Maklumat Perniagaan</legend>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="business_name" class="form-label">Nama perniagaan <span class="text-danger">*</span></label>
                                <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" maxlength="200"
                                       class="form-control @error('business_name') is-invalid @enderror" required>
                                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="business_category_id" class="form-label">Kategori perniagaan <span class="text-danger">*</span></label>
                                <select id="business_category_id" name="business_category_id" data-app-select2 data-placeholder="Pilih kategori"
                                        class="form-select @error('business_category_id') is-invalid @enderror" required>
                                    <option value="">Pilih kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('business_category_id') === (string) $category->id)>
                                            {{ $category->name_bm }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('business_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="business_address" class="form-label">Alamat perniagaan <span class="text-danger">*</span></label>
                                <input id="business_address" type="text" name="business_address" value="{{ old('business_address') }}" maxlength="500"
                                       class="form-control @error('business_address') is-invalid @enderror" required>
                                @error('business_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="ssm_no" class="form-label">No. pendaftaran SSM <span class="text-body-secondary">(jika ada)</span></label>
                                <input id="ssm_no" type="text" name="ssm_no" value="{{ old('ssm_no') }}" maxlength="50"
                                       class="form-control @error('ssm_no') is-invalid @enderror">
                                @error('ssm_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h5 border-bottom pb-2 mb-3">3. Produk, Perkhidmatan dan Keperluan</legend>

                        <div class="mb-3">
                            <label for="business_description" class="form-label">Penerangan ringkas perniagaan, produk atau perkhidmatan <span class="text-danger">*</span></label>
                            <textarea id="business_description" name="business_description" data-rich-text data-placeholder="Terangkan produk atau perkhidmatan anda"
                                      class="form-control @error('business_description') is-invalid @enderror" rows="4">{{ old('business_description') }}</textarea>
                            @error('business_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="business_needs" class="form-label">Keperluan perniagaan atau bidang yang boleh dibantu oleh dewan <span class="text-danger">*</span></label>
                            <textarea id="business_needs" name="business_needs" data-rich-text data-placeholder="Contohnya: latihan pengurusan, rakan niaga, isu perniagaan"
                                      class="form-control @error('business_needs') is-invalid @enderror" rows="4">{{ old('business_needs') }}</textarea>
                            @error('business_needs')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h5 border-bottom pb-2 mb-3">4. Persetujuan</legend>
                        <div class="form-check">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1" @checked(old('consent')) required>
                            <label class="form-check-label" for="consent">
                                Saya bersetuju maklumat ini disimpan dan digunakan oleh Dewan Perniagaan Puncak Alam
                                untuk tujuan pentadbiran keahlian, selaras dengan Akta Perlindungan Data Peribadi 2010.
                            </label>
                            @error('consent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="announcement_consent" id="announcement_consent" value="1" @checked(old('announcement_consent'))>
                            <label class="form-check-label" for="announcement_consent">
                                (Pilihan) Saya bersetuju menerima pengumuman dan kemas kini dewan melalui e-mel. Anda boleh berhenti pada bila-bila masa melalui pautan dalam setiap e-mel.
                            </label>
                        </div>
                    </fieldset>

                    <div class="d-grid d-sm-flex justify-content-sm-end">
                        <button type="submit" class="btn btn-primary btn-lg px-5">Hantar permohonan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
