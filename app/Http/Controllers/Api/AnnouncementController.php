<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AnnouncementController extends Controller
{
    /**
     * How many members the next announcement would reach.
     */
    public function recipients(AnnouncementService $announcements): JsonResponse
    {
        return response()->json(['data' => ['count' => $announcements->recipients()->count()]]);
    }

    /**
     * Send the announcement to every eligible member.
     */
    public function store(Request $request, AnnouncementService $announcements): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $result = $announcements->send(
                $request->user(),
                trim($validated['subject']),
                trim($validated['body']),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }
}
