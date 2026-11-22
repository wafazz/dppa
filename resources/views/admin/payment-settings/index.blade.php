@extends('layouts.admin')

@section('title', __('app.billing.settings_title'))

@section('content')
<div data-vue="payment-settings-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
