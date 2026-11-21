<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePositionAppointmentRequest;
use App\Http\Requests\UpdatePositionAppointmentRequest;
use App\Http\Resources\PositionAppointmentResource;
use App\Models\PositionAppointment;
use App\Services\PositionAppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionAppointmentController extends Controller
{
    /**
     * Appointment history with optional filters for position, member and current status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'position_id' => ['nullable', 'integer', 'exists:chamber_positions,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'current' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $appointments = PositionAppointment::query()
            ->with(['member', 'chamberPosition'])
            ->when($validated['position_id'] ?? null, fn ($query, $positionId) => $query->where('chamber_position_id', $positionId))
            ->when($validated['member_id'] ?? null, fn ($query, $memberId) => $query->where('member_id', $memberId))
            ->when($request->has('current') && $request->boolean('current'), fn ($query) => $query->current())
            ->orderByDesc('is_current')
            ->orderByDesc('term_start')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return PositionAppointmentResource::collection($appointments);
    }

    /**
     * Record a new appointment for an approved member.
     */
    public function store(StorePositionAppointmentRequest $request, PositionAppointmentService $appointments): JsonResponse
    {
        $appointment = $appointments->create($request->validated(), $request->user());

        return (new PositionAppointmentResource($appointment->load(['member', 'chamberPosition'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an appointment. Past appointments are ended, never deleted.
     */
    public function update(
        UpdatePositionAppointmentRequest $request,
        PositionAppointment $positionAppointment,
        PositionAppointmentService $appointments,
    ): JsonResource {
        return new PositionAppointmentResource($appointments->update($positionAppointment, $request->validated()));
    }
}
