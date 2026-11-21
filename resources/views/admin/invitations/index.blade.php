@extends('layouts.admin')

@section('title', __('app.invitations.title'))

@section('content')
<div data-vue="invitations-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
