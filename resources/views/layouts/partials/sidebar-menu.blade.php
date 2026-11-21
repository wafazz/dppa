{{-- Sidebar items, submenus and counter badges. Built by App\Services\SidebarMenu. --}}
@foreach (app(\App\Services\SidebarMenu::class)->items(auth()->user()) as $item)
    @if (count($item['children']))
        <li class="nav-item has-treeview {{ $item['active'] ? 'menu-open' : '' }}">
            <a href="#" class="nav-link {{ $item['active'] ? 'active' : '' }}" role="button" aria-expanded="{{ $item['active'] ? 'true' : 'false' }}">
                @include('layouts.partials.nav-icon', ['name' => $item['icon']])
                <p>{{ $item['label'] }}</p>
                @if ($item['badge'])
                    <span class="badge rounded-pill {{ $item['badge_class'] }} sidebar-badge">{{ $item['badge'] }}</span>
                @endif
                <span class="nav-arrow" aria-hidden="true"></span>
            </a>
            <ul class="nav nav-treeview">
                @foreach ($item['children'] as $child)
                    <li class="nav-item">
                        <a href="{{ $child['url'] }}" class="nav-link {{ $child['active'] ? 'active' : '' }}">
                            <p>{{ $child['label'] }}</p>
                            @if ($child['badge'])
                                <span class="badge rounded-pill {{ $child['badge_class'] }} sidebar-badge">{{ $child['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a href="{{ $item['url'] }}" class="nav-link {{ $item['active'] ? 'active' : '' }}">
                @include('layouts.partials.nav-icon', ['name' => $item['icon']])
                <p>{{ $item['label'] }}</p>
                @if ($item['badge'])
                    <span class="badge rounded-pill {{ $item['badge_class'] }} sidebar-badge">{{ $item['badge'] }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach
