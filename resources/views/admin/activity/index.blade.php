@extends('layouts.admin')

@section('title', __('app.activity.title'))

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <p class="small text-body-secondary mb-3">
            {{ $isManagement ? __('app.activity.intro_all') : __('app.activity.intro_own') }}
        </p>

        <form method="GET" action="{{ route($indexRoute) }}" class="row g-2 align-items-end" novalidate>
            @if ($isManagement)
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1" for="filter-account">{{ __('app.activity.filter_user') }}</label>
                    <select id="filter-account" name="user_id" class="form-select">
                        <option value="">{{ __('app.activity.filter_all') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $account->id)>
                                {{ $account->name }} ({{ $account->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="filter-category">{{ __('app.activity.filter_category') }}</label>
                <select id="filter-category" name="category" class="form-select">
                    <option value="">{{ __('app.activity.filter_all') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>
                            {{ __('app.activity.categories.'.$category) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="filter-from">{{ __('app.activity.filter_from') }}</label>
                <input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="filter-to">{{ __('app.activity.filter_to') }}</label>
                <input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">{{ __('app.activity.filter_apply') }}</button>
                <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">{{ __('app.activity.filter_reset') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header small text-body-secondary" aria-live="polite">
        @if ($entries->total() > 0)
            {{ __('app.activity.showing', ['from' => $entries->firstItem(), 'to' => $entries->lastItem(), 'total' => $entries->total()]) }}
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-nowrap">{{ __('app.activity.col_time') }}</th>
                    <th scope="col">{{ __('app.activity.col_who') }}</th>
                    <th scope="col">{{ __('app.activity.col_event') }}</th>
                    <th scope="col">{{ __('app.activity.col_details') }}</th>
                    <th scope="col" class="text-nowrap">{{ __('app.activity.col_ip') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-nowrap small">{{ $entry->occurred_at->timezone($timezone)->format('d/m/Y H:i:s') }}</td>
                        <td class="small">
                            @if ($entry->user)
                                {{ $entry->user->name }}
                            @elseif ($entry->event === 'auth.login_failed')
                                {{ __('app.activity.who_unknown', ['email' => $entry->properties['email'] ?? '—']) }}
                            @else
                                {{ __('app.activity.who_system') }}
                            @endif
                        </td>
                        <td class="small fw-semibold">{{ $entry->label() }}</td>
                        <td class="small">
                            @if (! empty($entry->properties['changes']))
                                <details>
                                    <summary class="text-primary">{{ __('app.activity.show_changes') }}</summary>
                                    <dl class="row mb-0 mt-1">
                                        @foreach ($entry->properties['changes'] as $field => $change)
                                            <dt class="col-4 text-break">{{ $field }}</dt>
                                            <dd class="col-8 mb-1 text-break">
                                                @if (is_array($change) && array_key_exists('new', $change))
                                                    <span class="text-body-secondary">{{ __('app.activity.old') }}:</span>
                                                    {{ is_scalar($change['old']) || $change['old'] === null ? ($change['old'] ?? '—') : json_encode($change['old']) }}
                                                    <br>
                                                    <span class="text-body-secondary">{{ __('app.activity.new') }}:</span>
                                                    {{ is_scalar($change['new']) || $change['new'] === null ? ($change['new'] ?? '—') : json_encode($change['new']) }}
                                                @else
                                                    {{ is_scalar($change) ? $change : json_encode($change) }}
                                                @endif
                                            </dd>
                                        @endforeach
                                    </dl>
                                </details>
                            @elseif ($entry->description)
                                {{ $entry->description }}
                            @else
                                <span class="text-body-secondary">{{ __('app.activity.no_details') }}</span>
                            @endif
                        </td>
                        <td class="small text-nowrap">{{ $entry->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-body-secondary">{{ __('app.activity.no_entries') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
