@extends('layouts.admin')

@section('title', __('app.settings.title'))

@section('content')
<div data-vue="settings-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
