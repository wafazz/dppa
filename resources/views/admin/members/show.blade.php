@extends('layouts.admin')

@section('title', __('app.members.detail_title'))

@section('content')
<div data-vue="member-show" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
