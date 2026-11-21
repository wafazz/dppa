@extends('layouts.admin')

@section('title', __('app.profile.title'))

@section('content')
<div data-vue="profile-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
