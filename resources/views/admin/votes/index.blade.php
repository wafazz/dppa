@extends('layouts.admin')

@section('title', __('app.votes.title'))

@section('content')
<div data-vue="votes-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
