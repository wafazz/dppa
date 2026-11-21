@extends('layouts.admin')

@section('title', __('app.accounts.title'))

@section('content')
<div data-vue="accounts-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
