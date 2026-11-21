@extends('layouts.admin')

@section('title', __('app.positions.title'))

@section('content')
<div data-vue="positions-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
