@extends('layouts.public')

@section('title', 'Pengesahan E-mel')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm text-center">
            <div class="card-body p-5">
                @if ($status === 'verified')
                    <h1 class="h3 mb-3">E-mel disahkan</h1>
                    <p class="mb-0">Terima kasih. E-mel anda telah disahkan dan permohonan keahlian anda kini menunggu semakan jawatankuasa.</p>
                @elseif ($status === 'already')
                    <h1 class="h3 mb-3">Sudah disahkan</h1>
                    <p class="mb-0">E-mel ini telah pun disahkan. Tiada tindakan lagi diperlukan.</p>
                @else
                    <h1 class="h3 mb-3">Pautan tidak sah</h1>
                    <p class="mb-0">Pautan pengesahan ini tidak sah atau telah tamat tempoh. Hubungi pihak dewan untuk mendapatkan pautan baharu.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
