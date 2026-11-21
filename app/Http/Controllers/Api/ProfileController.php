<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Requests\UploadBusinessDocumentRequest;
use App\Models\User;
use App\Services\Avatar;
use App\Services\BusinessDocument;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function show(Request $request, Avatar $avatar): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user(), $avatar)]);
    }

    public function update(UpdateProfileRequest $request, ProfileService $profile, Avatar $avatar): JsonResponse
    {
        $user = $profile->updatePersonal($request->user(), $request->validated());

        return response()->json(['data' => $this->payload($user, $avatar)]);
    }

    public function storeBusinessDocument(UploadBusinessDocumentRequest $request, ProfileService $profile, BusinessDocument $documents, Avatar $avatar): JsonResponse
    {
        $user = $profile->storeBusinessDocument($request->user(), $documents, $request->file('ssm'));

        return response()->json(['data' => $this->payload($user, $avatar)]);
    }

    public function showBusinessDocument(Request $request, BusinessDocument $documents): Response
    {
        return $documents->response($request->user());
    }

    public function submitBusiness(Request $request, ProfileService $profile, Avatar $avatar): JsonResponse
    {
        $user = $profile->submitBusiness($request->user());

        return response()->json(['data' => $this->payload($user, $avatar)]);
    }

    public function updateBusiness(UpdateBusinessProfileRequest $request, ProfileService $profile, Avatar $avatar): JsonResponse
    {
        $user = $profile->updateBusiness($request->user(), $request->validated());

        return response()->json(['data' => $this->payload($user, $avatar)]);
    }

    public function updatePassword(UpdatePasswordRequest $request, ProfileService $profile): JsonResponse
    {
        $profile->changePassword($request->user(), $request->validated('password'), $request->session()->getId());

        return response()->json(['message' => __('app.profile.password_saved')]);
    }

    public function storeAvatar(UploadAvatarRequest $request, Avatar $avatar): JsonResponse
    {
        $avatar->store($request->user(), $request->file('avatar')->getContent());

        return response()->json(['data' => $this->payload($request->user()->refresh(), $avatar)]);
    }

    public function destroyAvatar(Request $request, Avatar $avatar): JsonResponse
    {
        $avatar->remove($request->user());

        return response()->json(['data' => $this->payload($request->user()->refresh(), $avatar)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, Avatar $avatar): array
    {
        $user->loadMissing('accountPosition', 'businessCategory');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_type_label' => $user->account_type->label(),
            'role_label' => $user->isStaff() ? $user->role->label() : null,
            'position_name' => $user->accountPosition?->name_bm,
            'avatar_url' => $avatar->url($user),
            'address_line1' => $user->address_line1,
            'address_line2' => $user->address_line2,
            'postcode' => $user->postcode,
            'city' => $user->city,
            'state' => $user->state,
            'states' => config('membership.states'),
            'business_name' => $user->business_name,
            'ssm_no' => $user->ssm_no,
            'business_category_id' => $user->business_category_id,
            'business_category_name' => $user->businessCategory?->name_bm,
            'business_phone' => $user->business_phone,
            'business_status' => $user->businessStatus(),
            'business_document_name' => $user->business_document_name,
            'has_business_document' => $user->business_document_path !== null,
            'business_submitted_at' => $user->business_submitted_at?->toIso8601String(),
            'business_verified' => $user->hasVerifiedBusiness(),
            'business_verified_at' => $user->business_verified_at?->toIso8601String(),
        ];
    }
}
