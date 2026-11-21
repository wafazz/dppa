@extends('layouts.admin')

@section('title', __('app.email_templates.title'))

@section('content')
<div data-vue="email-templates-index" data-props="{{ json_encode($props) }}">
    <p class="text-body-secondary">{{ __('app.common.loading') }}</p>
</div>
@endsection
