<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * The activity log. Superadmin and admin accounts see every account's activity and can filter by account.
     * Every other account sees only its own activity.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $isManagement = $viewer->isManagement();
        $timezone = config('app.timezone');

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = ActivityLog::query()
            ->visibleTo($viewer)
            ->with('user:id,name,email');

        if ($isManagement && ! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['category'])) {
            $query->where('event', 'like', $validated['category'].'.%');
        }

        if (! empty($validated['from'])) {
            $query->where('occurred_at', '>=', Carbon::parse($validated['from'], $timezone)->startOfDay());
        }

        if (! empty($validated['to'])) {
            $query->where('occurred_at', '<=', Carbon::parse($validated['to'], $timezone)->endOfDay());
        }

        $entries = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.activity.index', [
            'indexRoute' => request()->user()->isMember() ? 'member.activity.index' : 'admin.activity.index',
            'entries' => $entries,
            'isManagement' => $isManagement,
            'accounts' => $isManagement
                ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
            'categories' => ['auth', 'account', 'member', 'position', 'appointment', 'category', 'invitation', 'setting', 'management_position', 'profile', 'business', 'two_factor', 'vote'],
            'filters' => $validated,
            'timezone' => $timezone,
        ]);
    }
}
