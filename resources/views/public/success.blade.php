@extends('layouts.public')

@section('title', 'Permohonan Diterima')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm text-center">
            <div class="card-body p-5">
                <h1 class="h3 mb-3">Terima kasih</h1>
                <p class="mb-2">Permohonan keahlian anda telah diterima.</p>
                <p class="mb-4">
                    Status permohonan anda ialah <span class="badge text-bg-warning">Menunggu</span>.
                    Pihak jawatankuasa akan menyemak permohonan anda dan menghubungi anda melalui maklumat yang diberikan.
                </p>
                @if (session('verification_sent'))
                    <p class="mb-4 alert alert-info small">
                        Pautan pengesahan telah dihantar ke e-mel anda. Sila klik pautan dalam e-mel itu untuk mengesahkan alamat e-mel anda.
                    </p>
                @else
                    <p class="mb-4 alert alert-warning small">
                        Pautan pengesahan e-mel belum dapat dihantar. Pihak dewan akan cuba menghantarnya semula.
                    </p>
                @endif
                <p class="text-body-secondary small mb-0">Anda boleh menutup halaman ini.</p>
            </div>
        </div>
    </div>
</div>
@endsection
