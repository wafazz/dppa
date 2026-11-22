@extends('layouts.admin')

@section('title', __('app.billing.fees_title'))

@section('content')
<div data-vue="fees-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
