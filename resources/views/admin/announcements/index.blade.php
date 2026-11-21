@extends('layouts.admin')

@section('title', __('app.announcements.title'))

@section('content')
<div data-vue="announcements-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
