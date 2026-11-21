@extends('layouts.admin')

@section('title', __('app.member_dashboard.title'))

@section('content')
@php
    $done = collect($dashboard['checklist'])->where('done', true)->count();
    $percent = (int) round($done / max(count($dashboard['checklist']), 1) * 100);
@endphp
<div class="row g-3">
    <div class="col-12">
        <h2 class="h5 mb-0">{{ __('app.member_dashboard.welcome', ['name' => $dashboard['name']]) }}</h2>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('app.member_dashboard.membership') }}</div>
                <div class="fs-4 fw-semibold">{{ $dashboard['member_no'] ?? __('app.member_dashboard.pending_number') }}</div>
                <span class="badge text-bg-secondary mt-1">{{ $dashboard['status'] }}</span>
                @if ($dashboard['approved_at'])
                    <div class="small text-body-secondary mt-2">{{ __('app.member_dashboard.approved_on', ['date' => $dashboard['approved_at']]) }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('app.member_dashboard.waiting') }}</div>
                <div class="fs-4 fw-semibold">{{ $dashboard['waiting_count'] }}</div>
                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('member.votes.index', ['status' => 'open']) }}">{{ __('app.member_dashboard.view_all') }}</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('app.member_dashboard.results') }}</div>
                <div class="fs-4 fw-semibold">{{ $dashboard['results_count'] }}</div>
                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('member.votes.index', ['status' => 'closed']) }}">{{ __('app.member_dashboard.view_results') }}</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('app.member_dashboard.security') }}</div>
                <div class="mt-1">
                    @if ($dashboard['two_factor_on'])
                        <span class="badge text-bg-success">{{ __('app.member_dashboard.two_factor_on') }}</span>
                    @else
                        <span class="badge text-bg-warning">{{ __('app.member_dashboard.two_factor_off') }}</span>
                    @endif
                </div>
                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('member.profile.index') }}">{{ __('app.member_dashboard.profile') }}</a>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('app.member_dashboard.waiting_title') }}</div>
            <div class="list-group list-group-flush">
                @forelse ($dashboard['waiting'] as $vote)
                    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold">{{ $vote['title'] }}</div>
                            <div class="small text-body-secondary">{{ __('app.member_dashboard.closes', ['date' => $vote['closes_at']]) }}</div>
                        </div>
                        <a class="btn btn-sm btn-primary" href="{{ route('member.votes.index', ['status' => 'open']) }}">{{ __('app.member_dashboard.vote_now') }}</a>
                    </div>
                @empty
                    <div class="list-group-item text-body-secondary">{{ __('app.member_dashboard.no_waiting') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('app.member_dashboard.checklist') }}</div>
            <div class="card-body">
                <div class="progress mb-3" role="progressbar" aria-label="{{ __('app.member_dashboard.checklist') }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: {{ $percent }}%">{{ $percent }}%</div>
                </div>
                <ul class="list-unstyled mb-0">
                    @foreach ($dashboard['checklist'] as $item)
                        <li class="d-flex justify-content-between align-items-center py-1">
                            <span>{{ __('app.member_dashboard.check.'.$item['label']) }}</span>
                            @if ($item['done'])
                                <span class="badge text-bg-success">{{ __('app.member_dashboard.done') }}</span>
                            @else
                                <a class="btn btn-sm btn-outline-warning" href="{{ route('member.profile.index') }}">{{ __('app.member_dashboard.do_now') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <hr>
                <div class="fw-semibold mb-1">{{ __('app.member_dashboard.positions') }}</div>
                @forelse ($dashboard['positions'] as $position)
                    <div class="small">{{ $position['name'] }} @if ($position['term_end']) <span class="text-body-secondary">({{ __('app.member_dashboard.until', ['date' => $position['term_end']]) }})</span> @endif</div>
                @empty
                    <div class="small text-body-secondary">{{ __('app.member_dashboard.no_positions') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ __('app.member_dashboard.activity') }}</span>
                <a class="small" href="{{ route('member.activity.index') }}">{{ __('app.member_dashboard.view_activity') }}</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($dashboard['activity'] as $entry)
                    <div class="list-group-item d-flex justify-content-between small">
                        <span>{{ $entry['label'] }}</span>
                        <span class="text-body-secondary text-nowrap">{{ $entry['occurred_at'] }}</span>
                    </div>
                @empty
                    <div class="list-group-item text-body-secondary">{{ __('app.member_dashboard.no_activity') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
