@extends('layouts.admin')

@section('title', __('app.members.title'))

@section('content')
<div data-vue="members-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
