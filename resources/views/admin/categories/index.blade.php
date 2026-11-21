@extends('layouts.admin')

@section('title', __('app.categories.title'))

@section('content')
<div data-vue="categories-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
